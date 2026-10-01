<?php

namespace Webatvantage\PhpCsFixer\Config\Tests;

use PhpCsFixer\FixerFactory;
use PhpCsFixer\RuleSet\RuleSet as PhpCsFixerRuleSet;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use PHPUnit\Framework\TestCase;
use Webatvantage\PhpCsFixer\Config\Config;

final class RuleSetStabilityTest extends TestCase
{
	/**
	 * Formatting twice must give what formatting once gave.
	 *
	 * Webatvantage/chained_call_argument_indentation moves a whole argument list across, which
	 * only settles while every construct inside it is re-indented from scratch on each run. Where
	 * nothing does that the shift lands on top of the previous one and gains a level every time —
	 * array elements did so until array_indentation was enabled, and heredoc bodies, which no rule
	 * normalises, are why that fixer positions them absolutely. CI auto-commits whatever
	 * `composer format` produces, so drift would be committed rather than reported.
	 *
	 * @dataProvider provideStabilityCases
	 */
	public function testFormattingIsStable(string $code): void
	{
		$once = self::format($code);

		self::assertSame($once, self::format($once));
	}

	public static function provideStabilityCases(): iterable
	{
		yield 'array arguments in a chain head' => [
			<<<'PHP'
				<?php

				final class C
				{
					public function h($value)
					{
						return $this->build(
							[
								'template' => 'page.tpl',
							],
							[
								'value' => $value,
							],
						)
							->withTarget('#panel')
							->withMessage('ok');
					}
				}

				PHP,
		];

		yield 'heredoc argument in a chain head' => [
			<<<'PHP'
				<?php

				final class C
				{
					public function h()
					{
						return $this->build(
							<<<'TXT'
								line one

								  deeper
								TXT,
						)
							->render();
					}
				}

				PHP,
		];

		yield 'nested chain heads with arrays' => [
			<<<'PHP'
				<?php

				final class C
				{
					public function h()
					{
						return outer(
							inner(
								[
									'a' => 1,
								],
							)
								->mid(),
							2,
						)
							->tail();
					}
				}

				PHP,
		];
	}

	/**
	 * Run the project's whole rule set over the code the way the runner does.
	 */
	private static function format(string $code): string
	{
		$config = Config::default();

		$factory = new FixerFactory();
		$factory->registerBuiltInFixers();
		$factory->registerCustomFixers($config->getCustomFixers());
		$factory->useRuleSet(new PhpCsFixerRuleSet($config->getRules()));
		$factory->setWhitespacesConfig(new WhitespacesFixerConfig("\t", "\n"));

		$file = new \SplFileInfo('dummy.php');
		$tokens = Tokens::fromCode($code);

		foreach ($factory->getFixers() as $fixer)
		{
			$fixer->fix($file, $tokens);

			if ($tokens->isChanged())
			{
				$tokens->clearEmptyTokens();
				$tokens->clearChanged();
			}
		}

		return $tokens->generateCode();
	}
}
