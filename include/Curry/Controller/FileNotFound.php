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

namespace Curry\Controller;
use Curry\App;
use Curry\Generator\AbstractGenerator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class FileNotFound implements EventSubscriberInterface {
	/**
	 * @var App
	 */
	private $app;

	/**
	 * @param App $app
	 */
	public function __construct(App $app)
	{
		$this->app = $app;
	}

	public static function getSubscribedEvents()
	{
		return array(
			KernelEvents::EXCEPTION => 'onKernelException',
		);
	}

	public function onKernelException(GetResponseForExceptionEvent $event)
	{
		$exception = $event->getException();
		if ($exception instanceof NotFoundHttpException) {
			// we don't need to explicitly set 404 status, HttpKernel will do this for us.
			if ($this->app['errorPage.notFound']
					&& ($page = \PageQuery::create()->findPk($this->app['errorPage.notFound']))) {
				$generator = AbstractGenerator::create($this->app, $page->getActivePageRevision());
				$event->setResponse($generator->render());
			} else {
				$event->setResponse(new Response('Page not found'));
			}
		}
	}
}
