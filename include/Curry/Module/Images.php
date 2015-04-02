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
use Curry\Util\ArrayHelper;

/**
 * Module to show a set of images.
 * 
 * Requires a template, available variables:
 * 
 * * images (array): List of the images
 *   * Source (string): Image source.
 *   * Title (string): Image title.
 *   * Description (string): Image description.
 *   * Link (string): Image link.
 *   * Width (int): Width of image in pixels.
 *   * Height (int): Height of image in pixels.
 * 
 * @package Curry\Module
 */
class Images extends AbstractModule {
	/**
	 * Array of images
	 *
	 * @var array
	 */
	protected $images = array();

	/** {@inheritdoc} */
	public function toTwig()
	{
		return array(
			'images' => ArrayHelper::objectsToArray($this->images, null, array($this, 'getImageProperties')),
		);
	}
	
	/**
	 * Image variables for twig.
	 *
	 * @param array $image
	 * @return array
	 */
	public function getImageProperties($image)
	{
		$vars = array(
			"Source" => $image['source'],
			"Title" => $image['title'],
			"Description" => $image['description'],
			"Link" => $image['link'],
			"Target" => $image['target'],
		);
		
		// attempt to find width/height of image
		try {
			list($width, $height, $type, $attr) = getimagesize($image['source']);
			$vars['Width'] = $width;
			$vars['Height'] = $height;
		}
		catch (\Exception $e) {}

		return $vars;
	}
	
	/** {@inheritdoc} */
	public static function getPredefinedTemplates()
	{
		return array(
			'HTML list' => <<<TPL
<ul>
	{% for image in images %}
	<li>
		{% if image.Link %}<a href="{{image.Link}}"{{image.Target ? ' target="'~image.Target~'"' : ''}}>{% endif %}
		<img src="{{image.Source}}" alt="{{image.Title}}" />
		{% if image.Link %}</a>{% endif %}
	</li>
	{% endfor %}
</ul>
TPL
		);
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$targets = array(null => "[ Default ]", "_self" => "_self", "_blank" => "_blank", "_top" => "_top");
		
		$imageForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Image',
			'elements' => array(
				'source' => array('previewImage', array(
					'label' => 'Image',
					'required' => false,
				)),
				'title' => array('text', array(
					'label' => 'Title',
					'required' => false,
				)),
				'description' => array('text', array(
					'label' => 'Description',
					'required' => false,
				)),
				'link' => array('link', array(
					'label' => 'Link',
				)),
				'target' => array('select', array(
					'label' => 'Target',
					'multiOptions' => $targets,
				)),
				
			),
		));
		
		$form = new \Curry_Form_MultiForm(array(
			'legend' => 'Images',
			'cloneTarget' => $imageForm,
			'defaults' => $this->images,
		));
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->images = $values;
	}
}
