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
 * Curry\Controller\Backend module for managing languages and translation-strings.
 * 
 * @package Curry\Controller\Backend
 */
class Curry_Backend_Translations extends \Curry\AbstractLegacyBackend {
	const PERMISSION_TRANSLATIONS = 'Translations';
	const PERMISSION_LANGUAGES = 'Languages';
	const PERMISSION_FIELDS = 'Fields';

	/** {@inheritdoc} */
	public function getGroup()
	{
		return "Content";
	}

	/** {@inheritdoc} */
	public function getPermissions()
	{
		return array(
			self::PERMISSION_TRANSLATIONS,
			self::PERMISSION_LANGUAGES,
			self::PERMISSION_FIELDS,
		);
	}

	public static function addLanguageForm(\Curry\AbstractLegacyBackend $backend, $user = null)
	{
		if (!$user)
			$user = User::getUser();

		$languages = LanguageQuery::create()
			->useUserLanguageQuery()
			  ->filterByUser($user)
			->endUse()
			->find()
			->toKeyValue('Langcode', 'Name');

		if (!count($languages))
			throw new Exception('You do not have access to any languages.');

		$langcode = null;
		if (isset($_GET['langcode']))
			$langcode = $_GET['langcode'];
		if (!array_key_exists($langcode, $languages))
			$langcode = null;
		if ($langcode === null) {
			$langcode = array_keys($languages);
			$langcode = array_shift($langcode);
		}

		if (empty($_GET['action'])) {
			header('cache-control: no-store'); // dont store state as this may cause problem with the form below
			$backend->addMainContent(self::getLanguageForm($languages, $langcode));
		}

		return $langcode;
	}

	public static function getLanguageForm($languages = null, $langcode = null)
	{
		$form = new Curry_Form(array(
			'method' => 'get',
			'action' => url(''),
			'class' => 'language-selector',
			'elements' => array(
				'langcode' => array('select', array(
					'label' => 'Language',
					'onchange' => 'this.form.submit();',
					'multiOptions' => $languages,
					'value' => $langcode,
					'description' => 'Change this if you want to set language specific content.',
				)),
			)
		));

		foreach($_GET as $k => $v) {
			if($k !== 'langcode' && !is_array($v))
				$form->addElement('hidden', $k, array('value' => $v));
		}

		return $form;
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$this->addMainMenu();
		if (!LanguageQuery::create()->count()) {
			$this->addMessage('You need to create a language before you can create translations.');
			return;
		}
		$langcode = self::addLanguageForm($this);
		$language = LanguageQuery::create()->findPk($langcode);
		$form = self::getTranslationForm($language);
		if(isPost() && $form->isValid($_POST)) {
			self::fillTranslationsFromForm($language, $form->getValues());
			$form = self::getTranslationForm($language);
		}
		$this->addMainContent($form);
	}

	public function addMainMenu()
	{
		$views = self::getPermissions();
		$views = array_combine($views, $views);
		$views[self::PERMISSION_TRANSLATIONS] = 'Main';
		foreach($views as $name => $view) {
			if ($this->hasPermission($name))
				$this->addMenuItem($name, url('', array('module','view'=>$view)));
		}
	}

	/**
	 * Show edit language.
	 */
	public function showLanguages()
	{
		$this->addMainMenu();

		$langcodes = array();
		foreach(Zend_Locale::getLocaleList() as $langcode => $ignore) {
			list($l, $t) = explode('_', $langcode);
			$language = Zend_Locale::getTranslation($l, 'language', 'en_US');
			if (!$language)
				$language = $langcode;
			$territory = $t ? Zend_Locale::getTranslation($t, 'territory', 'en_US') : '';
			$langcodes[$langcode] = $language.($territory ? " ($territory)" : ' (Generic)');
		}

		$existing = LanguageQuery::create()->find()->toKeyValue('Langcode', 'Name');
		foreach($existing as $langcode => $name) {
			if (!array_key_exists($langcode, $langcodes))
				$langcodes[$langcode] = $name . ' (Custom)';
		}

		asort($langcodes);

		$form = new Curry_ModelView_Form('Language', array(
			'ignorePks' => false,
			'columnElements' => array(
				'langcode' => array('select', array(
					'multiOptions' => $langcodes,
					'disable' => array_keys($existing),
					'onchange' => "$('[name=\"name\"]', this.form).val(this.options[this.selectedIndex].text);",
				)),
			),
			'onFillForm' => function($item, $form) {
				if ($item->isNew()) {
					$form->addElement('select', 'copy', array(
						'label' => 'Copy translations from',
						'multiOptions' => array('' => '[ None ]') + LanguageQuery::create()->find()->toKeyValue('PrimaryKey', 'Name'),
						'order' => 2,
					));
				} else {
					// Prevent changing langcode (propel cant change primary keys anyway)
					$form->removeElement('langcode');
				}
			},
			'onFillModel' => function(Language $item, $form, $values) {
				$user = User::getUser();
				if ($item->isNew()) {
					$item->addUser($user);
					if (isset($values['copy']) && $values['copy']) {
						$translations = LanguageStringTranslationQuery::create()->findByLangcode($values['copy']);
						foreach($translations as $translation) {
							$item->addLanguageStringTranslation($translation->copy());
						}
					}
				}
			}
		));
		$list = new Curry_ModelView_List('Language', array(
			'modelForm' => $form,
			'maxPerPage' => 0,
		));
		$this->addMainContent($list);
	}

	/**
	 * Show edit fields.
	 */
	public function showFields()
	{
		$this->addMainMenu();
		$form = new Curry_Form_ModelForm('LanguageString', array(
			'ignorePks' => false,
			'columnElements' => array(
				'last_used' => false,
			),
		));
		$list = new Curry_ModelView_List('LanguageString', array(
			'modelForm' => $form,
			'maxPerPage' => 0,
			'defaultSortColumn' => 'id',
			'columns' => array(
				'id' => array(
					'hide' => false,
				),
			),
		));
		$this->addMainContent($list);
	}

	/**
	 * Language translation form.
	 *
	 * @param Language $language
	 * @return Curry_Form
	 */
	protected function getTranslationForm(Language $language)
	{
		$form = new Curry_Form(array(
			'action' => url('', $_GET),
			'method' => 'post',
		));
		$translations = LanguageStringTranslationQuery::create()
			->filterByLanguage($language)
			->find()
			->toKeyValue('StringId', 'Translation');
		foreach(LanguageStringQuery::create()->find()->toKeyValue('Id', 'ElementType') as $id => $elementType) {
			$translation = array_key_exists($id, $translations) ? $translations[$id] : '';
			$options = array(
				'label' => $id,
				'value' => $translation,
			);
			try{
				$form->getPluginLoader('element')->load($elementType);
				if ($elementType == 'textarea') {
					$options['rows'] = 4;
					//$options['wrap'] = null;
				}
			} catch(Zend_Loader_Exception $e) {
				$elementType = 'text';
			}
			$form->addElement($elementType, sha1($id), $options);
		}
		$form->addElement('submit', 'submit', array('label' => 'Save'));
		return $form;
	}

	/**
	 * Fill LanguageStringTranslation objects from array.
	 *
	 * @param Language $language
	 * @param array $values
	 */
	protected function fillTranslationsFromForm(Language $language, array $values) {
		foreach(LanguageStringQuery::create()->find() as $string) {
			$field = sha1($string->getId());
			if($values[$field] != '') {
				// ignore empty string as we want to keep fallback in that case
				// find existing translation
				$translation = LanguageStringTranslationQuery::create()
					->filterByLanguage($language)
					->filterByLanguageString($string)
					->findOne();

				// create new?
				if(!$translation) {
					$translation = new LanguageStringTranslation();
					$translation->setLanguage($language);
					$translation->setLanguageString($string);
				}

				// set new translation and save
				$translation->setTranslation($values[$field]);
				$translation->save();
			}
		}
	}
}
