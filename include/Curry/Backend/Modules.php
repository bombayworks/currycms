<?php
namespace Curry\Backend;

use Curry\Form\ModelForm;
use Curry\Module\AbstractModule;
use Symfony\Component\HttpFoundation\Request;

class Modules extends AbstractBackend {
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
		foreach(AbstractModule::getModuleList() as $className) {
			$parts = explode(" ", str_replace(" Module ", " ", strtr($className, "\\_", '  ')));
			$package = array_shift($parts);
			if (!$package)
				$package = 'Other';
			$modules[$package][$className] = join(" / ", $parts);
		}

		$templates = array('' => "[ None ]") + \Curry_Backend_Template::getTemplateSelect();

		$form = new ModelForm('Module', array(
			'columnFields' => array(
				'module_class' => array(
					'type' => 'choice',
					'choices' => $modules,
				),
				'content_visibility' => array(
					'type' => 'choice',
					'choices' => \PageModulePeer::$contentVisiblityOptions,
				),
				'template' => array(
					'type' => 'choice',
					'choices' => $templates,
				),
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
