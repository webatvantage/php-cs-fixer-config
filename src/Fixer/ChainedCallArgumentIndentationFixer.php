<?php

namespace Webatvantage\PhpCsFixer\Config\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

final class ChainedCallArgumentIndentationFixer extends AbstractFixer implements WhitespacesAwareFixerInterface
{
	public function getName(): string
	{
		return 'Webatvantage/chained_call_argument_indentation';
	}

	public function getDefinition(): FixerDefinitionInterface
	{
		return new FixerDefinition(
			'A call whose closing parenthesis is followed by a chained `->` on the next line must have that parenthesis, and the chain itself, indented one level past the line the call starts on.',
			[
				new CodeSample(
					<<<'PHP'
						<?php
						$report = new Report(
							rows: 100,
							verbose: true,
						)
							->withFormat(Format::Csv);

						PHP,
				),
			],
			'PSR-12 puts the closing parenthesis at the indentation of the statement itself, which leaves it hanging a level below the `->` continuation that follows it. This lines the parenthesis up with the chain and indents the arguments one level further.',
		);
	}

	public function getPriority(): int
	{
		// Lower than method_argument_space (30), statement_indentation (-3) and
		// property_hook_braces (-31) so we re-indent after they have had their say.
		return -40;
	}

	public function isCandidate(Tokens $tokens): bool
	{
		return $tokens->isAnyTokenKindsFound(Token::getObjectOperatorKinds());
	}

	protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
	{
		// Front to back, so a nested call is re-indented only after the call it sits inside has
		// settled — its own anchor line is one of the lines the outer call shifts.
		for ($index = 0, $count = $tokens->count(); $index < $count; $index++)
		{
			if (!$tokens[$index]->equals('('))
			{
				continue;
			}

			$closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS, $index);
			$chainIndex = $this->chainOperatorAfter($tokens, $closeIndex);

			if (null === $chainIndex)
			{
				continue;
			}

			$anchorIndent = $this->anchorIndent($tokens, $index);

			if (null === $anchorIndent)
			{
				continue;
			}

			$this->reindent($tokens, $index, $closeIndex, $anchorIndent . $this->whitespacesConfig->getIndent());
		}
	}

	/**
	 * The whitespace token holding the line break between the closing parenthesis and a chained
	 * `->`, or null when no chain continues on the next line.
	 */
	private function chainOperatorAfter(Tokens $tokens, int $closeIndex): ?int
	{
		$nextIndex = $closeIndex + 1;

		if ($nextIndex >= $tokens->count() || !$tokens[$nextIndex]->isWhitespace())
		{
			return null;
		}

		if (null === $this->indentAfterLastNewLine($tokens[$nextIndex]->getContent()))
		{
			return null;
		}

		$operatorIndex = $tokens->getNextMeaningfulToken($nextIndex);

		if (null === $operatorIndex || !$tokens[$operatorIndex]->isObjectOperator())
		{
			return null;
		}

		return $nextIndex;
	}

	/**
	 * The indentation of the line the call starts on, or null when that line is itself a `->`
	 * continuation — a call further down a chain is already aligned and must be left alone.
	 */
	private function anchorIndent(Tokens $tokens, int $openIndex): ?string
	{
		for ($index = $openIndex - 1; $index >= 0; $index--)
		{
			if (!$tokens[$index]->isWhitespace())
			{
				continue;
			}

			$indent = $this->indentAfterLastNewLine($tokens[$index]->getContent());

			if (null === $indent)
			{
				continue;
			}

			$firstIndex = $tokens->getNextMeaningfulToken($index);

			if (null !== $firstIndex && $tokens[$firstIndex]->isObjectOperator())
			{
				return null;
			}

			return $indent;
		}

		return '';
	}

	private function reindent(Tokens $tokens, int $openIndex, int $closeIndex, string $targetIndent): void
	{
		$closeWhitespace = $tokens[$closeIndex - 1];

		if (!$closeWhitespace->isWhitespace())
		{
			return;
		}

		$currentIndent = $this->indentAfterLastNewLine($closeWhitespace->getContent());

		// The closing parenthesis has to sit on its own line for there to be anything to re-indent.
		if (null === $currentIndent)
		{
			return;
		}

		$lineIndexes = [];

		for ($index = $openIndex + 1; $index < $closeIndex; $index++)
		{
			$token = $tokens[$index];

			// Heredoc and nowdoc bodies carry their indentation outside of whitespace tokens, so
			// shifting the lines around them would silently change the string's contents.
			if ($token->isGivenKind(\T_START_HEREDOC))
			{
				return;
			}

			if (!$token->isWhitespace())
			{
				continue;
			}

			$indent = $this->indentAfterLastNewLine($token->getContent());

			if (null === $indent)
			{
				continue;
			}

			// Every line of the block is nested inside the closing parenthesis' line and so carries
			// its indentation as a prefix. Anything else is hand-aligned; leave the block alone.
			// strncmp rather than strpos: the parenthesis can sit at column 0, and an empty needle
			// is a warning on PHP 7.4, where it only became legal in PHP 8.0.
			if (0 !== strncmp($indent, $currentIndent, \strlen($currentIndent)))
			{
				return;
			}

			$lineIndexes[] = $index;
		}

		// The chain's own indentation is set from the same anchor rather than read off the file.
		// Nothing upstream normalises a `->` line nested inside an argument list, so trusting what
		// is there would let each run build on the last one and drift a level deeper every time.
		$this->setIndent($tokens, $closeIndex + 1, $targetIndent);

		if ($currentIndent === $targetIndent)
		{
			return;
		}

		foreach ($lineIndexes as $index)
		{
			$indent = $this->indentAfterLastNewLine($tokens[$index]->getContent());

			$this->setIndent($tokens, $index, $targetIndent . substr($indent, \strlen($currentIndent)));
		}
	}

	private function setIndent(Tokens $tokens, int $index, string $indent): void
	{
		$content = $tokens[$index]->getContent();
		$current = $this->indentAfterLastNewLine($content);

		if (null === $current || $current === $indent)
		{
			return;
		}

		$tokens[$index] = new Token([
			\T_WHITESPACE,
			substr($content, 0, \strlen($content) - \strlen($current)) . $indent,
		]);
	}

	/**
	 * The trailing indentation of a whitespace token, or null when it spans no new line.
	 */
	private function indentAfterLastNewLine(string $content): ?string
	{
		$position = strrpos($content, "\n");

		if (false === $position)
		{
			return null;
		}

		return substr($content, $position + 1);
	}
}
