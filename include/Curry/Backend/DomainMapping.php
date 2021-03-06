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
 * Creates mapping between domains and pages.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_DomainMapping extends \Curry\Backend\AbstractLegacyBackend {
	/** {@inheritdoc} */
	public function getName()
	{
		return "Domain mapping";
	}

	/** {@inheritdoc} */
	public function getGroup()
	{
		return "System";
	}

	/** {@inheritdoc} */
	public function showMain()
	{

		if(!is_writable($this->app['configPath']))
			$this->addMessage("Configuration file doesn't seem to be writable.", self::MSG_ERROR);
			
		$config = $this->app->openConfiguration();

		$pages = PagePeer::getSelect();

		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
		    'method' => 'post',
		    'elements' => array(
				'enabled' => array('checkbox', array(
					'label' => 'Enable domain mapping',
					'value' => $config->domainMapping->enabled,
				)),
				'default_base_page' => array('select', array(
					'label' => 'Default base page',
					'description' => 'The default base page will only be used if there are no other domains matching and domain mapping is enabled',
					'value' => $config->domainMapping->default,
					'multiOptions' => array('' => '[ None ]') + $pages,
				)),
			)
		));

		$domainForm = new Curry_Form_Dynamic(array(
			'legend' => 'Domain',
			'elements' => array(
				'domain' => array('text', array(
					'label' => 'Domain',
					'description' => 'You can use default as a wildcard to fetch unmatched domains.',
					'required' => true,
				)),
				'base_page' => array('select', array(
					'label' => 'Base page',
					'multiOptions' => array('' => '[ None ]') + $pages,
					'required' => true,
				)),
				'include_www' => array('checkbox',array(
					'label' => 'Include www'
				))
			),
		));
		
		$form->addSubForm(new Curry_Form_MultiForm(array(
			'legend' => '',
			'cloneTarget' => $domainForm,
			'defaults' => $config->domainMapping->domains ? $config->domainMapping->domains->toArray() : array(),
		)),'domainMapping');

		$form->addElement('submit', 'save', array('label' => 'Save'));

		if (isPost() && $form->isValid($_POST)) {
			$values = $form->getValues();
			
			if(!$config->domainMapping)
				$config->domainMapping = array();

			$config->domainMapping->enabled = count($values['domainMapping']) ? (bool)$values['enabled'] : false;
			$config->domainMapping->default = $values['default_base_page'];
			$config->domainMapping->domains = $values['domainMapping'];

	
			try {
				$this->app->writeConfiguration($config);
				$this->addMessage("Settings saved.", self::MSG_SUCCESS);
			}
			catch (Exception $e) {
				$this->addMessage($e->getMessage(), self::MSG_ERROR);
			}
		}

		$this->addMainContent($form);
	}
}
