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
 * Twig Node for placeholder tag.
 *
 * @package Curry\Twig
 */
class Curry_Twig_Node_Placeholder extends Twig_Node
{
	/**
	 * Constructor
	 *
	 * @param string $name
	 * @param int $lineno
	 * @param string $tag
	 */
	public function __construct($name, $lineno, $tag = null)
	{
		parent::__construct(array(), array('name' => $name), $lineno, $tag);
	}

	/**
	 * Compile placeholder node.
	 *
	 * @param Twig_Compiler $compiler
	 */
	public function compile(Twig_Compiler $compiler)
	{
		$compiler
			->addDebugInfo($this)
			->write('if (isset($context[')
			->string($this->getAttribute('name'))
			->raw('])) { echo $context[')
			->string($this->getAttribute('name'))
			->raw("]; }\n");
	}
}
