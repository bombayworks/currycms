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
namespace Curry\Util;

/**
 * Static class with HTML utility functions.
 * 
 * @package Curry\Util
 */
class Html {
	
	/**
	 * Create <option value="$key">$value</option> from an array.
	 *
	 * @param array $options
	 * @param array|string $selected	The selected option(s).
	 * @param array $disabled The disabled options.
	 * @return string
	 */
	public static function createSelectOptions($options, $selected, $disabled = array())
	{
		$ret = "";
		foreach($options as $k => $v) {
			if(is_array($v)) { // optgroup
				$optgroup = "";
				foreach($v as $ok => $ov)
					$optgroup .= self::_createSelectOption($ok, $ov, $selected, $disabled);
				$ret .= self::tag('optgroup', array('label' => $k), $optgroup);
			} else {
				$ret .= self::_createSelectOption($k, $v, $selected, $disabled);
			}
		}
		return $ret;
	}
	
	/**
	 * Internal helper function for createSelectOptions.
	 *
	 * @param string $k
	 * @param string $v
	 * @param array|string $selected
	 * @param array|string $disabled
	 * @return string
	 */
	private static function _createSelectOption($k, $v, $selected, $disabled)
	{
		$attr = array('value' => $k);
		if(is_array($selected) ? in_array($k, $selected) : $k == $selected)
			$attr['selected'] = 'selected';
		if(is_array($disabled) ? in_array($k, $disabled) : $k == $disabled)
			$attr['disabled'] = 'disabled';
		return self::tag('option', $attr, $v);
	}
	
	/**
	 * Will create a string with the tag '<tagName attr1="value1" attr2="value2">$content</tagName>'
	 *
	 * @param string $tagName
	 * @param array $attributes
	 * @param string $content
	 * @param boolean $allowSelfClosing
	 * @return string
	 */
	public static function tag($tagName, array $attributes = array(), $content = "", $allowSelfClosing = false)
	{
		if(!is_string($content))
			$content = (string)$content;
		if($content !== "")
			return "<$tagName ".self::attr($attributes).">$content</$tagName>";
		else
			return "<$tagName ".self::attr($attributes).($allowSelfClosing ? ' />' : "></$tagName>");
	}
	
	/**
	 * Will create a string with the attributes 'attr1="value1" attr2="value2"'
	 *
	 * @param array $attributes
	 * @return string
	 */
	public static function attr(array $attributes)
	{
		$attr = array();
		foreach($attributes as $k => $v)
			$attr[] = $k.'="'.htmlspecialchars($v).'"';
		return join(" ", $attr);
	}
	
	/**
	 * Create <input type="hidden" name="$key" value="$value" /> from an array.
	 *
	 * @param array $env
	 * @return string
	 */
	public static function createHiddenFields(array $env)
	{
		return self::_createHiddenFields('', $env);
	}
	
	/**
	 * Internal helper function for createHiddenFields.
	 *
	 * @param string $name
	 * @param array $env
	 * @return string
	 */
	private static function _createHiddenFields($name, array $env)
	{
		$hidden = "";
		foreach($env as $k => $v) {
			$n = strlen($name) ? $name.'['.$k.']' : $k;
			$hidden .= is_array($v) ? self::_createHiddenFields($n, $v) : self::tag('input', array('type' => 'hidden', 'name' => $n, 'value' => $v), "", true);
		}
		return $hidden;
	}
	
}