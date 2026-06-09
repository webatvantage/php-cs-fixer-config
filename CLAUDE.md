# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A small library that packages a `friendsofphp/php-cs-fixer` configuration for Webatvantage projects. It exposes a factory (`Config::default()`) that consumers wire up in their own `.php-cs-fixer.php`. The rule set is verified by formatting itself; the only PHPUnit tests cover the custom fixer in `src/Fixer/` (rule-list changes do not need new tests).

## Common commands

- `composer install` — install dependencies
- `composer format` — run PHP-CS-Fixer against this repo using `.php-cs-fixer.php` (only fixes `src/`)
- `composer test` — run PHPUnit (covers custom fixers under `src/Fixer/`)
- `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --diff` — preview changes without writing

The `format` script is the same one consumers are expected to copy into their own projects (see `README.md`).

## Architecture

Three classes, layered:

- `src/RuleSet/Webatvantage.php` — the single source of truth for the actual rule list. `Webatvantage::make()` returns a `RuleSet` named `"webatvantage"`. Any change to the project's code-style policy happens here.
- `src/RuleSet.php` — an immutable value object holding `(name, rules, customFixers)`. `withRules()` / `withCustomFixers()` return new instances for consumer-side extension.
- `src/Config.php` — `Config::default()` composes the above into a `PhpCsFixer\Config` with the project's non-negotiable settings: tab indent, LF line endings, risky rules allowed, cache enabled, parallel runner auto-detected.

Consumers only import `Webatvantage\PhpCsFixer\Config\Config` and call `Config::default()->setFinder(...)`. They don't touch `RuleSet` or `Webatvantage` directly unless extending the rule set.

## Conventions to preserve

- PHP files in this repo use **tabs** (width 4), no final newline — enforced by `.editorconfig` and matched by `Config::default()`'s `setIndent("\t")`. Don't reformat to spaces.
- Each rule in `Webatvantage::make()` carries an inline comment summarizing what it does. Keep that pattern when adding rules — it's the closest thing to documentation for the rule set.
- Supports PHP `^7.4 || ^8.2` (see `composer.json`). Don't introduce syntax that breaks 7.4 in `src/`.

## CI

`.github/workflows/php-cs-fixer.yml` runs `composer format` on push/PR and auto-commits any fixes via `stefanzweifel/git-auto-commit-action`. That means a PR that's "not formatted" will be silently fixed up rather than failing — don't rely on CI to *reject* unformatted code.