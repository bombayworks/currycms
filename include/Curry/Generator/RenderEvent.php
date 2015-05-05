<?php
namespace Curry\Generator;

use Curry\Twig\Template;
use Symfony\Component\EventDispatcher\Event;

class RenderEvent extends Event
{
	/**
	 * @var
	 */
	protected $template;

	/**
	 * @var array
	 */
	protected $content;

	public function __construct(Template $template, $content)
	{
		$this->template = $template;
		$this->content = $content;
	}

	/**
	 * @return mixed
	 */
	public function getTemplate() {
		return $this->template;
	}

	/**
	 * @param mixed $template
	 */
	public function setTemplate($template) {
		$this->template = $template;
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