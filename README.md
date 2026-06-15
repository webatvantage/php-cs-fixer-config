# Webatvantage PHP Code Style Config

## Installation

Run

```sh
composer require --dev webatvantage/php-cs-fixer-config
```

## Usage

Config: `.php-cs-fixer.php`

```php
<?php

declare(strict_types=1);

use Webatvantage\PhpCsFixer\Config\Config;

$finder = PhpCsFixer\Finder::create()
	->in([
		__DIR__ . '/src',
		__DIR__ . '/tests',
	])
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCS(true);

return Config::default()
	->setFinder($finder);

```

### Target PHP version

`Config::default()` accepts the project's target PHP version in `PHP_VERSION_ID`
format and defaults to the runtime version (`PHP_VERSION_ID`). This gates PHP 8.0+
style such as trailing commas in multi-line parameter lists.

That default means formatting on a newer PHP than you support can introduce syntax
that's fatal on your floor (e.g. parameter trailing commas are PHP 8.0+ and fatal on
7.4). If your project supports an older PHP than the one you format on, pin the floor:

```php
return Config::default(70400) // 7.4; 80000 = 8.0, 80100 = 8.1, 80200 = 8.2, …
	->setFinder($finder);
```

## Additional

Composer format script

```json
{
  "scripts": {
    "format": "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php"
  }
}
```

## Development

Custom fixers under `src/Fixer/` are covered by PHPUnit tests.

```sh
composer install
composer test
```
