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
 * Page Generator for JSON content.
 *
 * @package Curry\Generator
 */
class JsonGenerator extends AbstractGenerator {
	/**
	 * Content type is application/json.
	 *
	 * @return string
	 */
	public function getContentType() {
		return "application/json";
	}

	/**
	 * Add additional headers to force no caching of page.
	 *
	 * @todo Should we really disable caching here?
	 */
	protected function preGeneration() {
		parent::preGeneration();
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
	}
}
