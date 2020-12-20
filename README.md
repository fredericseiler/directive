# Directive

[![Software License][ico-license]](LICENSE.md)

Directive helps you to manipulate Nginx configurations in PHP with ease.

## Requirements

The following versions of PHP are supported by this version.

* PHP 7.3
* PHP 8.0

## Installation

```bash
$ composer require seiler/directive
```

```php
<?php

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

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-license]: https://img.shields.io/packagist/l/directive/directive.svg?style=flat-square
