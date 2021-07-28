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

namespace pointybeard\PdfChip;

use Exception;
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
        // (guard) PdfChip is not installed or isn't in PATH
        self::assertPdfChipInstalled();

        $command = sprintf('%s %s', self::which(self::PDFCHIP), $args);

        try {
            self::runCommand($command, $stdout, $stderr, $exitCode);
        } catch (Exception $ex) {
            throw new PdfChipExecutionFailedException($args, $stderr, $exitCode, 0, $ex);
        }
    }

    private static function runCommand(string $command, string &$stdout = null, string &$stderr = null, int &$exitCode = null): void
    {
        $pipes = null;
        $exitCode = null;

        $proc = proc_open(
            "{$command};echo $? >&3",
            [
                0 => ['pipe', 'r'], // STDIN
                1 => ['pipe', 'w'], // STDOUT
                2 => ['pipe', 'w'], // STDERR
                3 => ['pipe', 'w'], // Used to capture the exit code
            ],
            $pipes,
            getcwd(),
            null
        );

        // Close STDIN stream
        fclose($pipes[0]);

        // (guard) proc_open failed to return a resource
        if (false == is_resource($proc)) {
            throw new PdfChipException("Failed to run command {$command}. proc_open() returned FALSE.");
        }

        // Get contents of STDOUT and close stream
        $stdout = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);

        // Get contents od STDERR and close stream
        $stderr = trim(stream_get_contents($pipes[2]));
        fclose($pipes[2]);

        // Grab the exit code then close the stream
        if (false == feof($pipes[3])) {
            $exitCode = (int) trim(stream_get_contents($pipes[3]));
        }
        fclose($pipes[3]);

        // Close the process we created
        proc_close($proc);

        // (guard) proc_close return indiciated a failure
        if (0 != $exitCode) {
            // There was some kind of error. Throw an exception.
            // If STDERR is empty, in effort to give back something
            // meaningful, grab contents of STDOUT instead
            throw new PdfChipException("Failed to run command {$command}. Returned: ".(true == empty(trim($stderr)) ? $stdout : $stderr));
        }
    }

    private static function which(string $prog): ?string
    {
        // (guard) $prog is in the $paths array
        if (true == array_key_exists($prog, self::$paths) && null != self::$paths[$prog]) {
            return self::$paths[$prog];
        }

        try {
            self::runCommand("which {$prog}", $output);
            self::$paths[$prog] = $output;
        } catch (Exception $ex) {
            $output = null;
        }

        return $output;
    }

    private static function assertPdfChipInstalled(): void
    {
        if (null == self::which(self::PDFCHIP)) {
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
