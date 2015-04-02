<?php
namespace Curry\Generator;

use Symfony\Component\EventDispatcher\Event;

class PostGenerateEvent extends Event
{
	/**
	 * @var array
	 */
	protected $content;

	public function __construct($content)
	{
		$this->content = $content;
	}

	/**
	 * @return array
	 */
	public function getContent()
	{
		return $this->content;
	}

	/**
	 * @param array $content
	 */
	public function setContent($content)
	{
		$this->content = $content;
	}
}