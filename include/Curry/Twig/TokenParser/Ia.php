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
 * Inline admin tag parser.
 *
 * @package Curry\Twig
 */
class Curry_Twig_TokenParser_Ia extends Twig_TokenParser {
	/**
	 * Parse tag.
	 *
	 * @param Twig_Token $token
	 * @return Twig_Node
	 */
	public function parse(Twig_Token $token)
	{
		$lineno = $token->getLine();
		$stream = $this->parser->getStream();
		$name = null;
		$options = array();

		if($stream->test(Twig_Token::NAME_TYPE)){
			
			$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
			$stream->expect(Twig_Token::OPERATOR_TYPE, '=');
			if($stream->test(Twig_Token::NAME_TYPE))
				$value = $stream->expect(Twig_Token::NAME_TYPE);
			else
				$value = $stream->expect(Twig_Token::STRING_TYPE);

			$options[$name] = $value;

			while($stream->test(Twig_Token::PUNCTUATION_TYPE, ',')){
				$stream->expect(Twig_Token::PUNCTUATION_TYPE);
				$name = $stream->expect(Twig_Token::NAME_TYPE)->getValue();
				$stream->expect(Twig_Token::OPERATOR_TYPE, '=');
				if($stream->test(Twig_Token::NAME_TYPE))
					$value = $stream->expect(Twig_Token::NAME_TYPE);
				else
					$value = $stream->expect(Twig_Token::STRING_TYPE);
				$options[$name] = $value;
			}
		}
		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);
		$body = $this->parser->subparse(array($this, 'decideBlockEnd'), true);

		if ($stream->test(Twig_Token::NAME_TYPE)) {
			$value = $stream->next()->getValue();

			if ($value != $name) {
				throw new Twig_Error_Syntax(sprintf("Expected endblock for block '$name' (but %s given)", $value), $lineno);
			}
		}
		//still need to expect BLOCK_END_TYPE after subparse
		$this->parser->getStream()->expect(Twig_Token::BLOCK_END_TYPE);

		return new Curry_Twig_Node_Ia($lineno, $this->getTag(), $body, $options);
	}

	/** {@inheritdoc} */
	public function decideBlockEnd(Twig_Token $token)
	{
		return $token->test('endia');
	}

	/**
	 * Gets the name associated with this token parser.
	 *
	 * @return string The tag name
	 */
	public function getTag()
	{
		return 'ia';
	}

}
