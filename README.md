# Directive

[![Latest Version][ico-version]][link-version]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-build]][link-build]
[![Coverage Status][ico-coverage]][link-coverage]
[![Quality Score][ico-code-quality]][link-code-quality]

Directive helps you to manipulate Nginx configurations in PHP with ease.

This package is compliant with [PSR-1], [PSR-2] and [PSR-4].
If you notice compliance oversights, please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

## Requirements

The following versions of PHP are supported by this version.

* PHP 5.6
* PHP 7.0
* PHP 7.1

## Installation

### With Composer

```bash
$ composer require seiler/directive
```

```php
<?php
require 'vendor/autoload.php';

use Seiler\Directive;
```

### Without Composer

Why are you not using [Composer](http://getcomposer.org/)?
Download [Directive.php](https://github.com/fredericseiler/directive/blob/master/src/Directive.php)
from the repo and save the file into your project path somewhere.

```php
<?php
require '/path/to/Directive.php';

use Seiler\Directive;
```

## Usage

1. Load a Nginx configuration:

```php
$config = file_get_contents('/path/to/nginx/config/file.conf');

$directive = Directive::fromString($config);
```

2. Add your changes:

```php
$directive->server->serverName->value('example.org');
```

3. Save your changes:

```php
file_put_contents('/path/to/nginx/config/file.conf', $directive);
```

## Documentation

*WIP*

## Testing

```bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email frederic@seiler.io instead of using the issue tracker.

## Credits

- [Frederic Seiler][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/seiler/directive.svg?style=flat-square
[ico-license]: https://img.shields.io/packagist/l/seiler/directive.svg?style=flat-square
[ico-build]: https://img.shields.io/travis/fredericseiler/directive/master.svg?style=flat-square
[ico-coverage]: https://img.shields.io/scrutinizer/coverage/g/fredericseiler/directive.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/fredericseiler/directive.svg?style=flat-square

[link-version]: https://packagist.org/packages/seiler/directive
[link-build]: https://travis-ci.org/fredericseiler/directive
[link-coverage]: https://scrutinizer-ci.com/g/fredericseiler/directive/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/fredericseiler/directive
[link-downloads]: https://packagist.org/packages/seiler/directive
[link-author]: https://github.com/fredericseiler
[link-contributors]: ../../contributors
