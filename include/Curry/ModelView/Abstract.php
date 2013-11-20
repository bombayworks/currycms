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
 *
 * @package Curry\ModelView
 */
abstract class Curry_ModelView_Abstract {
	protected $parentView = null;

	abstract public function render(Curry_Backend $backend, array $params);
	abstract public function getModelClass();

	public function getSelfSelection($params)
	{
		$pk = isset($params['item']) ? json_decode($params['item'], true) : null;
		return $pk ? PropelQuery::from($this->getModelClass())->findPk($pk) : null;
	}

	public function getSelection($params)
	{
		return $this->parentView ? $this->parentView->getSelfSelection($params) : null;
	}

	public function getParentSelection($params)
	{
		return $this->parentView ?
			$this->parentView->getSelection($this->getParentParams($params))
			: null;
	}

	public function getParentParams($params)
	{
		return isset($params['_parent']) ? $params['_parent'] : null;
	}

	public function dispatch(array $action, Curry_Backend $backend, array $params)
	{
		$this->render($backend, $params);
	}
	
	public function show(Curry_Backend $backend, $params = null)
	{
		if ($params === null)
			$params = $_GET;
		try {
			/*if (isAjax())
				$pr = Curry_URL::setPreventRedirect(true);*/
			$action = isset($params['action']) ? explode(".", $params['action']) : array();
			$this->dispatch($action, $backend, $params);
			/*if (isAjax())
				Curry_URL::setPreventRedirect($pr);*/
		}
		catch (Curry_Exception_RedirectPrevented $e) {
			Curry_Application::returnPartial('<script type="text/javascript">window.location.href = "'.addcslashes($e->getUrl(), '"').'";</script>');
		}
	}
}
