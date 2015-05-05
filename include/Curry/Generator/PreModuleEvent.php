<?php
namespace Curry\Generator;

use Curry\Module\PageModuleWrapper;
use Symfony\Component\EventDispatcher\Event;

class PreModuleEvent extends Event
{

	/**
	 * @var PageModuleWrapper
	 */
	protected $moduleWrapper;

	/**
	 * @var bool
	 */
	protected $enabled = true;

	/**
	 * @var null|string
	 */
	protected $template;

	/**
	 * @var string
	 */
	protected $target;

	/**
	 * @var string
	 */
	protected $content;

	/**
	 * @var array
	 */
	protected $extra = array();

	public function __construct(PageModuleWrapper $moduleWrapper)
	{
		$this->moduleWrapper = $moduleWrapper;
		$this->enabled = $moduleWrapper->getEnabled();
		$this->template = $moduleWrapper->getTemplate();
		$this->target = $moduleWrapper->getTarget();
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

	/**
	 * @return boolean
	 */
	public function isEnabled()
	{
		return $this->enabled;
	}

	/**
	 * @param boolean $enabled
	 */
	public function setEnabled($enabled)
	{
		$this->enabled = $enabled;
	}

	/**
	 * @return string
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * @param string $target
	 */
	public function setTarget($target)
	{
		$this->target = $target;
	}

	/**
	 * @return null|string
	 */
	public function getTemplate()
	{
		return $this->template;
	}

	/**
	 * @param null|string $template
	 */
	public function setTemplate($template)
	{
		$this->template = $template;
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