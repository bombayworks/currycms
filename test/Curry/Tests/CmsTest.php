<?php

namespace Curry\Tests;

use Curry\App;
use Symfony\Component\HttpFoundation\Request;

class CmsTest extends \PHPUnit_Framework_TestCase {
	static protected $fixturesDirectory;

	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();

		self::$fixturesDirectory = realpath(dirname(dirname(__DIR__)).'/fixtures');
		$app = App::create(array (
				'curry' =>
				array (
					'name' => 'Curry Unit Tests',
					'adminEmail' => 'info@currycms.com',
					'cache' => array('method' => 'none'),
					'pageCache' => false,
					'autoBackup' => false,
					'projectPath' => self::$fixturesDirectory,
					'migrationVersion' => App::MIGRATION_VERSION,
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
		$app->boot();

		// Empty database
		$con = \Propel::getConnection();
		$con->beginTransaction();
		try {
			foreach(\Curry\Util\Propel::getModels(false) as $model)
				\PropelQuery::from($model)->deleteAll();
			$con->commit();
		}
		catch (\Exception $e) {
			$con->rollBack();
			throw $e;
		}

		$setup = new \Curry_Backend_Setup($app);
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
		$app->cache->clean();
	}

	protected static function handleRequest(Request $request)
	{
		return App::getInstance()->handle($request);
	}

	public function testPropelInitialized()
	{
		$this->assertTrue(class_exists('Page'));
	}

	public function testFrontPage()
	{
		$response = self::handleRequest(Request::create('/'));
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertEquals('<!DOCTYPE html>
<html>
<head>
  <title>Home</title>
</head>
<body>
</body>
</html>', $response->getContent());
	}

	public function testBackend()
	{
		$response = self::handleRequest(Request::create('/admin/', 'POST', array('login_username' => 'admin', 'login_password' => 'admin')));
		$this->assertEquals(200, $response->getStatusCode());
		$this->assertNotEmpty($response->getContent());
	}

}