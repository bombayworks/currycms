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
use Symfony\Component\HttpFoundation\Response;

class Maintenance {
    protected $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function __invoke($message, $page = null)
    {
        if($page !== null && ($page = \PageQuery::create()->findPk($page))) {
            // @todo set global maintenance message variable
            //$vars['curry']['MaintenanceMessage'] = $message;
            $generator = AbstractGenerator::create($this->app, $page->getActivePageRevision());
            $response = $generator->render();
        } else {
            $response = Response::create($message);
        }
        $response->setStatusCode(503);
        $response->headers->set('Retry-After', '3600');
        return $response;
    }
}
