<?php

namespace Curry\Generator;

use Curry\App;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ModuleHtmlHead implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return array(
			GeneratorEvents::PRE_MODULE => array('preModule'),
			GeneratorEvents::POST_MODULE => array('postModule'),
			GeneratorEvents::POST_GENERATION => array('postGeneration'),
		);
	}

	public function preModule(PreModuleEvent $event)
	{
		$htmlHead = App::getInstance()->generator->getHtmlHead();
		$htmlHead->clearBacklog();
	}

	public function postModule(PostModuleEvent $event)
	{
		$htmlHead = App::getInstance()->generator->getHtmlHead();
		$replay = $event->getExtra('module_htmlhead.replay');
		if($replay) {
			$htmlHead->replay($replay);
		} else {
			$event->setExtra('module_htmlhead.replay', $htmlHead->getBacklog());
		}
	}

	public function postGeneration()
	{
		$appVars = App::getInstance()->globals;
		$htmlHead = App::getInstance()->generator->getHtmlHead();
		$appVars->HtmlHead = $htmlHead->getContent();
	}
}