<?php
namespace Curry\Backend;

use Symfony\Component\HttpFoundation\Request;

class Modules extends Base {
	public function getName()
	{
		return 'Predefined modules';
	}

	public function getGroup()
	{
		return 'System';
	}

	public function initialize()
	{
		$modules = array();
		foreach(\Curry_Module::getModuleList() as $className) {
			$parts = explode("_", str_replace("_Module_", "_", $className));
			$package = array_shift($parts);
			$modules[$package][$className] = join(" / ", $parts);
		}

		$templates = array('' => "[ None ]") + \Curry_Backend_Template::getTemplateSelect();

		$form = new \Curry_Form_ModelForm('Module', array(
			'columnElements' => array(
				'module_class' => array('select', array(
					'multiOptions' => $modules,
				)),
				'content_visibility' => array('select', array(
					'multiOptions' => \PageModulePeer::$contentVisiblityOptions,
				)),
				'template' => array('select', array(
					'multiOptions' => $templates,
				)),
			)
		));
		$this->addView('modules', new \Curry_ModelView_List('Module', array(
			'modelForm' => $form,
		)));
	}

	public function show(Request $request)
	{
		$this->addMainContent($this->modules->getHtml($request->query->all()));
		return $this->render();
	}
}
