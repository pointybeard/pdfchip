<?php

declare(strict_types=1);

/*
 * This file is part of the "PHP Wrapper for callas pdfToolbox CLI" repository.
 *
 * Copyright 2021 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

namespace pointybeard\PdfChip\Exceptions;

use Throwable;

class PdfChipExecutionFailedException extends PdfChipException
{
    private $error = null;

    private $args = null;

    private $exitCode = null;

    public function __construct(string $args, string $error, int $exitCode, int $code = 0, ?Throwable $previous = null)
    {
        $this->args = $args;
        $this->error = $error;
        $this->exitCode = $exitCode;

        parent::__construct(sprintf('Failed running pdfChip with arguments %s. Exited with error code %d. Returned: %s', $args, $exitCode, $error), $code, $previous);
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getArgs(): string
    {
        return $this->args;
    }

    public function getError(): string
    {
        return $this->error;
    }
}
