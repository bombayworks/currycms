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
namespace Curry\Module;

/**
 * Module to show a set of links.
 * 
 * Requires a template, available variables:
 * 
 * * links (array): List of links
 *   * Title (string): Link title
 *   * Url (string): Link URL
 *   * Target (string): Link target
 *   * Active (bool): Is this the current url?
 * 
 * @package Curry\Module
 */
class Links extends AbstractModule {
	/**
	 * List of links.
	 *
	 * @var array
	 */
	protected $links = array();
	
	/** {@inheritdoc} */
	public function toTwig()
	{
		return array(
			'links' => \Curry\Util\ArrayHelper::objectsToArray($this->links, null, array($this, 'getLinkProperties')),
		);
	}
	
	/**
	 * Prepare link for Twig.
	 *
	 * @param array $link
	 * @return array
	 */
	public function getLinkProperties($link)
	{
		return array(
			'Title' => $link['title'],
			'Url' => $link['url'],
			'Target' => $link['target'],
			'Active' => url($link['url'])->getAbsolute() == url('')->getAbsolute(),
		);
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML list' => <<<TPL
<ul>
	{% for link in links %}
	<li class="{{link.Active?'active':''}}"><a href="{{link.Url}}"{{link.Target ? ' target="'~link.Target~'"' : ''}}>{{link.Title}}</a></li>
	{% endfor %}
</ul>
TPL
		);
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$targets = array(null => "[ Default ]", "_self" => "_self", "_blank" => "_blank", "_top" => "_top");
		
		$linkForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Link',
			'elements' => array(
				'title' => array('text', array(
					'label' => 'Title',
					'required' => true,
				)),
				'url' => array('link', array(
					'label' => 'Link',
					'required' => true,
				)),
				'target' => array('select', array(
					'label' => 'Target',
					'multiOptions' => $targets,
				)),
			),
		));
		
		$form = new \Curry_Form_MultiForm(array(
			'legend' => 'Links',
			'cloneTarget' => $linkForm,
			'defaults' => $this->links,
		));
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->links = $values;
	}
}
