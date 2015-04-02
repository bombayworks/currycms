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
use Curry\App;

/**
 * Static class to manage internatianalization.
 * 
 * @package Curry
 */
class Curry_Language {
	/**
	 * Array of variables for every language.
	 *
	 * @var array
	 */
	protected static $languageStrings = array();
	
	/**
	 * List of used variables.
	 *
	 * @var string[]
	 */
	protected static $used = array();
	
	/**
	 * The currently active language.
	 *
	 * @var Language|null
	 */
	protected static $currentLanguage = null;
	
	/**
	 * Sets the currently active language.
	 * 
	 * This also attempts to change the locale to langcode.UTF-8
	 * 
	 * @param string|Language $language Specifies the language, either langcode using a string or a Language instance.
	 * @param bool $setlocale If true, an attempt to set the locale using setlocale() is done.
	 * @return null|string	Returns the system locale.
	 */
	public static function setLanguage($language, $setlocale = true)
	{
		$locale = null;
		if(is_string($language)) {
			self::$currentLanguage = LanguageQuery::create()->findPk($language);
		} else {
			self::$currentLanguage = $language;
		}
		if($setlocale && self::$currentLanguage)
			$locale = setlocale(LC_ALL, self::getLangCode().'.UTF-8', self::getLangCode().'.UTF8');
		return $locale;
	}
	
	/**
	 * Get the currently active language.
	 *
	 * @return Language|null
	 */
	public static function getLanguage()
	{
		return self::$currentLanguage;
	}

	/**
	 * Get the langcode for the currently active language.
	 *
	 * @return string|null
	 */
	public static function getLangCode()
	{
		return self::$currentLanguage ? self::$currentLanguage->getPrimaryKey() : null;
	}
	
	/**
	 * Get array with language strings for the specified language. This function caches the language strings in memory.
	 *
	 * @param Language|string|null $language Language, langcode (string) or null for active language.
	 * @return array
	 */
	private static function _getLanguage($language)
	{
		if ($language === null)
			$language = self::$currentLanguage;
		else if (is_string($language))
			$language = LanguageQuery::create()->findPk($language);
		else if (!($language instanceof Language))
			throw new Exception('Invalid language');
			
		if ($language) {
			// initialize language strings
			if (!array_key_exists($language->getLangcode(), self::$languageStrings)) {
				App::getInstance()->logger->info('Loading translation-strings for ' . $language->getName() . ' ('.$language->getLangcode().')');
				self::$languageStrings[$language->getLangcode()] = LanguageStringTranslationQuery::create()
					->filterByLanguage($language)
					->find()
					->toKeyValue('StringId', 'Translation');
			}
		}
		
		return $language;
	}
	
	/**
	 * Get a list of all languages.
	 *
	 * @return string[]	An associative array, with langcode as key and name as value.
	 */
	public static function getLanguages()
	{
		return LanguageQuery::create()->find()->toKeyValue('Langcode', 'Name');
	}
	
	/**
	 * Check if a variable exists for the specified language.
	 *
	 * @param string $variableName Name of lang string.
	 * @param Language|string|null $language Language, langcode (string) or null for active language.
	 * @return bool	True if the variable was found, otherwise false.
	 */
	public static function hasVariable($variableName, $language = null)
	{
		$language = self::_getLanguage($language);
		return $language ? array_key_exists($variableName, self::$languageStrings[$language->getLangcode()]) : false;
	}
	
	/**
	 * Check if the specified langcode exists as a language.
	 *
	 * @param string $languageCode
	 * @return bool
	 */
	public static function hasLanguage($languageCode)
	{
		$languages = self::getLanguages();
		return array_key_exists($languageCode, $languages);
	}
	
	/**
	 * Get language string from the specified language. If the string is not found, the variableName is returned.
	 *
	 * @param string $variableName
	 * @param Language|string|null $language Language, langcode (string) or null for active language.
	 * @return string
	 */
	public static function get($variableName, $language = null)
	{
		if(\Curry\App::getInstance()->config->curry->updateTranslationStrings)
			self::$used[] = $variableName;
		
		$language = self::_getLanguage($language);
		if($language && isset(self::$languageStrings[$language->getLangcode()][$variableName]))
			return self::$languageStrings[$language->getLangcode()][$variableName];
		return $variableName;
	}
	
	/**
	 * Get an array of all the variables for the specified language.
	 *
	 * @param Language|string|null $language Language, langcode (string) or null for active language.
	 * @return string[]
	 */
	public static function getLanguageVariables($language = null)
	{
		$language = self::_getLanguage($language);
		return $language ? self::$languageStrings[$language->getLangcode()] : array();
	}
	
	/**
	 * Create non-existing language strings based on used variables (ie called L() or Curry_Language::get())
	 *
	 * @return void
	 */
	public static function updateLanguageStrings()
	{
		$used = array_unique(self::$used);
		$existing = LanguageStringQuery::create()
			->select('Id')
			->find()->toArray();
		
		
		$new = array_diff($used, $existing);
		foreach($new as $id) {
			try {
				$s = new LanguageString();
				$s->setId($id)
					->save();
			} catch(Exception $e) {}
		}
		
		LanguageStringQuery::create()
			->filterById($used, Criteria::IN)
			->update(array('LastUsed' => 'now'));
	}
}
