<?php

namespace Webatvantage\PhpCsFixer\Config\Tests\RuleSet;

use PHPUnit\Framework\TestCase;
use Webatvantage\PhpCsFixer\Config\RuleSet\Webatvantage;

final class WebatvantageTest extends TestCase
{
	/**
	 * Trailing commas in parameter lists are PHP 8.0 syntax and fatal on 7.4. The
	 * 'parameters' element must therefore be gated on the consumer's target PHP
	 * version: absent for the 7.4 floor, present once the floor is 8.0 or newer.
	 *
	 * @dataProvider provideParameterTrailingCommaCases
	 *
	 * @param int|null $targetPhpVersion null exercises the default argument.
	 */
	public function testParameterTrailingCommaIsGatedOnTargetPhpVersion(?int $targetPhpVersion, bool $expected): void
	{
		$ruleSet = $targetPhpVersion === null ? Webatvantage::make() : Webatvantage::make($targetPhpVersion);
		$rules = $ruleSet->rules();

		self::assertArrayHasKey('trailing_comma_in_multiline', $rules);

		$elements = $rules['trailing_comma_in_multiline']['elements'] ?? [];

		if ($expected)
		{
			self::assertContains('parameters', $elements, 'PHP 8.0+ should enforce trailing commas in parameter lists.');
		}
		else
		{
			self::assertNotContains('parameters', $elements, 'Trailing commas in parameter lists fatal on PHP 7.4.');
		}
	}

	public static function provideParameterTrailingCommaCases(): array
	{
		return [
			'default (runtime)' => [null, \PHP_VERSION_ID >= 80000],
			'PHP 7.4' => [70400, false],
			'PHP 7.4 patch' => [70499, false],
			'PHP 8.0 (boundary)' => [80000, true],
			'PHP 8.2' => [80200, true],
		];
	}

	/**
	 * The remaining trailing-comma contexts (valid since PHP 7.3) must always be
	 * enforced, regardless of the target version.
	 *
	 * @dataProvider provideAlwaysOnCases
	 */
	public function testNonParameterTrailingCommasAreAlwaysEnforced(?int $targetPhpVersion): void
	{
		$ruleSet = $targetPhpVersion === null ? Webatvantage::make() : Webatvantage::make($targetPhpVersion);
		$elements = $ruleSet->rules()['trailing_comma_in_multiline']['elements'] ?? [];

		self::assertContains('arguments', $elements);
		self::assertContains('array_destructuring', $elements);
		self::assertContains('arrays', $elements);
		self::assertContains('match', $elements);
	}

	public static function provideAlwaysOnCases(): array
	{
		return [
			'default' => [null],
			'PHP 7.4' => [70400],
			'PHP 8.2' => [80200],
		];
	}

	/**
	 * A named argument written without the space, handler:$handler, is legal PHP and reads as a
	 * label; the fixer catches it through its named_argument construct.
	 */
	public function testNamedArgumentsAreSpaced(): void
	{
		$rules = Webatvantage::make()->rules();

		self::assertArrayHasKey('single_space_around_construct', $rules);
		self::assertContains('named_argument', $rules['single_space_around_construct']['constructs_followed_by_a_single_space']);
	}

	/**
	 * PHP 8.5 clone-with is written like a call, clone($object, ['property' => $value]). The fixer
	 * reads clone as the unary keyword and would push a space in front of that argument list.
	 */
	public function testCloneIsLeftAlone(): void
	{
		$rules = Webatvantage::make()->rules();

		self::assertNotContains('clone', $rules['single_space_around_construct']['constructs_followed_by_a_single_space']);
	}
}
