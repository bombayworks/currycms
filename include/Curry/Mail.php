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
namespace Curry;

use Symfony\Component\HttpFoundation\Request;

/**
 * Adds input/output encoding to Zend_Mail.
 * 
 * @package Curry
 */
class Mail extends \Zend_Mail
{
	/**
	 * Constructor
	 * 
	 * Override default charset to output encoding.
	 *
	 * @param string|null $charset
	 */
	public function __construct($charset = null)
	{
		if ($charset === null) {
			$charset = 'utf-8';
		}
		parent::__construct($charset);
	}

	/**
	 * If developmentMode is enabled, redirect mails to adminEmail.
	 * 
	 * {@inheritdoc}
	 */
	public function send($transport = null)
	{
		if ($transport === null)
			App::getInstance()->sendMail($this);
		return parent::send($transport);
	}

	/**
	 * Create a mail from a URL, Page or PageRevision.
	 *
	 * @param string|\Page|\PageRevision|Request $page
	 * @param array $variables Additional template variables.
	 * @return Mail
	 */
	public static function createFromPage($page)
	{
		$app = \Curry\App::getInstance();

		// Make sure we have a request object
		if (is_string($page)) {
			$request = Request::create($page);
		} elseif ($page instanceof \Page) {
			$request = Request::create($page->getUrl());
		} elseif ($page instanceof \PageRevision) {
			$request = Request::create($page->getPage()->getUrl());
		} elseif ($page instanceof Request) {
			$request = $page;
		} else {
			throw new \Exception('Expected parameter $page to be one of string|Page|PageRevision|Request.');
		}
		
		// Generate page
		$response = $app->handle($request, \Symfony\Component\HttpKernel\HttpKernelInterface::SUB_REQUEST, false);
		
		// Create email
		$mail = new Mail();
		$mail->setBodyHtml($response->getContent());

		return $mail;
	}
}
