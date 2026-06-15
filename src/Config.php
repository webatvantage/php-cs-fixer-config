<?php

namespace Webatvantage\PhpCsFixer\Config;

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use Webatvantage\PhpCsFixer\Config\RuleSet\Webatvantage;

class Config
{
	/**
	 * @param int $targetPhpVersion Target PHP version in PHP_VERSION_ID format. Defaults to the runtime version; gates PHP 8.0+ style (e.g. trailing commas in parameter lists).
	 */
	public static function default(int $targetPhpVersion = \PHP_VERSION_ID): \PhpCsFixer\Config
	{
		$ruleSet = Webatvantage::make($targetPhpVersion);

		return (new \PhpCsFixer\Config($ruleSet->name()))
			->setIndent("\t")
			->setLineEnding("\n")
			->setRiskyAllowed(true)
			->setUsingCache(true)
			->setParallelConfig(ParallelConfigFactory::detect())
			->setRules($ruleSet->rules())
			->registerCustomFixers($ruleSet->customFixers());
	}
}
