# PHP Wrapper for callas pdfChip

A PHP wrapper class for [callas pdfChip](https://www.callassoftware.com/en/products/pdfchip).

## Installation

This library is installed via [Composer](http://getcomposer.org/). To install, use `composer require pointybeard/pdfchip` or add `"pointybeard/pdfchip": "^1.0"` to your `composer.json` file.

And run composer to update your dependencies:

    $ curl -s http://getcomposer.org/installer | php
    $ php composer.phar update

### Using the Mock Executable

If the system you are running code on does not have pdfChip installed or it cannot be activiated, use the mock command `bin/pdfchip` instead. It emulates `--help`, `--status`, and processing input html.

To do this, make `bin/pdfchip` executable and place it in your system path (e.g. symlinking to `/usr/local/bin`).

Now, the pdfchip library will call this mock executable instead. Note you will always get the same pdf output regardless of input.

### Requirements

This library requires pdfChip and PHP 7.4 or later.

## Usage

Here is a basic usage example:

```php
<?php

declare(strict_types=1);

include "vendor/autoload.php";

use pointybeard\PdfChip;

// Print version information
print PdfChip\PdfChip::version() . PHP_EOL;

// Generate a PDF from input files
PdfChip\PdfChip::process(
    ["test.html", "test2.html"],
    "test.pdf",
    [
        "maxpages" => 1,
        "zoom-factor" => 3,
        "remote-content" => "off",
        "licensetype" => "all",
        "use-system-proxy",
        "underlay" => ["file.pdf", "file2.pdf"]
    ],
    $o,
    $e
);

var_dump($o, $e);
```

See `pdfChip --help` on the command line to see help information for each of the options it supports.

## Support

If you believe you have found a bug, please report it using the [GitHub issue tracker](https://github.com/pointybeard/pdfchip/issues),
or better yet, fork the library and submit a pull request.

## Contributing

We encourage you to contribute to this project. Please check out the [Contributing documentation](https://github.com/pointybeard/pdfchip/blob/master/CONTRIBUTING.md) for guidelines about how to get involved.

## License

"PHP Wrapper for callas pdfChip" is released under the [MIT License](http://www.opensource.org/licenses/MIT).
