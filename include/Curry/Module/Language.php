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
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Module to redirect to different pages depending on the detected browser language.
 * 
 * This module doesn't generate any content.
 * 
 * @package Curry\Module
 */
class Language extends AbstractModule {
	/**
	 * List of languages.
	 *
	 * @var array
	 */
	protected $languages = array();
	
	/** {@inheritdoc} */
	public function showFront(\Curry_Twig_Template $template = null)
	{
		if(is_array($this->languages) && count($this->languages)) {
			// allowed languages
			$allowedLanguages = array();
			foreach($this->languages as $lang)
				$allowedLanguages[$lang['code']] = $lang;
			
			// find preferred language
			reset($this->languages);
			$language = current($this->languages);
			foreach($this->getLanguage() as $lang => $quality) {
				if(array_key_exists($lang, $allowedLanguages)) {
					$language = $allowedLanguages[$lang];
					break;
				}
			}
			
			$page = \PageQuery::create()->findPk($language['page_id']);
			if($page)
				return RedirectResponse::create(url($page->getUrl(), $_GET));
			else
				$this->app->logger->notice('Redirect page not found');
		} else
			$this->app->logger->notice('No languages found');
		
		return '';
	}
	
	/**
	 * Check browser language, and return quality.
	 *
	 * @return array
	 */
	private function getLanguage()
	{
		$lang = array();
		
		// check to see if language is set
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
			// Example: sv-se,sv;q=0.8,en-us;q=0.5,en;q=0.3
			$languages = strtolower($_SERVER["HTTP_ACCEPT_LANGUAGE"] );
			$languages = str_replace(' ', '', $languages);
			$languages = explode( ",", $languages );
			
			$langc = array();
			foreach($languages as $language) {
				$l = explode(";", $language);
				if(count($l) == 1) {
					$langc[] = array_shift($l);
				} else if(count($l) == 2) {
					$langc[] = array_shift($l);
					
					$quality = explode("=", array_shift($l));
					if(count($quality) == 2) {
						$quality = (float)$quality[1];
						foreach($langc as $lc)
							$lang[$lc] = $quality;
						$langc = array();
					}
				}
			}
			
			foreach($langc as $lc)
				$lang[$lc] = '0';
		}
		
		arsort($lang, SORT_NUMERIC);
		return $lang;
	}
	
	/** {@inheritdoc} */
	public function showBack()
	{
		$languageForm = new \Curry_Form_Dynamic(array(
			'legend' => 'Language',
			'elements' => array(
				'code' => array('text', array(
					'label' => 'Language code',
					'required' => true,
					'description' => 'The code identifying the language. For your browser, it should be one of the following: ' . implode(", ", array_keys($this->getLanguage())),
				)),
				'page_id' => array('select', array(
					'label' => 'Redirect to page',
					'required' => true,
					'multiOptions' => \PagePeer::getSelect(),
					'description' => 'When this language is detected from the users browser, the user will be redirected to this page.',
				)),
			),
		));
		
		$form = new \Curry_Form_MultiForm(array(
			'legend' => 'Languages',
			'cloneTarget' => $languageForm,
			'defaults' => $this->languages,
		));
		
		return $form;
	}
	
	/** {@inheritdoc} */
	public function saveBack(\Zend_Form_SubForm $form)
	{
		$values = $form->getValues(true);
		$this->languages = $values;
	}
}
