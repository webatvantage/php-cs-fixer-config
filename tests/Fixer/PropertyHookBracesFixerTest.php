<?php

namespace Webatvantage\PhpCsFixer\Config\Tests\Fixer;

use PhpCsFixer\Tokenizer\Tokens;
use PHPUnit\Framework\TestCase;
use Webatvantage\PhpCsFixer\Config\Fixer\PropertyHookBracesFixer;

final class PropertyHookBracesFixerTest extends TestCase
{
	/**
	 * @dataProvider provideFixCases
	 */
	public function testFix(string $expected, string $input): void
	{
		$fixer = new PropertyHookBracesFixer();
		$tokens = Tokens::fromCode($input);

		$fixer->fix(new \SplFileInfo('dummy.php'), $tokens);

		self::assertSame($expected, $tokens->generateCode());
	}

	public static function provideFixCases(): iterable
	{
		yield 'outer brace mangled (arrow accessors)' => [
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get => $this->a;
						set => $this->a = $value;
					}
				}

				PHP,
			<<<'PHP'
				<?php
				class C {
					public int $a
						{
						get => $this->a;
						set => $this->a = $value;
					}
				}

				PHP,
		];

		yield 'outer + block-form accessor braces mangled' => [
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get {
							return $this->a;
						}
						set {
							$this->a = $value;
						}
					}
				}

				PHP,
			<<<'PHP'
				<?php
				class C {
					public int $a
						{
						get
						{
							return $this->a;
						}
						set
						{
							$this->a = $value;
						}
					}
				}

				PHP,
		];

		yield 'already correct - no change (idempotence)' => self::same(
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get => $this->a;
					}
				}

				PHP,
		);

		yield 'no property hooks - no change' => self::same(
			<<<'PHP'
				<?php
				class C {
					public function get(): int
					{
						return 1;
					}
				}

				PHP,
		);

		yield 'method call get() inside accessor body - only the accessor brace is touched' => [
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get {
							return $this->get();
						}
					}
				}

				PHP,
			<<<'PHP'
				<?php
				class C {
					public int $a
						{
						get
						{
							return $this->get();
						}
					}
				}

				PHP,
		];

		yield 'control structures inside accessor body untouched (nested if has its own next-line brace)' => self::same(
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get {
							if ($this->a > 0)
							{
								return $this->a;
							}

							return 0;
						}
					}
				}

				PHP,
		);

		yield 'mixed arrow + block accessors' => [
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get => $this->a;
						set {
							$this->a = $value;
						}
					}
				}

				PHP,
			<<<'PHP'
				<?php
				class C {
					public int $a
						{
						get => $this->a;
						set
						{
							$this->a = $value;
						}
					}
				}

				PHP,
		];

		yield 'multiple properties with hooks in same class' => [
			<<<'PHP'
				<?php
				class C {
					public int $a {
						get => 1;
					}
					public int $b {
						get => 2;
					}
				}

				PHP,
			<<<'PHP'
				<?php
				class C {
					public int $a
						{
						get => 1;
					}
					public int $b
						{
						get => 2;
					}
				}

				PHP,
		];
	}

	private static function same(string $code): array
	{
		return [$code, $code];
	}
}
