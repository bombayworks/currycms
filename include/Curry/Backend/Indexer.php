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
use Curry\Util\Propel;
use Curry\Util\Html;

/**
 * Manage search index.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_Indexer extends \Curry\Backend\AbstractLegacyBackend {
	/** {@inheritdoc} */
	public function getGroup()
	{
		return "System";
	}
	
	/**
	 * Create menu items.
	 */
	public function addMainMenu()
	{
		$this->addMenuItem("Search", url('', array("module", "view" => "Main")));
		$this->addMenuItem("Create index", url('', array("module", "view" => "Create")));
		$this->addMenuItem("Rebuild index", url('', array("module", "view" => "Rebuild")));
		$this->addMenuItem("Optimize index", url('', array("module", "view" => "Optimize")));
		$this->addMenuItem("Index statistics", url('', array("module", "view" => "Statistics")));
	}
	
	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMainMenu();
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'elements' => array(
				'keywords' => array('text', array(
					'label' => 'Keywords',
					'required' => true,
				)),
				'search' => array('submit', array(
					'label' => 'Search',
				)),
			)
		));
		
		// Validate
		if (isPost() && $form->isValid($_POST)) {
			$this->addMainContent($form);
			
			$index = Curry_Core::getSearchIndex();
			$hits = $index->find($form->keywords->getValue());
			
			$html = "";
			foreach($hits as $hit) {
				$fieldNames = $hit->getDocument()->getFieldNames();
				$title = in_array('title', $fieldNames) ? (string)$hit->title : '<Untitled>';
				$url = in_array('url', $fieldNames) ? (string)$hit->url : null;
				$model = in_array('model', $fieldNames) ? (string)$hit->model : 'Unknown type';
				$item = ($url !== null) ? Html::tag('a', array('href' => $url), $title) : $title;
				$item .= ' ('.htmlspecialchars($model).')<br/>';
				$html .= '<li>'.$item.'<small>Fields: '.htmlspecialchars(join(', ', $fieldNames)).'</small></li>';
			}
			$html .= "<li>Hits: ".count($hits) . " / " . $index->numDocs()."</li>";
			$this->addMainContent("<ul>".$html."</ul>");
		} else {
			$this->addMainContent($form);
		}
	}
	
	/**
	 * Create index.
	 */
	public function showCreate()
	{
		$this->addMainMenu();
		
		Curry_Core::getSearchIndex(true);
		$this->addMessage('Index created', self::MSG_SUCCESS);
	}

	/**
	 * Rebuild the search index.
	 *
	 * @param bool $ajax
	 * @throws Exception
	 */
	public static function doRebuild($ajax = false)
	{
		$ses = new \Zend\Session\Container(__CLASS__);
		$index = Curry_Core::getSearchIndex();
		$app = new Frontend();
		Curry_URL::setReverseRouteCallback(array($app, 'reverseRoute'));

		try {
			while ($ses->model < count($ses->models)) {
				$model = $ses->models[$ses->model];
				if ($model === '@custom') {
					// Trigger custom indexer
					$ses->model++;
					$indexerClass = \Curry\App::getInstance()->config->curry->indexerClass;
					if($indexerClass && is_callable(array($indexerClass, 'build'))) {
						call_user_func(array($indexerClass, 'build'));
					}
				} else {
					// Remove old entries
					if (!$ses->offset) {
						$hits = $index->find('model:'.$model);
						foreach ($hits as $hit)
							$index->delete($hit->id);
					}

					$query = PropelQuery::from($model);
					$maxItems = $query->count();
					$items = $query->offset($ses->offset)->limit($ses->limit)->find();
					foreach($items as $item) {
						$ses->offset++;
						if (!self::updateItem($item, $index, false))
							$ses->failed++;
						else
							$ses->success++;
					}
					// move on to next model?
					if ($ses->offset >= $maxItems || count($items) < $ses->limit) {
						$ses->model++;
						$ses->offset = 0;
						$maxItems = 1;
					}
				}

				$continue = $ses->model < count($ses->models);
				if ($continue && $ajax) {
					// Return current status
					$part = 1 / count($ses->models);
					$progress = $ses->model * $part + ($ses->offset / $maxItems) * $part;
					self::returnJson(array(
						'progress' => round(100 * $progress),
						'continue' => true,
						'status' => "Indexing ".$ses->models[$ses->model]."...",
					));
				}
			}
			// All done!
			if ($ajax) {
				$status = "Completed, ".$ses->success.' entries updated successfully!';
				if ($ses->failed) {
					$status .= ' '.$ses->failed.' items failed.';
				}
				self::returnJson(array(
					'progress' => 100,
					'continue' => false,
					'status' => $status,
				));
			}
		}
		catch (Exception $e) {
			if ($ajax)
				self::returnJson(array('continue' => false, 'status' => $e->getMessage()));
			else
				throw $e;
		}
	}

	/**
	 * Remove propel model item from index.
	 *
	 * @param BaseObject $item
	 * @param Zend_Search_Lucene_Interface $index
	 */
	public static function removeItem(BaseObject $item, Zend_Search_Lucene_Interface $index = null)
	{
		$model = get_class($item);
		if (!$index)
			$index = Curry_Core::getSearchIndex();
		$hits = $index->find("model:$model");
		$pk = serialize($item->getPrimaryKey());
		foreach ($hits as $hit) {
			if ($hit->model_id == $pk)
				$index->delete($hit->id);
		}
	}

	/**
	 * Add or update propel model item in search index.
	 *
	 * @param BaseObject $item
	 * @param Zend_Search_Lucene_Interface $index
	 * @param bool $removeOld
	 * @return bool
	 */
	public static function updateItem(BaseObject $item, Zend_Search_Lucene_Interface $index = null, $removeOld = true)
	{
		$model = get_class($item);
		try {
			if (!$index)
				$index = Curry_Core::getSearchIndex();
			if ($removeOld)
				self::removeItem($item, $index);
			$hasI18n = in_array('i18n', array_keys(PropelQuery::from($model)->getTableMap()->getBehaviors()));
			if ($hasI18n) {
				$translations = $item->{"get{$model}I18ns"}();
				foreach ($translations as $translation) {
					$item->setLocale($translation->getLocale());
					self::addSearchDocument($index, $item->getSearchDocument(), $item, $translation->getLocale());
				}
			} else {
				self::addSearchDocument($index, $item->getSearchDocument(), $item);
			}
			return true;
		}
		catch (Exception $e) {
			trace_error($model.'('.(string)$item.'): '.$e->getMessage());
			return false;
		}
	}

	/**
	 * @param Zend_Search_Lucene_Interface $index
	 * @param Zend_Search_Lucene_Document|array|null $doc
	 * @param BaseObject $item
	 * @param null|string $locale
	 * @throws Exception
	 */
	protected static function addSearchDocument(Zend_Search_Lucene_Interface $index, $doc, BaseObject $item, $locale = null)
	{
		if ($doc === null) {
			return;
		} else if (is_array($doc)) {
			foreach($doc as $d)
				self::addSearchDocument($index, $d, $item, $locale);
		} else if (is_object($doc) && $doc instanceof Zend_Search_Lucene_Document) {
			// Add document to the index.
			$doc->addField(Zend_Search_Lucene_Field::Keyword('model', get_class($item)));
			$doc->addField(Zend_Search_Lucene_Field::Keyword('model_id', serialize($item->getPrimaryKey())));
			if($locale !== null)
				$doc->addField(Zend_Search_Lucene_Field::Keyword('locale', $locale));
			$index->addDocument($doc);
		} else {
			throw new Exception('Invalid result from getSearchDocument.');
		}
	}

	/**
	 * Should be called before calling doRebuild().
	 */
	public static function initRebuild()
	{
		$models = array();
		foreach(Propel::getModels() as $classes) {
			foreach($classes as $model) {
				if(in_array('Curry_ISearchable', class_implements($model)))
					$models[] = $model;
			}
		}

		if (\Curry\App::getInstance()->config->curry->indexerClass)
			$models[] = '@custom';

		$ses = new \Zend\Session\Container(__CLASS__);
		$ses->models = $models;
		$ses->model = 0;
		$ses->offset = 0;
		$ses->limit = 10;
		$ses->failed = 0;
		$ses->success = 0;
	}
	
	/**
	 * Rebuild search index. Will use ajax to split up the process over several
	 * requests to show progress and overcome execution time limits.
	 */
	public function showRebuild()
	{
		if (isPost('index')) {
			self::doRebuild(isAjax());
			return;
		}

		$this->addMainMenu();
		self::initRebuild();

		$url = url('', array('module', 'view'));
		$rebuildLink = url('', array('module', 'view' => 'RebuildAll', 'logintoken' => User::getUser()->getLoginToken()))->getAbsolute("&", true);
		$html = <<<HTML
<div id="progressbar"></div>
<p id="status">&nbsp;</p>
<button id="rebuild-start" class="btn btn-primary">Start</button>
<a href="$rebuildLink" class="btn btn-link">Rebuild link</button>
<script type="text/javascript">
(function() {
$.require('jquery-ui', function() {
	function index() {
		$.post("$url", {index: 'true'}, function(data) {
			$('#progressbar').progressbar('value', data.progress);
			$('#status').text(data.status);
			if (data.continue)
				index();
		});
	}
	$("#progressbar").progressbar({ value: 0 });
	$("#rebuild-start").click(function() {
		$(this).attr('disabled', 'disabled');
		$('#status').html('Indexing...');
		index();
	});
});
})();
</script>
HTML;
		$this->addMainContent($html);
	}

	/**
	 * Rebuild search index using a single request.
	 *
	 * @throws Exception
	 */
	public function showRebuildAll()
	{
		if (Curry_URL::validate()) {
			// Override and increase max execution time if set
			$timeLimit = ini_get('max_execution_time');
			if($timeLimit && $timeLimit < 250) {
				@set_time_limit(250);
			}

			Curry_Backend_Indexer::initRebuild();
			Curry_Backend_Indexer::doRebuild();
			$ses = new \Zend\Session\Container(__CLASS__);
			if ($ses->failed)
				$this->addMessage($ses->failed.' entries failed indexing.', self::MSG_WARNING);

			$this->addMainMenu();
			$this->addMessage('Search index rebuilt, '.$ses->success.' entries updated successfully.', self::MSG_SUCCESS);
		} else {
			throw new Exception('Invalid rebuild link!');
		}
	}
	
	/**
	 * Optimize index.
	 */
	public function showOptimize()
	{
		$this->addMainMenu();
		
		$index = Curry_Core::getSearchIndex();
		$index->optimize();
		$this->addMessage('Index successfully optimized', self::MSG_SUCCESS);
	}
	
	/**
	 * Show index statistics.
	 */
	public function showStatistics()
	{
		$this->addMainMenu();
		
		$index = Curry_Core::getSearchIndex();
		$this->addMessage('Number of documents: '.$index->numDocs());
		$this->addMessage('Number of deleted documents: ' . ($index->count() - $index->numDocs()));
		$this->addMessage('Number of terms: '.count($index->terms()));
	}
}
