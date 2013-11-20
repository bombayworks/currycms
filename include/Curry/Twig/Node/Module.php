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
 * Custom compilation of Twig class file. Add list of placeholders to class.
 * 
 * @package Curry\Twig
 */
class Curry_Twig_Node_Module extends Twig_Node_Module {
	/**
	 * Override compilation of class header to add static placeholder array and accessor method.
	 *
	 * @param Twig_Compiler $compiler
	 */
	protected function compileClassHeader(Twig_Compiler $compiler)
	{
		parent::compileClassHeader($compiler);
		
		$placeholders = array();
		$this->getVariables($this, $placeholders);
		
		$compiler
			->write("// Curry: placeholders \n")
			->write("static protected \$placeholders = ".var_export(array_keys($placeholders), true).";\n\n")
			->write("\n")
			->write("public function getPlaceholders()\n")
			->write("{\n")
			->indent()
				->write("return self::\$placeholders;\n")
			->outdent()
			->write("}\n\n");
	}
	
	/**
	 * Find placeholder nodes recursively.
	 *
	 * @param Twig_Node $node
	 * @param array $placeholders
	 */
	protected function getVariables($node, &$placeholders)
	{
		if($node instanceof Curry_Twig_Node_Placeholder) {
			$placeholders[$node->getAttribute('name')] = true;
		}
		
		foreach($node->getIterator() as $n) {
			if($n instanceof Twig_Node)
				$this->getVariables($n, $placeholders);
		}
	}
}
