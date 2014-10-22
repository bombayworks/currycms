<?php

namespace Curry\Generator;

use Curry\Module\AbstractModule;
use Curry\Module\PageModuleWrapper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ModuleCacher implements EventSubscriberInterface
{
	protected $cache;

	public function __construct($cache) {
		$this->cache = $cache;
	}

	public static function getSubscribedEvents()
	{
		return array(
			GeneratorEvents::PRE_MODULE => array('preModule', 1000),
			GeneratorEvents::POST_MODULE => array('postModule', -10000),
		);
	}

	public function preModule(PreModuleEvent $event)
	{
		$pageModuleWrapper = $event->getModuleWrapper();
		$module = $pageModuleWrapper->createObject();
		$cacheProperties = $module->getCacheProperties();
		$event->setExtra('module_cacher.properties', $cacheProperties);

		if ($cacheProperties !== null) {
			$cacheName = $this->getCacheName($pageModuleWrapper, $cacheProperties);
			$event->setExtra('module_cacher.name', $cacheName);
			if (($cache = $this->cache->load($cacheName)) !== false) {
				$event->setContent($cache['content']);
				$event->setExtras(array_merge($event->getExtras(), $cache['extra']));
				$event->setExtra('module_cacher.cached', true);
				$event->stopPropagation();
			}
		}
	}

	public function postModule(PostModuleEvent $event)
	{
		if (!$event->getExtra('module_cacher.cached')) {
			/** @var \Curry\Module\CacheProperties $cacheProperties */
			$cacheProperties = $event->getExtra('module_cacher.properties');
			if ($cacheProperties !== null) {
				$cacheName = $event->getExtra('module_cacher.name');
				$cache = array(
					'content' => $event->getContent(),
					'extra' => $event->getExtras(),
				);
				$this->cache->save($cache, $cacheName, array(), $cacheProperties->getLifetime());
			}
		}
	}

	/**
	 * @param $pageModuleWrapper
	 * @param $cacheProperties
	 * @return string
	 */
	protected function getCacheName($pageModuleWrapper, $cacheProperties) {
		$params = array(
			'_moduleDataId' => $pageModuleWrapper->getModuleDataId(),
			'_template' => $pageModuleWrapper->getTemplate()
		);
		if ($cacheProperties !== null) {
			$params = array_merge($params, $cacheProperties->getParams());
		}

		return md5(__CLASS__ . '_Module_' . serialize($params));
	}
}