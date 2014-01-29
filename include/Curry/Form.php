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
 * Adds curry paths to Zend_Form.
 * 
 * @package Curry\Form
 *
 */
class Curry_Form extends Zend_Form
{
	/**
	 * Unique identifier for this form.
	 * 
	 * @var string
	 */
	protected $uniqueId = "";

	/**
	 * Do automatic CSRF check?
	 *
	 * @var bool
	 */
	protected $csrfCheck = true;
	
	/**
	 * Constructor
	 *
	 * @param mixed $options
	 */
	public function __construct($options = null)
	{
		$this->addPrefixPaths(array( array('prefix' => 'Curry_Form', 'path' => 'Curry/Form/') ));
		parent::__construct($options);
		
		// Need a unique identifier to generate salt for multiple forms on the same page
		$bt = debug_backtrace();
		$this->uniqueId = substr(sha1($bt[0]['file'].':'.$bt[0]['line']), 0, 6);
	}

	/**
	 * Perform automatic CSRF check?
	 *
	 * @return bool
	 */
	public function getCsrfCheck() {
		return $this->csrfCheck;
	}

	/**
	 * Perform automatic CSRF check?
	 *
	 * @param bool $value
	 */
	public function setCsrfCheck($value) {
		$this->csrfCheck = $value;
	}
	
	/** @inheritdoc */
	public function isValid($data)
	{
		if($this->csrfCheck)
			$this->addCsrfProtection();
		return parent::isValid($data);
	}
	
	/** @inheritdoc */
	public function getView()
	{
		if (null === $this->_view) {
			$viewRenderer = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
			if (!$viewRenderer->view) {
				$view = new Zend_View();
				$view->doctype('XHTML1_STRICT');
				$viewRenderer->setView($view);
			}
		}
		return parent::getView();
	}
	
	/** @inheritdoc */
	public function render(Zend_View_Interface $view = null)
	{
		if($this->csrfCheck)
			$this->addCsrfProtection();
		return parent::render($view);
	}
	
	/**
	 * Add Zend_Form_Element_Hash element to form for CSRF protection.
	 */
	public function addCsrfProtection()
	{
		if($this->getMethod() == "post" && isset($this->_elements['csrf']) == false){
			$salt = md5($this->getAction().$this->uniqueId.Curry_Core::$config->curry->secret);
			$this->addElement('hash','csrf',array(
				'timeout' => 3600,
				'salt' => $salt,
				'session' => new \Zend\Session\Container(__CLASS__.'_'.$salt.'_csrf'),
			));
		}
	}
}
