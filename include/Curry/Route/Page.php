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
 * Routes a request to a page.
 * 
 * @package Curry\Route
 */
class Curry_Route_Page implements Curry_IRoute {
	/**
	 * Perform routing.
	 *
	 * @param Curry_Request $request
	 * @return Page|bool
	 */
	public function route(Curry_Request $request)
	{
		$requestUri = $request->getUrl()->getPath();

		// add trailing slash if missing
		if($requestUri && substr($requestUri,-1) != '/')
			$requestUri .= '/';
		
		// use domain mapping to restrict page to a certain page-branch
		$rootPage = null;
		if(Curry_Core::$config->curry->domainMapping->enabled){
			$currentDomain = strtolower($_SERVER['HTTP_HOST']);
			foreach (Curry_Core::$config->curry->domainMapping->domains as $domain) {
				if(strtolower($domain->domain) === $currentDomain
				|| ($domain->include_www && strtolower('www.'.$domain->domain) === $currentDomain)){
					$rootPage = $domain->base_page;
					break;
				}
			}
			if(!$rootPage && Curry_Core::$config->curry->domainMapping->default)
				$rootPage = Curry_Core::$config->curry->domainMapping->default;
			if($rootPage)
				$rootPage = PageQuery::create()->findPk($rootPage);
		}
		
		// attempt to find page using url
		if(Curry_Core::$config->curry->pageCache) {
			$pages = array();
			$allPages = Page::getCachedPages();
			foreach($allPages as $page) {
				if($page->getUrl() == $requestUri) {
					if(!$rootPage || $rootPage->isAncestorOf($page) || $rootPage->getPageId() == $page->getPageId())
						$pages[] = $page;
				}
			}
		} else {
			$pages = PageQuery::create()
				->filterByUrl($requestUri)
				->_if($rootPage)
					->branchOf($rootPage)
				->_endif()
				->joinWith('Page.ActivePageRevision apr', Criteria::LEFT_JOIN)
				->find();
		}
		
		if(count($pages) > 1)
			throw new Exception('URL refers to multiple pages: ' . $requestUri);
		else if(count($pages) == 1)
			return $pages[0];
		return false;
	}
}