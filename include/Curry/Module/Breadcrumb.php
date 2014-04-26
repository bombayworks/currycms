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
 * Breadcrumb navigation.
 * 
 * Requires a template, the following variables are available:
 * 
 * * pages (array of Page): An array of pages, either from root to
 *   current page, or the other way around depending on setting in backend.
 * 
 * @package Curry\Module
 */
class Curry_Module_Breadcrumb extends Curry_Module {
	/**
	 * List pages from root to the current page.
	 */
	const FROM_ROOT = 'FromRoot';
	
	/**
	 * List pages from current page to the root page.
	 *
	 */
	const TO_ROOT = 'ToRoot';
	
	/**
	 * The direction, one of FROM_ROOT and TO_ROOT.
	 *
	 * @var string
	 */
	protected $direction = self::FROM_ROOT;
	
	/**
	 * Custom root page.
	 *
	 * @var int|null
	 */
	protected $rootPageId = null;
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		$page = \Curry\App::getInstance()->page;
		$pages = array();
		while($page) {
			$pages[] = $page->toTwig();
			if($page->getPageId() == $this->rootPageId)
				break;
			$page = Page::getCachedParent($page);
		}
		
		if($this->direction === self::FROM_ROOT)
			$pages = array_reverse($pages);
		
		return array(
			'pages' => $pages,
		);
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML list' => <<<TPL
<ul class="breadcrumb">
	{% for page in pages %}
	<li>
		{% if loop.last or not page.Visible %}
		<span>{{ page.Name }}</span>
		{% else %}
		<a href="{{page.Url}}">{{ page.Name }}</a>
		{% endif %}
	</li>
	{% endfor %}
</ul>
TPL
			,
			'HTML simple' => <<<TPL
<p class="breadcrumb">
	You are here:
	{% for page in pages %}
		{{ loop.first ? '' : '&raquo;' }}
		{% if loop.last or not page.Visible %}
		<span>{{ page.Name }}</span>
		{% else %}
		<a href="{{page.Url}}">{{ page.Name }}</a>
		{% endif %}
	{% endfor %}
</p>
TPL
		);
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new Curry_Form_SubForm(array(
			'elements' => array(
				'direction' => array('select', array(
					'label' => 'Direction',
					'multiOptions' => array(self::TO_ROOT => "From page to root", self::FROM_ROOT => "From root to page"),
					'value' => $this->direction,
				)),
				'root' => array('select', array(
					'label' => 'RootPage',
					'multiOptions' => PagePeer::getSelect(),
					'value' => $this->rootPageId,
				)),
			)
		));
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->direction = $values['direction'];
		$this->rootPageId = $values['root'];
	}
}
