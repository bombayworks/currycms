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
 * Helper functions for copy-paste and page-sync functionality.
 *
 * @package Curry\Controller\Backend
 */
class Curry_Backend_PageSyncHelper
{
	/**
	 * Restore multiple pages from "copy code".
	 *
	 * @param Page $page
	 * @param array $pageData
	 * @param bool|array $subpages
	 * @return array
	 * @throws Exception
	 */
	public static function restorePages(Page $page, array $pageData, $subpages)
	{
		// url => Page
		$pages = array();

		// Restore object
		$p = new stdClass();
		$p->data = $pageData;
		$p->parent = null;
		$p->page = $page;

		// Flatten pages to restore
		$pageDatas = array();
		$stack = array($p);
		while (count($stack)) {
			$p = array_shift($stack);
			foreach ($p->data['pages'] as $sub) {
				$sp = new stdClass();
				$sp->data = $sub;
				$sp->parent = $p;
				$sp->page = $p->page ? PageQuery::create()
					->childrenOf($p->page)
					->filterByName($sub['name'])
					->findOne() : null;
				array_push($stack, $sp);
			}
			if (is_bool($subpages) || (is_array($subpages) && in_array($p->data['id'], $subpages)))
				$pageDatas[$p->data['url']] = $p;
			if($subpages === false)
				break;
		}

		// Create pages
		foreach ($pageDatas as $p) {
			if (isset($pages[$p->data['url']]))
				continue;
			if (!$p->page) {
				$parent = $p->parent->page;
				if (!$parent) {
					throw new Exception('Parent page not found for page: '.$p->data['name']);
				}
				$pp = new Page();
				if (!PageQuery::create()->findOneByUid($p->data['uid']))
					$pp->setUid($p->data['uid']);
				$pp->insertAsLastChildOf($parent);
				$pp->save();
				$pp->setAutoName($p->data['name']);
				$pp->save();
				$p->page = $pp;
			}
			$pages[$p->data['url']] = $p->page;
		}

		// Rearrange pages depending on base page
		$alreadyMoved = array();
		foreach ($pageDatas as $url => $p) {
			$basePage = $p->data['revision']['base_page'];
			if ($basePage && isset($pageDatas[$basePage]) && !isset($alreadyMoved[$basePage])) {
				$alreadyMoved[$basePage] = true;
				Curry_Array::insertBefore($pageDatas, array($basePage => $pageDatas[$basePage]), $url);
			}
		}
		// Restore page data
		foreach ($pageDatas as $p)
			self::restorePage($pages[$p->data['url']], $p->data, $pages);
		return $pages;
	}

	/**
	 * Restore page from serialized object.
	 *
	 * @param Page $page
	 * @param array $pageData
	 * @param array $pageMap
	 */
	public static function restorePage(Page $page, array $pageData, array $pageMap)
	{
		unset($pageData['id']);
		unset($pageData['uid']);
		unset($pageData['name']);
		unset($pageData['url']);
		if($pageData['redirect_page'])
			$page->setRedirectPage(self::findPageByMap($pageData['redirect_page'], $pageMap));
		$page->fromArray($pageData, BasePeer::TYPE_FIELDNAME);
		$page->save();

		if(!$pageData['revision'])
			return;

		// Create new revision
		$pr = new PageRevision();
		$pr->setPage($page);
		$pr->setBasePage(self::findPageByMap($pageData['revision']['base_page'], $pageMap));
		$pr->fromArray($pageData['revision'], BasePeer::TYPE_FIELDNAME);
		$pr->setDescription('Copied');
		$page->setWorkingPageRevision($pr);
		$page->save();

		// Add module data...
		$order = array();
		$parentPages = Curry_Array::objectsToArray($page->getWorkingPageRevision()->getInheritanceChain(true), null, 'getPageId');
		$inheritedModules = $pr->getModules();
		foreach($pageData['revision']['modules'] as $module) {
			$pm = null;
			if (!$module['is_inherited']) {
				$pm = PageModuleQuery::create()->findOneByUid($module['uid']);
				if ($pm && !in_array($pm->getPageId(), $parentPages)) {
					// Page module exists, but is not in our "inheritance chain"
					// Give the module a new unique-id, and create the module here
					$pm->setUid(Curry_Util::getUniqueId());
					$pm->save();
					$pm = null;
				}
			} else {
				// find inherited module
				foreach($inheritedModules as $inheritedModule) {
					if($inheritedModule->getUid() == $module['uid']) {
						$pm = $inheritedModule;
						break;
					}
				}
			}
			if (!$pm) {
				$pm = new PageModule();
				$pm->setPage($page);
				$pm->fromArray($module, BasePeer::TYPE_FIELDNAME);
			}
			if (!$module['is_inherited']) {
				$rm = new RevisionModule();
				$rm->setPageModule($pm);
				$rm->setPageRevision($pr);
			}
			foreach($module['datas'] as $moduleData) {
				$md = new ModuleData();
				$md->setPageModule($pm);
				$md->setPageRevision($pr);
				$md->fromArray($moduleData, BasePeer::TYPE_FIELDNAME);
			}
			$order[] = $pm->getUid();
		}
		$pr->save();

		$modules = Curry_Array::objectsToArray($pr->getModules(), 'getUid');
		if (array_keys($modules) !== $order) {
			foreach($order as $uid) {
				$module = $modules[$uid];
				$sortorder = new ModuleSortorder();
				$sortorder->setPageModule($module);
				$sortorder->setPageRevision($pr);
				$sortorder->insertAtBottom();
				$sortorder->save();
			}
		}

		$page->setActivePageRevision($pr);
		$page->save();
	}

	/**
	 * Find page in map, if not found attempts to find in database.
	 *
	 * @param string $name
	 * @param array $map
	 * @return Page|null
	 */
	protected static function findPageByMap($name, $map)
	{
		if($name) {
			if(isset($map[$name]))
				return $map[$name];
			else {
				$page = PageQuery::create()->findOneByUrl($name);
				if(!$page)
					throw new Exception('Page with url '.$name.' not found.');
				return $page;
			}
		}
		return null;
	}

	/**
	 * Get "copy code" for page.
	 *
	 * @param Page $page
	 * @return array
	 */
	public static function getPageCode(Page $page)
	{
		$pages = array();
		foreach($page->getChildren() as $child)
			$pages[] = self::getPageCode($child);
		return array(
			"id" => $page->getPageId(),
			"uid" => $page->getUid(),
			"name" => $page->getName(),
			"url" => $page->getUrl(),
			"visible" => $page->getVisible(),
			"enabled" => $page->getEnabled(),
			"include_in_index" => $page->getIncludeInIndex(),
			"redirect_method" => $page->getRedirectMethod(),
			"redirect_page" => $page->getRedirectPage() ? $page->getRedirectPage()->getUrl() : null,
			"redirect_url" => $page->getRedirectUrl(),
			"model_route" => $page->getModelRoute(),
			"meta" => self::getMetadataCode($page),
			"image" => $page->getImage(),
			"langcode" => $page->getLangcode(),
			"generator" => $page->getGenerator(),
			"encoding" => $page->getEncoding(),
			"revision" => $page->getActivePageRevision() ? self::getPageRevisionCode($page->getActivePageRevision()) : null,
			"modified" => $page->getLastModified(),
			"pages" => $pages,
		);
	}

	/**
	 * Get "copy code" for page metadata.
	 *
	 * @todo Implement this.
	 * @param Page $page
	 * @return array
	 */
	public static function getMetadataCode(Page $page)
	{
		return array();
	}

	/**
	 * Get "copy code" for page revision.
	 *
	 * @param PageRevision $pageRevision
	 * @return array
	 */
	public static function getPageRevisionCode(PageRevision $pageRevision)
	{
		$modules = array();
		foreach($pageRevision->getPageModuleWrappers() as $pmw)
			$modules[] = self::getModuleCode($pmw);
		return array(
			"base_page" => $pageRevision->getBasePage() ? $pageRevision->getBasePage()->getUrl() : null,
			"description" => $pageRevision->getDescription(),
			"template" => $pageRevision->getTemplate(),
			"modules" => $modules,
		);
	}

	/**
	 * Get "copy code" for module.
	 *
	 * @param Curry_PageModuleWrapper $module
	 * @return array
	 */
	public static function getModuleCode(Curry_PageModuleWrapper $module)
	{
		$datas = array();
		$moduleDatas = ModuleDataQuery::create()
			->filterByPageModule($module->getPageModule())
			->filterByPageRevision($module->getPageRevision())
			->orderByLangcode()
			->find();
		foreach($moduleDatas as $moduleData)
			$datas[] = self::getModuleDataCode($moduleData);
		return array(
			"is_inherited" => $module->isInherited(),
			"uid" => $module->getPageModule()->getUid(),
			"name" => $module->getName(),
			"module_class" => $module->getClassName(),
			"inherit" => true,
			"target" => $module->getTarget(),
			"content_visibility" => $module->getPageModule()->getContentVisibility(),
			"search_visibility" => $module->getPageModule()->getSearchVisibility(),
			"datas" => $datas,
		);
	}

	/**
	 * Get "copy code" for module data.
	 *
	 * @param ModuleData $moduleData
	 * @return array
	 */
	public static function getModuleDataCode(ModuleData $moduleData)
	{
		return array(
			"inherited" => true,
			"template" => $moduleData->getTemplate(),
			"enabled" => $moduleData->getEnabled(),
			"data" => $moduleData->getData(),
			"langcode" => $moduleData->getLangcode(),
		);
	}
}