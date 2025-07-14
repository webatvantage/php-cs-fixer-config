<?php

namespace Webatvantage\PhpCsFixer\Config;

class RuleSet
{
	private string $name;

	private array $rules;

	private array $customFixers;

	private function __construct(string $name, array $rules = [], array $customFixers = [])
	{
		$this->name = $name;
		$this->rules = $rules;
		$this->customFixers = $customFixers;
	}

	/**
	 * @param string $name
	 * @param array $rules
	 * @param array $customFixers
	 *
	 * @return static
	 */
	public static function make(string $name, array $rules, array $customFixers = []): self
	{
		return new static($name, $rules, $customFixers);
	}

	/**
	 * Returns the name of the rule set.
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Returns rules along with their configuration.
	 */
	public function rules(): array
	{
		return $this->rules;
	}

	/**
	 * Returns custom fixers required by this rule set.
	 */
	public function customFixers(): array
	{
		return $this->customFixers;
	}

	/**
	 * Returns a new rule set with custom fixers.
	 *
	 * @return static
	 */
	public function withCustomFixers(array $customFixers): self
	{
		return new static($this->name, $this->rules, array_merge($this->customFixers, $customFixers));
	}

	/**
	 * Returns a new rule set with merged rules.
	 *
	 * @return static
	 */
	public function withRules(array $rules): self
	{
		return new static($this->name, $this->rules, array_merge($this->rules, $rules));
	}
}
