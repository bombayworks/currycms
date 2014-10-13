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
 * Manage users and user permissions.
 * 
 * @package Curry\Controller\Backend
 *
 */
class Curry_Backend_Profile extends \Curry\AbstractLegacyBackend
{
	/** {@inheritdoc} */
	public function getGroup()
	{
		return "Accounts";
	}
	
	/** {@inheritdoc} */
	public function showMain()
	{
		$user = User::getUser();
		if(!$user || $user->isNew())
			return;
		
		$form = $this->getForm($user);
		if (isPost() && $form->isValid($_POST)) {
			$this->saveUser($user, $form->getValues());
		}
		
		$this->addMainContent($form);
	}
	
	/**
	 * Profile form.
	 *
	 * @param User $user
	 * @return Curry_Form
	 */
	private function getForm(User $user)
	{
		$form = new Curry_Form(array(
			'action' => url('', array("module","view")),
			'method' => 'post',
			'elements' => array(
				'name' => array('text', array(
					'label' => 'Username',
					'required' => true,
					'value' => $user->getName()
				)),
				'old_password' => array('password', array(
					'label' => 'Password',
					'description' => 'You can leave this blank if you dont wish to change password.',
				)),
				'password' => array('password', array(
					'label' => 'New password',
				)),
				'password_confirm' => array('password', array(
					'label' => 'Confirm password',
				)),
				'save' => array('submit', array(
					'label' => 'Save',
				)),
			)
		));
		
		if(isPost() && ($_POST['old_password'] || $_POST['password'] || $_POST['password_confirm'])) {
			$form->old_password->setRequired(true);
			$form->password->setRequired(true);
			$form->password_confirm->setRequired(true);
			
			$form->old_password->addValidator(new Curry_Validate_Password($user));
			
			$identical = new Zend_Validate_Identical($_POST['password']);
			$identical->setMessage('Passwords do not match.');
			$form->password_confirm->addValidator($identical);
		}
		
		return $form;
	}
	
	/**
	 * Save profile form.
	 *
	 * @param User $user
	 * @param array $values
	 */
	public function saveUser(User $user, array $values)
	{
		$user->setName($values['name']);
		// change password?
		if(strlen($values['password'])) {
			$user->setPlainPassword($values['password']);
			$this->addMessage('Password has been changed.', self::MSG_SUCCESS);
			$user->setLoggedIn();
		}
		$user->save();
	}
}

/**
 * @ignore
 */
class Curry_Validate_Password extends Zend_Validate_Abstract
{
	const NOT_MATCH = 'notMatch';

	protected $_messageTemplates = array(
		self::NOT_MATCH => 'Password is invalid'
	);
	
	protected $_user;
	
	public function __construct(User $user = null)
	{
		if (null !== $user) {
			$this->setUser($user);
		}
	}
	
	public function setUser(User $user)
	{
		$this->_user = $user;
		return $this;
	}
	
	public function getUser()
	{
		return $this->_user;
	}

	public function isValid($value, $context = null)
	{
		$value = (string) $value;
		$this->_setValue($value);
		
		if ($this->_user->verifyPassword($value))
			return true;

		$this->_error(self::NOT_MATCH);
		return false;
	}
}
