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

use Curry\App;
use Curry\ClassEnumerator;

/**
 * The base class for all page-modules.
 *
 * @package Curry\Module
 *
 */
abstract class AbstractModule {
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * The database object in which the module data is stored.
	 *
	 * @var int
	 */
	protected $moduleDataId;

	/**
	 * An array of available modules.
	 *
	 * @var array
	 */
	private static $modules;

	public function __construct() {
		$this->app = App::getInstance();
	}

	/**
	 * Only serialize public/protected variables.
	 *
	 * @return array
	 */
	public function __sleep() {
		$fields = get_object_vars($this);
		unset($fields['app']);
		unset($fields['moduleDataId']);
		return array_keys($fields);
	}

	public function __wakeup() {
		$this->app = App::getInstance();
	}

	/**
	 * Get a list of all available modules.
	 *
	 * @return array
	 */
	public static function getModuleList() {
		if (self::$modules) {
			return self::$modules;
		}

		// find all backend directories
		$dirs = glob(
			\Curry_Util::path(
				App::getInstance()->config->curry->projectPath,
				'include',
				'*',
				'Module'
			),
			GLOB_ONLYDIR
		);
		if (!$dirs) {
			$dirs = array();
		}
		$dirs[] = \Curry_Util::path(
			App::getInstance()->config->curry->basePath,
			'include',
			'Curry',
			'Module'
		);

		// find all php files in the directories
		$modules = array();
		foreach($dirs as $dir) {
			$classes = ClassEnumerator::findClasses($dir);
			foreach ($classes as $className) {
				if (class_exists($className)) {
					$r = new \ReflectionClass($className);
					if ($r->isSubclassOf(__CLASS__) && !$r->isAbstract())
						$modules[$className] = $className;
				}
			}
		}
		ksort($modules);
		self::$modules = $modules;
		return self::$modules;
	}

	/**
	 * This function will be called when rendering a module. By default a
	 * template is required, and it will be rendered using the results from
	 * the toTwig() function.
	 *
	 * @param \Curry_Twig_Template|null $template
	 * @return string    The content generated by the module.
	 */
	public function showFront(\Curry_Twig_Template $template = NULL) {
		if (!$template) {
			throw new \Exception(
				'A template is required for this module (' . get_class(
					$this
				) . ').'
			);
		}
		return $template->render($this->toTwig());
	}

	/**
	 * Creates an associative array of values to be rendered by Twig.
	 *
	 * @return array    The array to be rendered by Twig.
	 */
	public function toTwig() {
		return array();
	}

	/**
	 * Returns a template to use if no template is set in the Curry CMS backend.
	 * You only need to implement this if your module should have a default template.
	 *
	 * @return string|null    Template string.
	 */
	public static function getDefaultTemplate() {
		return NULL;
	}

	/**
	 * Controls the back-end form. This function is supposed to return a
	 * Curry_Form_SubForm. If you don't want a backend for your module you
	 * dont need to implement this.
	 *
	 * @return \Curry_Form_SubForm|null
	 */
	public function showBack() {
		return NULL;
	}

	/**
	 * This function is automatically called when the form is saved.
	 *
	 * @param \Zend_Form_SubForm $form
	 */
	public function saveBack(\Zend_Form_SubForm $form) {
	}

	/**
	 * This function determine if the user should be allowed to select a template for
	 * this module. The default is true, override if you don't want it.
	 *
	 * @return bool
	 */
	public static function hasTemplate() {
		return TRUE;
	}

	/**
	 * Get a list of predefined templates. The keys will be used as names and the
	 * value is the actual template.
	 *
	 * @return string[]
	 */
	public static function getPredefinedTemplates() {
		return array();
	}

	/**
	 * Return an object describing how caching of this module is handled. Return
	 * null to disable caching.
	 *
	 * @return \Curry\Module\CacheProperties|null
	 */
	public function getCacheProperties() {
		return NULL;
	}

	/**
	 * Returns an array of commands to show for this module when viewed in
	 * inline-admin mode.
	 *
	 * @param array $commands
	 * @return array
	 */
	public function getInlineCommands($commands) {
		return $commands;
	}

	/**
	 * Get the ModuleDataId associated to this module instance.
	 *
	 * @return int
	 */
	public function getModuleDataId() {
		return $this->moduleDataId;
	}

	/**
	 * Get the ModuleData object associated to this module instance.
	 *
	 * @return \ModuleData
	 */
	public function getModuleData() {
		return \ModuleDataQuery::create()->findPk($this->moduleDataId);
	}

	/**
	 * Get the PageModule object associated to this module instance.
	 *
	 * @return \PageModule
	 */
	public function getPageModule() {
		return $this->getModuleData()->getPageModule();
	}

	/**
	 * Get the PageModuleId associated to this module instance.
	 *
	 * @return int
	 */
	public function getPageModuleId() {
		return $this->getModuleData()->getPageModuleId();
	}

	/**
	 * Get the PageRevision object associated to this module instance.
	 *
	 * @return \PageRevision
	 */
	public function getPageRevision() {
		return $this->getModuleData()->getPageRevision();
	}

	/**
	 * Set the module data id. This is the primary key of the ModuleData
	 * object used when writing the module to the database.
	 *
	 * @param int $id
	 */
	public function setModuleDataId($id) {
		$this->moduleDataId = $id;
	}

	/**
	 * Serializes this object and stores it in the database in the related ModuleData object.
	 *
	 * @return bool Return true if the module was changed, otherwise false.
	 */
	public function saveModule() {
		if ($this->moduleDataId === FALSE) {
			return FALSE;
		}

		if (!$this->moduleDataId) {
			throw new \Exception('Not allowed to save ModuleData.');
		}

		$moduleData = $this->getModuleData();
		if (!$moduleData) {
			throw new \Exception('ModuleData not found.');
		}

		$moduleData->setData(serialize($this));
		return $moduleData->save() ? TRUE : FALSE;
	}
}
