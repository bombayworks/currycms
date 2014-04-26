<?php

namespace Curry\Tests;

class CmsTest extends \PHPUnit_Framework_TestCase {
	static protected $fixturesDirectory;

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		self::$fixturesDirectory = realpath(dirname(dirname(__DIR__)).'/fixtures');
		\Curry_Core::init(array (
				'curry' =>
				array (
					'name' => 'Curry Unit Tests',
					'adminEmail' => 'info@currycms.com',
					'autoBackup' => false,
					'projectPath' => self::$fixturesDirectory,
					'migrationVersion' => \Curry_Core::MIGRATION_VERSION,
					'template' => array(
						'root' => self::$fixturesDirectory.'/templates',
						'options' => array(
							'cache' => false,
						),
					),
					'propel' => array(
						'conf' => self::$fixturesDirectory.'/propel/build/conf/curry-conf.php',
						'projectClassPath' => self::$fixturesDirectory.'/propel/build/classes',
					),
				),
			));

		// Empty database
		$con = \Propel::getConnection();
		$con->beginTransaction();
		try {
			foreach(\Curry_Propel::getModels(false) as $model)
				\PropelQuery::from($model)->deleteAll();
			$con->commit();
		}
		catch (Exception $e) {
			$con->rollBack();
			throw $e;
		}

		$setup = new \Curry_Backend_Setup();
		$setup->saveConfiguration(array(
				'template' => 'empty',
				'admin' => array(
					'username' => 'admin',
					'password' => 'admin',
				),
				'user' => array(
					'username' => 'user',
					'password' => 'user',
				)
			));
	}

	protected static function createRequest($method, $uri, $get = array(), $post = array(), $cookie = array(), $env = array())
	{
		$request = new \Curry_Request($method, $uri);
		$request->addParamSource('cookie', $cookie);
		$request->addParamSource('post', $post);
		$request->addParamSource('get', $get);
		$request->addParamSource('env', $env);
		return $request;
	}

	protected static function setupRequest($method, $uri, $get = array(), $post = array(), $cookie = array(), $env = array())
	{
		$_SERVER['HTTP_METHOD'] = strtoupper($method);
		$_SERVER['REQUEST_URI'] = $uri;
		$_GET = $get;
		$_POST = $post;
		$_COOKIE = $cookie;
		$_ENV = $env;
	}

	protected static function handleRequest(\Curry_Request $request)
	{
		ob_start();
		$app = \Curry\Controller\Frontend::getInstance();
		$app->handle($request);
		return ob_get_clean();
	}

	public function testPropelInitialized()
	{
		$this->assertTrue(class_exists('Page'));
	}

	public function testFrontPage()
	{
		$this->assertEquals('<!DOCTYPE html>
<html>
<head>
  <title>Home</title>
</head>
<body>
</body>
</html>', self::handleRequest(self::createRequest('get', '/')));
	}

	public function testBackend()
	{
		ob_start();
		self::setupRequest('post', '/admin.php', array(), array('login_username' => 'admin', 'login_password' => 'admin'));
		$admin = \Curry_Admin::getInstance();
		$admin->show();
		$content = ob_get_clean();
		$this->assertNotEmpty($content);
	}

}