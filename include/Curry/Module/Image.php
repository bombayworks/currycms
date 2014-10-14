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
 * Single image module.
 * 
 * Requires a template, available variables:
 * 
 * * Source (string): Image source.
 * * Title (string): Image title.
 * * Description (string): Image description.
 * * Link (string): Image link.
 * * Width (int): Width of image in pixels.
 * * Height (int): Height of image in pixels.
 * 
 * @package Curry\Module
 */
class Image extends AbstractModule {
	/**
	 * Image source.
	 *
	 * @var string
	 */
	protected $source = "images/default.jpg";
	
	/**
	 * Image title.
	 *
	 * @var string
	 */
	protected $title = "";
	
	/**
	 * Image description.
	 *
	 * @var string
	 */
	protected $description = "";
	
	/**
	 * Image link.
	 *
	 * @var string
	 */
	protected $link = "";

	/** {@inheritdoc} */
	public function toTwig()
	{
		$vars = array(
			"Source" => $this->source,
			"Title" => $this->title,
			"Description" => $this->description,
			"Link" => $this->link,
		);
		
		// attempt to find width/height of image
		try {
			list($width, $height, $type, $attr) = getimagesize($this->source);
			$vars['Width'] = $width;
			$vars['Height'] = $height;
		}
		catch (\Exception $e) {}
		
		return $vars;
	}
	
	/** {@inheritdoc} */
	public static function getDefaultTemplate()
	{
		return
			<<<TPL
{% if Link %}<a href="{{Link}}">{% endif %}
<img src="{{Source}}" alt="{{Title}}" />
{% if Link %}</a>{% endif %}
TPL
		;
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML img' => self::getDefaultTemplate(),
		);
	}

	/** {@inheritdoc} */
	public function showBack()
	{
		$form = new \Curry_Form_SubForm(array(
			'elements' => array(
				'source' => array('previewImage', array(
					'label' => 'Source',
					'value' => $this->source,
				)),
				'title' => array('text', array(
					'label' => 'Title',
					'value' => $this->title,
				)),
				'description' => array('text', array(
					'label' => 'Description',
					'value' => $this->description,
				)),
				'link' => array('link', array(
					'label' => 'Link',
					'value' => $this->link,
				)),
			)
		));

		return $form;
	}

	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->source = $values['source'];
		$this->title = $values['title'];
		$this->description = $values['description'];
		$this->link = $values['link'];
	}
}
