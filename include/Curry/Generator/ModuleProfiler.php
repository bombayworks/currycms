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
		$app = \Curry\App::getInstance();
		$pageModuleWrapper = $event->getModuleWrapper();

		$queryCount = $event->getExtra('module_profiler.query_count');

		$time = microtime(true) - $event->getExtra('module_profiler.time');
		$userTime = Helper::getCpuTime('u') - $event->getExtra('module_profiler.user_time');
		$systemTime = Helper::getCpuTime('s') - $event->getExtra('module_profiler.system_time');
		$memoryUsage = memory_get_usage(true) - $event->getExtra('module_profiler.memory_usage');
		$sqlQueries = $queryCount !== null ? Propel::getQueryCount() - $queryCount : null;

		$cpuLimit = $app->config->curry->debug->moduleCpuLimit;
		$timeLimit = $app->config->curry->debug->moduleTimeLimit;
		$memoryLimit = $app->config->curry->debug->moduleMemoryLimit;
		$sqlLimit = $app->config->curry->debug->moduleSqlLimit;

		if (($userTime + $systemTime) > $cpuLimit || $time > $timeLimit)
			trace_warning('Module generation time exceeded limit');
		if ($memoryUsage > $memoryLimit)
			trace_warning('Module memory usage exceeded limit');
		if ($sqlQueries > $sqlLimit)
			trace_warning('Module sql query count exceeded limit');

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