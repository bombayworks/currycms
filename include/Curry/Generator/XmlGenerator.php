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
namespace Curry\Generator;

/**
 * Page generator for XML documents.
 *
 * Changes content type to application/xml.
 *
 * @package Curry\Generator
 */
class XmlGenerator extends AbstractGenerator {
	/**
	 * Content type is application/xml
	 *
	 * @return string
	 */
	public function getContentType() {
		return "application/xml";
	}
}
