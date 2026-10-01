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

	/**
	 * PHP strips the closing marker's indentation from every line of a heredoc body, so shifting
	 * a block that holds one is only safe while body and marker move by the same amount. Assert
	 * on the values themselves, since the indentation is the part that is meant to change.
	 *
	 * @dataProvider provideHeredocCases
	 */
	public function testHeredocValuesSurviveReindentation(string $code): void
	{
		$fixed = self::fix($code);

		self::assertNotSame($code, $fixed, 'fixture is not re-indented, so it proves nothing');
		self::assertSame(self::heredocValues($code), self::heredocValues($fixed));
	}

	public static function provideHeredocCases(): iterable
	{
		yield 'blank line and a deeper line' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
						line one

						  deeper
						TXT,
				)
					->render();

				PHP,
		];

		yield 'closing marker at column 0' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
				flush left
				TXT,
				)
					->render();

				PHP,
		];

		yield 'empty body' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
						TXT,
				)
					->render();

				PHP,
		];

		yield 'blank line before the marker' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'TXT'
						one

						TXT,
				)
					->render();

				PHP,
		];

		yield 'two heredocs in one argument list' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<'A'
						aaa
						A,
					<<<'B'
						bbb
						B,
				)
					->render();

				PHP,
		];

		yield 'heredoc inside a nested chain head' => [
			<<<'PHP'
				<?php
				return outer(
					inner(
						<<<'TXT'
							deep
							TXT,
					)
					->mid(),
				)
				->tail();

				PHP,
		];

		yield 'interpolated, variable at the start of a line' => [
			<<<'PHP'
				<?php
				return $this->build(
					<<<TXT
				line one
				$name
				last
				TXT,
				)
					->render();

				PHP,
		];
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

		yield 'heredoc body and closing marker move together' => [
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

	/**
	 * The value of every heredoc in the snippet, evaluated in place.
	 *
	 * @return array<string>
	 */
	private static function heredocValues(string $code): array
	{
		$name = 'Tom'; // Referenced by the evaluated fixtures that interpolate.
		$values = [];
		$parts = [];
		$depth = 0;

		foreach (Tokens::fromCode($code) as $token)
		{
			if ($token->isGivenKind(\T_START_HEREDOC))
			{
				$depth++;
			}

			if (0 === $depth)
			{
				continue;
			}

			$parts[] = $token->getContent();

			if (!$token->isGivenKind(\T_END_HEREDOC))
			{
				continue;
			}

			if (0 === --$depth)
			{
				$values[] = eval('return ' . implode('', $parts) . ';');
				$parts = [];
			}
		}

		return $values;
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
