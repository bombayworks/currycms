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
 * Simple extension of Zend_Form_SubForm to add prefix paths to Curry custom elements.
 * 
 * @package Curry\Form
 */
class Curry_Form_SubForm extends Zend_Form_SubForm
{
	/**
	 * Constructor
	 *
	 * @param mixed $options
	 */
	public function __construct($options = null)
	{
		$this->addPrefixPaths(array( array('prefix' => 'Curry_Form', 'path' => 'Curry/Form/') ));
		parent::__construct($options);
	}
}
