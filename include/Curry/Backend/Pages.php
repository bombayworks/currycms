<?php

namespace Curry\Backend;

use Symfony\Component\HttpFoundation\Request;

class Pages extends Base {
	public function initialize()
	{
		$this->addView('menu', $this->getMenu());
		$this->addViewFunction('page', array($this, 'showPage'), ':id/');
	}

	protected function getMenu()
	{
		$query = \PageQuery::create();
		$tree = new \Curry_Tree_Propel($query, array(
			'minExpandLevel' => 2,
			'autoFocus' => false,
			'selectMode' => 1, // single
			'dndCallback' => array(__CLASS__, 'movePage'),
			'nodeCallback' => array($this, 'getPageTreeNode'),
		));
		// Override tree cookies to force tree selection
		$cookieId = $tree->getOption('cookieId');
		setcookie($cookieId."-focus", isset($_GET['page_id']) ? $_GET['page_id'] : null);
		setcookie($cookieId."-select", isset($_GET['page_id']) ? $_GET['page_id'] : null);

		return $tree;
	}

	/**
	 * Get page tree node properties.
	 *
	 * @param \Page $page
	 * @param \Curry_Tree $tree
	 * @param int $depth
	 * @return array
	 */
	public function getPageTreeNode($page, \Curry_Tree $tree, $depth = 0)
	{
		$p = $tree->objectToJson($page, $tree, $depth);

		if($page->getWorkingPageRevisionId() && $page->getWorkingPageRevisionId() !== $page->getActivePageRevisionId()) {
			$p['title'] .= '*';
			$p['addClass'] = 'page-unpublished';
		}

		$p['expand'] = true;
		$p['href'] = $this->page->url(array('id' => $page->getPageId()));

		// Mark active node
		if(isset($_GET['page_id']) && $_GET['page_id'] == $page->getPageId())
			$p['activate'] = $p['focus'] = $p['select'] = true;

		// Icon
		$p['iconClass'] = 'no-icon';
		if(\Curry_Backend_Page::isTemplatePage($page)) {
			if ($page === \Curry_Backend_Page::getTemplatePage())
				$p['title'] .= ' <span class="icon-columns"></span>';
		} else {
			$icon = "";
			if(!$page->getEnabled())
				$icon .= '<span class="icon-lock" title="Inactive"></span>';
			if(!$page->getVisible())
				$icon .= '<span class="icon-eye-close" title="Do not show in menu"></span>';
			if($page->getRedirectMethod())
				$icon .= '<span class="icon-link" title="Redirect"></span>';
			if ($icon)
				$p['title'] .= " $icon";
		}
		return $p;
	}

	public function show(Request $request)
	{
		$this->addMenuItem('Foo', 'bar', 'message', 'notification');
		$this->addMainContent('foo');
		$this->addMenuContent($this->menu);
		return $this->render();
	}

	public function showPage(Request $request, $view)
	{
		$page = \PageQuery::create()->findPk($view['id']);

		$this->addBreadcrumb('Pages', $view->parent->url());
		$this->addBreadcrumb($page->getName(), $view->url());
		$this->addMenuContent($this->menu);
		$this->addMainContent($page->getName());


		$form = new \Curry\Form\Form(array(
			'fields' => array(
				'test' => array(
					'type' => 'text'
				)
			),
		));
		$this->addMainContent($form->render());

		return $this->render();
	}
}