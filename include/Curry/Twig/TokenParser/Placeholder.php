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
 * Implement placeholder tag.
 * 
 * Example:
 * {% placeholder 'Name' %}
 * 
 * Equivalent to:
 * {{ Name|raw }}
 * 
 * @package Curry\Twig
 */
class Curry_Twig_TokenParser_Placeholder extends Twig_TokenParser
{
	/**
	 * Parse tag.
	 *
	 * @param Twig_Token $token
	 * @return Twig_Node
	 */
	public function parse(Twig_Token $token)
	{
		$name = $this->parser->getExpressionParser()->parseExpression();

		if (!$name instanceof Twig_Node_Expression_Constant) {
			throw new Twig_Error_Syntax('The name in a "placeholder" statement must be a string.', $token->getLine());
		}
		
		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

		return new Curry_Twig_Node_Placeholder($name->getAttribute('value'), $token->getLine(), $this->getTag());
	}

	/**
	 * Gets the tag name associated with this token parser.
	 *
	 * @return string The tag name
	 */
	public function getTag()
	{
		return 'placeholder';
	}
}
