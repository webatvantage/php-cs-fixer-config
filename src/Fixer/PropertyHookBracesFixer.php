<?php

namespace Webatvantage\PhpCsFixer\Config\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class PropertyHookBracesFixer extends AbstractFixer
{
	public function getName(): string
	{
		return 'Webatvantage/property_hook_braces';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'PHP 8.4 property hook opening braces must stay on the same line as their signature.',
			[
				new CodeSample(
					<<<'PHP'
						<?php
						class Example
						{
							public int $bar
								{
								get => $this->bar;
							}
							public int $baz
								{
								get
								{
									return $this->baz;
								}
							}
						}

						PHP,
				),
			],
			'Counteracts `braces_position` which, with `control_structures_opening_brace = next_line_unless_newline_at_signature_end`, pushes property hook braces onto their own line. See PHP-CS-Fixer discussion #9657.',
		);
	}

	public function getPriority(): int
	{
		// Lower than braces_position (-2) and statement_indentation (-3) so we run last and clean up.
		return -31;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isTokenKindFound(CT::T_PROPERTY_HOOK_BRACE_OPEN);
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		for ($index = $tokens->count() - 1; $index >= 0; $index--)
		{
			if (!$tokens[$index]->isGivenKind(CT::T_PROPERTY_HOOK_BRACE_OPEN))
			{
				continue;
			}

			$this->collapseWhitespaceBefore($tokens, $index);

			$endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PROPERTY_HOOK, $index);
			$this->collapseAccessorBraces($tokens, $index, $endIndex);
		}
	}

	private function collapseAccessorBraces(Tokens $tokens, int $openIndex, int $closeIndex): void
	{
		$depth = 0;

		for ($i = $openIndex + 1; $i < $closeIndex; $i++)
		{
			$token = $tokens[$i];

			if ($token->equals('{') || $token->isGivenKind(CT::T_PROPERTY_HOOK_BRACE_OPEN))
			{
				$depth++;

				continue;
			}

			if ($token->equals('}') || $token->isGivenKind(CT::T_PROPERTY_HOOK_BRACE_CLOSE))
			{
				$depth--;

				continue;
			}

			if (0 !== $depth || !$token->isGivenKind(\T_STRING))
			{
				continue;
			}

			$content = strtolower($token->getContent());

			if ('get' !== $content && 'set' !== $content)
			{
				continue;
			}

			$nextIndex = $tokens->getNextMeaningfulToken($i);

			if (null === $nextIndex || !$tokens[$nextIndex]->equals('{'))
			{
				continue;
			}

			$this->collapseWhitespaceBefore($tokens, $nextIndex);
		}
	}

	private function collapseWhitespaceBefore(Tokens $tokens, int $index): void
	{
		$prevIndex = $index - 1;

		if ($prevIndex < 0 || !$tokens[$prevIndex]->isWhitespace())
		{
			return;
		}

		if (false === strpos($tokens[$prevIndex]->getContent(), "\n"))
		{
			return;
		}

		$tokens[$prevIndex] = new Token([\T_WHITESPACE, ' ']);
	}
}
