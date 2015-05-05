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
use Curry\Module\AbstractModule;
use Curry\Module\PageModuleWrapper;
use Curry\Util\ArrayHelper;
use Curry\Util\Helper;
use Curry\Util\Html;

/**
 * Static helper functions for the page backend.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_PageHelper {
	/**
	 * Create new page form.
	 *
	 * @param int $parentPageId
	 * @return Curry_Form
	 */
	public static function getNewPageForm($parentPageId = null, $advanced = false)
	{
		if (!PageQuery::create()->findRoot()) {
			$parentPageId = '@root';
		}

		// Check if we are creating a template page
		$templatePage = Curry_Backend_Page::getTemplatePage();
		$parentPage = $parentPageId ? PageQuery::create()->findPk($parentPageId) : null;
		$isTemplatePage = $parentPage && $templatePage &&
			($templatePage == $parentPage || $parentPage->isDescendantOf($templatePage));

		$parents = PagePeer::getSelect();
		if (!$advanced && $templatePage) {
			// Remove template pages
			foreach($templatePage->getDescendants() as $subpage)
				unset($parents[$subpage->getPageId()]);
			unset($parents[$templatePage->getPageId()]);
		}

		// Disable pages where user dont have create page access
		$disabled = array();
		foreach($parents as $id => $name) {
			$page = PageQuery::create()->findPk($id);
			if ($page && !Curry_Backend_Page::getPagePermission($page, PageAccessPeer::PERM_CREATE_PAGE))
				$disabled[] = $id;
		}

		$basePageId = $templatePage ? $templatePage->getPageId() : $parentPageId;
		list($basePageElement, $basePreviewElement) = self::getBasePageSelect(null, $basePageId, $advanced);

		$form = new Curry_Form(array(
			'action' => url('', array('module','view','page_id')),
			'method' => 'post',
			'class' => isAjax() ? 'dialog-form' : '',
			'elements' => array(
				'pid_newpage' => array('hidden'),
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'description' => 'Description of the page',
					'autofocus' => 'autofocus',
				)),
				'parent_page_id' => array('select', array(
					'label' => 'Parent Page',
					'multiOptions' => $parents,
					'value' => $parentPageId,
					'disable' => $disabled,
				)),
				'base_page_id' => $basePageElement,
				'base_page_preview' => $basePreviewElement,
				'enabled' => array('checkbox', array(
					'label' => 'Active',
					'value' => !$isTemplatePage,
					'description' => 'Only active pages can be accessed.'
				)),
				'visible' => array('checkbox', array(
					'label' => 'Show in menu',
					'value' => true,
					'description' => 'Enable this if you want the page to show up in menus.'
				)),
				'index' => array('checkbox', array(
					'label' => 'Include in search index',
					'value' => !$isTemplatePage,
					'description' => 'Disable this if you dont want the page to be included in search results.'
				)),
				'createpage' => array('submit', array(
					'label' => 'Create new page'
				))
			)
		));
		return $form;
	}
	
	/**
	 * Save new page.
	 *
	 * @param array $values
	 * @return Page
	 */
	public static function saveNewPage(array $values)
	{
		$subpage = new Page();
		$subpage->setUid(Helper::getUniqueId());
		$subpage->setVisible($values['visible']);
		$subpage->setEnabled($values['enabled']);
		$subpage->setIncludeInIndex($values['index']);

		if ($values['parent_page_id'] == '@root' && !PageQuery::create()->findRoot()) {
			// Create root node
			$subpage->makeRoot();
			$subpage->setName($values['name']);
			$subpage->setUrl('/');
			// Grant everyone access
			$pageAccess = new PageAccess();
			$pageAccess->setPage($subpage);
			$permissions = PageAccess::getPermissionTypes();
			$grant = array_fill(0, count($permissions), true);
			$pageAccess->fromArray(array_combine($permissions, $grant));
		} else {
			$parentPage = PageQuery::create()->findPk($values['parent_page_id']);
			$subpage->insertAsLastChildOf($parentPage);
			$subpage->setAutoName($values['name']);
		}

		$subpage->save();
		return $subpage;
	}
	
	/**
	 * Metadata form.
	 *
	 * @param PageRevision $pageRevision
	 * @return Curry_Form
	 */
	public static function getMetadataForm(PageRevision $pageRevision)
	{
		$query = PageMetadataQuery::create()
			->joinWith('Metadata', Criteria::RIGHT_JOIN)
			->addJoinCondition('Metadata', 'PageMetadata.PageRevisionId = ?', $pageRevision->getPageRevisionId())
			->orderBy('Metadata.SortableRank');
		$metadatas = $query->find();
		
		$form = new Curry_Form(array(
			'action' => url('', array("module","view","page_id")),
			'method' => 'post',
			'elements' => array(
				'pid_metadata' => array('hidden'),
			)
		));
		
		$typeOptions = array(
			'textarea' => array(
				'rows' => 6,
			)
		);
		
		foreach($metadatas as $pageMetadata) {
			$metadata = $pageMetadata->getMetadata();
			$options = array(
				'label' => $metadata->getDisplayName(),
				'value' => $pageMetadata->getValue() !== null ? $pageMetadata->getValue() : $metadata->getDefaultValue(),
			);
			if(isset($typeOptions[$metadata->getType()]))
				ArrayHelper::extend($options, $typeOptions[$metadata->getType()]);
			$form->addElement($metadata->getType(), $metadata->getName(), $options);
		}
		$form->addElement('submit', 'save', array('label' => 'Save'));
		return $form;
	}
	
	/**
	 * Save page metadata.
	 *
	 * @param PageRevision $pageRevision
	 * @param array $values
	 */
	public static function savePageMetadata(PageRevision $pageRevision, array $values)
	{
		foreach(MetadataQuery::create()->find() as $metadata) {
			$pageMetadata = PageMetadataQuery::create()
				->filterByMetadata($metadata)
				->filterByPageRevision($pageRevision)
				->findOneOrCreate();
			if($values[$metadata->getName()] !== $metadata->getDefaultValue()) {
				$pageMetadata->setValue($values[$metadata->getName()]);
				$pageMetadata->save();
			} else {
				$pageMetadata->delete();
			}
		}
	}
	
	/**
	 * Create new metadata field form.
	 *
	 * @return Curry_Form
	 */
	public static function getNewMetadataForm()
	{
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'class' => isAjax() ? 'dialog-form' : '',
			'elements' => array(
				'pid_newmetadata' => array('hidden'),
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'validators' => array('alnum'),
					'description' => 'Name used to be able to access this value from the templates (using page.meta.Name). Recommended format is CamelCase.'
				)),
				'display_name' => array('text', array(
					'label' => 'Display Name',
					'required' => true,
					'description' => 'Name to display in the back-end.'
				)),
				'type' => array('text', array(
					'label' => 'Type',
					'required' => true,
					'description' => 'May be one of the element types supported by Zend_Form, eg: text, textarea, checkbox, etc',
				)),
				'default_value' => array('text', array(
					'label' => 'Default value',
				)),
				'save' => array('submit', array(
					'label' => 'Save',
				)),
			)
		));
		return $form;
	}
	
	/**
	 * Save metadata field.
	 *
	 * @param array $values
	 */
	public static function saveNewMetadata(array $values)
	{
		$metadata = new Metadata();
		$metadata->setName($values['name']);
		$metadata->setDisplayName($values['display_name']);
		$metadata->setType($values['type']);
		$metadata->setDefaultValue($values['default_value']);
		$metadata->save();
	}
	
	/**
	 * Set publish date form.
	 *
	 * @param PageRevision $pageRevision
	 * @return Curry_Form
	 */
	public static function getPublishDateForm(PageRevision $pageRevision)
	{
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'class' => isAjax() ? 'dialog-form' : '',
			'elements' => array(
				'pid_setpublishdate' => array('hidden'),
				'publish_date' => array('date', array(
					'label' => 'Date',
					'value' => $pageRevision->getPublishDate('Y-m-d'),
				)),
				'publish_time' => array('text', array(
					'label' => 'Time',
					'value' => $pageRevision->getPublishDate('H:i:s'),
				)),
				'submit' => array('submit', array(
					'label' => 'Set publish date'
				))
			)
		));
		return $form;
	}
	
	/**
	 * Create new revision form.
	 *
	 * @param Page $page
	 * @param int $copyRevisionId
	 * @return Curry_Form
	 */
	public static function getNewRevisionForm(Page $page, $copyRevisionId)
	{
		$pageRevisions = ArrayHelper::objectsToArray($page->getPageRevisions(), 'getPageRevisionId', 'getDescription');
		$pageRevisions = array(null => '[ None ]') + $pageRevisions;
		
		if($page->getWorkingPageRevisionId())
			$pageRevisions[$page->getWorkingPageRevisionId()] .= " [working]";
		if($page->getActivePageRevisionId())
			$pageRevisions[$page->getActivePageRevisionId()] .= " [active]";
		
		return new Curry_Form(array(
			'action' => url('', array("module","view","page_id")),
			'method' => 'post',
			'class' => isAjax() ? 'dialog-form' : '',
			'elements' => array(
				'pid_createrevision' => array('hidden'),
				'description' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'description' => 'Description of the revision'
				)),
				'copy_revision_id' => array('select', array(
					'label' => 'Copy from revision',
					'multiOptions' => $pageRevisions,
					'description' => 'Copy content and templates from another revision.',
					'value' => $copyRevisionId,
				)),
				'set_working' => array('checkbox', array(
					'label' => 'Set as working revision',
					'required' => true,
					'value' => true,
				)),
				'createrevision' => array('submit', array(
					'label' => 'Create new revision'
				))
			)
		));
	}
	
	/**
	 * Save new revision.
	 *
	 * @param Page $page
	 * @param array $values
	 * @return PageRevision
	 */
	public static function saveNewRevision(Page $page, array $values)
	{
		if($values['copy_revision_id']) {
			$copyRevision = PageRevisionQuery::create()->findPk($values['copy_revision_id']);
			if(!$copyRevision)
				throw new Exception("PageRevision not found.");
			$pageRevision = $copyRevision->duplicate();
		} else {
			$pageRevision = new PageRevision();
			$pageRevision->setPage($page);
		}
		$pageRevision->setDescription($values['description']);
		$pageRevision->save();
		
		if($values['set_working']) {
			$page->setWorkingPageRevision($pageRevision);
			$page->save();
		}
		
		return $pageRevision;
	}
	
	/**
	 * Get page properties form.
	 *
	 * @param Page $page
	 * @return Curry_Form
	 */
	public static function getPagePropertiesForm(Page $page)
	{
		$form = new Curry_Form(array(
			'action' => url('', array("module","view","page_id")),
			'method' => 'post',
			'elements' => array(
				'pid_properties' => array('hidden'),
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'value' => $page->getName(),
					'description' => 'The name of the page is shown in menus. It\'s also used to generate the default title and URL for the page.'
				)),
				'url' => array('text', array(
					'label' => 'URL',
					'value' => $page->getUrl(),
					'description' => 'The URL of the page. Subpages will be based on this URL.',
				)),
				'enabled' => array('checkbox', array(
					'label' => 'Active',
					'value' => $page->getEnabled(),
					'description' => 'Only active pages can be accessed.'
				)),
				'visible' => array('checkbox', array(
					'label' => 'Show in menu',
					'value' => $page->getVisible(),
					'description' => 'Enable this if you want the page to show up in menus.'
				)),
				'index' => array('checkbox', array(
					'label' => 'Include in search index',
					'value' => $page->getIncludeInIndex(),
					'description' => 'Disable this if you dont want the page to be included in search results.'
				)),
			)
		));

		$redirectValue = null;
		if($page->getRedirectUrl())
			$redirectValue = "external";
		else if($page->getRedirectPageId())
			$redirectValue = $page->getRedirectPageId();
		else
			$redirectValue = ""; // first-subpage
		
		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Redirect',
			'class' => $page->getRedirectMethod() ? '' : 'advanced',
			'elements' => array(
				'method' => array('select', array(
					'label' => 'Method',
					'multiOptions' => array(
						'' => '[ None ]',
						PagePeer::REDIRECT_METHOD_CLONE => 'Clone',
						PagePeer::REDIRECT_METHOD_PERMANENT => 'Permanent redirect (301)',
						PagePeer::REDIRECT_METHOD_TEMPORARY => 'Temporary redirect (302)',
					),
					'value' => $page->getRedirectMethod(),
					'onchange' => '$("#redirect-page_id").attr("disabled", this.value == "").change();',
				)),
				'page_id' => array('select', array(
					'label' => 'Page',
					'multiOptions' => array("" => '[ First subpage ]', 'external' => '[ External ]') + PagePeer::getSelect(),
					'value' => $redirectValue,
					'onchange' => '$("#redirect-url").attr("disabled", $(this).attr("disabled") || this.value != "external");',
					'disabled' => (!$page->getRedirectMethod() ? 'disabled' : null),
				)),
				'url' => array('text', array(
					'label' => 'External URL',
					'value' => $page->getRedirectUrl() ? $page->getRedirectUrl() : 'http://',
					'disabled' => (!$page->getRedirectMethod() || $redirectValue != 'external') ? 'disabled' : null,
				)),
			)
		)), 'redirect');

		// Base page
		$advanced = Curry_Backend_Page::getPagePermission($page, PageAccessPeer::PERM_MODULES);
		$pageRevision = $page->getPageRevision();
		list($basePageElement, $basePreviewElement) = self::getBasePageSelect($page, $pageRevision->getBasePageId(), $advanced);

		// Template
		$templates = Curry_Backend_Template::getTemplateSelect();
		if($pageRevision->getInheritRevision()) {
			$template = $pageRevision->getInheritRevision()->getInheritedProperty('Template', 'Undefined', false);
			$templates = array('' => '[ Inherited: '.$template.' ]') + $templates;
		} else {
			$templates = array('' => '[ None ]') + $templates;
		}

		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Base page',
			'class' => 'advanced',
			'elements' => array(
				'base_page_id' => $basePageElement,
				'base_page_preview' => $basePreviewElement,
			)
		)), 'base_page');

		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => 'Advanced',
			'class' => 'advanced',
			'elements' => array(
				'image' => array('previewImage', array(
					'label' => 'Image',
					'value' => $page->getImage(),
					'description' => 'Select an image to represent the page. This will show up when selecting base page.',
				)),
				'template' => array('select', array(
					'label' => 'Page template',
					'multiOptions' => $templates,
					'value' => (string)$pageRevision->getTemplate(),
					'description' => 'Override page template inherited from base page.',
				)),
				'langcode' => array('select', array(
					'label' => 'Language',
					'value' => $page->getLangcode(),
					'multiOptions' => array('' => 'None (Inherit from parent)') + LanguageQuery::create()->find()->toKeyValue('Langcode','Name'),
					'description' => 'Language of page. Inherited from closest parent if not set.',
				)),
				'model_route' => array('text', array(
					'label' => 'Model route',
					'value' => $page->getModelRoute(),
					'description' => 'The model class used for routing.',
				)),
				'cache_lifetime' => array('text', array(
					'label' => 'Cache lifetime',
					'value' => $page->getCacheLifetime(),
					'description' => 'Full page caching, specified in seconds (0 to disable, -1 for infinity).',
				)),
				'generator' => array('text', array(
					'label' => 'Generator',
					'value' => $page->getGenerator(),
					'placeholder' => \Curry\App::getInstance()['defaultGeneratorClass'],
				)),
			)
		)), 'advanced');
		
		$form->addElement('submit', 'Save');
		
		return $form;
	}
	
	/**
	 * Save page properties.
	 *
	 * @param Page $page
	 * @param array $values
	 */
	public static function savePageProperties(Page $page, array $values)
	{
		$urlChanged = ($page->getUrl() != $values['url']);
		
		$page->setAutoName($values['name']);
		$page->setEnabled($values['enabled']);
		$page->setVisible($values['visible']);
		$page->setIncludeInIndex($values['index']);
		
		// Redirection
		$v = $values['redirect'];
		$page->setRedirectMethod($v['method'] ? $v['method'] : null);
		if($v['page_id'] == 'external') {
			$page->setRedirectUrl($v['url']);
		} else {
			$redirectPageId = (int)$v['page_id'];
			$page->setRedirectPageId($redirectPageId ? $redirectPageId : null);
			$page->setRedirectUrl(null);
		}

		// Base page and template
		$v = $values['base_page'];
		$pageRevision = $page->getPageRevision();
		$pageRevision->setBasePageId($v['base_page_id'] ? (int)$v['base_page_id'] : null);
		
		// Advanced
		$v = $values['advanced'];
		$page->setImage($v['image']);
		$pageRevision->setTemplate($v['template'] ? $v['template'] : null);
		$page->setLangcode(strlen($v['langcode']) ? $v['langcode'] : null);
		$page->setModelRoute($v['model_route'] ? $v['model_route'] : null);
		$page->setCacheLifetime((int)$v['cache_lifetime']);
		$page->setGenerator(strlen($v['generator']) ? $v['generator'] : null);
		$page->setEncoding(strlen($v['encoding']) ? $v['encoding'] : null);

		// Save page/revision
		$pageRevision->save();
		$page->save();
		
		// Update url recursively
		if(empty($values['url'])) {
			// reset url
			$page->setUrlRecurse($page->getExpectedUrl());
		} else if($urlChanged) {
			// set new url
			$url = $values['url'];
			if(substr($url, -1) != '/')
				$url .= '/';
			if($url != '/' && substr($url, 0, 1) == '/')
				$url = substr($url, 1);
			$page->setUrlRecurse($url);
		}
	}

	protected static function getBasePageSelect(Page $page = null, $basePageId = null, $advanced = false)
	{
		$pages = array('' => '[ Do not inherit ]');
		$templatePage = Curry_Backend_Page::getTemplatePage();
		if ($templatePage) {
			$pages['Templates'] = PagePeer::getSelect($templatePage);
			if ($advanced)
				$pages['Pages'] = array_diff_key(PagePeer::getSelect(), $pages['Templates']);
			else if ($basePageId && !array_key_exists($basePageId, $pages['Templates'])) {
				$basePage = PageQuery::create()->findPk($basePageId);
				$pages['Pages'] = array(
					$basePageId => $basePage ? $basePage->getName() : '<Unknown>'
				);
			}
		} else {
			$pages += PagePeer::getSelect();
		}

		$dependantPages = array();
		if ($page) {
			$dependantPages = ArrayHelper::objectsToArray($page->getDependantPages(), null, 'getPageId');
			$dependantPages[] = $page->getPageId();
		}

		$pageSelect = array('select', array(
			'label' => 'Base page',
			'multiOptions' => $pages,
			'value' => $basePageId,
			'description' => 'The page which content and templates will be inherited from.',
			'disable' => $dependantPages,
			'onchange' => "$(this).closest('form').find('.base-preview').attr('src', '".url('', array('module','view'=>'BasePreview'))."&page_id=' + $(this).val());",
		));
		$imageElement = array('rawHtml', array(
			'label' => 'Preview',
			'value' => '<img src="'.url('', array('module','view'=>'BasePreview','page_id' => $basePageId)).'" class="base-preview" />',
		));

		return array($pageSelect, $imageElement);
	}
	
	/**
	 * Create new module form.
	 *
	 * @param PageRevision $pageRevision
	 * @param string $moduleClass
	 * @return Curry_Form
	 */
	public static function getNewModuleForm($pageRevision, $moduleClass, $target)
	{
		$valid = array();
		$modules = array();
		foreach(ModuleQuery::create()->orderByTitle()->find() as $module) {
			$inTargets = in_array($target, $module->getTargets());
			if (!$target || ($module->getTargetsExclude() ? !$inTargets : $inTargets)) {
				$modules[$module->getModuleId()] = $module->getTitle();
				$valid[] = $module->getModuleId();
			}
		}

		$user = User::getUser();
		$modulePermission = $user->hasPagePermission($pageRevision->getPage(), PageAccessPeer::PERM_MODULES);
		if ($modulePermission) {
			$modules = array('Predefined' => $modules);
			foreach(AbstractModule::getModuleList() as $className) {
				$parts = explode("_", str_replace("_Module_", "_", $className));
				$package = array_shift($parts);
				$modules[$package][$className] = join(" / ", $parts);
				$valid[] = $className;
			}
		}
		
		if(!$moduleClass || !in_array($moduleClass, $valid)) {
			$form = new Curry_Form(array(
				'action' => url('', $_GET),
				'method' => 'post',
				'class' => isAjax() ? 'dialog-form' : '',
				'elements' => array(
					'module_class' => array('select', array(
						'label' => 'Type',
						'multiOptions' => $modules,
						'required' => true,
					)),
					'next' => array('submit', array(
						'label' => 'Next',
					)),
				)
			));
			return $form;
		} else {
			// Fetch template targets
			$targets = array();
			try {
				$template = $pageRevision->getInheritedProperty('Template');
				if($template)
					$template = \Curry\App::getInstance()->twig->loadTemplate($template);
				while($template) {
					$targets = array_merge($targets, $template->getPlaceholders());
					$template = $template->getParent(array());
				}
			}
			catch (Exception $e) {
				\Curry\App::getInstance()->logger->warning('Error in template: ' . $e->getMessage());
			}
			if(count($targets))
				$targets = array_combine(array_values($targets), array_values($targets));

			// Check for predefined module creation
			if (ctype_digit($moduleClass)) {
				$module = ModuleQuery::create()->findPk($moduleClass);
				$form = new Curry_Form(array(
					'action' => url('', $_GET),
					'method' => 'post',
					'class' => isAjax() ? 'dialog-form' : '',
					'elements' => array(
						'pid_newmodule' => array('hidden', array('value' => 1)),
						'module_class' => array('hidden', array('value' => $moduleClass)),
						'name' => array('text', array(
							'label' => 'Name',
							'required' => true,
							'description' => 'A descriptive name of the module.',
							'value' => $module->getName(),
						)),
					)
				));
				if (!$target) {
					// Show only acceptable targets...
					$form->addElement('select', 'target', array(
						'label' => 'Target',
						'description' => 'Specifies what placeholder/variable in the page-template to attach this module to.',
						'multiOptions' => $targets,
					));
				} else {
					$form->addElement('hidden', 'target', array('value' => $target));
					$form->setCsrfCheck(false);
					$_POST = $form->getValues();
				}
				$form->addElement('submit', 'add', array('label' => 'Add module'));
				return $form;
			}

			if(!class_exists($moduleClass))
				throw new Exception('Class \''.$moduleClass.'\' could not be loaded, please check the path and classname.');
				
			$defaultName = substr($moduleClass, strrpos($moduleClass, '_') + 1);
			$targets[''] = '[ Custom ]';
			asort($targets);
			
			$templates = array('' => "[ None ]", 'new' => "[ Create new ]") + Curry_Backend_Template::getTemplateSelect();
			$defaultTemplateName = 'Modules/'.$defaultName.'.html';
			$defaultTemplate = '';
			if($moduleClass !== 'Curry_Module_Article' && call_user_func(array($moduleClass, 'hasTemplate')))
				$defaultTemplate = array_key_exists($defaultTemplateName, $templates) ? $defaultTemplateName : 'new';
			
			$predefinedTemplates = call_user_func(array($moduleClass, 'getPredefinedTemplates'));
			$predefinedTemplates = count($predefinedTemplates) ? array_combine(array_keys($predefinedTemplates), array_keys($predefinedTemplates)) : array();
			$predefinedTemplates = array('' => '[ Empty ]') + $predefinedTemplates;
			
			$form = new Curry_Form(array(
				'action' => url('', $_GET),
				'method' => 'post',
				'class' => isAjax() ? 'dialog-form' : '',
				'elements' => array(
					'pid_newmodule' => array('hidden'),
					'module_class' => array('hidden', array('value' => $moduleClass)),
					'module_class_display' => array('rawHtml', array(
						'label' => 'Type',
						'value' => '<input type="text" value="'.$moduleClass.'" disabled="disabled" />',
					)),
					'name' => array('text', array(
						'label' => 'Name',
						'required' => true,
						'description' => 'A descriptive name of the module.',
						'value' => $defaultName,
					)),
					'target' => array('select', array(
						'label' => 'Target',
						'description' => 'Specifies what placeholder/variable in the page-template to attach this module to.',
						'value' => isset($_GET['target']) ? $_GET['target'] : null,
						'multiOptions' => $targets,
						'class' => 'trigger-change',
						'onchange' => "$('#target_name-label, #target_name-element').toggle($(this).val() == '');",
					)),
					'target_name' => array('text', array(
						'label' => 'Target Name',
					)),
					'template' => array('select', array(
						'class' => 'trigger-change',
						'label' => 'Template',
						'multiOptions' => $templates,
						'value' => $defaultTemplate,
						'onchange' => "$('#template_name-label, #template_name-element, #predefined_template-label, #predefined_template-element').toggle($(this).val() == 'new');",
					)),
					'template_name' => array('text', array(
						'label' => 'Name',
						'value' => $defaultTemplateName,
					)),
					'predefined_template' => array('select', array(
						'label' => 'Predefined template',
						'multiOptions' => $predefinedTemplates,
					)),
					'content_visibility' => array('select', array(
						'label' => 'Content Visibility',
						'description' => 'Set the visibility of this module in the Content backend module.',
						'multiOptions' => PageModulePeer::$contentVisiblityOptions,
						'value' => PageModulePeer::CONTENT_VISIBILITY_ALWAYS,
						'required' => true,
					)),
					'search_visibility' => array('checkbox', array(
						'label' => 'Search Visibility',
						'description' => 'If this module should be rendered when indexing pages.',
						'value' => true,
						'required' => true,
					)),
				)
			));
			
			$form->addDisplayGroup(array('position', 'content_visibility', 'search_visibility'), 'advanced', array('class' => 'advanced', 'legend' => 'Advanced'));
			$form->addElement('submit', 'add', array('label' => 'Add module'));
		}
		return $form;
	}
	
	/**
	 * Save new module.
	 * 
	 * @todo Set module position.
	 *
	 * @param PageRevision $pageRevision
	 * @param array $values
	 * @return PageModule
	 */
	public static function saveNewModule(PageRevision $pageRevision, array $values)
	{
		$pageModule = new PageModule();
		$pageModule->setUid(Helper::getUniqueId());
		$pageModule->setPageId($pageRevision->getPageId());
		if (ctype_digit($values['module_class'])) {
			$module = ModuleQuery::create()->findPk($values['module_class']);
			$pageModule->setModuleClass($module->getModuleClass());
			$pageModule->setName($values['name']);
			$pageModule->setTarget($values['target']);
			$pageModule->setContentVisibility($module->getContentVisibility());
			$pageModule->setSearchVisibility($module->getSearchVisibility());
			$template = $module->getTemplate();
		} else {
			$template = null;
			if($values['template'] == 'new' && $values['template_name']) {
				$className = $values['module_class'];
				$predefinedTemplates = call_user_func(array($className, 'getPredefinedTemplates'));

				$root = \Curry\App::getInstance()['template.root'];
				$template = $values['template_name'];
				$templateFile = $root . DIRECTORY_SEPARATOR . $template;
				if(!file_exists($templateFile)) {
					$dir = dirname($templateFile);
					if(!is_dir($dir))
						mkdir($dir, 0777, true);
					$code = $predefinedTemplates[$values['predefined_template']];
					file_put_contents($templateFile, (string)$code);
				}
			} else if($values['template']) {
				$template = $values['template'];
			}

			$target = '';
			if(!empty($values['target']))
				$target = $values['target'];
			else if(!empty($values['target_name']))
				$target = $values['target_name'];
			if(empty($target))
				throw new Exception('Module target not set');

			$pageModule = new PageModule();
			$pageModule->setUid(Helper::getUniqueId());
			$pageModule->setPageId($pageRevision->getPageId());
			$pageModule->setModuleClass($values['module_class']);
			$pageModule->setName($values['name']);
			$pageModule->setTarget($target);
			$pageModule->setContentVisibility($values['content_visibility']);
			$pageModule->setSearchVisibility($values['search_visibility']);
		}
		$pageModule->save();

		$revisionModule = new RevisionModule();
		$revisionModule->setPageModule($pageModule);
		$revisionModule->setPageRevision($pageRevision);
		$revisionModule->save();
		
		$moduleData = new ModuleData();
		$moduleData->setPageRevision($pageRevision);
		$moduleData->setPageModule($pageModule);
		$moduleData->setEnabled(true);
		$moduleData->setTemplate($template);
		$moduleData->save();
		
		// create default data
		$wrapper = new PageModuleWrapper($pageModule, $pageRevision, null);
		$wrapper->createData();
		
		return $pageModule;
	}
	
	/**
	 * Module properties form.
	 *
	 * @param PageModuleWrapper $pageModuleWrapper
	 * @return Curry_Form
	 */
	public static function getModulePropertiesForm(PageModuleWrapper $pageModuleWrapper)
	{
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
			'elements' => array(
				'pid_moduleproperties' => array('hidden'),
				'name' => array('text', array(
					'label' => 'Name',
					'required' => true,
					'value' => $pageModuleWrapper->getPageModule()->getName(),
					'description' => 'The name of this module. If you have many modules of the same type on the same page this will help you to seperate them from each other.'
				)),
				'target' => array('text', array(
					'label' => 'Target',
					'description' => 'Specifies what variable in the page-template to attach this module to.',
					'required' => true,
					'value' => $pageModuleWrapper->getTarget(),
				)),
				'content_visibility' => array('select', array(
					'label' => 'Content Visibility',
					'description' => 'Set the visibility of this module in the Content backend module.',
					'multiOptions' => PageModulePeer::$contentVisiblityOptions,
					'value' => $pageModuleWrapper->getPageModule()->getContentVisibility(),
					'required' => true,
				)),
				'search_visibility' => array('checkbox', array(
					'label' => 'Search Visibility',
					'description' => 'If this module should be rendered when indexing pages.',
					'value' => $pageModuleWrapper->getPageModule()->getSearchVisibility(),
					'required' => true,
				)),
			)
		));

		$showSelect = array("true" => "Yes", "false" => "No");
		$defaultTemplate = $pageModuleWrapper->isInherited() ? "[ Inherit ]" : "[ None ]";
		$template = $pageModuleWrapper->getModuleData()->getTemplate();
		$templatesSelect = Curry_Backend_Template::getTemplateSelect();
		if ($template && !array_key_exists($template, $templatesSelect)) {
			$templatesSelect[$template] = $template . ' <MISSING!>';
		}
		
		$form->addSubForm(new Curry_Form_SubForm(array(
			'legend' => $pageModuleWrapper->isInherited() ? 'Override inherited settings' : 'Inherited settings',
			'elements' => array(
				'template' => ($pageModuleWrapper->hasTemplate() ?
					array('select', array(
						'label' => 'Template',
						'multiOptions' => array(null => $defaultTemplate) + $templatesSelect,
						'value' => $template,
					))
					:
					array('select', array(
						'label' => 'Template',
						'multiOptions' => array("None"),
						'disabled' => 'disabled'
					))
				),
				'show' => array('select', array(
					'label' => 'Show',
					'multiOptions' => $pageModuleWrapper->isInherited() ? array('' => "[ Inherit ]") + $showSelect : $showSelect,
					'value' => self::bool2str($pageModuleWrapper->getModuleData()->getEnabled()),
				))
			)
		)), 'local');

		$form->addElement('submit', 'save', array('label'=>'Save'));
		
		return $form;
	}
	
	/**
	 * Save module properties.
	 *
	 * @param PageModuleWrapper $pageModuleWrapper
	 * @param array $values
	 */
	public static function saveModuleProperties(PageModuleWrapper $pageModuleWrapper, array $values)
	{
		$modified = false;
		
		// PageModule
		$pageModule = $pageModuleWrapper->getPageModule();
		$pageModule->setName($values['name']);
		$pageModule->setTarget($values['target']);
		$pageModule->setContentVisibility($values['content_visibility']);
		$pageModule->setSearchVisibility($values['search_visibility']);
		$modified |= $pageModule->isModified();
		$pageModule->save();
		
		// ModuleData
		$moduleData = $pageModuleWrapper->getModuleData();
		$moduleData->setTemplate($values['local']['template'] === '' ? null : $values['local']['template']);
		$moduleData->setEnabled(self::str2bool($values['local']['show']));
		$modified |= $moduleData->isModified();
		$moduleData->save();
		
		if($modified) {
			$pageRevision = $pageModuleWrapper->getPageRevision();
			$pageRevision->setUpdatedAt(time());
			$pageRevision->save();
		}
	}
	
	/**
	 * Show page permission form.
	 *
	 * @param Page $page
	 * @return string
	 */
	public static function getPagePermissionForm(Page $page)
	{
		$numColumns = count(PageAccess::getPermissionTypes());
		$form = '<form action="'.url('', array('module','view','page_id')).'" method="post" class="permissions-form">';
		$form.= '<div class="zend_form">';
		$form.= '<input type="hidden" name="pid_permissions" value="1">';
		$form.= '<table>';
		$form.= '<thead><tr class="permission-types"><th>&nbsp;</th>';
		foreach(PageAccess::getPermissionTypes() as $phpName)
			$form.= '<th>'.substr($phpName, 4).'</th>';
		$form.= '</tr></thead>';
		$form.= '<tfoot>';
		$form.= '<tr><td colspan="'.($numColumns+1).'"><input type="submit" class="btn btn-primary" value="Save" /></td></tr>';
		$form.= '</tfoot>';
		$form.= '<tbody>';
		$form.= '<tr><td>Everyone</td>'.self::getPagePermissionRow($page, 'everyone').'</tr>';
		$form.= '<tr class="permission-group"><th>Roles</th></tr>';
		foreach(UserRoleQuery::create()->find() as $role) {
			$form.= '<tr><td>'.$role->getName().'</td>'.self::getPagePermissionRow($page, 'role['.$role->getUserRoleId().']', null, $role).'</tr>';
		}
		$form.= '<tr class="permission-group"><th>Users</th></tr>';
		foreach(UserQuery::create()->find() as $user) {
			$form.= '<tr><td>'.$user->getName().'</td>'.self::getPagePermissionRow($page, 'user['.$user->getUserId().']', $user).'</tr>';
		}
		$form.= '</tbody>';
		$form.= '</table>';
		$form.= '</div>';
		$form.= '</form>';
		return $form;
	}
	
	/**
	 * Save page permissions
	 *
	 * @param Page $page
	 * @param array $values
	 */
	public static function savePagePermission(Page $page, array $values)
	{
		self::savePagePermissionEntry($page, null, null, (array)$values['everyone']);
		foreach((array)$values['user'] as $userId => $permissions) {
			self::savePagePermissionEntry($page, UserQuery::create()->findPk($userId), null, $permissions);
		}
		foreach((array)$values['role'] as $roleId => $permissions) {
			self::savePagePermissionEntry($page, null, UserRoleQuery::create()->findPk($roleId), $permissions);
		}
	}
	
	/**
	 * Save page permission entry.
	 *
	 * @param Page $page
	 * @param User|null $user
	 * @param UserRole|null $role
	 * @param array $permission
	 */
	protected static function savePagePermissionEntry(Page $page, User $user = null, UserRole $role = null, array $permission = array())
	{
		$userPermission = Curry_Backend_Page::getPagePermission($page);
		$permissionTypes = PageAccess::getPermissionTypes();
		
		$access = PageAccessQuery::create()
			->filterByPage($page)
			->filterByUserAndRole($user, $role)
			->findOneOrCreate();
		$valMap = array('' => null, 'yes' => true, 'no' => false);
		foreach($permission as $colName => $val) {
			if(!array_key_exists($colName, $permissionTypes))
				continue;
			if(!$userPermission[$colName])
				continue;
			if($colName == PageAccessPeer::PERM_SUBPAGES && $val == '')
				$val = 'yes';
			if(!array_key_exists($val, $valMap))
				throw new Exception('Invalid value for permission');
			$access->{'set'.$permissionTypes[$colName]}($valMap[$val]);
		}
		if(count($access->getPermissions()) > 1)
			$access->save();
		else if(!$access->isNew())
			$access->delete();
	}
	
	/**
	 * Get page permission table row.
	 *
	 * @param Page $page
	 * @param string $name
	 * @param User|null $user
	 * @param UserRole|null $role
	 * @return string
	 */
	protected static function getPagePermissionRow(Page $page, $name, User $user = null, UserRole $role = null)
	{
		$inheritPermission = $page->getPageAccess($user, $role ? $role : ($user ? $user->getUserRole() : null));
		$userPermission = Curry_Backend_Page::getPagePermission($page);
		
		$access = PageAccessQuery::create()
			->filterByPage($page)
			->filterByUserAndRole($user, $role)
			->findOne();
		
		$row = '';
		foreach(PageAccess::getPermissionTypes() as $colName => $phpName) {
			$fieldName = $name.'['.$colName.']';
			$val = $access ? $access->{'get'.$phpName}() : null;
			
			if($colName == PageAccessPeer::PERM_SUBPAGES) {
				if($val === null)
					$val = $inheritPermission[$colName];
				$row.= '<td><input type="hidden" name="'.$fieldName.'" value="no" /><input type="checkbox" name="'.$fieldName.'" value="yes" '.($userPermission[$colName] ? '' : 'disabled="disabled" ').($val?'checked="checked" ':'').'/></td>';
				continue;
			}
			
			$options = array('' => '(inherited)', 'yes' => 'Yes', 'no' => 'No');
			if($val === null)
				$options[''] = ($inheritPermission[$colName] ? 'Yes ' : 'No ') . $options[''];
			$val = $val === null ? '' : ($val ? 'yes' : 'no');
			
			$selectedColor = 'black';
			$opts = '';
			foreach($options as $optionValue => $optionLabel) {
				$attr = array('value' => $optionValue);
				$color = $optionValue ? ($optionValue=='yes'?'green':'red') : '#aaa';
				$attr['style'] = 'color:'.$color;
				if($optionValue === $val) {
					$selectedColor = $color;
					$attr['selected'] = 'selected';
				}
				$opts .= Html::tag('option', $attr, $optionLabel);
			}
			$row.= '<td><select name="'.$fieldName.'" '.($userPermission[$colName] ? '' : 'disabled="disabled" ').'style="color:'.$selectedColor.'" onchange="this.style.color = this.options[this.selectedIndex].style.color">';
			$row.= $opts;
			$row.= '</select></td>';
		}
		return $row;
	}
	
	/**
	 * Convert bool or null to string.
	 *
	 * @param bool|null $v
	 * @return string
	 */
	public static function bool2str($v)
	{
		if($v === null)
			return "";
		return $v ? "true" : "false";
	}
	
	/**
	 * Convert string to bool or null.
	 *
	 * @param string $v
	 * @return bool|null
	 */
	public static function str2bool($v)
	{
		if($v === "")
			return null;
		return ($v === "true");
	}
}
