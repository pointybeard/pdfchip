<?php

declare(strict_types=1);

/*
 * This file is part of the "PHP Wrapper for callas pdfChip" repository.
 *
 * Copyright 2021-22 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

namespace pointybeard\PdfChip\Exceptions;

use Throwable;

class PdfChipAssertionFailedException extends PdfChipException
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct("Assertion failed. Returned: {$message}", $code, $previous);
    }
}
