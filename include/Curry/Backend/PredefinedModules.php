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
 * Manage predefined modules.
 *
 * @package Curry\Controller\Backend
 */
class Curry_Backend_PredefinedModules extends \Curry\AbstractLegacyBackend {
	/** {@inheritdoc} */
	public function getName()
	{
		return 'Predefined modules';
	}

	/** {@inheritdoc} */
	public function getGroup()
	{
		return 'System';
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$modules = array();
		foreach(Curry_Module::getModuleList() as $className) {
			$parts = explode("_", str_replace("_Module_", "_", $className));
			$package = array_shift($parts);
			$modules[$package][$className] = join(" / ", $parts);
		}

		$templates = array('' => "[ None ]") + Curry_Backend_Template::getTemplateSelect();

		$form = new Curry_Form_ModelForm('Module', array(
			'columnElements' => array(
				'module_class' => array('select', array(
					'multiOptions' => $modules,
				)),
				'content_visibility' => array('select', array(
					'multiOptions' => PageModulePeer::$contentVisiblityOptions,
				)),
				'template' => array('select', array(
					'multiOptions' => $templates,
				)),
			)
		));
		$list = new Curry_ModelView_List('Module', array(
			'modelForm' => $form,
		));
		$this->addMainContent($list);
	}
}
