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
use Curry\Backend\AbstractLegacyBackend;
use Curry\Module\AbstractModule;
use Curry\Util\ArrayHelper;

/**
 * Manage users and user permissions.
 * 
 * @package Curry\Backend
 */
class Curry_Backend_Users extends AbstractLegacyBackend
{
	const PERMISSION_USERS = 'Users';
	const PERMISSION_ROLES = 'Roles';
	const PERMISSION_FILEACCESS = 'FileAccess';

	/** {@inheritdoc} */
	public function getGroup()
	{
		return "Accounts";
	}

	public function getName()
	{
		return "Users and roles";
	}

	public function getPermissions()
	{
		return array(
			self::PERMISSION_USERS,
			self::PERMISSION_ROLES,
			self::PERMISSION_FILEACCESS,
		);
	}

	protected function getViews()
	{
		$all = array_combine(self::getPermissions(), array('Users','Roles','File permissions'));
		$access = array();
		foreach($all as $view => $name) {
			if ($this->hasPermission($view))
				$access[$view] = $name;
		}
		return $access;
	}

	protected function addMenu()
	{
		foreach($this->getViews() as $view => $name)
			$this->addMenuItem($name, url('', array('module','view'=>$view)));
	}

	/** {@inheritdoc} */
	public function showMain()
	{
		$views = $this->getViews();
		if (!count($views))
			throw new Exception('You don\'t have access to any user/role settings.');

		// Redirect to first view
		reset($views);
		list($view, $name) = each($views);
		url('', array('module','view'=>$view))->redirect();
	}

	public function showUsers()
	{
		if (!$this->hasPermission(self::PERMISSION_USERS))
			throw new Exception('You dont have permission to access this view.');

		$this->addMenu();
		$q = UserQuery::create()
			->leftJoinUserRole()
			->withColumn('UserRole.Name', 'role');
		$list = new Curry_ModelView_List($q, array(
			'actions' => array(
				'edit' => array(
					'href' => (string)url('', array('module','view'=>'User')),
				),
				'new' => array(
					'href' => (string)url('', array('module','view'=>'User')),
				),
				'delete' => array(
					'href' => (string)url('', array('module','view'=>'DeleteUser')),
				),
			)
		));
		$list->removeColumn('password');
		$list->removeColumn('salt');
		if ($this->hasPermission(self::PERMISSION_FILEACCESS)) {
			$list->addAction('file_permissions', array(
				'action' => $this->getFileAccessList(),
				'class' => 'inline',
				'single' => true,
			));
		}
		$this->addMainContent($list);
	}

	public function showRoles()
	{
		if (!$this->hasPermission(self::PERMISSION_ROLES))
			throw new Exception('You dont have permission to access this view.');

		$this->addMenu();

		$user = User::getUser();
		$backendModules = AbstractLegacyBackend::getBackendList();

		$disable = array();
		$backend = array("*" => "All");
		if (!$user->hasAccess('*'))
			$disable[] = '*';
		foreach($backendModules as $backendClass => $backendName) {
			$backend[$backendClass] = $backendName;
			$permissions = method_exists($backendClass, 'getPermissions') ? call_user_func(array($backendClass, 'getPermissions')) : array();
			foreach($permissions as $permission) {
				$backend[$backendClass."/".$permission] = Curry_Core::SELECT_TREE_PREFIX . $permission;
				if (!$user->hasAccess($backendClass."/".$permission))
					$disable[] = $backendClass."/".$permission;
			}
			if (!$user->hasAccess($backendClass))
				$disable[] = $backendClass;
		}

		$content = array();
		$contentAccess = array("*" => "All") + AbstractModule::getModuleList();
		$allContentAccess = $user->hasAccess('Curry_Backend_Content/*');
		foreach($contentAccess as $k => $v) {
			$content['Curry_Backend_Content/'.$k] = $v;
			if (!$allContentAccess && !$user->hasAccess('Curry_Backend_Content/'.$k))
				$disable[] = 'Curry_Backend_Content/'.$k;
		}

		$form = new Curry_ModelView_Form('UserRole', array(
			'elements' => array(
				'backend' => array('multiselect', array(
					'label' => 'Curry\Controller\Backend access',
					'multiOptions' => $backend,
					'size' => 10,
					'order' => 1,
					'disable' => $disable,
					'validators' => array(
						array('InArray', true, array(array_diff(array_keys($backend), $disable))),
					),
				)),
				'content' => array('multiselect', array(
					'label' => 'Content access',
					'multiOptions' => $content,
					'size' => 10,
					'order' => 2,
					'disable' => $disable,
					'validators' => array(
						array('InArray', true, array(array_diff(array_keys($content), $disable))),
					),
				)),
			),
			'onFillForm' => function(UserRole $role, $form) {
				$access = UserRoleAccessQuery::create()
					->filterByUserRole($role)
					->select('Module')
					->find()
					->getArrayCopy();
				$form->backend->setValue($access);
				$form->content->setValue($access);
			},
			'onFillModel' => function(UserRole $role, $form, $values) {
				$access = array_merge((array)$values['backend'], (array)$values['content']);
				$collection = new PropelObjectCollection();
				$collection->setModel('UserRoleAccess');
				foreach($access as $a) {
					$ura = new UserRoleAccess();
					$ura->setModule($a);
					$collection->append($ura);
				}
				$role->setUserRoleAccesss($collection);
			},
		));

		$q = UserRoleQuery::create();
		$list = new Curry_ModelView_List($q, array(
			'modelForm' => $form,
		));
		$list->addAction('file_permissions', array(
			'action' => $this->getFileAccessList(),
			'class' => 'inline',
			'single' => true,
		));
		$this->addMainContent($list);
	}

	public function showFileAccess()
	{
		if (!$this->hasPermission(self::PERMISSION_FILEACCESS))
			throw new Exception('You dont have permission to access this view.');

		$this->addMenu();
		$q = FilebrowserAccessQuery::create()
			->joinWith('User', Criteria::LEFT_JOIN)
			->joinWith('UserRole', Criteria::LEFT_JOIN);

		$roles = array();
		foreach(UserRoleQuery::create()->find()->toKeyValue() as $k => $v)
			$roles['r'.$k] = $v;

		$formOptions = array(
			'elements' => array(
				'who' => array('select', array(
					'label' => 'Who',
					'order' => 3,
					'multiOptions' => array(
						'' => '[ Everyone ]',
						'Users' => UserQuery::create()->find()->toKeyValue(),
						'Role' => $roles,
					),
				)),
			),
			'onFillForm' => function(FilebrowserAccess $fa, $form) {
				$val = $fa->getUserRoleId() ? 'r'.$fa->getUserRoleId()
					: ($fa->getUserId() ? $fa->getUserId() : '');
				$form->who->setValue($val);
			},
			'onFillModel' => function(FilebrowserAccess $fa, $form, $values) {
				$val = $values['who'];
				if (!$val) {
					$fa->setUserId(null);
					$fa->setUserRoleId(null);
				} else if (substr($val, 0, 1) == 'r') {
					$fa->setUserRoleId(substr($val, 1));
				} else {
					$fa->setUserId($val);
				}
			},
		);
		$listOptions = array(
			'columns' => array(
				'owner' => array(
					'label' => 'Who',
					'callback' => function(FilebrowserAccess $fa) {
						$owner = array();
						if ($fa->getUserId())
							$owner[] = 'User: '.$fa->getUser()->getName();
						if ($fa->getUserRoleId())
							$owner[] = 'Role: '.$fa->getUserRole()->getName();
						if (!count($owner))
							return '[ Everyone ]';
						return join(' / ', $owner);
					},
				),
			),
		);
		$list = $this->getFileAccessList($q, $formOptions, $listOptions);
		$this->addMainContent($list);
	}

	protected function getValidRoles()
	{
		$user = User::getUser();
		$roles = array();
		foreach(UserRoleQuery::create()->find() as $role) {
			foreach(UserRoleAccessQuery::create()->filterByUserRole($role)->find() as $access) {
				if (!$user->hasAccess($access->getModule()))
					continue 2;
			}
			$roles[$role->getUserRoleId()] = $role;
		}
		return $roles;
	}

	protected function getFileAccessList($query = null, $formOptions = array(), $listOptions = array())
	{
		if (!$query)
			$query = FilebrowserAccessQuery::create();

		$o = array(
			'columnElements' => array(
				'path' => array('filebrowser', array(
					'filebrowserOptions' => array(
						'local' => false,
					),
					'finderOptions' => array(
						'type' => 'folder',
					),
					'allowEmpty' => false,
					'validators' => array(
						array('callback', false, array('callback' => array($this, 'validatePath'))),
					),
				)),
				'write' => array('select', array(
					'label' => 'Access',
					'multiOptions' => array(
						'' => 'Read',
						'1' => 'Read / Write',
					),
				))
			),
		);
		ArrayHelper::extend($o, $formOptions);

		$modelForm = new Curry_Form_ModelForm('FilebrowserAccess', $o);
		$modelForm->path->getValidator('callback')->setMessage("Invalid permissions to path '%value%'");
		$form = new Curry_ModelView_Form($modelForm);

		$o = array(
			'title' => 'File permissions',
			'modelForm' => $form,
			'columns' => array(
				'write' => array(
					'label' => 'Access',
					'display' => '{{Write?"Read / Write":"Read"}}',
				),
			)
		);
		ArrayHelper::extend($o, $listOptions);
		$list = new Curry_ModelView_List($query, $o);

		return $list;
	}

	public function validatePath($path, $values)
	{
		$virtual = Curry_Backend_FileBrowser::publicToVirtual($path);
		if (!$virtual)
			return false;
		$physical = Curry_Backend_FileBrowser::virtualToPhysical($virtual);
		if (!$physical)
			return false;
		if ($values['write'] && !Curry_Backend_FileBrowser::isPhysicalWritable($physical))
			return false;
		return true;
	}

	public function showDeleteUser()
	{
		if (!$this->hasPermission(self::PERMISSION_USERS))
			throw new Exception('You dont have permission to access this view.');

		$user = isset($_GET['item']) ? UserQuery::create()->findPk($_GET['item']) : null;
		if (!$user)
			throw new Exception('User not found.');

		$validRoles = $this->getValidRoles();
		if (!array_key_exists($user->getUserRoleId(), $validRoles)) {
			$this->addMessage('You dont have access to delete users with that role.', self::MSG_ERROR);
			return;
		}

		$name = method_exists($user, '__toString') ? '`'.htmlspecialchars((string)$user).'`' : 'this item';
		if(isPost() && $_POST['do_delete']) {
			$user->delete();
			$this->createModelUpdateEvent('User', $user->getPrimaryKey(), 'delete');
			$this->addMainContent('<p>'.$name.' has been deleted.</p>');
		} else {
			// Show delete form
			$this->addMainContent('<form method="post" action="'.url('', $_GET).'">'.
				'<input type="hidden" name="do_delete" value="1" />'.
				'<p>Do you really want to delete '.$name.'?</p>'.
				'<button type="submit" class="btn btn-danger">Delete</button>'.
				'</form>');
		}
	}

	/**
	 * This is partial-html used for the popup when creating or editing a user.
	 */
	public function showUser()
	{
		if (!$this->hasPermission(self::PERMISSION_USERS))
			throw new Exception('You dont have permission to access this view.');

		$user = isset($_GET['item']) ? UserQuery::create()->findPk($_GET['item']) : null;

		$validRoles = $this->getValidRoles();
		if ($user && !array_key_exists($user->getUserRoleId(), $validRoles)) {
			$this->addMessage('You dont have access to users with that role.', self::MSG_ERROR);
			return;
		}
		
		// create new if user not set
		if(!$user)
			$user = new User();
		
		// Validate
		$form = $this->getUserForm($user);
		$form->fillForm($user);
		if (isPost() && $form->isValid($_POST)) {
			$this->createModelUpdateEvent(get_class($user), $user->getPrimaryKey(), $user->isNew() ? 'insert' : 'update');
			$this->saveUser($user, $form);
			if (isAjax())
				self::returnPartial('');
		}
		
		// Render
		$this->addMainContent($form);
	}

	protected static function getUserHome(User $user, $create = false)
	{
		if ($user->isNew())
			return null;
		$folder = 'user-content/'.$user->getUserId().'/';
		$q = FilebrowserAccessQuery::create()
			->filterByUser($user)
			->filterByName('Home')
			->filterByPath($folder);
		return $create ? $q->findOneOrCreate() : $q->findOne();
	}
	
	/**
	 * Get user form.
	 *
	 * @param User $user
	 * @return Curry_Form_ModelForm
	 */
	protected function getUserForm(User $user)
	{
		$createHomeFolder = $user->isNew() || self::getUserHome($user) !== null;

		$validRoles = $this->getValidRoles();
		$invalidRoles = UserRoleQuery::create()
			->filterByUserRoleId(array_keys($validRoles), Criteria::NOT_IN)
			->select('UserRoleId')
			->find()
			->getArrayCopy();

		$validLanguages = UserLanguageQuery::create()
			->_if(!$user->hasAccess('*'))
				->filterByUser(User::getUser())
			->_endif()
			->select('langcode')
			->find()->getArrayCopy();
		$invalidLanguages = LanguageQuery::create()
			->filterByLangcode($validLanguages, Criteria::NOT_IN)
			->select('langcode')
			->find()
			->getArrayCopy();

		$form = new Curry_Form_ModelForm('User', array(
			'method' => 'post',
			'action' => url('', $_GET),
			'columnElements' => array(
				'password' => array('password', array(
					'required' => $user->isNew(),
				)),
				'relation__userrole' => array('select', array(
					'label' => 'Role',
					'disable' => $invalidRoles,
					'validators' => array(
						array('InArray', true, array(array_keys($validRoles))),
					),
				)),
				'relation__language' => array('multiselect', array(
					'class' => 'chosen',
					'disable' => $invalidLanguages,
					'validators' => array(
						array('InArray', true, array($validLanguages)),
					),
				)),
			),
		));
		$form->addElements(array(
			'verify_password' => array('password', array(
				'label' => 'Verify password',
				'required' => $user->isNew(),
				'validators' => array(
					array('identical', false, array('token' => 'password')),
				),
			)),
			'create_home_folder' => array('checkbox', array(
				'label' => 'Create home folder',
				'description' => 'Creates a folder in /user-content/ for the user and adds file permission for it.',
				'value' => $createHomeFolder,
				'order' => 5,
			)),
		));
		$form->withRelation('UserRole');
		$form->withRelation('Language');
		$form->addElement('submit', 'save', array('label' => 'Save'));
		return $form;
	}

	/**
	 * Save user.
	 *
	 * @param User $user
	 * @param Curry_Form_ModelForm $form
	 */
	protected function saveUser(User $user, Curry_Form_ModelForm $form)
	{
		$values = $form->getValues();
		$password = $values['password'];
		if ($password || $user->isNew())
			$user->setPlainPassword($password);
		$form->removeElement('password');
		$form->fillModel($user);
		$user->save();

		$home = self::getUserHome($user, true);
		if($values['create_home_folder']) {
			$folder = $this->app->config->curry->wwwPath . DIRECTORY_SEPARATOR;
			$folder .= str_replace('/', DIRECTORY_SEPARATOR, rtrim($home->getPath(), '/'));
			if (!file_exists($folder))
				@mkdir($folder, 0777, true);
			if ($home->isNew()) {
				$home->setWrite(true);
				$home->save();
			}
		} else if($home && !$home->isNew()) {
			$home->delete();
		}
	}
}
