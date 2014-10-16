const VERSION = 2;

const ACTIVE_REVISION = "active";
const WORKING_REVISION = "working";

protected static $autoRevision = self::ACTIVE_REVISION;

protected static $pageCache;
protected static $pageCacheParent;
protected static $pageCacheChildren;

protected static function buildPageCache()
{
	if(self::$pageCache !== null)
		return true;
		
	if(!\Curry\App::getInstance()->config->curry->pageCache)
		return false;
	
	self::$pageCache = array();
	self::$pageCacheParent = array();
	self::$pageCacheChildren = array();
	
	$pages = PageQuery::create()
		->joinWith('Page.ActivePageRevision', Criteria::LEFT_JOIN)
		->orderByBranch()
		->find();
		
	$ancestors = array();
	foreach($pages as $page) {
		$ancestors[$page->getLevel()] = $page;
		self::$pageCache[$page->getPageId()] = $page;
		self::$pageCacheChildren[$page->getPageId()] = array();
		if($page->getLevel()) {
			$parent = $ancestors[$page->getLevel() - 1];
			self::$pageCacheParent[$page->getPageId()] = $parent;
   			self::$pageCacheChildren[$parent->getPageId()][] = $page;
		}
	}
	return true;
}

public static function getCachedRoot(Page $page)
{
	if(self::buildPageCache()) {
		reset(self::$pageCache);
		return current(self::$pageCache);
	} else
		return PageQuery::create()->findRoot();
}

public static function getCachedPages()
{
	if(self::buildPageCache())
		return self::$pageCache;
	else
		return PageQuery::create()->find();
}

public static function getCachedParent(Page $page)
{
	if(self::buildPageCache())
		return isset(self::$pageCacheParent[$page->getPageId()]) ? self::$pageCacheParent[$page->getPageId()] : null;
	else
		return $page->getParent();
}

public static function getCachedChildren(Page $page)
{
	if(self::buildPageCache())
		return isset(self::$pageCacheChildren[$page->getPageId()]) ? self::$pageCacheChildren[$page->getPageId()] : array();
	else
		return $page->getChildren();
}

public static function getCachedPath(Page $page)
{
	if(self::buildPageCache()) {
		$pages = array();
		$p = $page;
		do {
			$pages[] = $p;
		} while($p = Page::getCachedParent($p));
		return $pages;
	} else
		return $page->getPath()->getArrayCopy();
}

public static function setRevisionType($revision)
{
	self::$autoRevision = $revision;
}

public function getPageRevisionId($revision = null)
{
	if(!$revision)
		$revision = self::$autoRevision;
		
	switch($revision) {
		case self::ACTIVE_REVISION:
			return $this->getActivePageRevisionId();
		case self::WORKING_REVISION:
			if($this->getWorkingPageRevisionId())
				return $this->getWorkingPageRevisionId();
			return $this->getActivePageRevisionId();
			
		default:
			throw new Exception('Unknown page revision type');
	}
}

/**
 * Create initial revisions for page.
 *
 * @param Page|null $basePage
 */
public function createDefaultRevisions($basePage = null) {
	$revision = new PageRevision();
	$revision->setPage($this);
	$revision->setBasePage($basePage);
	$revision->setPublishedDate(time());
	$revision->setDescription("Initial (auto-created)");
	$this->setActivePageRevision($revision);

	if (\Curry\App::getInstance()->config->curry->revisioning) {
		$revision2 = new PageRevision();
		$revision2->setPage($this);
		$revision2->setBasePage($basePage);
		$revision2->setDescription("Base (auto-created)");
		$this->setWorkingPageRevision($revision2);
	} else {
		$revision->setDescription("Base (auto-created)");
		$this->setWorkingPageRevision($revision);
	}
}

/**
 * Get page revision, working or active, depending parameter or otherwize the current RevisionType.
 *
 * @see setRevisionType
 *
 * @param string $revision	Published, working or auto (null).
 * @return PageRevision
 */
public function getPageRevision($revision = null)
{
	if(!$revision)
		$revision = self::$autoRevision;
		
	switch($revision) {
		case self::ACTIVE_REVISION:
			return $this->getActivePageRevision();
		case self::WORKING_REVISION:
			if($this->getWorkingPageRevision())
				return $this->getWorkingPageRevision();
			return $this->getActivePageRevision();
			
		default:
			throw new Exception('Unknown page revision type');
	}
}

public function getInheritedProperty($name, $default = null, $cache = true, $forceUpdate = false)
{
	// generate a unique cache name
	$app = \Curry\App::getInstance();
	$cacheName = __CLASS__ . '_' . $this->getPageRevisionId() . '_' . $name;

	// attempt to load value from cache
	$value = false;
	if ($cache && !$forceUpdate) {
		$value = $app->cache->load($cacheName);
	}
	
	// if value is not set...
	if($value === false) {
		$value = $default;
    	foreach(Page::getCachedPath($this) as $page) {
    		if (($v = $page->{"get$name"}()) !== null) {
    			$value = $v;
    			break;
    		}
    	}
        if($cache) {
        	if($value !== false) {
				$app->cache->save($value, $cacheName);
        	} else {
        		trace_warning("Unable to store $name for {$this->getUrl()}");
        	}
        }
	}
	return $value;
}

public function setUrlRecurse($newUrl)
{
	$oldUrl = $this->getUrl();
	
	// make sure it was changed
	if($newUrl !== $oldUrl) {
		if($this->isNew()) {
			$this->setUrl($newUrl);
			$this->save();
			return;
		}
			
		$descendants = PageQuery::create()->descendantsOf($this)->orderByBranch()->find();
		$parents = array($this->getLevel() => $this);
		$parentUrls = array($this->getLevel() => $this->getUrl());
		
		$this->setUrl($newUrl);
		foreach($descendants as $subpage) {
			$parents[$subpage->getLevel()] = $subpage;
			$parentUrls[$subpage->getLevel()] = $subpage->getUrl();
			$parent = $parents[$subpage->getLevel() - 1];
			$parentUrl = $parentUrls[$subpage->getLevel() - 1];
			if($subpage->getUrl() === $subpage->getExpectedUrl($parentUrl))
				$subpage->setUrl($subpage->getExpectedUrl($parent->getUrl()));
		}
		
		$descendants->append($this);
		$descendants->save();
	}
}

public function isCustomUrl()
{
	if($this->getName() == null)
		return false;
	return $this->getExpectedUrl() !== $this->getUrl();
}

public function getExpectedUrl($parentUrl = null)
{
	if($this->isRoot())
		return '/';
	if($parentUrl === null) {
		$parent = $this->getParent();
		if(!$parent)
			throw new Exception('Parent page not found.');
		$parentUrl = $parent->getUrl();
	}
	if($parentUrl == '/')
		$parentUrl = '';
	return ($parentUrl . \Curry\Util\StringHelper::getRewriteString($this->getName()) . '/');
}

public function getActualRedirectPage()
{
	if ($this->getRedirectPage())
		return $this->getRedirectPage();
	return $this->getFirstChild(PageQuery::create()->filterByEnabled(true));
}

public function getFinalUrl()
{
	if (!$this->getRedirectMethod())
		return $this->getUrl();
	if ($this->getRedirectMethod() == PagePeer::REDIRECT_METHOD_CLONE)
		return $this->getUrl();
	if ($this->getRedirectUrl() !== null)
		return $this->getRedirectUrl();
	$redirectPage = $this->getActualRedirectPage();
	return $redirectPage ? $redirectPage->getFinalUrl() : $this->getUrl();
}

public function getLastModified()
{
	$dates = array();
	$dates[] = $this->getCreatedAt('U');
	$dates[] = $this->getUpdatedAt('U');
	$pageRevision = $this->getPageRevision();
	if ($pageRevision) {
		$dates[] = $pageRevision->getCreatedAt('U');
		$dates[] = $pageRevision->getUpdatedAt('U');
		$pageModules = PageModuleQuery::create()
			->useRevisionModuleQuery()
			->filterByPageRevision($pageRevision)
			->endUse()
			->find();
		foreach($pageModules as $pageModule) {
			$dates[] = $pageModule->getCreatedAt('U');
			$dates[] = $pageModule->getUpdatedAt('U');
		}
		$moduleDatas = ModuleDataQuery::create()
			->filterByPageRevision($pageRevision)
			->filterByPageModule($pageModules)
			->find();
		foreach($moduleDatas as $moduleData) {
			$dates[] = $moduleData->getCreatedAt('U');
			$dates[] = $moduleData->getUpdatedAt('U');
		}
	}
	$dates = array_filter($dates);
	return count($dates) ? max($dates) : null;
}

/**
 * Set the name of the page. This will also set the Url recursivly and save().
 *
 * @param string $v
 */
public function setAutoName($v)
{
	if($this->isCustomUrl()) {
		$this->setName($v);
	} else {
		$this->setName($v);
		$this->setUrlRecurse($this->getExpectedUrl());
	}
}

public function getBodyId()
{
	return \Curry\Util\StringHelper::getRewriteString('page-'.$this->getUrl());
}

/**
 * Get the path to this page in this order: [this, parent, ..., root]
 *
 * @return array of Page
 */
public function getPath()
{
    return PageQuery::create()
        ->filterByTreeLeft($this->getLeftValue(), Criteria::LESS_EQUAL)
        ->filterByTreeRight($this->getRightValue(), Criteria::GREATER_EQUAL)
        ->orderByBranch(true)
        ->find();
}

/**
 * Fetches an array of pages that in some way inherits from this page,
 * either directly or indirectly through parent page or base page.
 *
 */
public function getDependantPages()
{
	$pages = array();
	$lastPages = array($this->getPageId() => $this);
	do {
		$c = count($pages);
		$pageIds = \Curry\Util\ArrayHelper::objectsToArray($lastPages, null, 'getPageId');
		$lastPages = PageQuery::create()
			->useWorkingPageRevisionQuery('', Criteria::INNER_JOIN)
				->filterByBasePageId($pageIds)
			->endUse()
			->find();
		$pages += \Curry\Util\ArrayHelper::objectsToArray($lastPages, 'getPageId', null);
	} while(count($pages) != $c);
	return $pages;
}

public function preInsert(PropelPDO $con = null)
{
	if ($this->getUid() === null)
		$this->setUid(Curry_Util::getUniqueId());
	return true;
}

public function postSave(PropelPDO $con = null)
{
	PagePeer::changePage();
}

public function postDelete(PropelPDO $con = null)
{
	PagePeer::changePage();
}

public function getPageAccess(User $user = null, UserRole $role = null)
{
	$parents = \Curry\Util\ArrayHelper::objectsToArray($this->getAncestors(), null, 'getPageId');
	$access = PageAccessQuery::create('pa')
		// User and role condition
		->_if($user && !$user->isNew())
			->filterByUser($user)
			->_or()
		->_endif()
		->_if($role && !$role->isNew())
			->filterByUserRole($role)
			->_or()
		->_endif()
		->where('pa.UserID IS NULL AND pa.UserRoleId IS NULL')
		// Page condition
		->filterByPage($this)
		->_or()
		->where('pa.PermSubpages = 1 AND pa.PageId IN ?', $parents)
		->orderByCascade()
		->find();
	$perm = array();
	foreach($access as $pageAccess)
		$perm = $pageAccess->getPermissions() + $perm;
	return $perm;
}

public function getMetadata()
{
	$cacheName = __CLASS__ . '_' . $this->getPageRevisionId() . '_Metadata';
	if(($meta = \Curry\App::getInstance()->cache->load($cacheName)) === false) {
		$meta = array('cascaded' => array(), 'inherited' => array());
		$metadatas = MetadataQuery::create()->find();
		
		$localPageRevisionId = $this->getPageRevisionId();
		$cascadeIds = \Curry\Util\ArrayHelper::objectsToArray(array_reverse(Page::getCachedPath($this)), null, 'getPageRevisionId');
		$inheritIds = array_reverse(\Curry\Util\ArrayHelper::objectsToArray($this->getPageRevision()->getInheritanceChain(true), null, 'getPageRevisionId'));
		$pageRevisionIds = array_unique(array_merge($cascadeIds, $inheritIds));
		$pageMetadatas = PageMetadataQuery::create()->filterByPageRevisionId($pageRevisionIds)->find();
		
		foreach($metadatas as $metadata) {
			$meta[$metadata->getName()] = $metadata->getDefaultValue();
			$meta['cascaded'][$metadata->getName()] = $metadata->getDefaultValue();
			$meta['inherited'][$metadata->getName()] = $metadata->getDefaultValue();
		}
		foreach($pageMetadatas as $pageMetadata) {
			if($pageMetadata->getPageRevisionId() == $localPageRevisionId)
				$meta[$pageMetadata->getName()] = $pageMetadata->getValue();
		}
		foreach($cascadeIds as $pageRevisionId) {
			foreach($pageMetadatas as $pageMetadata) {
				if($pageMetadata->getPageRevisionId() == $pageRevisionId)
					$meta['cascaded'][$pageMetadata->getName()] = $pageMetadata->getValue();
			}
		}
		foreach($inheritIds as $pageRevisionId) {
			foreach($pageMetadatas as $pageMetadata) {
				if($pageMetadata->getPageRevisionId() == $pageRevisionId)
					$meta['inherited'][$pageMetadata->getName()] = $pageMetadata->getValue();
			}
		}
		
		$metadatas->clearIterator();
		$pageMetadatas->clearIterator();
		
		\Curry\App::getInstance()->cache->save($meta, $cacheName);
	}
	
	return $meta;
}

public function toTwig()
{
	$self = $this;
	$p = $this->toArray();
	$p['parent'] = new \Curry\Util\OnDemand(array($this, 'twigGetParent'));
	$p['subpages'] = new \Curry\Util\OnDemand(function() use ($self) {
		if ($self->isLeaf())
			return array();
		return PageQuery::create()
			->filterByEnabled(true)
			->childrenOf($this)
			->filterByVisible(true);
	});
	$p['allSubpages'] = new \Curry\Util\OnDemand(function() use ($self) {
		if ($self->isLeaf())
			return array();
		return PageQuery::create()
			->filterByEnabled(true)
			->childrenOf($this);
	});
	
	$p['meta'] = new \Curry\Util\OnDemand(array($this, 'getMetadata'));
	
	$p['FullUrl'] = url($this->getUrl())->getAbsolute();
	$p['FinalUrl'] = new \Curry\Util\OnDemand(array($this, 'getFinalUrl'));
	$p['BodyId'] = $this->getBodyId();

	$isInternal = $this->getRedirectPageId() !== null;
	$isExternal = $this->getRedirectUrl() !== null;
	$p['IsInternalRedirect'] = $isInternal;
	$p['IsExternalRedirect'] = $isExternal;
	$p['IsRedirect'] = $isInternal || $isExternal;
	$p['RedirectUrl'] = $isInternal ? $this->getRedirectPage()->getUrl() : ($isExternal ? $this->getRedirectUrl() : '');		
	return $p;
}

public function twigGetParent()
{
	if(($parentPage = Page::getCachedParent($this)))
		return $parentPage->toTwig();
	return null;
}

// ISearchable::getSearchDocument()
public function getSearchDocument()
{
	if(!$this->getEnabled() || !$this->getActivePageRevision() || !$this->getIncludeInIndex())
		return null;
	
	$preventRedirect = Curry_URL::setPreventRedirect(true);
	$pageGenerator = Curry_Application::getInstance()->createPageGenerator($this->getActivePageRevision(), new Curry_Request('GET', $this->getUrl()));
	$language = $this->getInheritedProperty('Language');
	if($language)
		Curry_Language::setLanguage($language);
	$content = $pageGenerator->render(array(), array('indexing' => true));
	Curry_URL::setPreventRedirect($preventRedirect);
	
	$doc = Zend_Search_Lucene_Document_Html::loadHTML($content, true);
	if($language)
	    $doc->addField(Zend_Search_Lucene_Field::Keyword('locale', $language->getLangcode()));
	$doc->addField(Zend_Search_Lucene_Field::Keyword('url', $this->getUrl()));
	
	return $doc;
}

// Migration
static protected $migrate0Modules = array();
static protected $migrate1Modules = array();
static protected $migrate1ModuleData = array();

public static function preMigrate($version)
{
}

public static function postMigrate($version)
{
	if ($version < 1) {
		foreach(self::$migrate0Modules as $pageModuleId => $pageRevisionId) {
			$pm = PageModuleQuery::create()->findPk($pageModuleId);
			$pr = PageRevisionQuery::create()->findPk($pageRevisionId);
			if ($pm && $pr) {
				$pm->setPageId($pr->getPageId());
				$pm->save();
			} else {
				trace_warning('Unable to migrate page module.');
			}
		}
	}
	if ($version < 2) {
		// Hide non-inherited modules on "subpages"
		foreach(self::$migrate1Modules as $pageModuleId) {
			$revisionModules = RevisionModuleQuery::create()->findByPageModuleId($pageModuleId);
			$pages = PageQuery::create()
				->filterByPageRevision($revisionModules)
				->find();
			$revisions = PageRevisionQuery::create()
				->filterByBasePage($pages)
				->find();
			foreach($revisions as $rev) {
				$md = ModuleDataQuery::create()
					->filterByPageModuleId($pageModuleId)
					->filterByPageRevision($rev)
					->filterByLangcode('')
					->findOneOrCreate();
				$md->setEnabled(false);
				$md->save();
			}
		}
		// Local content: add as inherited content, if none exists
		foreach(self::$migrate1ModuleData as $data) {
			$md = ModuleDataQuery::create()
				->filterByPageModuleId($data['PageModuleId'])
				->filterByPageRevisionId($data['PageRevisionId'])
				->filterByLangcode($data['Langcode'])
				->findOneOrCreate();
			if ($md->getTemplate() === null)
				$md->setTemplate($data['Template']);
			if ($md->getEnabled() === null)
				$md->setEnabled($data['Enabled']);
			if ($md->getData() === null)
				$md->setData($data['Data']);
			$md->save();
		}
	}
}

public static function migrateData(&$table, array &$data, $version)
{
	if ($version < 1) {
		if ($table == 'PageModule') {
			$rm = new RevisionModule();
			$rm->setPageModuleId($data['PageModuleId']);
			$rm->setPageRevisionId($data['PageRevisionId']);
			$rm->save();
			$data['PageId'] = 0;
			$data['Uid'] = Curry_Util::getUniqueId();
			self::$migrate0Modules[$data['PageModuleId']] = $data['PageRevisionId'];
		} else if ($table == 'Page') {
			$data['Uid'] = Curry_Util::getUniqueId();
			switch ($data['RedirectMethod']) {
				case 'clone': break;
				case 'subpage': break;
				case '301':
					$data['RedirectMethod'] = PagePeer::REDIRECT_METHOD_PERMANENT;
					break;
				case '302':
					$data['RedirectMethod'] = PagePeer::REDIRECT_METHOD_TEMPORARY;
					break;
				default:
					$data['RedirectMethod'] = null;
			}
		} else if ($table == 'PageRevision') {
			$data['CreatedAt'] = $data['CreatedDate'];
			$data['UpdatedAt'] = $data['ModifiedDate'];
		}
	}
	if ($version < 2) {
		if ($table == 'PageModule') {
			if ($data['Inherit']) {
				self::$migrate1Modules[] = $data['PageModuleId'];
			}
			unset($data['Inherit']);
		} else if ($table == 'ModuleData') {
			if (!$data['Inherited']) {
				self::$migrate1ModuleData[] = $data;
				return false;
			}
		}
	}
	return true;
}