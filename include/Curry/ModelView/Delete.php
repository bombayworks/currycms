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
class Curry_ModelView_Delete extends Curry_ModelView_Abstract {
	protected $modelClass;
	
	public function __construct($modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function getModelClass()
	{
		return $this->modelClass;
	}

	public function render(\Curry\Backend\AbstractLegacyBackend $backend, array $params)
	{
		$item = $this->getSelection($params);
		if(!isset($item))
			throw new Exception('No item to delete');

		$name = method_exists($item, '__toString') ? '`'.htmlspecialchars((string)$item).'`' : 'this item';
		if(isPost() && $_POST['do_delete']) {
			$pk = $item->getPrimaryKey();
			$item->delete();

			// Trigger update event
			$backend->createModelUpdateEvent($this->modelClass, $pk, 'update');
			if ($item instanceof Curry_ISearchable)
				Curry_Backend_Indexer::removeItem($item);

			$backend->addMainContent('<p>'.$name.' has been deleted.</p>');
		} else {
			$backend->addMainContent('<form method="post" action="'.url('', $params).'">'.
				'<input type="hidden" name="do_delete" value="1" />'.
				'<p>Do you really want to delete '.$name.'?</p>'.
				'<button type="submit" class="btn btn-danger">Delete</button>'.
				'</form>');
		}
	}
}
