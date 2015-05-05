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
namespace Curry;

/**
 * Class to create/build URLs.
 * 
 * scheme://username:password@host:port/path?query_string#fragment
 *
 * @package Curry
 */
class URL {
	/**
	 * Scheme part of URL.
	 *
	 * @var string
	 */
	protected $scheme;
	
	/**
	 * Host part of URL.
	 *
	 * @var string
	 */
	protected $host;
	
	/**
	 * Port part of URL.
	 *
	 * @var integer|null
	 */
	protected $port;
	
	/**
	 * User part of URL (basic auth).
	 *
	 * @var string
	 */
	protected $user;
	
	/**
	 * Password part of URL (basic auth).
	 *
	 * @var string
	 */
	protected $password;
	
	/**
	 * Path part of URL.
	 *
	 * @var string
	 */
	protected $path;
	
	/**
	 * Query string part of URL.
	 *
	 * @var array|string
	 */
	protected $queryString;
	
	/**
	 * Fragment part of URL.
	 *
	 * @var string
	 */
	protected $fragment;
	
	/**
	 * Use reverse routing on this URL.
	 *
	 * @var bool
	 */
	protected $reverseRoute = true;
	
	/**
	 * Callback function to run when reverse-routing.
	 *
	 * @var callback|null
	 */
	protected static $reverseRouteCallback;
	
	/**
	 * Base URL info.
	 *
	 * @var null|array
	 */
	protected $baseUrl = null;

	/**
	 * Default Base URL info.
	 *
	 * @var null|array
	 */
	protected static $defaultBaseUrl = null;

	/**
	 * Default secret used when creating and validating HMAC.
	 *
	 * @var string
	 */
	protected static $defaultSecret = '';

	/**
	 * Helper function for constructor.
	 *
	 * @param string $url
	 * @return URL
	 */
	public static function create($url = "", array $vars = array()) {
		$u = new URL($url);
		$u->add($vars);
		return $u;
	}

	/**
	 * Create URL object from URL string.
	 *
	 * @param string $url
	 */
	public function __construct($url = "") {
		$this->setUrl($url);
	}

	/**
	 * Set the default secret to use when creating secured URLs.
	 *
	 * @param string $defaultSecret
	 */
	public static function setDefaultSecret($defaultSecret)
	{
		self::$defaultSecret = $defaultSecret;
	}

	/**
	 * Get the default secret, used when creating secured URLs.
	 *
	 * @return string
	 */
	public static function getDefaultSecret()
	{
		return self::$defaultSecret;
	}
	
	/**
	 * Set callback function for reverse-routing.
	 *
	 * @param string $callback
	 */
	public static function setReverseRouteCallback($callback)
	{
		self::$reverseRouteCallback = $callback;
	}
	
	/**
	 * Get scheme part of URL.
	 *
	 * @return string
	 */
	public function getScheme()
	{
		return $this->scheme;
	}
	
	/**
	 * Set scheme part of URL.
	 *
	 * @param string $scheme
	 * @return URL Returns self for chainability.
	 */
	public function setScheme($scheme)
	{
		$this->scheme = $scheme;
		return $this;
	}
	
	/**
	 * Get host part of URL.
	 *
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}
	
	/**
	 * Set host part of URL.
	 *
	 * @param string $host
	 * @return URL Returns self for chainability.
	 */
	public function setHost($host)
	{
		$this->host = $host;
		return $this;
	}
	
	/**
	 * Get port of URL.
	 *
	 * @return integer
	 */
	public function getPort()
	{
		return $this->port;
	}
	
	/**
	 * Set port part of URL.
	 *
	 * @param integer $port
	 * @return URL Returns self for chainability.
	 */
	public function setPort($port)
	{
		$this->port = $port;
		return $this;
	}
	
	/**
	 * Get user part of URL.
	 *
	 * @return string
	 */
	public function getUser()
	{
		return $this->user;
	}
	
	/**
	 * Set username part of URL.
	 *
	 * @param string $user
	 * @return URL Returns self for chainability.
	 */
	public function setUser($user)
	{
		$this->user = $user;
		return $this;
	}
	
	/**
	 * Get password part of URL.
	 *
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}
	
	/**
	 * Set password part of URL.
	 *
	 * @param string $password
	 * @return URL Returns self for chainability.
	 */
	public function setPassword($password)
	{
		$this->password = $password;
		return $this;
	}
	
	/**
	 * Get path part of URL.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->path;
	}
	
	/**
	 * Set path part of URL.
	 * 
	 * Some values have special meaning.
	 *   '' means the current script
	 *   leading '~' means relative to script path
	 *
	 * @param string $path
	 * @return URL Returns self for chainability.
	 */
	public function setPath($path)
	{
		$this->path = $path;
		return $this;
	}
	
	/**
	 * Get query string part of URL.
	 *
	 * @return string
	 */
	public function getQueryString()
	{
		if(is_array($this->queryString))
			return http_build_query($this->queryString, null, '&');
		return $this->queryString;
	}
	
	/**
	 * Set query string part of URL.
	 *
	 * @param string|array $queryString
	 * @return URL Returns self for chainability.
	 */
	public function setQueryString($queryString)
	{
		$this->queryString = $queryString;
		return $this;
	}
	
	/**
	 * Get fragment part of URL.
	 *
	 * @return string
	 */
	public function getFragment()
	{
		return $this->fragment;
	}
	
	/**
	 * Set fragment part of URL.
	 *
	 * @param string $fragment
	 * @return URL Returns self for chainability.
	 */
	public function setFragment($fragment)
	{
		$this->fragment = $fragment;
		return $this;
	}
	
	/**
	 * Get reverse route enabled/disabled.
	 *
	 * @return bool
	 */
	public function getReverseRoute()
	{
		return $this->reverseRoute;
	}
	
	/**
	 * Enable/disable reverse routing for this URL.
	 *
	 * @param bool $reverseRoute
	 * @return URL Returns self for chainability.
	 */
	public function setReverseRoute($reverseRoute)
	{
		$this->reverseRoute = $reverseRoute;
		return $this;
	}

	/**
	 * Get query-string variable.
	 *
	 * @param $name
	 * @return null|string
	 */
	public function getVar($name)
	{
		$vars = $this->getVars();
		return isset($vars[$name]) ? $vars[$name] : null;
	}
	
	/**
	 * Get query string field-value pairs
	 *
	 * @return string[]
	 */
	public function getVars()
	{
		if(!is_array($this->queryString)) {
			$vars = array();
			parse_str($this->queryString, $vars);
			return $vars;
		}
		return $this->queryString;
	}
	
	/**
	 * This accepts the URL in any format.
	 * 
	 * External:
	 * http://www.hello.com/test/
	 * 
	 * Internal (relative to base url):
	 * test/hello/
	 * 
	 * Internal (absolute project path):
	 * /test/hello/
	 * 
	 * It will also accept other parts of the url:
	 * /test/?foo=1&bar=2#fragment
	 *
	 * @param string $url
	 * @return URL
	 */
	public function setUrl($url) {
		$urlinfo = self::parse($url);
		$this->scheme = isset($urlinfo['scheme']) ? $urlinfo['scheme'] : null;
		$this->host = isset($urlinfo['host']) ? $urlinfo['host'] : null;
		$this->port = isset($urlinfo['port']) ? $urlinfo['port'] : null;
		$this->user = isset($urlinfo['user']) ? $urlinfo['user'] : null;
		$this->password = isset($urlinfo['pass']) ? $urlinfo['pass'] : null;
		$this->path = isset($urlinfo['path']) ? $urlinfo['path'] : '';
		$this->queryString = isset($urlinfo['query']) ? $urlinfo['query'] : array();
		$this->fragment = isset($urlinfo['fragment']) ? $urlinfo['fragment'] : null;
		return $this;
	}

	/**
	 * Replaces php's parse_url() function.
	 *
	 * @param $url
	 * @return bool
	 */
	public static function parse($url) {
		$delim = preg_quote(':/?#[]@', '%');
		$pattern = '%
			((?P<scheme>[a-z][a-z0-9\.+-]*):)?
			(// # authority
				( # userinfo
					(?P<user>[^:@]*)
					(:(?P<pass>[^@]*))?
					@
				)?
				(?P<host>(\[.*\])|([^'.$delim.']*))
				(:(?P<port>[0-9]+))?
			)?
			(?P<path>[^\?]*)
			(\?(?P<query>[^#]*))?
			(\#(?P<fragment>.*))?
		%x';
		if (!preg_match($pattern, $url, $urlinfo))
			return false;
		// decode percent-encoding
		$decode = array('user', 'pass', 'path', 'fragment');
		foreach($decode as $d) {
			if (array_key_exists($d, $urlinfo))
				$urlinfo[$d] = rawurldecode($urlinfo[$d]);
		}
		return $urlinfo;
	}

	/**
	 * Construct url-string from array as returned by parse().
	 *
	 * @param array $urlinfo
	 * @return string
	 */
	public static function build(array $urlinfo) {
		// scheme
		$ret = $urlinfo['scheme'] ? $urlinfo['scheme'] . ':' : '';
		if ($urlinfo['user'] || $urlinfo['pass'] || $urlinfo['host'] || $urlinfo['port']) {
			$ret .= '//';
			// credentials
			if($urlinfo['user'] || $urlinfo['pass'])
				$ret .= rawurlencode($urlinfo['user']) . ":" . rawurlencode($urlinfo['pass']) . '@';
			// host
			$ret .= $urlinfo['host'];
			// port
			if($urlinfo['port'])
				$ret .= ":" . $urlinfo['port'];
		}
		if (isset($urlinfo['path']))
			$ret .= self::encodeURI($urlinfo['path']);
		if (isset($urlinfo['query']) && $urlinfo['query'] !== '')
			$ret .= '?' . $urlinfo['query'];
		if (isset($urlinfo['fragment']) && $urlinfo['fragment'] !== '')
			$ret .= '#' . rawurlencode($urlinfo['fragment']);
		return $ret;
	}

	/**
	 * Encode an URI using percent-encoding in the same way rawurlencode() does, except
	 * that the following characters are preserved:
	 *
	 * Reserved: ;,/?:@&=+$
	 * Unescaped: !*'()
	 * Score: #
	 *
	 * @param $url
	 * @return string
	 */
	public static function encodeURI($url) {
		return strtr(rawurlencode($url), array(
			// Reserved
			'%3B' => ';', '%2C' => ',', '%2F' => '/', '%3F' => '?', '%3A' => ':', '%40' => '@', '%26' => '&', '%3D' => '=', '%2B' => '+', '%24' => '$',
			// Unescaped
			'%21' => '!', '%2A' => '*', '%27' => '\'', '%28' => '(', '%29' => ')',
			// Score
			'%23' => '#',
		));
	}

	/**
	 * Add GET variables to the query string.
	 *
	 * @param array $vars
	 * @param bool $overwrite
	 * @return URL
	 */
	public function add(array $vars = array(), $overwrite = true) {
		if (is_string($this->queryString)) {
			$queryString = $this->queryString;
			$this->queryString = array();
			parse_str($queryString, $this->queryString);
		}
		foreach($vars as $k => $v) {
			if(is_numeric($k)) {
				if(isset($_GET[$v]))
					$this->queryString[$v] = $_GET[$v];
			} else {
				if($overwrite || !array_key_exists($k, $this->queryString))
					$this->queryString[$k] = $v;
			}
		}
		return $this;
	}
	
	/**
	 * Remove GET variables from the query string.
	 *
	 * @param string|array $var
	 * @return URL
	 */
	public function remove($var) {
		if (is_string($this->queryString)) {
			$queryString = $this->queryString;
			$this->queryString = array();
			parse_str($queryString, $this->queryString);
		}
		if(is_string($var)) {
			unset($this->queryString[$var]);
		} else {
			foreach($var as $v)
				unset($this->queryString[$v]);
		}
		return $this;
	}
	
	/**
	 * Generate a hash for this URL.
	 * 
	 * @return string
	 */
	protected function getHash($secret)
	{
		if ($secret === true || $secret === null)
			$secret = self::$defaultSecret;
		if (!$secret)
			throw new \Exception('No secret specified');

		// remove hash from query string
		$url = clone $this;
		$url->setReverseRoute(false);
		$vars = $url->getVars();
		unset($vars['hash']);
		ksort($vars);
		$url->setQueryString($vars);

		return hash_hmac('sha1', $url->getAbsolute("&"), $secret);
	}

	/**
	 * Validate the requested URL. This will check if a secured hash is provided for the current URI.
	 *
	 * @param string|null $secret
	 * @return bool
	 */
	public static function validate($secret = null) {
		return URL::create(self::getRequestUri(), $_GET)->isValid($secret);
	}

	/**
	 * This will verify that this url has a secured hash.
	 *
	 * @param string|null $secret
	 * @return bool
	 */
	public function isValid($secret = null) {
		return $this->getVar('hash') === $this->getHash($secret);
	}
	
	/**
	 * Get the string representation of this URL object.
	 *
	 * @param string $separator
	 * @param bool $secure
	 * @return string
	 */
	public function getUrl($separator = "&", $secure = false) {
		return $this->getBase() . $this->getRelative($separator, $secure);
	}
	
	/**
	 * Get the relative URL including the following parts: path, query string, fragment.
	 *
	 * @param string $separator
	 * @param bool|string $secure
	 * @return string
	 */
	public function getRelative($separator = "&", $secure = false) {
		$query = $this->getQueryString();
		$path = $this->path;
		
		// TODO: make sure this works with reverse-routes
		if($secure) {
			$hash = $this->getHash($secure);
			if (is_array($query))
				$query['hash'] = $hash;
			else
				$query .= (empty($query) ? '' : $separator) . 'hash=' . $hash;
		}
		
		if($path === "") {
			// use current script
			$path = self::getScriptPath();
		} else if($path === null) {
			$path = "";
		} else if($path{0} == '/') {
			// absolute path
		} else if($path{0} == '~') {
			// path relative to script
			$scriptPath = self::getScriptPath();
			$lastSlash = strrpos($scriptPath, "/");
			$path = ($lastSlash === false ? $scriptPath : substr($scriptPath, 0, $lastSlash)) . substr($path, 1);
		} else {
			// path relative to base
			$base = $this->getBaseUrl();
			$path = $base['path'] . $path;
		}
		
		if($this->reverseRoute && self::$reverseRouteCallback) {
			call_user_func_array(self::$reverseRouteCallback, array(&$path, &$query));
		}

		$ret = self::encodeURI($path);

		if (is_array($query) && count($query))
			$ret .= '?' . http_build_query($query, null, $separator);
		else if (is_string($query) && $query !== '')
			$ret .= '?' . $query;
		
		if($this->fragment)
			$ret .= "#" . rawurlencode($this->fragment);

		return $ret;
	}

	/**
	 * Get full URL including all parts.
	 *
	 * @param string $separator
	 * @param bool $secure
	 * @return string
	 */
	public function getAbsolute($separator = "&", $secure = false) {
		return $this->getBase(true) . $this->getRelative($separator, $secure);
	}
	
	/**
	 * Check if URL is absolute. That is if any of the following parts has been
	 * set: scheme, user, password, host or port.
	 *
	 * @return bool
	 */
	public function isAbsolute() {
		return $this->scheme || $this->user || $this->password || $this->host || $this->port;
	}

	/**
	 * Check if any authority part of URL has been set (user, password, host or port).
	 *
	 * @return bool
	 */
	public function hasAuthority() {
		return $this->user || $this->password || $this->host || $this->port;
	}
	
	/**
	 * Set the base URL. This is the URL used when converting relative urls to absolute paths.
	 *
	 * @param string|array $baseUrl
	 */
	public static function setDefaultBaseUrl($baseUrl) {
		self::$defaultBaseUrl = is_array($baseUrl) ? $baseUrl : self::parse($baseUrl);
		// Add default components
		self::$defaultBaseUrl += array(
			'scheme' => '',
			'user' => '',
			'pass' => '',
			'host' => '',
			'port' => '',
			'path' => ''
		);
	}

	/**
	 * Get base URL. This is returned in the same format as parse_url().
	 *
	 * @param bool $asString
	 * @return array|string
	 */
	public static function getDefaultBaseUrl($asString = false) {
		if (self::$defaultBaseUrl === null) {
			$secure = !empty($_SERVER['HTTPS'])
				? strtolower($_SERVER['HTTPS']) != 'off'
				: (isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] == 443 : false);
			$scheme = $secure ? 'https' : 'http';
			$port = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '';
			if(isset($_SERVER['HTTP_HOST'])) {
				$host = explode(":", $_SERVER['HTTP_HOST'], 2);
				$hostname = $host[0];
				if (count($host) > 1) {
					$port = $host[1];
				}
			} elseif(isset($_SERVER['SERVER_ADDR'])) {
				$hostname = $_SERVER['SERVER_ADDR'];
			} else {
				$hostname = 'localhost';
			}
			$port = $port && $port != ($secure ? 443 : 80) ? $port : '';
			self::setDefaultBaseUrl(array(
				'scheme' => $scheme,
				'host' => $hostname,
				'port' => $port,
				'path' => '/',
			));
		}
		return $asString ? self::build(self::$defaultBaseUrl) : self::$defaultBaseUrl;
	}

	/**
	 * Set base url for this instance.
	 *
	 * @param $baseUrl
	 */
	public function setBaseUrl($baseUrl) {
		$this->baseUrl = is_array($baseUrl) ? $baseUrl : self::parse($baseUrl);
		// Add default components
		$this->baseUrl += array(
			'scheme' => '',
			'user' => '',
			'pass' => '',
			'host' => '',
			'port' => '',
			'path' => ''
		);
	}

	/**
	 * Get base url for this instance, either as an array (as returned by parse_url) or
	 * as a string.
	 *
	 * @param bool $asString
	 * @return array|null|string
	 */
	public function getBaseUrl($asString = false) {
		$base = $this->baseUrl === null ? self::getDefaultBaseUrl() : $this->baseUrl;
		return $asString ? self::build($base) : $base;
	}

	/**
	 * Get the base part of the URL. Base includes everything up to path, ie the
	 * following parts: scheme, user, password, host and port.
	 *
	 * @param bool $force
	 * @return string
	 */
	public function getBase($force = false) {
		if($this->isAbsolute()) {
			return self::build(array(
				'scheme' => $this->scheme,
				'user' => $this->user,
				'pass' => $this->password,
				'host' => $this->host,
				'port' => $this->port,
			));
		} else if ($force) {
			$base = $this->getBaseUrl();
			$base['path'] = '';
			return self::build($base);
		} else {
			return "";
		}
	}
	
	/**
	 * Get the requested URI.
	 *
	 * @return string
	 */
	public static function getRequestUri()
	{
		if(isset($_SERVER['REQUEST_URI']))
			return $_SERVER['REQUEST_URI'];
		if(isset($_SERVER['HTTP_X_REWRITE_URL']))
			return $_SERVER['HTTP_X_REWRITE_URL'];
		throw new \Exception('Unable to get request uri.');
	}
	
	/**
	 * Get the current script path.
	 *
	 * @return string
	 */
	public static function getScriptPath()
	{
		try {
			$urlInfo = parse_url(self::getRequestUri());
			return $urlInfo['path'];
		}
		catch (\Exception $e) { }
		return "/";
	}
	
	/**
	 * Alias of getUrl() for casting.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getUrl();
	}
}
