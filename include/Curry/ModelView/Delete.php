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
namespace Curry\ModelView;

use Curry\App;
use Symfony\Component\HttpFoundation\Request;

/**
 *
 * @package Curry\ModelView
 */
class Delete extends AbstractBackend {
	protected $modelClass;
	
	public function __construct($modelClass)
	{
		$this->modelClass = $modelClass;
		parent::__construct(App::getInstance());
	}

	public function getModelClass()
	{
		return $this->modelClass;
	}

	public function show(Request $request)
	{
		$modelClass = $this->modelClass;
		/** @var \BaseObject $item */
		$item = $this->getSelection();
		if(!isset($item) || !($item instanceof $modelClass))
			throw new \Exception('No item to delete');

		$name = method_exists($item, '__toString') ? '`'.htmlspecialchars((string)$item).'`' : 'this item';
		if($request->isMethod('POST') && $request->request->get('do_delete')) {
			$pk = $item->getPrimaryKey();
			$item->delete();

			// Trigger update event
			//$this->createModelUpdateEvent($this->modelClass, $pk, 'update');
			if ($item instanceof \Curry_ISearchable)
				\Curry_Backend_Indexer::removeItem($item);

			$this->addMainContent('<p>'.$name.' has been deleted.</p>');
		} else {
			$this->addMainContent('<form method="post">'.
				'<input type="hidden" name="do_delete" value="1" />'.
				'<p>Do you really want to delete '.$name.'?</p>'.
				'<button type="submit" class="btn btn-danger">Delete</button>'.
				'</form>');
		}
		return parent::render();
	}
}
