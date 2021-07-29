<?php

declare(strict_types=1);

/*
 * This file is part of the "PHP Wrapper for callas pdfChip" repository.
 *
 * Copyright 2021 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

namespace pointybeard\PdfChip;

use Exception;
use pointybeard\PdfChip\Exceptions\PdfChipException;
use pointybeard\PdfChip\Exceptions\PdfChipExecutionFailedException;
use pointybeard\Helpers\Functions\Cli;

class PdfChip
{
    // This is the name of the pdfChip executable
    private const PDFCHIP = 'pdfChip';

    private static $paths = [];

    // Supported options. This is a mirror of options available directly. See pdfChip --help for more details
    private static $options = [
        'maxpages',
        'underlay',
        'overlay',
        'import',
        'zoom-factor',
        'dump-static-html',
        'use-system-proxy',
        'remote-content',
        'licenseserver',
        'lsmessage',
        'timeout-licenseserver',
        'licensetype',
    ];

    // Make sure this class cannot be instanciated
    private function __construct()
    {
    }

    private static function runPdfChipWithArgs(string $args, string &$stdout = null, string &$stderr = null): void
    {
        // (guard) PdfChip is not installed or isn't in PATH
        self::assertPdfChipInstalled();

        $command = sprintf('%s %s', Cli\which(self::PDFCHIP), $args);

        try {
            Cli\run_command($command, $stdout, $stderr, $exitCode);
        } catch (Exception $ex) {
            throw new PdfChipExecutionFailedException($args, $stderr, $exitCode, 0, $ex);
        }
    }

    private static function assertPdfChipInstalled(): void
    {
        if (null == Cli\which(self::PDFCHIP)) {
            throw new PdfChipException('PdfChip executable cannot be located.');
        }
    }

    private static function assertOptionExists($option): void
    {
        if (false == in_array($option, self::$options)) {
            throw new PdfChipException("Invalid option '{$option}' specified.");
        }
    }

    private static function assertFileExists($file): void
    {
        if (false == is_readable($file) || false == file_exists($file)) {
            throw new PdfChipException("File '{$file}' does not exist or is not readable.");
        }
    }

    public static function version(): ?string
    {
        self::runPdfChipWithArgs('--version', $output);

        return $output;
    }

    public static function process($inputFile, string $outputFile, array $arguments = [], ?string &$output = null, ?string &$errors = null): bool
    {
        $args = [];
        foreach ($arguments as $name => $value) {
            if (is_numeric($name)) {

                // (guard) Option name is invalid
                self::assertOptionExists($name);

                $args[] = "--{$value}";

                continue;
            }

            if (false == is_array($value)) {
                $value = [$value];
            }

            // (guard) Option name is invalid
            self::assertOptionExists($name);

            $args[] = sprintf('--%s="%s"', $name, implode(' ', $value));
        }

        if (false == is_array($inputFile)) {
            $inputFile = [$inputFile];
        }

        // (guard) Input file doesn't exist
        array_map('self::assertFileExists', $inputFile);

        self::runPdfChipWithArgs(sprintf(
            '%s %s %s', // <input> [...] <output> <args>
            implode(' ', $inputFile),
            $outputFile,
            implode(' ', $args)
        ), $output, $errors);

        return true;
    }
}
