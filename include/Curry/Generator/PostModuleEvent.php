<?php
namespace Curry\Generator;

use Curry\Module\PageModuleWrapper;

class PostModuleEvent extends \Symfony\Component\EventDispatcher\Event
{

	/**
	 * @var PageModuleWrapper
	 */
	protected $moduleWrapper;

	/**
	 * @var string
	 */
	protected $content;

	/**
	 * @var extra
	 */
	protected $extra;

	public function __construct(PageModuleWrapper $moduleWrapper, $content, $extra)
	{
		$this->moduleWrapper = $moduleWrapper;
		$this->content = $content;
		$this->extra = $extra;
	}

	/**
	 * @return PageModuleWrapper
	 */
	public function getModuleWrapper()
	{
		return $this->moduleWrapper;
	}

	/**
	 * @return string
	 */
	public function getContent()
	{
		return $this->content;
	}

	/**
	 * @param string $content
	 */
	public function setContent($content)
	{
		$this->content = $content;
	}

	public function setExtra($key, $value)
	{
		$this->extra[$key] = $value;
	}

	public function getExtra($key)
	{
		return isset($this->extra[$key]) ? $this->extra[$key] : null;
	}

	public function setExtras($extras)
	{
		$this->extra = $extras;
	}

	public function getExtras()
	{
		return $this->extra;
	}
}