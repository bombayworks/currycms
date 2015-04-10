<?php

namespace Curry\Generator;

use Curry\Configurable;
use Curry\Util\Helper;
use Curry\Util\Propel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ModuleProfiler extends Configurable implements EventSubscriberInterface
{
	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * Holds module debug info.
	 *
	 * @var array
	 */
	protected $moduleDebugInfo;

	protected $cpuLimit = 0.25;
	protected $timeLimit = 0.5;
	protected $memoryLimit = 5e6;
	protected $sqlLimit = 8;

	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	public static function getSubscribedEvents()
	{
		return array(
			GeneratorEvents::PRE_GENERATE => array('preGenerate', 10000),
			GeneratorEvents::POST_GENERATE => array('postGenerate', -10000),
			GeneratorEvents::PRE_MODULE => array('preModule', 10000),
			GeneratorEvents::POST_MODULE => array('postModule', -1000),
		);
	}

	public function preGenerate()
	{
		$this->moduleDebugInfo = array();
	}

	public function postGenerate()
	{
		$totalTime = 0;
		foreach($this->moduleDebugInfo as $mdi)
			$totalTime += $mdi[5];

		$labels = array('Name', 'Class', 'Template', 'Target', 'Cached', 'Time (ms)', 'Cpu (ms)', 'Memory Delta', 'Memory Peak', 'Queries');
		$this->logger->debug("Modules(".count($this->moduleDebugInfo)."): ".round($totalTime / 1000.0, 3)."s",
			array_merge(array($labels), $this->moduleDebugInfo));
	}

	public function preModule(PreModuleEvent $event)
	{
		$event->setExtra('module_profiler.time', microtime(true));
		$event->setExtra('module_profiler.query_count', Propel::getQueryCount());
		$event->setExtra('module_profiler.user_time', Helper::getCpuTime('u'));
		$event->setExtra('module_profiler.system_time', Helper::getCpuTime('s'));
		$event->setExtra('module_profiler.memory_usage', memory_get_usage(true));
	}

	public function postModule(PostModuleEvent $event)
	{
		$pageModuleWrapper = $event->getModuleWrapper();

		$queryCount = $event->getExtra('module_profiler.query_count');

		$time = microtime(true) - $event->getExtra('module_profiler.time');
		$userTime = Helper::getCpuTime('u') - $event->getExtra('module_profiler.user_time');
		$systemTime = Helper::getCpuTime('s') - $event->getExtra('module_profiler.system_time');
		$memoryUsage = memory_get_usage(true) - $event->getExtra('module_profiler.memory_usage');
		$sqlQueries = $queryCount !== null ? Propel::getQueryCount() - $queryCount : null;

		if (($userTime + $systemTime) > $this->cpuLimit || $time > $this->timeLimit)
			$this->logger->warning('Module generation time exceeded limit');
		if ($memoryUsage > $this->memoryLimit)
			$this->logger->warning('Module memory usage exceeded limit');
		if ($sqlQueries > $this->sqlLimit)
			$this->logger->warning('Module sql query count exceeded limit');

		// add module debug info
		$this->moduleDebugInfo[] = array(
			$pageModuleWrapper->getName(),
			$pageModuleWrapper->getClassName(),
			$pageModuleWrapper->getTemplate(),
			$pageModuleWrapper->getTarget(),
			(bool)$event->getExtra('module_cacher.cached'),
			round($time * 1000.0),
			round(($userTime + $systemTime) * 1000.0),
			Helper::humanReadableBytes($memoryUsage),
			Helper::humanReadableBytes(memory_get_peak_usage(true)),
			$sqlQueries !== null ? $sqlQueries : 'n/a',
		);

		// Remove values to prevent caching
		$extras = $event->getExtras();
		unset($extras['module_profiler.time']);
		unset($extras['module_profiler.query_count']);
		unset($extras['module_profiler.user_time']);
		unset($extras['module_profiler.system_time']);
		unset($extras['module_profiler.memory_usage']);
		$event->setExtras($extras);
	}
}