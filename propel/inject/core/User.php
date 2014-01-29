private static $user = false;
protected $pageAccess = null;
protected $isDummy = false;
const COOKIE_NAME = 'login';

/**
 * Get the currently logged in user.
 *
 * @return User
 */
public static function getUser()
{
	if(self::$user === false) {
		self::$user = null;
		self::login();
	}
	return self::$user;
}

public static function login()
{
	$setCookie = false;
	$redirect = false;
	$sessionManager = \Zend\Session\Container::getDefaultManager();
	$session = $sessionManager->sessionExists() ? new \Zend\Session\Container(__CLASS__) : null;

	if(isset($_GET['logout'])) {
		self::doLogout();
	} else if(!empty($_POST['login_username']) && isset($_POST['login_password'])) {
		// Login from POST
		self::$user = self::loginUser($_POST['login_username'], $_POST['login_password']);
		$setCookie = isset($_POST['login_remember']);
		if(isset($_POST['login_redirect']))
			$redirect = $_POST['login_redirect'];
	} else if($session && isset($session->token)) {
		// Login from session
		self::$user = self::loginUserFromToken($session->token);
		if(!self::$user)
			$session->unsetAll();
	} else if(!empty($_COOKIE[self::COOKIE_NAME])) {
		// Login from cookies
		self::$user = self::loginUserFromToken($_COOKIE[self::COOKIE_NAME]);
	} else if(!empty($_GET['logintoken'])) {
		// Login from get-vars
		self::$user = self::loginUserFromToken($_GET['logintoken']);
	}

	if(self::$user) {
		self::$user->setLoggedIn($setCookie);
		if($redirect)
			url($redirect)->redirect();
	}
}

public static function doLogout()
{
	self::$user = null;

	$sessionManager = \Zend\Session\Container::getDefaultManager();
	if($sessionManager->sessionExists()) {
		$session = new \Zend\Session\Container(__CLASS__);
		$session->exchangeArray(array()); // unset all
	}

	if(isset($_COOKIE[self::COOKIE_NAME]))
		setcookie(self::COOKIE_NAME, null, null, '/');
}

public function setLoggedIn($setCookie = false)
{
	// Store in session
	$session = new \Zend\Session\Container(__CLASS__);
	$session->token = $this->getLoginToken();

	// Create cookie
	if($setCookie) {
		$expiration = ($setCookie === true) ? Curry_Core::$config->curry->backend->loginTokenExpire : intval($setCookie);
		setcookie(self::COOKIE_NAME, $this->getLoginToken(), time() + $expiration);
	}
}

private static function loginUser($username, $password)
{
	$user = UserQuery::create()->findOneByName($username);
	if($user && $user->verifyPassword($password))
		return $user;
	return null;
}

private static function loginUserFromToken($token)
{
	$t = array();
	parse_str($token, $t);
	if (isset($t['e'], $t['v'], $t['d']) && time() < $t['e']) {
		$user = UserQuery::create()->findPk($t['v']);
		if($user && $user->getTokenDigest($t['v'], $t['e']) === $t['d']) {
			return $user;
		}
	}
	return null;
}

public function getLoginToken($expiration = null)
{
	if ($expiration === null)
		$expiration = Curry_Core::$config->curry->backend->loginTokenExpire;
	$expiration += time();
	$value = $this->getUserId();
	$digest = $this->getTokenDigest($value, $expiration);
	$data = array('e' => $expiration, 'v' => $value, 'd' => $digest);
	return http_build_query($data);
}

protected function getTokenDigest($value, $expiration)
{
	$data = http_build_query(array('e' => $expiration, 'v' => $value));
	return hash_hmac('sha1', $data, $this->getPassword());
}

protected function hashPassword($password)
{
	$options = Curry_Core::$config->curry->password->options->toArray();
	$algorithm = Curry_Core::$config->curry->password->algorithm;

	$hash = password_hash($password, $algorithm, $options);
	if ($hash === false)
		throw new Exception('Unable to create a password hash.');
	return $hash;
}

public function verifyPassword($password)
{
	$options = Curry_Core::$config->curry->password->options->toArray();
	$algorithm = Curry_Core::$config->curry->password->algorithm;

	if (password_verify($password, $this->getPassword())) {
		if (password_needs_rehash($this->getPassword(), $algorithm, $options)) {
			$hash = $this->hashPassword($password);
			$this->setPassword($hash);
			$this->save();
		}
		return true;
	}else{
		return false;
	}
}

public function setPlainPassword($password)
{
	$this->setPassword($this->hashPassword($password));
}

public function hasAccess($module, $allowAll = true)
{
	if ($this->isDummy)
		return true;
	$role = $this->getUserRole();
	if($role) {
		$accesses = $role->getUserRoleAccesss();
		foreach($accesses as $access) {
			if($allowAll && ($access->getModule() === "*"))
				return true;
			else if(strtolower($access->getModule()) === strtolower($module))
				return true;
		}
		$accesses->clearIterator();
	}
	return false;
}

public function hasPagePermission(Page $page, $permission = null)
{
	if ($this->isDummy) {
		if ($permission === null) {
			$perm = array();
			foreach(PageAccess::getPermissionTypes() as $colName => $phpName) {
				$perm[$colName] = true;
			}
			return $perm;
		}
		return true;
	}

	if(!$this->pageAccess) {
		$this->pageAccess = PageAccessQuery::create('pa')
			->filterByUser($this)
			->_or()
			->filterByUserRoleId($this->getUserRoleId())
			->_or()
			->where('pa.UserID IS NULL AND pa.UserRoleId IS NULL')
			->orderByCascade()
			->find();
	}

	$perm = array();
	foreach($this->pageAccess as $pageAccess) {
		$ancestor = $pageAccess->getPermSubpages() && $page->isDescendantOf($pageAccess->getPage());
		$direct = $pageAccess->getPageId() == $page->getPageId();
		if($ancestor || $direct)
			$perm = $pageAccess->getPermissions() + $perm;
	}
	$this->pageAccess->clearIterator();

	if($permission === null)
		return $perm;
	return $perm[$permission];
}

/**
 * Check if user has access to module, and if content visibility matches.
 *
 * @param Curry_PageModuleWrapper $wrapper
 * @return bool
 */
public function hasModuleAccess(Curry_PageModuleWrapper $wrapper)
{
	$anyAccess = $this->hasAccess('Curry_Backend_Content/*', false);
	$moduleClass = $wrapper->getPageModule()->getModuleClass();
	$moduleAccess = $this->hasAccess('Curry_Backend_Content/'.$moduleClass, false);

	if ($anyAccess || $moduleAccess) {
		switch ($wrapper->getPageModule()->getContentVisibility()) {
			case PageModulePeer::CONTENT_VISIBILITY_ALWAYS:
				return true;
			case PageModulePeer::CONTENT_VISIBILITY_NEVER:
				return false;
			case PageModulePeer::CONTENT_VISIBILITY_PAGE:
				return !$wrapper->isInherited();
			case PageModulePeer::CONTENT_VISIBILITY_SUBPAGES:
				return $wrapper->isInherited();
			case PageModulePeer::CONTENT_VISIBILITY_HAS_CONTENT:
				return $wrapper->hasData(false) || $wrapper->hasData(true);
			case PageModulePeer::CONTENT_VISIBILITY_HAS_LOCAL_CONTENT:
				return $wrapper->hasData(false);
			case PageModulePeer::CONTENT_VISIBILITY_HAS_INHERITED_CONTENT:
				return $wrapper->hasData(true);
			case PageModulePeer::CONTENT_VISIBILITY_IS_ENABLED:
				return $wrapper->getEnabled();
		}
	}
	return false;
}

public static function dummyAuth()
{
	$role = new UserRole();
	$role->setName("DummyRole");
	$role->setNew(false);

	$user = new User();
	$user->setName("Dummy");
	$user->setUserRole($role);
	$user->setNew(false);
	$user->isDummy = true;
	self::$user = $user;
}