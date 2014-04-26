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
use Curry\Controller\Frontend;

/**
 * Adds input/output encoding to Zend_Mail.
 * 
 * @package Curry
 */
class Curry_Mail extends Zend_Mail
{
	/**
	 * Is the default transport initialized?
	 *
	 * @var bool
	 */
	private static $initialized = false;
	
	/**
	 * Constructor
	 * 
	 * Override default charset to output encoding.
	 *
	 * @param string|null $charset
	 */
	public function __construct($charset = null)
	{
		if(!self::$initialized) {
			self::initMail();
		}
		if ($charset === null) {
			$charset = \Curry\App::getInstance()->config->curry->outputEncoding;
		}
		parent::__construct($charset);
	}
	
	/**
	 * Override default charset to internal encoding.
	 *
	 * {@inheritdoc}
	 */
	public function setBodyText($txt, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE)
	{
		if ($charset === null) {
			$charset = \Curry\App::getInstance()->config->curry->internalEncoding;
		}
		return parent::setBodyText($txt, $charset, $encoding);
	}

	/**
	 * Override default charset to internal encoding.
	 *
	 * {@inheritdoc}
	 */
	public function setBodyHtml($html, $charset = null, $encoding = Zend_Mime::ENCODING_QUOTEDPRINTABLE)
	{
		if ($charset === null) {
			$charset = \Curry\App::getInstance()->config->curry->internalEncoding;
		}
		return parent::setBodyHtml($html, $charset, $encoding);
	}
	
	/**
	 * If developmentMode is enabled, redirect mails to adminEmail.
	 * 
	 * {@inheritdoc}
	 */
	public function send($transport = null)
	{
		if (\Curry\App::getInstance()->config->curry->divertOutMailToAdmin) {
			$subject = '(dev) ' . $this->getSubject();
			$this->clearSubject();
			$this->setSubject($subject);
			$this->clearRecipients();
			$this->addTo(\Curry\App::getInstance()->config->curry->adminEmail);
		}
		return parent::send($transport);
	}
	
	/**
	 * Initializes the default mail transport configured in the curry config.
	 */
	public static function initMail()
	{
		if(self::$initialized)
			return;
		self::$initialized = true;
		$mail = \Curry\App::getInstance()->config->curry->mail;
		switch(strtolower($mail->method)) {
			case 'smtp':
				$transport = new Zend_Mail_Transport_Smtp($mail->host, $mail->options->toArray());
				Zend_Mail::setDefaultTransport($transport);
				break;
			
			case 'sendmail':
			default:
				$transport = new Zend_Mail_Transport_Sendmail($mail->options);
				Zend_Mail::setDefaultTransport($transport);
				break;
		}
	}
	
	/**
	 * Create a mail from a URL, Page or PageRevision.
	 *
	 * @param string|Page|PageRevision $page
	 * @param Curry_Request $request
	 * @param array $variables Additional template variables.
	 * @return Curry_Mail
	 */
	public static function createFromPage($page, $request = null, array $variables = array())
	{
		$app = Frontend::getInstance();
		
		// If a URL is provided, attempt to find page using route
		if(is_string($page)) {
			$r = new Curry_Request('GET', (string)url($page));
			$page = $app->findPage($r);
			if(!$request)
				$request = $r;
		}
		
		// Find PageRevision
		if($page instanceof PageRevision) {
			$pageRevision = $page;
		} elseif($page instanceof Page) {
			$pageRevision = $page->getPageRevision();
			if(!$pageRevision)
				throw new Exception('Page has no active revision.');
		} else {
			throw new Exception('$page is of invalid type, expected Page or PageRevision.');
		}
		
		// Create Curry_Request object if not provided
		$oldVal = Curry_URL::setPreventRedirect(true);
		if(!$request) {
			$url = (string)url($pageRevision->getPage()->getUrl());
			$request = new Curry_Request('GET', $url);
		}
		
		// Generate page
		$pageGenerator = $app->createPageGenerator($pageRevision, $request);
		$content = $pageGenerator->render($variables);
		
		// Create email
		$mail = new Curry_Mail();
		$mail->setBodyHtml($content);
		$mail->setBodyText(strip_tags($content));
		
		// restore redirect status
		Curry_URL::setPreventRedirect($oldVal);

		return $mail;
	}
}
