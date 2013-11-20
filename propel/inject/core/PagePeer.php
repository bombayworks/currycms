/**
 * Get a select-friendly list of all pages.
 *
 * @return array
 */
public static function getSelect(Page $rootPage = null)
{
	$offset = $rootPage ? $rootPage->getLevel() : 0;
	$pages = $rootPage ? $rootPage->getBranch() : PageQuery::create()->orderByBranch()->find();
	$ret = array();
	foreach($pages as $page) {
		$indent = str_repeat(Curry_Core::SELECT_TREE_PREFIX, $page->getLevel() - $offset);
		$ret[$page->getPageId()] = $indent . $page->getName();
	}
	return $ret;
}

static public function changePage()
{
	Curry_Core::$cache->clean(Zend_Cache::CLEANING_MODE_ALL);
}