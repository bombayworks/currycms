<?php

namespace Curry\Tests;

class URLTest extends \PHPUnit_Framework_TestCase {

	const TEST_URL = 'http://user:password@currycms.com:80/path?foo=bar#fragment';

	public function testUrlFull()
	{
		$url = new \Curry_URL(self::TEST_URL);
		$this->assertEquals('http', $url->getScheme());
		$this->assertEquals('user', $url->getUser());
		$this->assertEquals('password', $url->getPassword());
		$this->assertEquals('currycms.com', $url->getHost());
		$this->assertEquals('80', $url->getPort());
		$this->assertEquals('/path', $url->getPath());
		$this->assertEquals('foo=bar', $url->getQueryString());
		$this->assertEquals('fragment', $url->getFragment());
		$this->assertEquals(array('foo'=>'bar'), $url->getVars());
	}

	public function testUris()
	{
		\Curry_URL::setDefaultBaseUrl('');
		$uris = array(
			'ftp://ftp.is.co.za/rfc/rfc1808.txt',
			'http://www.ietf.org/rfc/rfc2396.txt',
			'ldap://[2001:db8::7]/c=GB?objectClass?one',
			'mailto:John.Doe@example.com',
			'news:comp.infosystems.www.servers.unix',
			'tel:+1-816-555-1212',
			'telnet://192.0.2.16:80/',
			'urn:oasis:names:specification:docbook:dtd:xml:4.1.2',
		);
		foreach($uris as $uri) {
			$url = new \Curry_URL($uri);
			$this->assertEquals($uri, (string)$url);
		}
	}

	public function testUrlHost()
	{
		$url = new \Curry_URL('//hostname/');
		$this->assertEquals('hostname', $url->getHost());
	}

	public function testUrlPath()
	{
		$url = new \Curry_URL('/path/to/file');
		$this->assertEquals('/path/to/file', $url->getPath());
	}

	public function testUrlRelativePath()
	{
		$url = new \Curry_URL('path/to/file');
		$this->assertEquals('path/to/file', $url->getPath());
	}

	public function testUrlBaseRelativePath()
	{
		$relative = 'path/to/file';
		$baseUrl = 'http://domain.com/sub/';
		$defaultBaseUrl = 'http://localhost/project/';

		\Curry_URL::setDefaultBaseUrl($defaultBaseUrl);

		// No base specified (should use default)
		$url = new \Curry_URL($relative);
		$this->assertEquals('/project/'.$relative, $url->getRelative());
		$this->assertEquals($defaultBaseUrl.$relative, $url->getAbsolute());

		// Base specified on instance
		$url = new \Curry_URL('path/to/file');
		$url->setBaseUrl($baseUrl);
		$this->assertEquals('/sub/'.$relative, $url->getRelative());
		$this->assertEquals($baseUrl.$relative, $url->getAbsolute());
	}

	public function testUrlScriptRelativePath()
	{
		$_SERVER['REQUEST_URI'] = '/test/index.php';
		$url = new \Curry_URL('~/path/to/file');
		$this->assertEquals('/test/path/to/file', $url->getRelative());
	}

	public function testQueryStringEncoding()
	{
		// Test encoding of utf-8 characters
		$vars = array('☃' => '☀');
		$encoded = '%E2%98%83=%E2%98%80';

		$url = new \Curry_URL();
		$url->add($vars);
		$this->assertEquals($encoded, $url->getQueryString());

		$url = new \Curry_URL('?'.$encoded);
		$this->assertEquals($vars, $url->getVars());
	}

	public function testPathEncoding()
	{
		$unencoded = '/☀';
		$encoded = '/%E2%98%80';

		$url = new \Curry_URL();
		$url->setPath($unencoded);
		$this->assertEquals($encoded, $url->getRelative());

		$url->setUrl($encoded);
		$this->assertEquals($unencoded, $url->getPath());
	}

	public function testSecure()
	{
		$secret = sha1(self::TEST_URL);
		$url = new \Curry_URL(self::TEST_URL);
		$secureUrl = $url->getAbsolute('&', $secret);

		$url = new \Curry_URL($secureUrl);
		$this->assertNotEmpty($url->getVar('hash'));
		$this->assertTrue($url->isValid($secret));

		\Curry_URL::setDefaultSecret(sha1($secret));
		$url = new \Curry_URL(self::TEST_URL);
		$secureUrl = $url->getAbsolute('&', true);

		$url = new \Curry_URL($secureUrl);
		$this->assertNotEmpty($url->getVar('hash'));
		$this->assertTrue($url->isValid());
	}

	public function testAbsolute()
	{
		$url = new \Curry_URL(self::TEST_URL);
		$this->assertEquals(self::TEST_URL, $url->getAbsolute());
	}

	public function testToString()
	{
		$url = new \Curry_URL(self::TEST_URL);
		$this->assertEquals(self::TEST_URL, (string)$url);
	}

	public function testEmpty()
	{
		$url = new \Curry_URL();
		$this->assertEquals('', $url->getScheme());
		$this->assertEquals('', $url->getUser());
		$this->assertEquals('', $url->getPassword());
		$this->assertEquals('', $url->getHost());
		$this->assertEquals('', $url->getPort());
		$this->assertEquals('', $url->getPath());
		$this->assertEquals('', $url->getQueryString());
		$this->assertEquals('', $url->getFragment());
		$this->assertEquals(array(), $url->getVars());
	}

	public function testSetters()
	{
		$url = new \Curry_URL();
		$url->setScheme('http')
			->setUser('user')
			->setPassword('password')
			->setHost('currycms.com')
			->setPort('80')
			->setPath('/path')
			->setQueryString('foo=bar')
			->setFragment('fragment');
		$this->assertEquals(self::TEST_URL, (string)$url);
	}

	public function testQueryString()
	{
		$varString = "foobar";
		$varArray = array($varString => '');

		$url = new \Curry_URL();
		$url->setQueryString($varString);
		$this->assertEquals($varString, $url->getQueryString());
		$this->assertEquals($varArray, $url->getVars());
	}

	public function testQueryStringKeyValue()
	{
		$varArray = array('foo' => 'bar', 'baz' => 'qux');
		$varString = "foo=bar&baz=qux";

		// Set query string using array
		$url = new \Curry_URL();
		$url->setQueryString($varArray);
		$this->assertEquals($varArray, $url->getVars());
		$this->assertEquals($varString, $url->getQueryString());

		// Set query string using string
		$url = new \Curry_URL();
		$url->setQueryString($varString);
		$this->assertEquals($varArray, $url->getVars());
		$this->assertEquals($varString, $url->getQueryString());
	}

	public function testNoScheme()
	{
		$url = new \Curry_URL(str_replace('http:', '', self::TEST_URL));
		$this->assertEquals('', $url->getScheme());
	}
}