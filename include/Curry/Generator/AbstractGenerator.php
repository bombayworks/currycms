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
use Curry\App;
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
	 * @var App
	 */
	protected $app;

	/**
	 * @param App $app
	 * @param \PageRevision $pageRevision
	 * @return AbstractGenerator
	 * @throws \Exception
	 */
	public static function create(App $app, \PageRevision $pageRevision)
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
	 * @param App $app
	 * @param \PageRevision $pageRevision
	 */
	public function __construct(App $app, \PageRevision $pageRevision)
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

		/** @var PreModuleEvent $event */
		$event = $this->app->dispatcher->dispatch(GeneratorEvents::PRE_MODULE, new PreModuleEvent($pageModuleWrapper));

		if ($event->getContent() === null) {
			// TODO: how do we handle $event->isEnabled() ?
			/** @var \Curry\Module\AbstractModule $module */
			$module = $pageModuleWrapper->createObject();
			$template = null;
			if ($event->getTemplate() !== null)
				$template = \Curry_Twig_Template::loadTemplate($event->getTemplate());
			else if ($module->getDefaultTemplate())
				$template = \Curry_Twig_Template::loadTemplateString($module->getDefaultTemplate());
			if ($template && $template->getEnvironment()) {
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
		} else {
			$content = $event->getContent();
		}

		/** @var PostModuleEvent $postEvent */
		$postEvent = $this->app->dispatcher->dispatch(GeneratorEvents::POST_MODULE, new PostModuleEvent($pageModuleWrapper, $content, $event->getExtras()));

		return $postEvent->getContent();
	}

	/**
	 * Save module content to cache.
	 *
	 * @param string $cacheName
	 * @param int|bool|null $lifetime
	 */
	protected function saveModuleCache($cacheName, $lifetime)
	{

	}

	/**
	 * Generate a page, and return module content as an associative array.
	 *
	 * @param array $options
	 * @return string
	 */
	public function generate(array $options = array())
	{
		$this->app->dispatcher->dispatch(GeneratorEvents::PRE_GENERATE);

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

		$event = new PostGenerateEvent($moduleContent);
		$this->app->dispatcher->dispatch(GeneratorEvents::POST_GENERATE, $event);
		return $event->getContent();
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

		$prevPage = isset($this->app->page) ? $this->app->page : null;
		$prevPageRevision = isset($this->app->pageRevision) ? $this->app->pageRevision : null;
		$prevGenerator = isset($this->app->generator) ? $this->app->generator : null;
		$this->app->page = $this->getPage();
		$this->app->pageRevision = $this->pageRevision;
		$this->app->generator = $this;

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
		$event = new RenderEvent($this->getTemplateObject(), $moduleContent);
		$this->app->dispatcher->dispatch(GeneratorEvents::RENDER, $event);
		$response = new Response($event->getTemplate()->render($event->getContent()));

		// restore app variables
		$this->app->page = $prevPage;
		$this->app->pageRevision = $prevPageRevision;
		$this->app->generator = $prevGenerator;

		return $response;
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
	 * @return \Curry\Module\PageModuleWrapper[]
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
