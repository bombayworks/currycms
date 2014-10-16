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
 * Generator for XHTML documents.
 *
 * Same as HTML documents except content type.
 *
 * @package Curry\Generator
 */
class XhtmlGenerator extends HtmlGenerator {
	/**
	 * Set content type for Xhtml
	 *
	 * If browser accepts application/xhtml+xml, we use that, otherwise text/html.
	 *
	 * @return string
	 */
	public function getContentType() {
		$mime = "text/html";
		if (stristr($_SERVER["HTTP_ACCEPT"], "application/xhtml+xml")) {
			if (preg_match('/application\/xhtml\+xml;q=0(\.[1-9]+)/i', $_SERVER["HTTP_ACCEPT"], $matches)) {
				$xhtml_q = $matches[1];
				if (preg_match('/text\/html;q=0(\.[1-9]+)/i', $_SERVER["HTTP_ACCEPT"], $matches)) {
					$html_q = $matches[1];
					if ($xhtml_q >= $html_q) {
						$mime = "application/xhtml+xml";
					}
				}
			} else {
				$mime = "application/xhtml+xml";
			}
		}

		// special check for the W3C_Validator
		if (stristr($_SERVER["HTTP_USER_AGENT"], "W3C_Validator")) {
			$mime = "application/xhtml+xml";
		}

		return $mime;
	}
}
