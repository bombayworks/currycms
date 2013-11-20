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

/**
 * Module to handle javascript and stylesheet includes.
 * 
 * @package Curry\Module
 */
class Curry_Module_Includes extends Curry_Module {
	/**
	 * Minify JavaScript and CSS?
	 *
	 * @var bool
	 */
	protected $minify = false;
	
	/**
	 * List of script files.
	 *
	 * @var array
	 */
	protected $script = array();
	
	/**
	 * List of stylesheet files
	 *
	 * @var array
	 */
	protected $stylesheet = array();
	
	/**
	 * Inline script.
	 *
	 * @var string
	 */
	protected $inlineScript = '';
	
	/**
	 * This module doesnt support a template.
	 *
	 * @return bool
	 */
	public static function hasTemplate()
	{
		return false;
	}
	
	/** {@inheritdoc} */
	public function getCacheProperties()
	{
		// If minification is enabled, cache for 10 minutes, otherwise we dont need to set a limit
		return new Curry_CacheProperties(array(), $this->minify ? 600 : false);
	}
	
	/** {@inheritdoc} */
	public function showFront(Curry_Twig_Template $template = null)
	{
		$pageGenerator = $this->getPageGenerator();
		if(!($pageGenerator instanceof Curry_PageGenerator_Html))
			throw new Exception('Includes module only works on pages with PageGenerator set to Curry_PageGenerator_Html.');
		$head = $pageGenerator->getHtmlHead();
		
		// Stylesheets
		$minify = array();
		foreach($this->stylesheet as $stylesheet) {
			if($this->minify && !$stylesheet['condition'] && strpos($stylesheet['source'], '//') === false) {
				$minify[] = $stylesheet;
				continue;
			}
			if($stylesheet['condition'])
				$head->beginConditional($stylesheet['condition']);
			$head->addStylesheet($stylesheet['source'], $stylesheet['media']);
			if($stylesheet['condition'])
				$head->endConditional();
		}
		if(count($minify)) {
			$minifyUrl = $this->getMinifyCss($minify);
			$head->addStylesheet($minifyUrl);
		}
		
		// Scripts
		$minify = array();
		foreach($this->script as $script) {
			if($this->minify && !$script['condition'] && strpos($script['source'], '://') === false) {
				$minify[] = $script['source'];
				continue;
			}
			if($script['condition'])
				$head->beginConditional($script['condition']);
			$attr = array();
			if(count($script['async']))
				$attr['async'] = 'async';
			if(count($script['defer']))
				$attr['defer'] = 'defer';
			$head->addScript($script['source'], $script['type'], $attr);
			if($script['condition'])
				$head->endConditional();
		}
		if(count($minify)) {
			$minifyUrl = $this->getMinifyJs($minify);
			$head->addScript($minifyUrl, 'text/javascript');
		}
		
		// Inline script
		if($this->inlineScript)
			$head->addInlineScript($this->inlineScript);
	}
	
	/**
	 * Get minified JavaScript path.
	 *
	 * @param array $files
	 * @return string
	 */
	public function getMinifyJs($files)
	{
		$basedir = 'cache/';
		$target = $basedir.sha1(serialize($files)).'.js';
		
		// create basedir if it doesnt exist
		if(!is_dir($basedir))
			@mkdir($basedir, 0777, true);
		
		// if minified file already exists, make sure it's up to date
		if(file_exists($target)) {
			$lastModified = self::getLastModified($files);
			$targetModified = filemtime($target);
			if($targetModified >= $lastModified) {
				trace_notice('Using cached js minification file');
				return $target . '?' . $targetModified;
			}
			trace_notice('Updating js minification file');
		}
		
		// Combine and minify files
		$content = Minify::combine($files);
		
		// Write content
		file_put_contents($target, $content);
		return $target . '?' . filemtime($target);
	}
	
	/**
	 * Get minified CSS path.
	 *
	 * @param array $files
	 * @return string
	 */
	public function getMinifyCss($files)
	{
		$basedir = 'cache/';
		$target = $basedir.sha1(serialize($files)).'.css';
		
		// create basedir if it doesnt exist
		if(!is_dir($basedir))
			@mkdir($basedir, 0777, true);
		
		// if minified file already exists, make sure it's up to date
		if(file_exists($target)) {
			$data = self::readMinificationHeader($target);
			if(self::getLastModified($data['files']) == $data['modified']) {
				trace_notice('Using cached css minification file');
				return $target . '?' . $data['modified'];
			}
			trace_notice('Updating css minification file');
		}
		
		// Combine files (and process imports)
		$imports = array();
		$content = "";
		foreach($files as $file) {
			$processor = new CssProcessor($file['source']);
			$relative = Curry_Util::getRelativePath($basedir, dirname($file['source']));
			$content .= $processor->getContent($file['media'], $relative);
			$imports = array_merge($imports, $processor->getImportedFiles());
		}
		
		// Minify
		$source = new Minify_Source(array(
			'id' => $target,
			'content' => $content,
			'contentType' => Minify::TYPE_CSS,
		));
		$content = Minify::combine(array($source));
		
		// Add header
		$header = array('files' => $imports, 'modified' => self::getLastModified($imports));
		$content = "/* ".json_encode($header)." */\n" . $content;
		
		// Write content
		file_put_contents($target, $content);
		return $target . '?' . $header['modified'];
	}
	
	/**
	 * Reads minification header.
	 * 
	 * @param string
	 * @return array
	 */
	protected static function readMinificationHeader($file)
	{
		$fp = @fopen($file, "r");
		if(!$fp)
			throw new Exception('Unable to read minification target file: '.$file);
		
		$line = stream_get_line($fp, 4096, "\n");
		fclose($fp);
		
		if(!preg_match('@/\* (.*) \*/$@', $line, $m))
			throw new Exception('Invalid minification target file: '.$file);
			
		$data = json_decode($m[1], true);
		if(!$data)
			throw new Exception('Unable to parse minification file header: '.$file);
			
		return $data;
	}
	
	/**
	 * From an array of files, get the last modified time.
	 *
	 * @param array $files
	 * @return int
	 */
	protected static function getLastModified(array $files)
	{
		$lastModified = 0;
		foreach($files as $file)
			$lastModified = max($lastModified, filemtime($file));
		return $lastModified;
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new Curry_Form_SubForm(array(
			'elements' => array(
				'minify' => array('checkbox', array(
					'label' => 'Minify',
					'value' => $this->minify,
				)),
			)
		));
		
		$scriptForm = new Curry_Form_Dynamic(array(
			'legend' => 'Script',
			'elements' => array(
				'source' => array('filebrowser', array(
					'label' => 'Source',
					'required' => true,
					'description' => 'May be a local or external file. However, currently external files are not minified.',
				)),
				'type' => array('text', array(
					'label' => 'Type',
					'required' => true,
					'value' => 'text/javascript',
					'description' => 'The type attribute of the script tag.'
				)),
				'condition' => array('text', array(
					'label' => 'Condition',
					'value' => '',
					'description' => 'Wrap the tag in a conditional comment, example: lt IE 8. Leave blank to disable.',
				)),
				'async' => array('multiCheckbox', array(
					'label' => 'Async',
					'multiOptions' => array('1' => 'Async'),
					'value' => false,
					'description' => "Load the script asyncronously using the HTML5 async attribute.",
				)),
				'defer' => array('multiCheckbox', array(
					'label' => 'Defer',
					'multiOptions' => array('1' => 'Defer'),
					'value' => false,
					'description' => "Defer execution of script until after the HTML has been loaded.",
				)),
			),
		));
		$scriptForm->addDisplayGroup(array('type','condition','async','defer'), 'options', array('Legend' => 'Options', 'class' => 'horizontal-group'));
		
		$form->addSubForm(new Curry_Form_MultiForm(array(
			'legend' => 'Script includes',
			'cloneTarget' => $scriptForm,
			'defaults' => $this->script,
		)), 'script');
		
		$stylesheetForm = new Curry_Form_Dynamic(array(
			'legend' => 'Stylesheet',
			'elements' => array(
				'source' => array('filebrowser', array(
					'label' => 'Source',
					'required' => true,
				)),
				'media' => array('text', array(
					'label' => 'Media',
					'required' => true,
					'value' => 'all',
				)),
				'condition' => array('text', array(
					'label' => 'Condition',
					'value' => '',
				)),
			),
		));
		
		$form->addSubForm(new Curry_Form_MultiForm(array(
			'legend' => 'Stylesheet includes',
			'cloneTarget' => $stylesheetForm,
			'defaults' => $this->stylesheet,
		)), 'stylesheet');
		
		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Custom inline javascript',
			'class' => $this->inlineScript ? '' : 'advanced',
			'elements' => array(
				'source' => array('codeMirror', array(
					'codeMirrorOptions' => array(
						'mode' => array(
							'name' => 'javascript',
						),
					),
					'label' => 'Source',
					'value' => $this->inlineScript,
					'wrap' => 'off',
					'rows' => 15,
					'cols' => 35,
				)),
			),
		)), 'inline_script');
		
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->minify = (bool)$values['minify'];
		$this->script = (array)$values['script'];
		$this->stylesheet = (array)$values['stylesheet'];
		$this->inlineScript = $values['inline_script']['source'];
	}
}

/**
 * @ignore
 */
class CssProcessor {
	protected $dir;
	protected $rewrite;
	protected $content;
	protected $importedFiles = array();

	public function __construct($path, array &$importedFiles = array())
	{
		$this->dir = dirname($path);
		$content = (string)@file_get_contents($path);
		
		// keep track of imported files
		$this->importedFiles =& $importedFiles;
		$this->importedFiles[$path] = true;
		
		// remove UTF-8 BOM if present
		if (pack("CCC",0xef,0xbb,0xbf) === substr($content, 0, 3))
		$content = substr($content, 3);

		// ensure uniform EOLs
		$this->content = str_replace("\r\n", "\n", $content);
	}

	protected static function resolve($path, $parentDir)
	{
		$path = ($path{0} == '/' ? $path : $parentDir . '/' . $path);
		$parts = array();
		foreach(explode("/", $path) as $part) {
			if($part == '..' && count($parts) && $parts[count($parts)-1] != '..')
				array_pop($parts);
			else if($part == '.' || $part == '')
				continue;
			else
				array_push($parts, $part);
		}
		return join("/", $parts);
	}

	public function getContent($media = null, $rewrite = '')
	{
		$content = preg_replace_callback(
			'/
				@import\\s+
				(?:url\\(\\s*)?      # maybe url(
				[\'"]?               # maybe quote
				(.*?)                # 1 = URI
				[\'"]?               # maybe end quote
				(?:\\s*\\))?         # maybe )
				([a-zA-Z,\\s]*)?     # 2 = media list
				;                    # end token
			/x',
			array($this, 'importCallback'),
			$this->content
		);

		if($rewrite) {
			$this->rewrite = $rewrite;
			$content = preg_replace_callback(
				'/url\\(\\s*([^\\)\\s]+)\\s*\\)/',
				array($this, 'urlCallback'),
				$content
			);
		}

		return ($media && $media != 'all' ? "\n@media $media {\n$content\n}\n" : $content);
	}

	public function importCallback($m)
	{
		$url = $m[1];
		$media = trim($m[2]);
		
		// external file, leave import in place
		if (strpos($url, '//') !== false)
			return $m[0];

		// already imported?
		$file = self::resolve($url, $this->dir);
		if(array_key_exists($file, $this->importedFiles))
			return '';

		$p = new CssProcessor($file, $this->importedFiles);
		return $p->getContent($media, dirname($url));
	}

	public function urlCallback($m)
	{
		$url = trim($m[1], '"\'');
		if ($url[0] !== '/') {
			if (strpos($url, '//') > 0) {
				// skip externals
			} else {
				$url = self::resolve($url, $this->rewrite);
			}
		}
		return "url('{$url}')";
	}
	
	public function getImportedFiles()
	{
		return array_keys($this->importedFiles);
	}
}
