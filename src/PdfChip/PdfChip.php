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
use pointybeard\Helpers\Functions\Cli;
use pointybeard\PdfChip\Exceptions\PdfChipAssertionFailedException;
use pointybeard\PdfChip\Exceptions\PdfChipException;
use pointybeard\PdfChip\Exceptions\PdfChipExecutionFailedException;

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
        // (guard) pdfChip is not installed or isn't in PATH
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
            throw new PdfChipAssertionFailedException(self::PDFCHIP.' executable cannot be located.');
        }
    }

    private static function assertOptionExists($option): void
    {
        if (false == in_array($option, self::$options)) {
            throw new PdfChipAssertionFailedException("Invalid option '{$option}' specified.");
        }
    }

    private static function assertFileExists($file): void
    {
        if (false == is_readable($file) || false == file_exists($file)) {
            throw new PdfChipAssertionFailedException("File '{$file}' does not exist or is not readable.");
        }
    }

    public static function version(): ?string
    {
        self::runPdfChipWithArgs('--version', $output);

        return $output;
    }

    public static function processString(string $input, string $outputFile, array $arguments = [], ?string &$output = null, ?string &$errors = null): string
    {
        // Save the string contents to a tmp file then call self::process();
        $inputFile = tempnam(sys_get_temp_dir(), self::PDFCHIP);

        // (guard) Unable to create a temporary file name
        if (false == $inputFile) {
            throw new PdfChipException('Unable to generate temporary file.');
        }

        // (guard) Unable to save contents to temporary file
        if (true !== file_put_contents($inputFile, $input)) {
            throw new PdfChipException("Unable to save input string to temporary file {$inputFile}.");
        }

        self::process($inputFile, $outputFile, $arguments, $output, $errors);

        return $inputFile;
    }

    public static function process($inputFiles, string $outputFile, array $arguments = [], ?string &$output = null, ?string &$errors = null): bool
    {
        $args = [];
        foreach ($arguments as $name => $value) {

            // (guard) option name is invalid
            self::assertOptionExists($name);

            if (is_numeric($name)) {
                $args[] = "--{$value}";

                continue;
            }

            if (false == is_array($value)) {
                $value = [$value];
            }

            $args[] = sprintf('--%s="%s"', $name, implode(' ', $value));
        }

        if (false == is_array($inputFiles)) {
            $inputFiles = [$inputFiles];
        }

        // (guard) input file doesn't exist
        array_map('self::assertFileExists', $inputFiles);

        self::runPdfChipWithArgs(sprintf(
            '%s %s %s', // <input> [...] <output> <args>
            implode(' ', $inputFiles),
            $outputFile,
            implode(' ', $args)
        ), $output, $errors);

        return true;
    }
}
