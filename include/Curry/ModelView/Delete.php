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

		$items = array();
		if ($this['id'] === ':id' && $request->query->has('item')) {
			$pks = array_map(function($i) { return json_decode($i, true); }, $request->query->get('item', array()));
			if ($pks && $this->parent instanceof AbstractBackend) {
				$items = \PropelQuery::from($this->parent->getModelClass())->findPks($pks)->getArrayCopy();
			}
		} else {
			$items = array($this->getSelection());
		}
		$items = array_filter($items, function($item) use($modelClass) {
			return $item instanceof $modelClass;
		});
		if(!count($items))
			throw new \Exception('No item to delete');

		$names = array_map(function($item) {
			return method_exists($item, '__toString') ? '`'.htmlspecialchars((string)$item).'`' : 'this item';
		}, $items);

		if($request->isMethod('POST') && $request->request->get('do_delete')) {
			foreach($items as $i => $item) {
				$pk = $item->getPrimaryKey();
				$item->delete();

				// Trigger update event
				//$this->createModelUpdateEvent($this->modelClass, $pk, 'delete');
				if ($item instanceof \Curry_ISearchable)
					\Curry_Backend_Indexer::removeItem($item);
			}

			$this->addMainContent('<p>'.$names[$i].' has been deleted.</p>');
		} else {
			$this->addMainContent('<form method="post">'.
				'<input type="hidden" name="do_delete" value="1" />'.
				'<p>Do you really want to delete '.join(', ', $names).'?</p>'.
				'<button type="submit" class="btn btn-danger">Delete</button>'.
				'</form>');
		}
		return parent::render();
	}
}
