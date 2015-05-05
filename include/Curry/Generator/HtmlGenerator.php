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
use Curry\App;
use Curry\Util\HtmlHead;

/**
 * Page Generator for HTML documents.
 *
 * Adds support for inline admin and HTML-head.
 *
 * @package Curry\Generator
 */
class HtmlGenerator extends AbstractGenerator {
	/**
	 * Object to modify HTML-head
	 *
	 * @var HtmlHead
	 */
	protected $htmlHead;

	/**
	 * {@inheritdoc}
	 */
	public function __construct(App $app, \PageRevision $pageRevision)
	{
		parent::__construct($app, $pageRevision);
		$this->htmlHead = new HtmlHead();
	}

	/**
	 * Content type is text/html.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		return "text/html";
	}

	/**
	 * Get an Curry\Util\HtmlHead object to modify the &lt;head&gt; section of the html-page.
	 *
	 * @return HtmlHead
	 */
	public function getHtmlHead()
	{
		return $this->htmlHead;
	}
}
