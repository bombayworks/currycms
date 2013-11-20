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
 * Inline admin twig node.
 *
 * @package Curry\Twig
 */
class Curry_Twig_Node_Ia extends Twig_Node
{
	/**
	 * Node options.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Constructor
	 *
	 * @param int $lineno
	 * @param string $tag
	 * @param Twig_Node $body
	 * @param array $options
	 */
	public function __construct($lineno, $tag, $body, $options)
	{
		parent::__construct(array('body' => $body), $options, $lineno, $tag);
		$this->options = $options;
	}

	/**
	 * Compile node.
	 *
	 * @param Twig_Compiler $compiler
	 */
	public function compile(Twig_Compiler $compiler)
	{
		
		$compiler
			->addDebugInfo($this)
			->write("if(Curry_InlineAdmin::\$active){\n")
			->indent()
				->write("\$options = array(\n");
			foreach ($this->attributes as $name => $value) {
				$compiler
					->write("'".$name."' => ");
				if($value->test(Twig_Token::NAME_TYPE))
					$compiler->write("\$context['".$value->getValue()."'],\n");
				else
					$compiler->write("'".$value->getValue()."',\n");
			}
			$compiler->write(");\n");

			$compiler
				->write("\$_url = url('admin.php',\$options)->getAbsolute();\n")
				->write("\$_id = implode('',\$options);\n")
				->write("echo str_replace(array('{{Id}}','{{Url}}','{{ClassName}}','{{Name}}'),array(\$_id,\$_url,\$options['module'],\$options['name']),Curry_InlineAdmin::getAdminItemStartTpl());\n")
			->outdent()
			->write("}\n")
			->subcompile($this->getNode('body'))
			->write("if(Curry_InlineAdmin::\$active){\n")
			->indent()
				->write("echo str_replace(array('{{Id}}','{{Url}}'),array(\$_id,\$_url),Curry_InlineAdmin::getAdminItemEndTpl());\n")
			->outdent()
			->write("}\n");

	}

}