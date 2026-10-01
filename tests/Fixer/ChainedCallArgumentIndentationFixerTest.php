<?php

namespace Webatvantage\PhpCsFixer\Config\Tests\Fixer;

use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use PHPUnit\Framework\TestCase;
use Webatvantage\PhpCsFixer\Config\Fixer\ChainedCallArgumentIndentationFixer;

final class ChainedCallArgumentIndentationFixerTest extends TestCase
{
	/**
	 * @dataProvider provideFixCases
	 */
	public function testFix(string $expected, string $input): void
	{
		self::assertSame($expected, self::fix($input));
	}

	/**
	 * Running the fixer over its own output must be a no-op. Nothing upstream normalises a `->`
	 * line nested inside an argument list, so a fixer that read its target indentation off that
	 * line would drift a level deeper on every run.
	 *
	 * @dataProvider provideFixCases
	 */
	public function testFixIsIdempotent(string $expected, string $input): void
	{
		self::assertSame($expected, self::fix(self::fix($input)));
	}

	public static function provideFixCases(): iterable
	{
		yield 'call heading a chain' => [
			<<<'PHP'
				<?php
				return $this->build(
						100,
						true,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				return $this->build(
					100,
					true,
				)
					->withFormat(Format::Csv);

				PHP,
		];

		yield 'assignment' => [
			<<<'PHP'
				<?php
				$report = $this->build(
						100,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				$report = $this->build(
					100,
				)
				->withFormat(Format::Csv);

				PHP,
		];

		yield 'nested call heading its own chain' => [
			<<<'PHP'
				<?php
				return outer(
						inner(
								1,
							)
							->mid(),
						3,
					)
					->tail();

				PHP,
			<<<'PHP'
				<?php
				return outer(
					inner(
						1,
					)
					->mid(),
					3,
				)
				->tail();

				PHP,
		];

		yield 'a call further down a chain is already aligned' => [
			<<<'PHP'
				<?php
				return $this
					->where(
						1,
					)
					->orderBy(
						2,
					)
					->first();

				PHP,
			<<<'PHP'
				<?php
				return $this
					->where(
						1,
					)
					->orderBy(
						2,
					)
					->first();

				PHP,
		];

		yield 'chain continuing on the closing parenthesis line' => [
			<<<'PHP'
				<?php
				return $this->call(
					1,
				)->go();

				PHP,
			<<<'PHP'
				<?php
				return $this->call(
					1,
				)->go();

				PHP,
		];

		yield 'no chain at all' => [
			<<<'PHP'
				<?php
				return $this->call(
					1,
				);

				PHP,
			<<<'PHP'
				<?php
				return $this->call(
					1,
				);

				PHP,
		];

		yield 'heredoc body would be corrupted by shifting, so leave the block alone' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
						line one
						TXT,
				)
					->render();

				PHP,
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
						line one
						TXT,
				)
					->render();

				PHP,
		];

		// Named arguments and the nullsafe operator are PHP 8.0 syntax, and Tokens::fromCode()
		// parses rather than merely lexes, so these cases are a fatal on 7.4 rather than a failure.
		if (\PHP_VERSION_ID < 80000)
		{
			return;
		}

		yield 'named arguments' => [
			<<<'PHP'
				<?php
				return $this->build(
						rows: 100,
						verbose: true,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				return $this->build(
					rows: 100,
					verbose: true,
				)
					->withFormat(Format::Csv);

				PHP,
		];

		yield 'nullsafe chain' => [
			<<<'PHP'
				<?php
				$value = $this->maybe(
						1,
					)
					?->value();

				PHP,
			<<<'PHP'
				<?php
				$value = $this->maybe(
					1,
				)
					?->value();

				PHP,
		];

		// Chaining straight off `new` without wrapping parentheses is PHP 8.4 syntax. It is the
		// shape this fixer was written for, so it is worth covering where the parser allows it.
		if (\PHP_VERSION_ID < 80400)
		{
			return;
		}

		yield 'new without parentheses' => [
			<<<'PHP'
				<?php
				$report = new Report(
						rows: 100,
						verbose: true,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				$report = new Report(
					rows: 100,
					verbose: true,
				)
					->withFormat(Format::Csv);

				PHP,
		];
	}

	private static function fix(string $code): string
	{
		$fixer = new ChainedCallArgumentIndentationFixer();
		$fixer->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\n"));

		$tokens = Tokens::fromCode($code);
		$fixer->fix(new \SplFileInfo('dummy.php'), $tokens);

		return $tokens->generateCode();
	}
}
