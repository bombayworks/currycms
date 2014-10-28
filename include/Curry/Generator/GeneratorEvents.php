<?php

namespace Curry\Generator;

final class GeneratorEvents
{
	const PRE_GENERATE = 'generator.pre_generate';
	const POST_GENERATE = 'generator.post_generate';
	const PRE_MODULE = 'generator.pre_module';
	const POST_MODULE = 'generator.post_module';
	const RENDER = 'generator.render';
}