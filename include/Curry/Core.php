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
use Curry\Util\Html;

/**
 * CurryCms main initialization and configuration class.
 * 
 * @package Curry
 */
class Curry_Core {
	/**
	 * String used to prefix tree-structures in select elements.
	 */
	const SELECT_TREE_PREFIX = "\xC2\xA0\xC2\xA0\xC2\xA0"; // utf-8 version of \xA0 or &nbsp;
}
