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
 * Helper class to embed flash content.
 *
 * @package Curry
 */
class Curry_Flash {
	/**
	 * SWFObject path.
	 */
	const SWFOBJECT_PATH = 'shared/libs/swfobject-2.2/';
	
	/**
	 * Embed using SWFObject static method.
	 */
	const SWFOBJECT_STATIC = "SWF_OBJECT_STATIC";
	
	/**
	 * Embed using SWFObject dynamic method.
	 */
	const SWFOBJECT_DYNAMIC = "SWF_OBJECT_DYNAMIC";
	
	/**
	 * Generates embedding code for flash.
	 * 
	 * $options = array(
	 * 	'flashvars' => array(...),
	 * 	'params' => array(
	 * 		'wmode' => 'transparent'
	 * 	),
	 * 	'attributes' => array(
	 * 		'id' => '',
	 * 		'name' => ''
	 * 	),
	 *  'target' => '',
	 * 	'expressInstall' => '',
	 *  'alternativeContent' => '',
	 * )
	 *
	 * @param string $method
	 * @param string $source
	 * @param int $width
	 * @param int $height
	 * @param string $version
	 * @param array $options
	 * @return array	Array with two elements, 'html' and 'script'.
	 */
	public static function embed($method, $source, $width, $height, $version, array $options)
	{
		switch($method) {
			case self::SWFOBJECT_DYNAMIC:
				return self::swfobjectDynamic($source, $width, $height, $version, $options);
				break;
			case self::SWFOBJECT_STATIC:
				return self::swfobjectStatic($source, $width, $height, $version, $options);
				break;
			default:
				throw new Exception("Unknown embed method.");
		}
	}
	
	/**
	 * Generates SWFObject dynamic embedding code.
	 *
	 * @param string $source
	 * @param int $width
	 * @param int $height
	 * @param string $version
	 * @param array $options
	 * @return array
	 */
	private static function swfobjectDynamic($source, $width, $height, $version, array $options)
	{
		if(!is_array($options['attributes']))
			$options['attributes'] = array();
			
		if(isset($options['flashvars']))
			$options['params']['flashvars'] = http_build_query($options['flashvars']);
		
		$params = json_encode($options['params']);
		$attributes = count($options['attributes']) ? json_encode($options['attributes']) : '{}';
		$expressInstall = empty($options['expressInstall']) ? 'null' : '"' . $options['expressInstall'] . '"';
		
		return array(
			'html' => '<div id="'.$options['target'].'">'.$options['alternativeContent'].'</div>',
			'script' => "swfobject.embedSWF('$source', '{$options['target']}', '$width', '$height', '$version', $expressInstall, null, $params, $attributes);",
		);
	}
	
	/**
	 * Generates SWFObject static embedding code.
	 *
	 * @param string $source
	 * @param int $width
	 * @param int $height
	 * @param string $version
	 * @param array $options
	 * @return array
	 */
	private static function swfobjectStatic($source, $width, $height, $version, array $options)
	{
		$attributes = (array)$options['attributes'];
		$params = (array)$options['params'];
		if(isset($options['flashvars']))
			$params['flashvars'] = http_build_query($options['flashvars']);
			
		$objectAttr = array(
			'id' => $options['target'],
			'classid' => 'clsid:D27CDB6E-AE6D-11cf-96B8-444553540000',
			'width' => $width,
			'height' => $height,
		);
		$object2Attr = array(
			'type' => 'application/x-shockwave-flash',
			'data' => $source,
			'width' => $width,
			'height' => $height,
		);
		$attributes2 = $attributes;
		unset($attributes2['id']);
		unset($attributes2['name']);
		
		$paramHtml = "";
		foreach($params as $k => $v)
			$paramHtml .= Curry_Html::createTag("param", array('name' => $k, 'value' => $v), '', true);
		
		return array(
			'html' => Curry_Html::createTag("object", array_merge($objectAttr, $attributes),
				Curry_Html::createTag("param", array('name' => 'movie', 'value' => $source), '', true).
				$paramHtml.
				'<!--[if !IE]>-->'.
				Curry_Html::createTag("object", array_merge($object2Attr, $attributes2),
					$paramHtml.
					'<!--<![endif]-->'.
					$options['alternativeContent'].
					'<!--[if !IE]>-->').
				'<!--<![endif]-->'
			),
			'script' => 'swfobject.registerObject("'.$options['target'].'", "'.$version.'"'.($options['expressInstall'] ? ', "'.$options['expressInstall'].'"' :'').');',
		);
	}
}
