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
namespace Curry\Twig;

/**
 * Base class for Twig templates.
 * 
 * @package Curry
 */
abstract class Template extends \Twig_Template {
	/** 
	 * {@inheritdoc}
	 * 
	 * Additianally, currycms automatically wraps ModelCriteria-objects in
	 * Curry_Twig_QueryWrapper objects, and automatically calls the toTwig()
	 * function if it exists.
	 * 
	 * @see Curry_Twig_QueryWrapper
	 */
	protected function getAttribute($object, $item, array $arguments = array(), $type = \Twig_TemplateInterface::ANY_CALL, $noStrictCheck = false, $line = -1)
	{
		if(is_object($object) && method_exists($object, '__get') && isset($object->$item))
			$attr = $object->$item;
		else
			$attr = parent::getAttribute($object, $item, $arguments, $type, $noStrictCheck, $line);
		
		while(is_object($attr)) {
			if (method_exists($attr, 'toTwig'))
				$attr = $attr->toTwig();
			else if($attr instanceof \ModelCriteria)
				$attr = new \Curry_Twig_QueryWrapper($attr);
			else
				break;
		}

		return $attr;
	}
	
	/** {@inheritdoc} */
	public function render(array $context)
	{
		foreach($context as &$var) {
			if($var instanceof \ModelCriteria)
				$var = new \Curry_Twig_QueryWrapper($var);
			if(is_object($var) && method_exists($var, 'toTwig'))
				$var = $var->toTwig();
		}
		unset($var);
		return parent::render($context);
	}
}
