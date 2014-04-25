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
 * Base class for Twig templates.
 * 
 * @package Curry
 */
abstract class Curry_Twig_Template extends Twig_Template {
	/**
	 * Shared twig environment.
	 *
	 * @var Twig_Environment
	 */
	private static $twig;
	
	/** 
	 * {@inheritdoc}
	 * 
	 * Additianally, currycms automatically wraps ModelCriteria-objects in
	 * Curry_Twig_QueryWrapper objects, and automatically calls the toTwig()
	 * function if it exists.
	 * 
	 * @see Curry_Twig_QueryWrapper
	 */
	protected function getAttribute($object, $item, array $arguments = array(), $type = Twig_TemplateInterface::ANY_CALL, $noStrictCheck = false, $line = -1)
	{
		if(is_object($object) && method_exists($object, '__get') && isset($object->$item))
			$attr = $object->$item;
		else
			$attr = parent::getAttribute($object, $item, $arguments, $type, $noStrictCheck, $line);
		
		while(is_object($attr)) {
			if (method_exists($attr, 'toTwig'))
				$attr = $attr->toTwig();
			else if($attr instanceof ModelCriteria)
				$attr = new Curry_Twig_QueryWrapper($attr);
			else
				break;
		}

		return $attr;
	}
	
	/** {@inheritdoc} */
	public function render(array $context)
	{
		foreach($context as &$var) {
			if($var instanceof ModelCriteria)
				$var = new Curry_Twig_QueryWrapper($var);
			if(is_object($var) && method_exists($var, 'toTwig'))
				$var = $var->toTwig();
		}
		unset($var);
		return parent::render($context);
	}
	
	/**
	 * Create twig environment with the options specified in the curry cms configuration.
	 *
	 * @param Twig_LoaderInterface $loader
	 * @return Twig_Environment
	 */
	private static function createTwigEnvironment(Twig_LoaderInterface $loader)
	{
		$options = \Curry\App::getInstance()->config->curry->template->options->toArray();
		$twig = new Twig_Environment($loader, $options);
		$twig->setParser(new Curry_Twig_Parser($twig));
		$twig->addTokenParser(new Curry_Twig_TokenParser_Placeholder());
		$twig->addTokenParser(new Curry_Twig_TokenParser_Ia());
		$twig->addFunction('url', new Twig_Function_Function('url'));
		$twig->addFunction('L', new Twig_Function_Function('L'));
		$twig->addFunction('round', new Twig_Function_Function('round'));
		$twig->addFilter('ldate', new Twig_Filter_Function('Curry_Twig_Template::ldate'));
		$twig->addFilter('dump', new Twig_Filter_Function('var_dump'));
		
		return $twig;
	}
	
	/**
	 * Locale based date function (using strftime).
	 *
	 * @param mixed $string
	 * @param string $format
	 * @return string
	 */
	public static function ldate($date, $format)
	{
		if ($date instanceof DateTime) {
			$date = $date->format('U');
		} else if ((string)intval($date) === (string)$date) {
			$date = intval($date);
		} else {
			$date = strtotime((string)$date);
		}
		return strftime($format, $date);
	}
	
	/**
	 * Load template from filename using the shared environment.
	 *
	 * @param string $filename
	 * @return Curry_Twig_Template
	 */
	public static function loadTemplate($filename)
	{
		self::getSharedEnvironment();
		return self::$twig->loadTemplate($filename);
	}
	
	/**
	 * Load template from string using the shared environment.
	 *
	 * @param string $template
	 * @return Curry_Twig_Template
	 */
	public static function loadTemplateString($template)
	{
		self::getSharedEnvironment();
		$loader = self::$twig->getLoader();
		self::$twig->setLoader(new Twig_Loader_String());
		$tpl = self::$twig->loadTemplate($template);
		self::$twig->setLoader($loader);
		return $tpl;
	}
	
	/**
	 * Get shared Twig environment.
	 * 
	 * This environment is automatically setup from the curry cms configuration.
	 *
	 * @return Twig_Environment
	 */
	public static function getSharedEnvironment()
	{
		if(!self::$twig) {
			$loader = new Twig_Loader_Filesystem(\Curry\App::getInstance()->config->curry->template->root);
			self::$twig = self::createTwigEnvironment($loader);
		}
		return self::$twig;
	}
}
