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
    private const EXECUTABLE_NAME = 'pdfChip';

    public const OPTION_TYPE_LONG = '--';

    public const OPTION_TYPE_SHORT = '-';

    public const OPTION_DELIMITER_DEFAULT = ',';

    public const STRING_TYPE_SVG = 'svg';

    public const STRING_TYPE_HTML = 'html';

    // Supported options. This is a mirror of options available directly. See pdfChip --help for more details
    private static $options = ['maxpages', 'underlay' => ['delimiter' => ' '], 'overlay' => ['delimiter' => ' '], 'import', 'zoom-factor', 'dump-static-html', 'use-system-proxy', 'remote-content', 'licenseserver', 'lsmessage', 'timeout-licenseserver', 'licensetype'];

    // Make sure this class cannot be instanciated
    private function __construct()
    {
    }

    private static function runWithArgs(string $args, string &$stdout = null, string &$stderr = null): void
    {
        // (guard) pdfChip is not installed or isn't in PATH
        self::assertInstalled();

        $command = sprintf('%s %s', Cli\which(self::EXECUTABLE_NAME), $args);

        try {
            Cli\run_command($command, $stdout, $stderr, $exitCode);
        } catch (Exception $ex) {
            throw new PdfChipExecutionFailedException($args, $stderr, $exitCode, 0, $ex);
        }
    }

    private static function assertInstalled(): void
    {
        if (null == Cli\which(self::EXECUTABLE_NAME)) {
            throw new PdfChipAssertionFailedException(self::EXECUTABLE_NAME.' executable cannot be located.');
        }
    }

    private static function assertOptionValid($option): void
    {
        if (null == self::getOption($option)) {
            throw new PdfChipAssertionFailedException("Unsupported option '{$option}' specified.");
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
        self::runWithArgs('--version', $output);

        return $output;
    }

    public static function getOptions(): array
    {
        return self::$options;
    }

    private static function getOptionType(string $name): string
    {
        return 1 == strlen($name) ? self::OPTION_TYPE_SHORT : self::OPTION_TYPE_LONG;
    }

    private static function getOption($name, bool $resolveAlias = true)
    {

        // (guard) option doesn't exist
        if (false == in_array($name, self::$options) && false == array_key_exists($name, self::$options)) {
            return null;
        }

        // (guard) simple option with no additional properties
        if (false == array_key_exists($name, self::$options)) {
            return $name;
        }

        $o = self::$options[$name];

        return false == $resolveAlias || false == isset($o['aliasFor']) ? $o : self::getOption($o['aliasFor'], false);
    }

    private static function getOptionDelimiter(string $name): string
    {
        return self::getOption($name)['delimiter'] ?? self::OPTION_DELIMITER_DEFAULT;
    }

    private static function generateOptionKeyValueString(string $name, $value): string
    {
        // (guard) option name is invalid
        self::assertOptionValid($name);

        // This will give us either the short (-) or long (--) prefix to use later
        $type = self::getOptionType($name);

        // (guard) value is null
        if (null == $value) {
            return $type.$name;
        }

        return $type.sprintf(
            self::OPTION_TYPE_SHORT == $type
                ? '%s "%s"'
                : '%s="%s"',
            $name,
            implode(self::getOptionDelimiter($name), false == is_array($value) ? [$value] : $value) //imploding an array gives us support for multiple values as input
        );
    }

    public static function processHtmlString(string $input, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null): string
    {
        return self::processString($input, self::STRING_TYPE_HTML, $outputFile, $options, $output, $errors);
    }

    public static function processSvgString(string $input, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null): string
    {
        return self::processString($input, self::STRING_TYPE_SVG, $outputFile, $options, $output, $errors);
    }

    public static function processString(string $input, string $inputFileType, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null): string
    {
        // Save the string contents to a tmp file then call self::process();
        $inputFile = tempnam(sys_get_temp_dir(), self::EXECUTABLE_NAME);

        // (guard) Unable to create a temporary file name
        if (false == $inputFile) {
            throw new PdfChipException('Unable to generate temporary file.');
        }

        // pdfChip requires a file extension otherwise it will refuse to load the file.
        // So, rename the temp file to include the specified extension
        if (false == rename($inputFile, $inputFile .= $inputFileType)) {
            throw new PdfChipException("Unable to generate temporary file. Failed to add .{$inputFileType} extension.");
        }

        // (guard) Unable to save contents to temporary file
        if (false === file_put_contents($inputFile, $input)) {
            throw new PdfChipException("Unable to save input string to temporary file {$inputFile}.");
        }

        return self::process($inputFile, $outputFile, $options, $output, $errors);
    }

    public static function process($inputFiles, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null): string
    {
        $opts = [];
        foreach ($options as $name => $value) {
            // (guard) $name is numeric
            if (true == is_numeric($name)) {
                $name = $value;
                $value = null;
            }
            $opts[] = self::generateOptionKeyValueString($name, $value);
        }

        if (false == is_array($inputFiles)) {
            $inputFiles = [$inputFiles];
        }

        // (guard) input file doesn't exist
        array_map('self::assertFileExists', $inputFiles);

        self::runWithArgs(sprintf(
            '%s %s %s', // <input> [...] <output> <options>
            implode(' ', $inputFiles),
            $outputFile,
            implode(' ', $opts)
        ), $output, $errors);

        return $outputFile;
    }
}
