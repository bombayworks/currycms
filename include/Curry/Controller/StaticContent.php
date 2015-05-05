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
use Curry\Exception;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class StaticContent implements EventSubscriberInterface {
    protected $urlPath;
    protected $localPath;

    public function __construct($urlPath, $localPath)
    {
        $this->urlPath = $urlPath;
        $this->localPath = realpath($localPath);

        if (!$this->localPath) {
            throw new \Exception('Path not found: '.$urlPath);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if (preg_match('#^'.preg_quote($this->urlPath, '#').'(.*)$#', $request->getPathInfo(), $m)
                && ($file = $this->findSharedFile($m[1])) !== false) {
            $request->attributes->set('file', $file);
            $request->attributes->set('_controller', $this);
        }
    }

    public function findSharedFile($path)
    {
        $base = $this->localPath.'/';
        $target = realpath($base.$path);
        if ($target && substr($target, 0, strlen($base)) === $base
                && substr($target, -strlen($path)) === $path) {
            return $target;
        }
        return false;
    }

    public function __invoke(Request $request, $file)
    {
        $response = new BinaryFileResponse($file);
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $extensionToMime = array(
            'css' => 'text/css',
            'js' => 'application/javascript',
            'gif' => 'image/gif',
            'html' => 'text/html',
        );
        if (isset($extensionToMime[$extension]))
            $response->headers->set('Content-Type', $extensionToMime[$extension]);
        return $response;
    }
}
