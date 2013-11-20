/**
 * Returns the pages from which which this page inherits. The items are
 * returned in the following order [[self], parent, grand-father, ...].
 *
 * @return array[int]PageRevision
 */
public function getInheritanceChain($includeSelf = false)
{
	$ancestors = array();
	
	$pageRev = $includeSelf ? $this : $this->getInheritRevision();
	while($pageRev) {
		$ancestors[] = $pageRev;
		$pageRev = $pageRev->getInheritRevision();
	}
	
	return $ancestors;
}

/**
 * Finds the parent page and returns the revision (working or active)
 *
 * @return PageRevision|null
 */
public function getInheritRevision($revision = null)
{
	$page = $this->getBasePage();
	return $page ? $page->getPageRevision($revision) : null;
}

public function getInheritedProperty($name, $default = null, $cache = true, $forceUpdate = false)
{
	// generate a unique cache name
	$cacheName = __CLASS__ . '_' . $this->getPageRevisionId() . '_' . $name;
	
	// attempt to load value from cache
	$value = false;
	if ($cache && !$forceUpdate)
		$value = Curry_Core::$cache->load($cacheName);
	
	// if value is not set...
	if($value === false) {
		$value = $default;
		$revision = $this;
		while($revision) {
       		if (($v = $revision->{"get$name"}()) !== null) {
   				$value = $v;
   				break;
    		}
    		$revision = $revision->getInheritRevision();
		}
        if($cache) {
        	if($value !== false) {
        		Curry_Core::$cache->save($value, $cacheName);
        	} else {
        		trace_warning("Unable to store $name for $this->getUrl()");
        	}
        }
	}
	return $value;
}

public function getModules()
{
	$parents = $this->getInheritanceChain(true);
	$parentIds = Curry_Array::objectsToArray($parents, null, 'getPageRevisionId');

	PageModulePeer::clearInstancePool();
	RevisionModulePeer::clearInstancePool();
	ModuleSortorderPeer::clearInstancePool();
	ModuleDataPeer::clearInstancePool();

	$pageModules = PageModuleQuery::create()
		->joinWith('PageModule.RevisionModule', Criteria::INNER_JOIN)
		->where('RevisionModule.PageRevisionId IN ?', $parentIds)
		->orderByPageModuleId()
		->find();
	$pageModules->populateRelation('ModuleSortorder', ModuleSortorderQuery::create()->filterByPageRevisionId($parentIds));
	$pageModules->populateRelation('ModuleData', ModuleDataQuery::create()->filterByPageRevisionId($parentIds));

	$modules = array();
	$parentIds = array_reverse($parentIds);
	foreach($parentIds as $pageRevisionId) {
		foreach($pageModules as $pageModule) {
			$revMods = $pageModule->getRevisionModules();
			if ($revMods->count() !== 1)
				throw new Exception('Invalid RevisionModule count!');
			if($revMods[0]->getPageRevisionId() == $pageRevisionId)
				$modules[] = $pageModule;
		}
		$pageModules->clearIterator();
		$sort = array();
		foreach($modules as $pageModule) {
			$moduleSortorders = $pageModule->getModuleSortorders();
			foreach($moduleSortorders as $moduleSortorder) {
				if($moduleSortorder->getPageRevisionId() == $pageRevisionId) {
					$sort[$pageModule->getPageModuleId()] = $moduleSortorder->getRank();
					break;
				}
			}
			$moduleSortorders->clearIterator();
		}
		if(count($sort) && count($sort) !== count($modules)) {
			foreach($modules as $pageModule) {
				if(!array_key_exists($pageModule->getPageModuleId(), $sort)) {
					$mo = new ModuleSortorder();
					$mo->setPageModule($pageModule);
					$mo->setPageRevisionId($pageRevisionId);
					$mo->insertAtBottom();
					$mo->save();
					$sort[$pageModule->getPageModuleId()] = $mo->getRank();
				}
			}
		}
		if(count($sort)) {
			array_multisort($sort, $modules);
		}
	}
	
	return $modules;
}

public function getPageModuleWrappers($langcode = null)
{
	// create wrappers
	$wrappers = array();
	foreach($this->getModules() as $pageModule)
		$wrappers[$pageModule->getPageModuleId()] = new Curry_PageModuleWrapper($pageModule, $this, $langcode);
	
	return $wrappers;
}

public function isPageModulesSorted()
{
	return ModuleSortorderQuery::create()
		->filterByPageRevision($this)
		->count() > 0;
}

public function allowEdit()
{
	return ($this->getPageRevisionId() && $this->getPageRevisionId() == $this->getPage()->getWorkingPageRevisionId());
}

public function duplicate()
{
	$copyObj = $this->copy();

	foreach ($this->getPageMetadatas() as $relObj) {
		$copyObj->addPageMetadata($relObj->copy());
	}

	foreach ($this->getRevisionModules() as $relObj) {
		$copyObj->addRevisionModule($relObj->copy());
	}

	foreach ($this->getModuleSortorders() as $relObj) {
		$copyObj->addModuleSortorder($relObj->copy());
	}

	foreach ($this->getModuleDatas() as $relObj) {
		$copyObj->addModuleData($relObj->copy());
	}

	return $copyObj;
}

public function postSave(PropelPDO $con = null)
{
	PagePeer::changePage();
}

public function postDelete(PropelPDO $con = null)
{
	PagePeer::changePage();
}