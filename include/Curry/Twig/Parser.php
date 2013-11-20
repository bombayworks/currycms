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
 * Implement custom parser to override Twig_Node_Module with custom class.
 *
 * @package Curry\Twig
 */
class Curry_Twig_Parser extends Twig_Parser {
	/**
	 * Replace Twig_Node_Module with Curry_Twig_Node_Module
	 *
	 * @param Twig_TokenStream $stream
	 * @return Curry_Twig_Node_Module
	 */
	public function parse(Twig_TokenStream $stream)
	{
		$node = parent::parse($stream);
		
		// Convert Twig_Node_Module to Curry_Twig_Node_Module
		return new Curry_Twig_Node_Module(
			$node->getNode('body'),
			$node->getNode('parent'),
			$node->getNode('blocks'),
			$node->getNode('macros'),
			$node->getNode('traits'),
			$node->getAttribute('embedded_templates'),
			$node->getAttribute('filename'));
	}
}
