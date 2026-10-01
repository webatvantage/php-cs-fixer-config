<?php

namespace Webatvantage\PhpCsFixer\Config\Fixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\Fixer\WhitespacesAwareFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
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

			// The deprecated BLOCK_TYPE_PARENTHESIS_BRACE and CT::T_ARRAY_SQUARE_BRACE_CLOSE are used
			// throughout: their replacements only exist from php-cs-fixer 3.95.5, and this package
			// supports ^3.84. Both remain available as aliases on current versions.
			$closeIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index);
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
			$this->hugLastArgument($tokens, $closeIndex);
		}
	}

	/**
	 * Pull the closing parenthesis up onto the last argument when that argument is a block closing
	 * on a line of its own, so the call ends `])` rather than giving the parenthesis its own line.
	 * The chain then continues from the line the call actually closes on.
	 *
	 * An argument list ending in a plain value keeps its trailing comma and its own closing line;
	 * there is nothing there for the parenthesis to sit against.
	 */
	private function hugLastArgument(Tokens $tokens, int $closeIndex): void
	{
		$lastIndex = $tokens->getPrevMeaningfulToken($closeIndex);

		if (null === $lastIndex)
		{
			return;
		}

		$commaIndex = null;

		if ($tokens[$lastIndex]->equals(','))
		{
			$commaIndex = $lastIndex;
			$lastIndex = $tokens->getPrevMeaningfulToken($commaIndex);
		}

		if (null === $lastIndex)
		{
			return;
		}

		// A short array's bracket is a custom token rather than a plain `]`.
		$closesBlock = $tokens[$lastIndex]->equalsAny([')', '}'])
			|| $tokens[$lastIndex]->isGivenKind(CT::T_ARRAY_SQUARE_BRACE_CLOSE);

		if (!$closesBlock)
		{
			return;
		}

		// The bracket has to close on its own line. One that trails its own content is already
		// hugging whatever came before it, and the parenthesis belongs on the next line.
		$indent = $tokens[$lastIndex - 1]->isWhitespace()
			? $this->indentAfterLastNewLine($tokens[$lastIndex - 1]->getContent())
			: null;

		if (null === $indent)
		{
			return;
		}

		for ($index = $closeIndex - 1; $index > $lastIndex; $index--)
		{
			$tokens->clearAt($index);
		}

		if (null !== $commaIndex)
		{
			$tokens->clearAt($commaIndex);
		}

		$this->alignChain($tokens, $closeIndex, $indent);
	}

	/**
	 * Put every `->` continuation line of the chain following a call at the same indentation.
	 * Scanning stops at the first token that cannot be part of the chain, so a line break that
	 * leads somewhere else leaves the rest of the statement alone.
	 */
	private function alignChain(Tokens $tokens, int $closeIndex, string $indent): void
	{
		for ($index = $closeIndex + 1, $count = $tokens->count(); $index < $count; $index++)
		{
			$token = $tokens[$index];

			if ($token->isWhitespace())
			{
				if (null === $this->indentAfterLastNewLine($token->getContent()))
				{
					continue;
				}

				$nextIndex = $tokens->getNextMeaningfulToken($index);

				if (null === $nextIndex || !$tokens[$nextIndex]->isObjectOperator())
				{
					return;
				}

				$this->setIndent($tokens, $index, $indent);

				continue;
			}

			// Step over a call's arguments or an index in one go; their contents are indented
			// relative to the chain line they hang off, which this has already settled.
			if ($token->equals('('))
			{
				$index = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $index);

				continue;
			}

			if ($token->isGivenKind(CT::T_ARRAY_INDEX_CURLY_BRACE_OPEN))
			{
				$index = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_ARRAY_INDEX_CURLY_BRACE, $index);

				continue;
			}

			if ($token->equals('['))
			{
				$index = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_INDEX_SQUARE_BRACE, $index);

				continue;
			}

			// Anything else ends the chain. Bailing out rather than guessing keeps a statement
			// that merely contains a chain from being re-indented wholesale.
			if (!$token->isObjectOperator() && !$token->isGivenKind([\T_STRING, \T_VARIABLE]) && !$token->isComment())
			{
				return;
			}
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
		$heredocRanges = [];
		$heredocStart = null;
		$heredocDepth = 0;

		for ($index = $openIndex + 1; $index < $closeIndex; $index++)
		{
			$token = $tokens[$index];

			if ($token->isGivenKind(\T_START_HEREDOC))
			{
				if (0 === $heredocDepth)
				{
					$heredocStart = $index;
				}

				$heredocDepth++;
			}

			// A heredoc body carries its indentation inside string tokens rather than whitespace
			// ones, so none of the line handling below applies to it. It is re-indented as a whole
			// once the lines around it have settled.
			if ($heredocDepth > 0)
			{
				if ($token->isGivenKind(\T_END_HEREDOC) && 0 === --$heredocDepth)
				{
					$heredocRanges[] = [$heredocStart, $index];
				}

				continue;
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
		$this->alignChain($tokens, $closeIndex, $targetIndent);

		if ($currentIndent === $targetIndent)
		{
			return;
		}

		foreach ($lineIndexes as $index)
		{
			$indent = $this->indentAfterLastNewLine($tokens[$index]->getContent());

			$this->setIndent($tokens, $index, $targetIndent . substr($indent, \strlen($currentIndent)));
		}

		foreach ($heredocRanges as list($startIndex, $endIndex))
		{
			$this->reindentHeredoc($tokens, $startIndex, $endIndex);
		}
	}

	/**
	 * Re-indent a heredoc or nowdoc to sit one level below the line it opens on.
	 *
	 * PHP strips the closing marker's indentation from every line of the body, so the marker and
	 * the body have to move together or the string's value changes. The target is absolute rather
	 * than a shift by however far the block moved: nothing upstream normalises heredoc contents,
	 * so a relative shift would survive into the next run and accumulate.
	 */
	private function reindentHeredoc(Tokens $tokens, int $startIndex, int $endIndex): void
	{
		$current = $this->leadingWhitespace($tokens[$endIndex]->getContent());
		$target = $this->openerIndent($tokens, $startIndex) . $this->whitespacesConfig->getIndent();

		if ($current === $target)
		{
			return;
		}

		// Collect every rewrite first: a body line that does not carry the marker's indentation is
		// hand-aligned, and the heredoc has to be left exactly as it was rather than half moved.
		$replacements = [$endIndex => $target . substr($tokens[$endIndex]->getContent(), \strlen($current))];

		for ($index = $startIndex; $index < $endIndex; $index++)
		{
			$token = $tokens[$index];
			$content = $token->getContent();

			// The opening token ends with the line break that starts the body, and holds no
			// indentation itself. Only a body line starting with a token that cannot carry
			// indentation, such as an interpolated variable, has to borrow it from here.
			if ($token->isGivenKind(\T_START_HEREDOC))
			{
				if (!$this->carriesOwnIndent($tokens, $index + 1))
				{
					if ('' !== $current)
					{
						return;
					}

					$replacements[$index] = $content . $target;
				}

				continue;
			}

			if (!$token->isGivenKind(\T_ENCAPSED_AND_WHITESPACE))
			{
				continue;
			}

			$result = '';
			$position = 0;
			$length = \strlen($content);
			$atLineStart = $tokens[$index - 1]->isGivenKind(\T_START_HEREDOC);

			while ($position < $length)
			{
				$character = $content[$position];

				// A blank line is allowed to carry no indentation at all, so leave it empty.
				if ($atLineStart && "\n" !== $character)
				{
					if (0 !== strncmp(substr($content, $position), $current, \strlen($current)))
					{
						return;
					}

					$result .= $target;
					$position += \strlen($current);
					$atLineStart = false;

					continue;
				}

				$result .= $character;
				$atLineStart = "\n" === $character;
				$position++;
			}

			// A line break at the very end of the token opens a line that continues in the next
			// one, which again may be unable to carry indentation of its own.
			if ($atLineStart && !$this->carriesOwnIndent($tokens, $index + 1))
			{
				if ('' !== $current)
				{
					return;
				}

				$result .= $target;
			}

			if ($result !== $content)
			{
				$replacements[$index] = $result;
			}
		}

		foreach ($replacements as $index => $content)
		{
			$tokens[$index] = new Token([$tokens[$index]->getId(), $content]);
		}
	}

	/**
	 * Whether the token starting a heredoc body line holds that line's indentation itself. The
	 * closing marker does, and so does any run of literal text; an interpolation does not.
	 */
	private function carriesOwnIndent(Tokens $tokens, int $index): bool
	{
		return $tokens[$index]->isGivenKind([\T_ENCAPSED_AND_WHITESPACE, \T_END_HEREDOC]);
	}

	/**
	 * The indentation of the line a heredoc opens on, as it stands once the block's own lines
	 * have been re-indented.
	 */
	private function openerIndent(Tokens $tokens, int $startIndex): string
	{
		for ($index = $startIndex - 1; $index >= 0; $index--)
		{
			if (!$tokens[$index]->isWhitespace())
			{
				continue;
			}

			$indent = $this->indentAfterLastNewLine($tokens[$index]->getContent());

			if (null !== $indent)
			{
				return $indent;
			}
		}

		return '';
	}

	private function leadingWhitespace(string $content): string
	{
		$trimmed = ltrim($content, " \t");

		return substr($content, 0, \strlen($content) - \strlen($trimmed));
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
