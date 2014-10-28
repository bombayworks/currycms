<?php
namespace Curry\Generator;

class PostGenerateEvent extends \Symfony\Component\EventDispatcher\Event
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