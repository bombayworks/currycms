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
namespace Curry\Util;

/**
 * Modify elements of the HTML <code><head></code>.
 * 
 * @package Curry\Util
 */
class HtmlHead {
	/**
	 * Content as an array, each line as one element.
	 *
	 * @var array
	 */
	protected $content = array();
	
	/**
	 * Array of included javascript files. Filenames are stored in the keys.
	 *
	 * @var array
	 */
	protected $javascript = array();
	
	/**
	 * Array of included stylesheet files. Filenames are stored in the keys.
	 *
	 * @var array
	 */
	protected $stylesheet = array();
	
	/**
	 * Log of all commands executed on this instance.
	 *
	 * @var array
	 */
	protected $backlog = array();
	
	/**
	 * Is there a conditional active?
	 *
	 * @var bool
	 */
	protected $conditional = false;
	
	/**
	 * Add stylesheet.
	 *
	 * @param string $href
	 * @param string $media
	 * @param array $attributes
	 */
	public function addStylesheet($href, $media = 'all', $attributes = array())
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		
		if(!isset($this->stylesheet[$href])) {
			$this->stylesheet[$href] = 1;
			
			$attributes = array_merge(array('href' => $href, 'media' => $media, 'type' => 'text/css', 'rel' => 'stylesheet'), $attributes);
			$this->content[] = self::createTag('link', $attributes);
		}
	}
	
	/**
	 * Add conditional comment.
	 * 
	 * @link http://msdn.microsoft.com/en-us/library/ms537512%28VS.85%29.aspx
	 * 
	 * @todo Add support for nested tags.
	 * @todo Add support for downlevel.
	 *
	 * @param string $condition
	 */
	public function beginConditional($condition = 'IE')
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		
		if ($this->conditional)
			throw new \Exception('Cannot create nested conditionals.');
			
		$this->content[] = "<!--[if $condition]>";
		$this->conditional = true;
	}
	
	/**
	 * End conditional comment.
	 * 
	 * @see Curry_HtmlHead::beginConditional();
	 */
	public function endConditional()
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		
		if (!$this->conditional)
			throw new \Exception('endConditional called without beginConditional');
		
		$this->content[] = "<![endif]-->";
		$this->conditional = false;
	}
	
	/**
	 * Add script.
	 *
	 * @param string $src
	 * @param string $type
	 * @param array $attributes
	 */
	public function addScript($src, $type = 'text/javascript', $attributes = array())
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		
		if(!isset($this->javascript[$src])) {
			$this->javascript[$src] = 1;
			$attributes = array_merge(array('src' => $src, 'type' => $type), $attributes);
			$this->content[] = self::createTag('script', $attributes, false) . '</script>';
		}
	}
	
	/**
	 * Add inline script.
	 *
	 * @param string $source
	 * @param string $type
	 * @param array $attributes
	 */
	public function addInlineScript($source, $type = 'text/javascript', $attributes = array())
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		
		$attributes = array_merge(array('type' => $type), $attributes);
		
		$this->content[] = self::createTag('script', $attributes, false);
		$this->content[] = "// <![CDATA[";
		$this->content[] = $source;
		$this->content[] = "// ]]>";
		$this->content[] = '</script>';
	}
	
	/**
	 * Add raw content to to HTML head.
	 *
	 * @param string $code
	 */
	public function addRaw($code)
	{
		$this->backlog[] = array('method' => __METHOD__, 'args' => func_get_args());
		$this->content[] = $code;
	}
	
	/**
	 * Clear backlog.
	 */
	public function clearBacklog()
	{
		$this->backlog = array();
	}
	
	/**
	 * Get the contents of the backlog. The backlog contains all commands
	 * executed on this object.
	 *
	 * @return array
	 */
	public function getBacklog()
	{
		return $this->backlog;
	}
	
	/**
	 * Replay commands (from getBacklog).
	 *
	 * @param array $backlog
	 */
	public function replay($backlog)
	{
		$this->backlog = array();
		foreach($backlog as $b)
			call_user_func_array(array($this, $b['method']), $b['args']);
	}
	
	/**
	 * Get content.
	 *
	 * @return string
	 */
	public function getContent()
	{
		return implode(PHP_EOL."  ", $this->content);
	}
	
	/**
	 * Helper function to create HTML element string.
	 *
	 * @param string $name
	 * @param array $attribs
	 * @param bool $close
	 * @return string
	 */
	protected static function createTag($name, $attribs, $close = true)
	{
		$tag = '<'.$name;
		foreach ($attribs as $k => $v)
			$tag .= ' '.$k.'="'.htmlspecialchars($v).'"';
		return $close ? $tag . ' />' : $tag . '>';
	}
}
