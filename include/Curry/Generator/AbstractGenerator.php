<?php
/**
 * Curry CMS
 *
 * LICENSE
 *
 * This source file is subject to the GPL license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://currycms.com/license
 *
 * @category   Curry CMS
 * @package    Curry
 * @copyright  2011-2012 Bombayworks AB (http://bombayworks.se)
 * @license    http://currycms.com/license GPL
 * @link       http://currycms.com
 */
namespace Curry\Generator;
use Curry\Module\AbstractModule;
use Curry\Module\PageModuleWrapper;
use Curry\Util\ArrayHelper;
use Curry\Util\Propel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Base class for generating pages.
 *
 * This takes a PageRevision and inserts its modules into a template
 * and return the generated content.
 *
 * @package Curry\Generator
 *
 */
class AbstractGenerator
{
	/**
	 * The pageRevision to generate.
	 *
	 * @var \PageRevision
	 */
	protected $pageRevision;

	/**
	 * Keeps track of the cached content when caching a module.
	 *
	 * @var array
	 */
	protected $moduleCache;

	/**
	 * Holds module debug info.
	 *
	 * @var array
	 */
	protected $moduleDebugInfo;

	/**
	 * @var \Curry\App
	 */
	protected $app;

	/**
	 * @param \Curry\App $app
	 * @param \PageRevision $pageRevision
	 * @return AbstractGenerator
	 * @throws \Exception
	 */
	public static function create(\Curry\App $app, \PageRevision $pageRevision)
	{
		$generatorClass = $pageRevision->getPage()->getInheritedProperty('Generator', $app->config->curry->defaultGeneratorClass);
		$generator = new $generatorClass($app, $pageRevision);
		if (!$generator instanceof AbstractGenerator) {
			throw new \Exception('Page generator must be of type Curry\Generator\AbstractGenerator');
		}
		return $generator;
	}

	/**
	 * Constructor
	 *
	 * @param \Curry\App $app
	 * @param \PageRevision $pageRevision
	 */
	public function __construct(\Curry\App $app, \PageRevision $pageRevision)
	{
		$this->app = $app;
		$this->pageRevision = $pageRevision;
	}

	/**
	 * Return the Page that is being generated.
	 *
	 * @return \Page
	 */
	public function getPage()
	{
		return $this->pageRevision->getPage();
	}

	/**
	 * Get the mime-type for this page.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		return "text/plain";
	}

	/**
	 * Insert module and return generated content.
	 *
	 * @param PageModuleWrapper $pageModuleWrapper
	 * @return string
	 */
	protected function insertModule(PageModuleWrapper $pageModuleWrapper)
	{
		$this->app->logger->debug(($pageModuleWrapper->getEnabled() ? 'Inserting' : 'Skipping').' module "'.$pageModuleWrapper->getName().'" of type "'.$pageModuleWrapper->getClassName() . '" with target "'.$pageModuleWrapper->getTarget().'"');

		if(!$pageModuleWrapper->getEnabled())
			return "";

		$cached = false;
		$devMode = $this->app->config->curry->developmentMode;
		if ($devMode) {
			$time = microtime(true);
			$sqlQueries = Propel::getQueryCount();
			$userTime = \Curry_Util::getCpuTime('u');
			$systemTime = \Curry_Util::getCpuTime('s');
			$memoryUsage = memory_get_usage(true);
		}

		$this->moduleCache = array();
		$module = $pageModuleWrapper->createObject();

		$cp = $module->getCacheProperties();
		$cacheName = $this->getModuleCacheName($pageModuleWrapper, $module);

		// try to use cached content
		if($cp !== null && ($cache = $this->app->cache->load($cacheName)) !== false) {
			$cached = true;
			$this->insertCachedModule($cache);
			$content = $cache['content'];
		} else {
			$template = null;
			if ($pageModuleWrapper->getTemplate())
				$template = \Curry_Twig_Template::loadTemplate($pageModuleWrapper->getTemplate());
			else if ($module->getDefaultTemplate())
				$template = \Curry_Twig_Template::loadTemplateString($module->getDefaultTemplate());
			if($template && $template->getEnvironment()) {
				$twig = $template->getEnvironment();
				$twig->addGlobal('module', array(
					'Id' => $pageModuleWrapper->getPageModuleId(),
					'ClassName' => $pageModuleWrapper->getClassName(),
					'Name' => $pageModuleWrapper->getName(),
					'ModuleDataId' => $pageModuleWrapper->getModuleDataId(),
					'Target' => $pageModuleWrapper->getTarget(),
				));
			}
			$content = (string)$module->showFront($template);

			if($cp !== null) {
				$this->moduleCache['content'] = $content;
				$this->saveModuleCache($cacheName, $cp->getLifetime());
			}
		}

		if ($devMode) {
			$time = microtime(true) - $time;
			$userTime = \Curry_Util::getCpuTime('u') - $userTime;
			$systemTime = \Curry_Util::getCpuTime('s') - $systemTime;
			$memoryUsage = memory_get_usage(true) - $memoryUsage;
			$sqlQueries = $sqlQueries !== null ? Propel::getQueryCount() - $sqlQueries : null;

			$cpuLimit = $this->app->config->curry->debug->moduleCpuLimit;
			$timeLimit = $this->app->config->curry->debug->moduleTimeLimit;
			$memoryLimit = $this->app->config->curry->debug->moduleMemoryLimit;
			$sqlLimit = $this->app->config->curry->debug->moduleSqlLimit;

			if (($userTime + $systemTime) > $cpuLimit || $time > $timeLimit)
				trace_warning('Module generation time exceeded limit');
			if ($memoryUsage > $memoryLimit)
				trace_warning('Module memory usage exceeded limit');
			if ($sqlQueries > $sqlLimit)
				trace_warning('Module sql query count exceeded limit');

			// add module debug info
			$this->moduleDebugInfo[] = array(
				$pageModuleWrapper->getName(),
				$pageModuleWrapper->getClassName(),
				$pageModuleWrapper->getTemplate(),
				$pageModuleWrapper->getTarget(),
				$cached,
				round($time * 1000.0),
				round(($userTime + $systemTime) * 1000.0),
				\Curry_Util::humanReadableBytes($memoryUsage),
				\Curry_Util::humanReadableBytes(memory_get_peak_usage(true)),
				$sqlQueries !== null ? $sqlQueries : 'n/a',
			);
		}

		return $content;
	}

	/**
	 * Save module content to cache.
	 *
	 * @param string $cacheName
	 * @param int|bool|null $lifetime
	 */
	protected function saveModuleCache($cacheName, $lifetime)
	{
		$this->app->cache->save($this->moduleCache, $cacheName, array(), $lifetime);
	}

	/**
	 * Inserting cached content.
	 *
	 * @param array $cache
	 */
	protected function insertCachedModule($cache)
	{
	}

	/**
	 * Get unique name for storing module cache.
	 *
	 * @param PageModuleWrapper $pageModuleWrapper
	 * @param AbstractModule $module
	 * @return string
	 */
	private function getModuleCacheName(PageModuleWrapper $pageModuleWrapper, AbstractModule $module)
	{
		$params = array(
			'_moduleDataId' => $pageModuleWrapper->getModuleDataId(),
			'_template' => $pageModuleWrapper->getTemplate()
		);

		$cp = $module->getCacheProperties();
		if($cp !== null)
			$params = array_merge($params, $cp->getParams());

		return md5(__CLASS__.'_Module_'.serialize($params));
	}

	/**
	 * Function to execute before generating page.
	 */
	protected function preGeneration()
	{
		$this->moduleDebugInfo = array();
	}

	/**
	 * Function to execute after generating page.
	 */
	protected function postGeneration()
	{
		if ($this->app->config->curry->developmentMode) {
			$totalTime = 0;
			foreach($this->moduleDebugInfo as $mdi)
				$totalTime += $mdi[5];
			$labels = array('Name', 'Class', 'Template', 'Target', 'Cached','Time (ms)', 'Cpu (ms)', 'Memory Delta', 'Memory Peak', 'Queries');
			$this->app->logger->debug("Modules(".count($this->moduleDebugInfo)."): ".round($totalTime / 1000.0, 3)."s",
					array_merge(array($labels), $this->moduleDebugInfo));
		}
	}

	/**
	 * Generate a page, and return module content as an associative array.
	 *
	 * @param array $options
	 * @return string
	 */
	public function generate(array $options = array())
	{
		$this->preGeneration();

		// Load page modules
		$moduleContent = array();
		$pageModuleWrappers = $this->getPageModuleWrappers();
		foreach($pageModuleWrappers as $pageModuleWrapper) {
			if(isset($options['pageModuleId']) && $pageModuleWrapper->getPageModuleId() != $options['pageModuleId'])
				continue;
			if(isset($options['indexing']) && $options['indexing'] && !$pageModuleWrapper->getPageModule()->getSearchVisibility())
				continue;

			$target = $pageModuleWrapper->getTarget();
			$content = $this->insertModule($pageModuleWrapper);
			if(isset($moduleContent[$target])) {
				$moduleContent[$target] .= $content;
			} else {
				$moduleContent[$target] = (string)$content;
			}
		}

		$this->postGeneration();
		return $moduleContent;
	}

	protected function getGlobals()
	{
		$lang = \Curry_Language::getLanguage();
		return array(
			'ContentType' => $this->getContentType(),
			'Encoding' => $this->getOutputEncoding(),
			'language' => $lang ? $lang->toArray() : null,
			'page' => $this->pageRevision->getPage()->toTwig(),
		);
	}

	/**
	 * Render a page and return content.
	 *
	 * @param array $vars
	 * @param array $options
	 * @return Response
	 */
	public function render(array $vars = array(), array $options = array())
	{
		$twig = \Curry_Twig_Template::getSharedEnvironment();

		// TODO: Rename curry to app?
		$appVars = $this->app->globals;
		if (isset($vars['curry']))
			ArrayHelper::extend($appVars, $vars['curry']);
		$vars['curry'] = ArrayHelper::extend($appVars, $this->getGlobals());
		foreach($vars as $k => $v)
			$twig->addGlobal($k, $v);

		$moduleContent = $this->generate($options);
		if(isset($options['pageModuleId'])) {
			$pageModuleId = $options['pageModuleId'];
			$pageModuleWrappers = $this->getPageModuleWrappers();
			if(isset($pageModuleWrappers[$pageModuleId])) {
				$pageModuleWrapper = $pageModuleWrappers[$pageModuleId];
				return $moduleContent[$pageModuleWrapper->getTarget()];
			} else {
				throw new \Exception('PageModule with id = '.$pageModuleId.' not found on page.');
			}
		}
		$template = $this->getTemplateObject();
		return new Response($this->renderTemplate($template, $moduleContent));
	}

	public function renderTemplate($template, $moduleContent)
	{
		return $template->render($moduleContent);
	}

	/**
	 * Return content to browser.
	 *
	 * @param string $content
	 */
	protected function sendContent($content)
	{
		$internalEncoding = $this->app->config->curry->internalEncoding;
		$outputEncoding = $this->getOutputEncoding();
		if ($outputEncoding && $internalEncoding != $outputEncoding) {
			trace_warning('Converting output from internal coding');
			$content = iconv($internalEncoding, $outputEncoding."//TRANSLIT", $content);
		}
		echo $content;
	}

	/**
	 * Set content-type header.
	 */
	protected function sendContentType()
	{
		header("Content-Type: ".$this->getContentTypeWithCharset());
	}

	/**
	 * Get the output encoding for this page. If the encoding hasnt been set for this page, the encoding set in the configuration will be used.
	 *
	 * @return string
	 */
	public function getOutputEncoding()
	{
		return $this->getPage()->getInheritedProperty('Encoding', $this->app->config->curry->outputEncoding);
	}

	/**
	 * Get value of HTTP Content-type header.
	 *
	 * @return string
	 */
	public function getContentTypeWithCharset()
	{
		$contentType = $this->getContentType();
		$outputEncoding = $this->getOutputEncoding();
		if($outputEncoding)
			$contentType .= "; charset=" . $outputEncoding;
		return $contentType;
	}

	/**
	 * Get the root template (aka page template) for the PageRevision we are rendering.
	 *
	 * @return string
	 */
	protected function getRootTemplate()
	{
		return $this->pageRevision->getInheritedProperty('Template');
	}

	/**
	 * Get an array of Curry\Module\PageModuleWrapper objects for all modules on the PageRevision we are rendering.
	 *
	 * @return array
	 */
	protected function getPageModuleWrappers()
	{
		$langcode = (string)\Curry_Language::getLangCode();
		$cacheName = md5(__CLASS__ . '_ModuleWrappers_' . $this->pageRevision->getPageRevisionId() . '_' . $langcode);

		if(($moduleWrappers = $this->app->cache->load($cacheName)) === false) {
			$moduleWrappers = $this->pageRevision->getPageModuleWrappers($langcode);
			$this->app->cache->save($moduleWrappers, $cacheName);
		}

		return $moduleWrappers;
	}

	/**
	 * Get the template object for this PageRevision.
	 *
	 * @return \Curry_Twig_Template
	 */
	public function getTemplateObject()
	{
		$rootTemplate = $this->getRootTemplate();
		if(!$rootTemplate)
			throw new \Exception("Page has no root template");
		return \Curry_Twig_Template::loadTemplate($rootTemplate);
	}
}
