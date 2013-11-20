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
 * Static class with utility functions for console output.
 * 
 * @package Curry
 */
class Curry_Console {
	/**
	 * Convert ANSI terminal color codes to HTML-code.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function colorize($text)
	{
		return '<span>'.preg_replace_callback("/\033\\[([\\d;]*)m/", function($m) { return '</span>'.Curry_Console::colorizeTag($m[1]); }, $text)."</span>";
	}
	
	/**
	 * Callback used by colorize to wrap text in colored <span> element.
	 *
	 * @param string $code
	 * @return string
	 */
	public static function colorizeTag($code)
	{
		/*
		style
		0	normal
		1	bold/bright
		2	dim
		3	?
		4	underline
		5	blink
		7	reverse
		8	hidden
	
		Black       0;30     Dark Gray     1;30
		Blue        0;34     Light Blue    1;34
		Green       0;32     Light Green   1;32
		Cyan        0;36     Light Cyan    1;36
		Red         0;31     Light Red     1;31
		Purple      0;35     Light Purple  1;35
		Brown       0;33     Yellow        1;33
		Light Gray  0;37     White         1;37
		*/

		$color = null;
		$style = null;
		$background = null;
	
		$codes = explode(";", $code);
		foreach($codes as $c) {
			$c = intval($c);
	
			if($c >= 30 && $c <= 37)
				$color = $c;
	
			if($c >= 0 && $c <= 8)
				$style = $c;
	
			if($c >= 40 && $c <= 47)
				$background = $c;
		}
	
		$css = array();
	
		if($color !== null) {
			if($style == 1) {
				switch($color) {
					case 30: $css['color'] = 'darkgray'; break;
					case 31: $css['color'] = 'pink'; break;
					case 32: $css['color'] = 'lightgreen'; break;
					case 33: $css['color'] = 'yellow'; break;
					case 34: $css['color'] = 'lightblue'; break;
					case 35: $css['color'] = 'purple'; break;
					case 36: $css['color'] = 'lightcyan'; break;
					case 37: $css['color'] = 'white'; break;
					case 39:
					default: $css['color'] = 'white';
				}
			} else {
				switch($color) {
					case 30: $css['color'] = 'black'; break;
					case 31: $css['color'] = 'red'; break;
					case 32: $css['color'] = 'green'; break;
					case 33: $css['color'] = 'brown'; break;
					case 34: $css['color'] = 'blue'; break;
					case 35: $css['color'] = 'purple'; break;
					case 36: $css['color'] = 'cyan'; break;
					case 37: $css['color'] = 'lightgray'; break;
					case 39:
					default: $css['color'] = 'white';
				}
			}
		}
	
		if($background !== null) {
			switch($background) {
				case 40: $css['background-color'] = 'black'; break;
				case 41: $css['background-color'] = 'red'; break;
				case 42: $css['background-color'] = 'green'; break;
				case 43: $css['background-color'] = 'yellow'; break;
				case 44: $css['background-color'] = 'blue'; break;
				case 45: $css['background-color'] = 'magenta'; break;
				case 46: $css['background-color'] = 'cyan'; break;
				case 47: $css['background-color'] = 'white'; break;
				case 49:
				default: $css['background-color'] = 'transparent';
			}
		}
	
		if($style === 4) {
			$css['text-decoration'] = 'underline';
		}
	
		$cssStyle = "";
		foreach($css as $prop => $value)
			$cssStyle .= $prop.": ".$value."; ";
	
		return '<span style="'.$cssStyle.'">';
	}
}