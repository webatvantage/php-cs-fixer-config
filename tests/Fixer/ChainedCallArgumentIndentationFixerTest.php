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
		yield 'return statement' => [
			<<<'PHP'
				<?php
				return new Report(
						rows: 100,
						verbose: true,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				return new Report(
					rows: 100,
					verbose: true,
				)
					->withFormat(Format::Csv);

				PHP,
		];

		yield 'assignment' => [
			<<<'PHP'
				<?php
				$report = new Report(
						rows: 100,
					)
					->withFormat(Format::Csv);

				PHP,
			<<<'PHP'
				<?php
				$report = new Report(
					rows: 100,
				)
				->withFormat(Format::Csv);

				PHP,
		];

		yield 'nullsafe chain' => [
			<<<'PHP'
				<?php
				$value = $this->maybe(
						a: 1,
					)
					?->value();

				PHP,
			<<<'PHP'
				<?php
				$value = $this->maybe(
					a: 1,
				)
					?->value();

				PHP,
		];

		yield 'nested call heading its own chain' => [
			<<<'PHP'
				<?php
				return outer(
						inner(
								a: 1,
							)
							->mid(),
						c: 3,
					)
					->tail();

				PHP,
			<<<'PHP'
				<?php
				return outer(
					inner(
						a: 1,
					)
					->mid(),
					c: 3,
				)
				->tail();

				PHP,
		];

		yield 'a call further down a chain is already aligned' => [
			<<<'PHP'
				<?php
				return $this
					->where(
						a: 1,
					)
					->orderBy(
						b: 2,
					)
					->first();

				PHP,
			<<<'PHP'
				<?php
				return $this
					->where(
						a: 1,
					)
					->orderBy(
						b: 2,
					)
					->first();

				PHP,
		];

		yield 'chain continuing on the closing parenthesis line' => [
			<<<'PHP'
				<?php
				return $this->call(
					a: 1,
				)->go();

				PHP,
			<<<'PHP'
				<?php
				return $this->call(
					a: 1,
				)->go();

				PHP,
		];

		yield 'no chain at all' => [
			<<<'PHP'
				<?php
				return $this->call(
					a: 1,
				);

				PHP,
			<<<'PHP'
				<?php
				return $this->call(
					a: 1,
				);

				PHP,
		];

		yield 'heredoc body would be corrupted by shifting, so leave the block alone' => [
			<<<'PHP'
				<?php
				return $this->build(
					body: <<<'TXT'
						line one
						TXT,
				)
					->render();

				PHP,
			<<<'PHP'
				<?php
				return $this->build(
					body: <<<'TXT'
						line one
						TXT,
				)
					->render();

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
