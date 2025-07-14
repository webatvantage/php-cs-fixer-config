<?php

namespace Webatvantage\PhpCsFixer\Config;

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;
use Webatvantage\PhpCsFixer\Config\RuleSet\Webatvantage;

class Config
{
	public static function default(): \PhpCsFixer\Config
	{
		$ruleSet = Webatvantage::make();

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
