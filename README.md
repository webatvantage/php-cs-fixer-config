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

## Additional

Composer format script

```json
{
  "scripts": {
    "format": "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php"
  }
}
```
