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
 * Helper class to manage inheritance for page modules.
 *
 * @package Curry
 */
class Curry_PageModuleWrapper {
	/**
	 * The PageRevision of this wrapper.
	 *
	 * @var PageRevision
	 */
	private $pageRevisionId;
	
	/**
	 * The PageModule of this wrapper;
	 *
	 * @var PageModule
	 */
	private $pageModuleId;
	
	/**
	 * Class name.
	 *
	 * @var string
	 */
	private $className;
	
	/**
	 * Name of template target to attach this module to.
	 *
	 * @var string
	 */
	private $target;
	
	/**
	 * Name of module.
	 *
	 * @var string
	 */
	private $name;
	
	/**
	 * Path to template.
	 *
	 * @var string|null
	 */
	private $template = null;
	
	/**
	 * Is this module enabled?
	 *
	 * @var bool|null
	 */
	private $enabled = null;
	
	/**
	 * The serialized data for this module.
	 *
	 * @var string|null
	 */
	private $data = null;
	
	/**
	 * ModuleDataId from where $template is inherited.
	 *
	 * @var int|null
	 */
	private $templateSourceId = null;
	
	/**
	 * ModuleDataId from where $enabled is inherited.
	 *
	 * @var int|null
	 */
	private $enabledSourceId = null;
	
	/**
	 * ModuleDataId from where $data is inherited.
	 *
	 * @var int|null
	 */
	private $dataSourceId = null;
	
	/**
	 * Is this module inherited from another page?
	 *
	 * @var bool
	 */
	private $inherited;
	
	/**
	 * Langcode for module.
	 *
	 * @var string
	 */
	private $langcode;
	
	/**
	 * Constructor
	 *
	 * @param PageModule $pageModule
	 * @param PageRevision $pageRevision
	 * @param string $langcode
	 */
	public function __construct(PageModule $pageModule, PageRevision $pageRevision, $langcode)
	{
		$this->pageRevisionId = $pageRevision->getPageRevisionId();
		$this->pageModuleId = $pageModule->getPageModuleId();
		$this->langcode = ($langcode === null ? "" : $langcode);
		$this->reload();
	}

	public function reload()
	{
		$pageRevision = $this->getPageRevision();
		$pageModule = $this->getPageModule();

		$this->inherited = ($pageModule->getPageId() !== $pageRevision->getPageId());
		$this->className = $pageModule->getModuleClass();
		$this->target = $pageModule->getTarget();
		$this->name = $pageModule->getName();

		$this->data = null;
		$this->dataSourceId = null;
		$this->template = null;
		$this->templateSourceId = null;
		$this->enabled = null;
		$this->enabledSourceId = null;

		$this->populateModuleData();
	}
	
	/**
	 * Get PageRevisionId for the related PageRevision.
	 *
	 * @return int
	 */
	public function getPageRevisionId()
	{
		return $this->pageRevisionId;
	}
		
	/**
	 * Return the PageRevision object.
	 *
	 * @return PageRevision
	 */
	public function getPageRevision()
	{
		return PageRevisionQuery::create()->findPk($this->pageRevisionId);
	}

	/**
	 * Get the Page from which this module belongs to.
	 *
	 * @return Page
	 */
	public function getOriginPage()
	{
		return $this->getPageModule()->getPage();
	}
	
	/**
	 * Return the PageModule object.
	 *
	 * @return PageModule
	 */
	public function getPageModule()
	{
		return PageModuleQuery::create()->findPk($this->pageModuleId);
	}
	
	/**
	 * Get PageModuleId.
	 *
	 * @return int
	 */
	public function getPageModuleId()
	{
		return $this->pageModuleId;
	}
	
	/**
	 * Get ModuleData object for this module. If it doesn't exist in the
	 * database, it will be created but not saved.
	 *
	 * @return ModuleData
	 */
	public function getModuleData()
	{
		return ModuleDataQuery::create()
			->filterByPageModuleId($this->pageModuleId)
			->filterByPageRevisionId($this->pageRevisionId)
			->filterByLangcode($this->langcode)
			->findOneOrCreate();
	}
	
	/**
	 * Get ModuleDataId.
	 *
	 * @param bool|null $inherited
	 * @return int|null
	 */
	public function getModuleDataId($inherited = null)
	{
		if($inherited === null)
			return $this->dataSourceId;
		return $this->getModuleData($inherited)->getModuleDataId();
	}
	
	/**
	 * Get module class name.
	 *
	 * @return string
	 */
	public function getClassName()
	{
		return $this->className;
	}
	
	/**
	 * Get the name of the template variable this module is attached to.
	 *
	 * @return string
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * Get the name of this module.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Check if this module is inherited from another PageRevision.
	 *
	 * @return boolean
	 */
	public function isInherited()
	{
		return $this->inherited;
	}
	
	/**
	 * Get filename of module template.
	 *
	 * @return string|null
	 */
	public function getTemplate()
	{
		return $this->template;
	}
	
	/**
	 * Is this module enabled?
	 *
	 * @return bool|null
	 */
	public function getEnabled()
	{
		return $this->enabled;
	}
	
	/**
	 * Get the module data. This is the serialized module.
	 *
	 * @return string|null
	 */
	public function getData()
	{
		return $this->data;
	}

	public function getTemplateSource()
	{
		return ModuleDataQuery::create()->findPk($this->templateSourceId);
	}

	public function getEnabledSource()
	{
		return ModuleDataQuery::create()->findPk($this->enabledSourceId);
	}

	public function getDataSource()
	{
		return ModuleDataQuery::create()->findPk($this->dataSourceId);
	}
	
	/**
	 * Internal function to read and cascade all ModuleData objects.
	 */
	protected function populateModuleData()
	{
		// get PageRevision ancestors
		$ancestors = array_reverse($this->getPageRevision()->getInheritanceChain(true));
		$ancestors = Curry_Array::objectsToArray($ancestors, null, 'getPageRevisionId');
		$ancestors = array_flip($ancestors);
		
		$keys = array();
		$depth = array();
		$lang = array();
		$moduleDatas = $this->getPageModule()->getModuleDatas();
		foreach($moduleDatas as $key => $moduleData) {
			if($moduleData->getLangcode() && $moduleData->getLangcode() !== $this->langcode)
				continue;
			if(!array_key_exists($moduleData->getPageRevisionId(), $ancestors))
				continue;
			$keys[] = $key;
			$depth[] = $ancestors[$moduleData->getPageRevisionId()];
			$lang[] = $moduleData->getLangcode() ? 1 : 0;
		}
		$moduleDatas->clearIterator(); // PropelCollection causes memory leak in php 5.3 unless we explicitly clear the iterator
		
		array_multisort($depth, $lang, $keys);
		foreach($keys as $key)
			$this->addData($moduleDatas[$key]);
	}
	
	/**
	 * Internal function to add a ModuleData object.
	 *
	 * @param ModuleData $moduleData
	 */
	protected function addData(ModuleData $moduleData)
	{
		if($moduleData->getTemplate() !== NULL) {
			$this->template = $moduleData->getTemplate();
			$this->templateSourceId = $moduleData->getModuleDataId();
		}
			
		if($moduleData->getEnabled() !== NULL) {
			$this->enabled = $moduleData->getEnabled();
			$this->enabledSourceId = $moduleData->getModuleDataId();
		}
			
		if($moduleData->getData() !== NULL) {
			$this->data = $moduleData->getData();
			$this->dataSourceId = $moduleData->getModuleDataId();
		}
	}
	
	/**
	 * Does this module want a template?
	 *
	 * @return bool
	 */
	public function hasTemplate()
	{
		// Make sure the module class exists
		$className = $this->getClassName();
		if(!class_exists($className))
			throw new Exception("Module class '{$className}' not found.");
			
		return call_user_func(array($className, 'hasTemplate'));
	}
	
	/**
	 * Create ModuleData object, if it doesnt exist it will create it with the current module data.
	 *
	 */
	public function createData()
	{
		$moduleData = $this->getModuleData();
		if($moduleData->getData() !== null)
			throw new Exception('Module already have data');
			
		if($this->data !== null)
			$moduleData->setData($this->data);
		else {
			$className = $this->getClassName();
			if(!class_exists($className))
				throw new Exception("Module class '{$className}' not found.");
			$obj = new $className;
			$moduleData->setData(serialize($obj));
		}
		
		$moduleData->save();
		$this->addData($moduleData);
	}
	
	/**
	 * Does the specified ModuleData have any data?
	 *
	 * @return bool
	 */
	public function hasData()
	{
		return $this->getModuleData()->getData() !== null;
	}

	/**
	 * Can the specified ModuleData be deleted?
	 *
	 * @return bool
	 */
	public function isDeletable()
	{
		return $this->langcode !== '' || $this->isInherited();
	}
	
	/**
	 * Get ModuleSortorder object for this module/pagerevision.
	 *
	 * @param bool $create
	 * @return ModuleSortorder|null
	 */
	public function getSortorder($create = false)
	{
		$sortorder = ModuleSortorderQuery::create()->findPk(array($this->pageModuleId, $this->pageRevisionId));
		if($sortorder === null && $create) {
			$sortorder = new ModuleSortorder();
			$sortorder->setPageModuleId($this->pageModuleId);
			$sortorder->setPageRevisionId($this->pageRevisionId);
		}
		return $sortorder;
	}
	
	/**
	 * Create the Curry_Module instance from the serialized data.
	 *
	 * @param bool $inherited
	 * @return Curry_Module
	 */
	public function createObject($inherited = true)
	{
		// Make sure the module class exists
		$className = $this->getClassName();
		if(!class_exists($className))
			throw new Exception("Module class '{$className}' not found.");
		
		// Get the module data
		$moduleDataId = null;
		if(!$inherited) {
			$moduleData = $this->getModuleData();
			$data = $moduleData->getData();
			$moduleDataId = $moduleData->getModuleDataId();
		} else {
			$data = $this->data;
			$moduleDataId = $this->dataSourceId;
		}
		
		// Create instance
		$obj = null;
		if ($data !== null) {
			try {
				$obj = unserialize($data);
				if ($obj && !($obj instanceof $className)) {
					trace_warning('Module class mismatch '.$className);
					$obj = null;
				}
			}
			catch(Exception $e) {
				trace_warning('Failed to unserialize module of class '.$className);
			}
		}
		
		if (!$obj)
			$obj = new $className;
			
		$obj->setModuleDataId($moduleDataId);
		
		return $obj;
	}
}
