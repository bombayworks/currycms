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
namespace Curry\Module;

use Curry\Util\ArrayHelper;
use Curry\Util\Flash as FlashUtil;

/**
 * Module to embed flash content.
 * 
 * Requires a template, the following variables are available:
 * 
 * * Source (string): Path to flash swf file.
 * * Target (string): ID of flash target element.
 * * AlternativeContent (string): Alternative HTML content when flash is unavailable.
 * * Html (string): Html code required for embedding this flash.
 * * Script (string): Javascript code required for embedding this flash.
 * 
 * @package Curry\Module
 */
class Flash extends AbstractModule {
	/**
	 * Flash source file.
	 *
	 * @var string
	 */
	protected $flash = '';
	
	/**
	 * Alternative html content.
	 *
	 * @var string
	 */
	protected $alternativeContent = '<a href="http://www.adobe.com/go/getflashplayer"><img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash player" /></a>';
	
	/**
	 * Embedding method.
	 *
	 * @var string
	 */
	protected $method = FlashUtil::SWFOBJECT_DYNAMIC;
	
	/**
	 * Flash width.
	 *
	 * @var string
	 */
	protected $width = '100%';
	
	/**
	 * Flash height.
	 *
	 * @var string
	 */
	protected $height = '100%';
	
	/**
	 * Flash version.
	 *
	 * @var string
	 */
	protected $version = '9.0.0';
	
	/**
	 * ID of flash target element.
	 *
	 * @var string
	 */
	protected $target = '';
	
	/**
	 * Path to express-install flash.
	 *
	 * @var string
	 */
	protected $expressInstall = 'expressInstall.swf';
	
	/**
	 * Add GET/POST/COOKIE to flashvars?
	 *
	 * @var array
	 */
	protected $addToFlashvars = array();
	
	/**
	 * Embed attributes.
	 *
	 * @var array
	 */
	protected $attributes = array();
	
	/**
	 * Embed parameters.
	 *
	 * @var array
	 */
	protected $params = array();
	
	/**
	 * Embed flashvars.
	 *
	 * @var array
	 */
	protected $flashvars = array();
	
	/**
	 * Embedded (inner) module.
	 *
	 * @var AbstractModule|null
	 */
	protected $module = null;
	
	/**
	 * Embedded module class name.
	 *
	 * @var string|null
	 */
	protected $className = null;
	
	/**
	 * Embedded module template.
	 *
	 * @var string|null
	 */
	protected $template = null;
	
	/**
	 * Flashvar name to put embedded module in.
	 *
	 * @var string
	 */
	protected $moduleFlashvar = 'data';
	
	/**
	 * Constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->target = 'flash-'.time();
	}
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		$flashvars = array();
		foreach($this->flashvars as $flashvar)
			$flashvars[$flashvar['name']] = $flashvar['value'];
		
		if($this->module) {
			$moduleTemplate = $this->template ? $this->app->twig->loadTemplate($this->template) : null;
			$flashvars[$this->moduleFlashvar] = $this->module->showFront($moduleTemplate);
		}
		
		if(in_array("get", $this->addToFlashvars))
			ArrayHelper::extend($flashvars, $_GET);
		if(in_array("post", $this->addToFlashvars))
			ArrayHelper::extend($flashvars, $_POST);
		if(in_array("cookie", $this->addToFlashvars))
			ArrayHelper::extend($flashvars, $_COOKIE);
		
		$options = array();
		$options['expressInstall'] = $this->expressInstall;
		$options['target'] = $this->target;
		$options['attributes'] = count($this->attributes) ? $this->attributes : null;
		$options['params'] = count($this->params) ? $this->params : null;
		$options['flashvars'] = count($flashvars) ? $flashvars : null;
		$options['alternativeContent'] = $this->alternativeContent;
		$flashContent = FlashUtil::embed($this->method, $this->flash, $this->width, $this->height, $this->version, $options);
		
		return array(
			'Source' => $this->flash,
			'Target' => $this->target,
			'AlternativeContent' => $this->alternativeContent,
			'Html' => $flashContent['html'],
			'Script' => $flashContent['script'],
		);
	}
	
	/** {@inheritdoc} */
	public static function getDefaultTemplate()
	{
		return <<<TPL
{{ Html|raw }}
<script type="text/javascript">
	{{ Script|raw }}
</script>
TPL;
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML swfobject' => self::getDefaultTemplate(),
		);
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new \Curry_Form_SubForm();
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Embed properties',
			'class' => ($this->module ? 'advanced' : ''),
			'elements' => array(
				'flash' => array('filebrowser', array(
					'label' => 'Flash',
					'required' => true,
					'value' => $this->flash,
				)),
				'method' => array('select', array(
					'label' => 'Method',
					'multiOptions' => array(
						FlashUtil::SWFOBJECT_DYNAMIC => "swfobject (dynamic)",
						FlashUtil::SWFOBJECT_STATIC => "swfobject (static)",
					),
					'required' => true,
					'value' => $this->method,
				)),
				'width' => array('text', array(
					'label' => 'Width',
					'required' => true,
					'value' => $this->width,
				)),
				'height' => array('text', array(
					'label' => 'Height',
					'required' => true,
					'value' => $this->height,
				)),
				'target' => array('text', array(
					'label' => 'Target Id',
					'required' => true,
					'value' => $this->target,
				)),
				'version' => array('text', array(
					'label' => 'Version',
					'required' => true,
					'value' => $this->version,
				)),
				'express_install' => array('text', array(
					'label' => 'Express Install SWF',
					'value' => (string)$this->expressInstall,
				)),
				'add_to_flashvars' => array('multiCheckbox', array(
					'label' => 'Add to flashvars',
					'multiOptions' => array('get' => 'GET', 'post' => 'POST', 'cookie' => 'Cookies'),
					'value' => $this->addToFlashvars
				)),
				'alternative_content' => array('textarea', array(
					'label' => 'Alternative content',
					'value' => $this->alternativeContent,
					'rows' => 5,
					'cols' => 40,
					'wrap' => 'off',
				)),
			),
		)), 'embed');
		
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Attributes',
			'class' => 'advanced',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Name',
					'value' => $this->attributes['name'],
				)),
				'class' => array('text', array(
					'label' => 'Class',
					'value' => $this->attributes['class'],
				)),
			),
		)), 'attributes');
		
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Parameters',
			'class' => 'advanced',
			'elements' => array(
				'play' => array('select', array(
					'label' => 'Play',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['play'],
				)),
				'loop' => array('select', array(
					'label' => 'Loop',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['loop'],
				)),
				'menu' => array('select', array(
					'label' => 'Menu',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['menu'],
				)),
				'quality' => array('select', array(
					'label' => 'Quality',
					'multiOptions' => array(''=>'[Default]','best'=>'best','high'=>'high','medium'=>'medium','autohigh'=>'autohigh','autolow'=>'autolow','low'=>'low'),
					'value' => $this->params['quality'],
				)),
				'scale' => array('select', array(
					'label' => 'Scale',
					'multiOptions' => array(''=>'[Default]','showall'=>'showall','noborder'=>'noborder','exactfit'=>'exactfit','noscale'=>'noscale'),
					'value' => $this->params['scale'],
				)),
				'salign' => array('select', array(
					'label' => 'salign',
					'multiOptions' => array(''=>'[Default]','tl'=>'tl','tr'=>'tr','bl'=>'bl','br'=>'br','l'=>'l','t'=>'t','r'=>'r','b'=>'b'),
					'value' => $this->params['salign'],
				)),
				'wmode' => array('select', array(
					'label' => 'wmode',
					'multiOptions' => array(''=>'[Default]','window'=>'window','opaque'=>'opaque','transparent'=>'transparent','direct'=>'direct','gpu'=>'gpu'),
					'value' => $this->params['wmode'],
				)),
				'bgcolor' => array('text', array(
					'label' => 'bgcolor',
					'value' => $this->params['bgcolor'],
				)),
				'devicefont' => array('select', array(
					'label' => 'devicefont',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['devicefont'],
				)),
				'seamlesstabbing' => array('select', array(
					'label' => 'seamlesstabbing',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['seamlesstabbing'],
				)),
				'swliveconnect' => array('select', array(
					'label' => 'swliveconnect',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['swliveconnect'],
				)),
				'allowfullscreen' => array('select', array(
					'label' => 'allowfullscreen',
					'multiOptions' => array(''=>'[Default]','true'=>'true','false'=>'false'),
					'value' => $this->params['allowfullscreen'],
				)),
				'allowscriptaccess' => array('select', array(
					'label' => 'allowscriptaccess',
					'multiOptions' => array(''=>'[Default]','always'=>'always','sameDomain'=>'sameDomain','never'=>'never'),
					'value' => $this->params['allowscriptaccess'],
				)),
				'allownetworking' => array('select', array(
					'label' => 'allownetworking',
					'multiOptions' => array(''=>'[Default]','all'=>'all','internal'=>'internal','none'=>'none'),
					'value' => $this->params['allownetworking'],
				)),
				'base' => array('text', array(
					'label' => 'base',
					'value' => $this->params['base'],
				)),
			),
		)), 'params');
		
		$variableForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Variable',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
				)),
				'value' => array('text', array(
					'label' => 'Value',
				)),
			),
		));
		
		$form->addSubForm(new \Curry_Form_MultiForm(array(
			'legend' => 'Flashvars',
			'class' => 'advanced',
			'cloneTarget' => $variableForm,
			'defaults' => $this->flashvars,
		)), 'flashvars');
		
		$templatesSelect = array(null => "[ None ]") + \Curry_Backend_Template::getTemplateSelect();
		$classNames = array(null => "[ None ]") + AbstractModule::getModuleList();
		$form->addSubForm(new \Curry_Form_SubForm(array(
			'legend' => 'Embedded module',
			'class' => 'advanced',
			'elements' => array(
				'class_name' => array('select', array(
					'label' => 'Module',
					'multiOptions' => $classNames,
					'value' => $this->className,
					'disable' => array(__CLASS__)
				)),
				'template' => array('select', array(
					'label' => 'Template',
					'multiOptions' => $templatesSelect,
					'value' => $this->template,
				)),
				'flashvar' => array('text', array(
					'label' => 'Flashvar-name',
					'value' => $this->moduleFlashvar,
				)),
			),
		)), 'emodule');
		
		if($this->module)
			$form->addSubForm($this->module->showBack(), 'submodule');
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		
		$this->flash = $values['embed']['flash'];
		$this->method = $values['embed']['method'];
		$this->width = $values['embed']['width'];
		$this->height = $values['embed']['height'];
		$this->target = $values['embed']['target'];
		$this->version = $values['embed']['version'];
		$this->expressInstall = $values['embed']['express_install'];
		$this->addToFlashvars = (array)$values['embed']['add_to_flashvars'];
		$this->alternativeContent = $values['embed']['alternative_content'];
		$this->attributes = self::filterEmpty($values['attributes']);
		$this->params = self::filterEmpty($values['params']);
		$this->flashvars = (array)$values['flashvars'];
		$this->className = $values['emodule']['class_name'];
		$this->template = $values['emodule']['template'];
		$this->moduleFlashvar = $values['emodule']['flashvar'];
		
		// Create module instance
		if($this->className) {
			if(!($this->module instanceof $this->className))
				$this->module = new $this->className;
		}
		else
			$this->module = null;
		
		$subform = $form->getSubForm('submodule');
		if($this->module && $subform) {
			$this->module->setModuleDataId(false);
			$this->module->saveBack($subform);
		}
	}
	
	/**
	 * Internal function to remove empty values from array.
	 *
	 * @param array $array
	 * @return array
	 */
	private static function filterEmpty(array $array)
	{
		return array_filter($array, array(__CLASS__, 'notEmpty'));
	}
	
	/**
	 * Internal function used by filterEmpty.
	 *
	 * @param mixed $var
	 * @return bool
	 */
	private static function notEmpty($var)
	{
		return !empty($var);
	}
}
