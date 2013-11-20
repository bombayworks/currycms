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
 * Navigation module.
 * 
 * Requires a template, the following variables are available:
 * 
 * * page (Page): Page to create navigation around. This is usually the parent of the navigation items.
 *   * Name (string): Name of page.
 *   * Visible (bool): Is page visible in menus?
 *   * Enabled (bool): Is page enabled?
 *   * IncludeInIndex (bool): Is page included in search index?
 *   * Url (string): Url relative to base path.
 *   * FullUrl (string): Full URL, including domain.
 *   * BodyId (string): Identifier to be used with CSS class or id.
 *   * IsInternalRedirect (bool): Does page redirect to another page?
 *   * IsExternalRedirect (bool): Does page redirect to an external page?
 *   * IsRedirect (bool): Does page redirect?
 *   * RedirectUrl (string): Url to redirection target.
 *   * parent (Page): Parent page object.
 *   * subpages (array of Page): List of (visible) subpages.
 *   * allSubpages (array of Page): List of all subpages.
 *   * meta (array): Page metadata.
 *     * MetadataProperty (string): Value of metadata
 *     * cascaded.MetadataProperty (string): Cascaded metadata value (from parent page).
 *     * inherited.MetadataProperty (string): Inherited metadata value (from base page).
 * 
 * @package Curry\Module
 */
class Curry_Module_Navigation extends Curry_Module {
	/**
	 * Sort by sortindex in ascending order.
	 */
	const ORDER_SORTINDEX_ASC = 0;
	
	/**
	 * Sort by sortindex in descending order.
	 */
	const ORDER_SORTINDEX_DESC = 1;
	
	/**
	 * Sort by name in ascending order.
	 */
	const ORDER_NAME_ASC = 2;
	
	/**
	 * Sort by name in descending order.
	 */
	const ORDER_NAME_DESC = 3;
	
	/**
	 * The selected page.
	 *
	 * @var int|null
	 */
	protected $pageId = null;
	
	/**
	 * Depth offset adjust the page, walking up or down (towards the current page) in the page tree.
	 *
	 * @var int
	 */
	protected $depthOffset = 0;
	
	/**
	 * Should we pick the parent page if the selected page doesn't have any children?
	 *
	 * @var bool
	 */
	protected $useParentPage = false;
	
	/**
	 * Should we include hidden pages in the subpages array?
	 *
	 * @var bool
	 */
	protected $showHidden = false;
	
	/**
	 * How should the subpages array be sorted?
	 *
	 * @var int
	 */
	protected $sortOrder = self::ORDER_SORTINDEX_ASC;
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		// find selected page
		if($this->pageId === null)
			$page = $this->getPageGenerator()->getPage();
		else
			$page = PageQuery::create()->findPk($this->pageId);
		
		// offset
		if($this->depthOffset < 0) {
			// walk up
			for($i = $this->depthOffset; $i < 0 && $page; ++$i)
				$page = Page::getCachedParent($page);
		} else if($this->depthOffset > 0) {
			// walk down
			$path = array();
			$activePage = $this->getPageGenerator()->getPage();
			while($activePage) {
				if($activePage === $page)
					break;
				$path[] = $activePage;
				$activePage = Page::getCachedParent($activePage);
			}
			if ($activePage && $this->depthOffset <= count($path))
				$page = $path[ count($path) - $this->depthOffset ];
			else
				$page = null;
		}

		// if we dont have any subpages...
		if($this->useParentPage && $page && !count(Page::getCachedChildren($page))) {
			// ...use the parent page if it exists
			$parent = Page::getCachedParent($page);
			if($parent)
				$page = $parent;
		}
		
		return $page ? array('page' => $this->twigGetPage($page)) : array();
	}

	/** {@inheritdoc} */
	public static function getDefaultTemplate()
	{
		return <<<TPL
{% macro subpages(page) %}
	{% if page.subpages %}
	<ul>
		{% for page in page.subpages %}
		<li><a href="{{page.Url}}">{{ page.Name }}</a>{{ _self.subpages(page) }}</li>
		{% endfor %}
	</ul>
	{% endif %}
{% endmacro %}
{{ _self.subpages(page) }}
TPL
		;
	}

	/**
	 * Google sitemap template.
	 *
	 * @return string
	 */
	public static function getSitemapTemplate()
	{
		return <<<TPL
{% macro displaypage(page) %}
<url>
	<loc>{{ url(page.Url,{}).getAbsolute() }}</loc>
</url>
{% endmacro %}
{% macro subpages(page) %}
{{ _self.displaypage(page) }}
	{% if page.subpages %}
		{% for page in page.subpages %}
			{{ _self.subpages(page) }}
		{% endfor %}
	{% endif %}
{% endmacro %}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{{ _self.subpages(page) }}
</urlset>
TPL
		;
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML list' => <<<TPL
{% if page.subpages %}
<ul>
	{% for page in page.subpages %}
	<li{{ page.IsActive ? ' class="current"' : '' }}><a href="{{page.Url}}">{{ page.Name }}</a></li>
	{% endfor %}
</ul>
{% endif %}
TPL
			,
			'HTML recursive list' => self::getDefaultTemplate(),
			'XML Sitemap' => self::getSitemapTemplate(),
		);
	}
	
	/**
	 * Get twig properties for page.
	 *
	 * @param Page $page
	 * @return array
	 */
	public function twigGetPage(Page $page)
	{
		$p = $page->toTwig();
		$p['parent'] = new Curry_OnDemand(array($this, 'twigGetParent'), $page);
		$p['subpages'] = new Curry_OnDemand(array($this, 'twigGetSubpages'), $page);
		
		$activePage = $this->getPageGenerator()->getPage();
		$p['IsActive'] = $activePage->getUrl() === $page->getUrl();
		$p['IsActiveSubpage'] = new Curry_OnDemand(array($this, 'twigGetActiveSubpage'), $page, $activePage);
		
		return $p;
	}
	
	/**
	 * Twig callback to get parent page.
	 *
	 * @param Page $page
	 * @return array|null
	 */
	public function twigGetParent(Page $page)
	{
		$parentPage = Page::getCachedParent($page);
		if($parentPage)
			return $this->twigGetPage($parentPage);
		return null;
	}
	
	/**
	 * Twig callback to get subpages for page.
	 *
	 * @param Page $page
	 * @return array|Curry_Twig_CollectionWrapper
	 */
	public function twigGetSubpages(Page $page)
	{
		if($page->isLeaf())
			return array();
		
		$subpages = array();
		$children = Page::getCachedChildren($page);
		foreach($children as $subpage) {
			if($subpage->getEnabled() && ($this->showHidden || $subpage->getVisible()))
				$subpages[] = $subpage;
		}
		if($children instanceof PropelCollection)
			$children->clearIterator();
		
		// set order
		switch($this->sortOrder) {
			case self::ORDER_SORTINDEX_DESC:
				Curry_Array::sortOn($subpages, 'getTreeLeft', Curry_Array::SORT_REVERSE);
				break;
			case self::ORDER_NAME_ASC:
				Curry_Array::sortOn($subpages, 'getName');
				break;
			case self::ORDER_NAME_DESC:
				Curry_Array::sortOn($subpages, 'getName', Curry_Array::SORT_REVERSE);
				break;
		}
		
		return new Curry_Twig_CollectionWrapper($subpages, array($this, 'twigGetPage'));
	}
	
	/**
	 * Twig callback to decide if a page is the active page or below it.
	 *
	 * @param Page $page
	 * @param Page $activePage
	 * @return bool
	 */
	public function twigGetActiveSubpage(Page $page, Page $activePage)
	{
		while($activePage) {
			if($activePage->getUrl() == $page->getUrl())
				return true;
			$activePage = $activePage->getParent();
		}
		return false;
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$pages = array(null => "[ Active Page ]");
		$pages += PagePeer::getSelect();
		
		$form = new Curry_Form_SubForm(array(
		    'elements' => array(
		    	'page_id' => array('select', array(
		    		'label' => 'Page',
		    		'multiOptions' => $pages,
		    		'value' => $this->pageId,
		    	)),
		    	'sort_order' => array('select', array(
		    		'label' => 'Sort order',
		    		'multiOptions' => array(
		    			self::ORDER_SORTINDEX_ASC => 'Page order',
		    			self::ORDER_SORTINDEX_DESC => 'Page order reversed',
		    			self::ORDER_NAME_ASC => 'Page name',
		    			self::ORDER_NAME_DESC => 'Page name reversed',
		    		),
		    		'value' => $this->sortOrder,
		    	)),
		    	'depth_offset' => array('text', array(
		    		'label' => 'Depth Offset',
		    		'description' => 'If positive, the selected page will move down the page tree towards the active page. If negative, the selected page will move up in the page tree towards the root.',
		    		'value' => $this->depthOffset,
		    	)),
		    	'use_parent_page' => array('checkbox', array(
		    		'label' => 'Use parent page if there are no subpages',
		    		'value' => $this->useParentPage,
		    	)),
		    	'show_hidden' => array('checkbox', array(
		    		'label' => 'Show hidden pages',
		    		'value' => $this->showHidden,
		    	)),
			)
		));
		
		$form->addDisplayGroup(array('depth_offset', 'use_parent_page', 'show_hidden'), 'advanced', array('legend' => 'Advanced', 'class' => 'advanced'));
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		
		$this->pageId = $values['page_id'] ? $values['page_id'] : null;
		$this->depthOffset = $values['depth_offset'];
		$this->useParentPage = $values['use_parent_page'];
		$this->showHidden = $values['show_hidden'];
		$this->sortOrder = $values['sort_order'];
	}
}
