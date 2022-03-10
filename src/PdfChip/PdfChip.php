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

namespace pointybeard\PdfChip;

use Exception;
use pointybeard\Helpers\Functions\Cli;
use pointybeard\Helpers\Functions\Flags;
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

    public const PAGES_REMAINING_UNKNOWN = null;

    public const PAGES_REMAINING_UNLIMITED = 'unlimited';

    public const FLAGS_SKIP_ASSERT_ACTIVATED = 0x0001;

    public const FLAGS_SKIP_ASSERT_INSTALLED = 0x0002;

    // Supported options. This is a mirror of options available directly. See pdfChip --help for more details
    private static $options = ['maxpages', 'underlay' => ['delimiter' => ' '], 'overlay' => ['delimiter' => ' '], 'import', 'zoom-factor', 'dump-static-html', 'use-system-proxy', 'remote-content', 'licenseserver', 'lsmessage', 'timeout-licenseserver', 'licensetype'];

    // Make sure this class cannot be instanciated
    private function __construct()
    {
    }

    private static function runWithArgs(string $args, string &$stdout = null, string &$stderr = null, ?int $flags = null): void
    {
        // (guard) pdfChip is not installed or isn't in PATH
        if (false == Flags\is_flag_set($flags, self::FLAGS_SKIP_ASSERT_INSTALLED)) {
            self::assertInstalled();
        }

        // (guard) pdfChip has not been activated
        if (false == Flags\is_flag_set($flags, self::FLAGS_SKIP_ASSERT_ACTIVATED)) {
            self::assertActivated();
        }

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

    private static function assertActivated(): void
    {
        if (false == self::isActivated()) {
            throw new PdfChipAssertionFailedException(self::EXECUTABLE_NAME.' has not been activated.');
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
        self::runWithArgs('--version', $output, $errors, self::FLAGS_SKIP_ASSERT_ACTIVATED);

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

    public static function remainingPagesPerHour()
    {
        self::runWithArgs('--status | grep "Pages per hour:"', $output, $errors, self::FLAGS_SKIP_ASSERT_ACTIVATED);

        // Examples of possible values for 'Pages per hour':
        // 1. ""
        // 2. Pages per hour: unlimited (unlimited remaining)
        // 3. Pages per hour: 1000 (523 remaining)

        preg_match("@\(([^ ]+) remaining\)$@", trim((string) $output), $matches);

        // (guard) unlimited pages
        if ('unlimited' == $matches[1]) {
            return self::PAGES_REMAINING_UNLIMITED;
        }

        // (guard) nothing was matched or its not something we recognise
        if (false == isset($matches[1]) || false == is_numeric($matches[1])) {
            return self::PAGES_REMAINING_UNKNOWN;
        }

        return (int) $matches[1];
    }

    public static function isActivated(): bool
    {
        self::runWithArgs('--status | grep "Activation:"', $output, $errors, self::FLAGS_SKIP_ASSERT_ACTIVATED);

        // Examples of possible values for 'Activation':
        // 1. ""
        // 2. Activation: 7E3HF8K...G795CNFMS	callas pdfChip S	/home/.../License.txt
        // 3. Activation: None

        preg_match('@^Activation: (.+)$@', trim((string) $output), $matches);

        // (guard) activation is 'None'
        if (false == isset($matches[1]) || empty($matches[1]) || 'none' == strtolower($matches[1])) {
            return false;
        }

        return true;
    }

    public static function processHtmlString($input, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null, ?int $flags = null): string
    {
        return self::processString($input, self::STRING_TYPE_HTML, $outputFile, $options, $output, $errors, $flags);
    }

    public static function processSvgString($input, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null, ?int $flags = null): string
    {
        return self::processString($input, self::STRING_TYPE_SVG, $outputFile, $options, $output, $errors, $flags);
    }

    public static function processString($input, $inputFileType, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null, ?int $flags = null): string
    {

        // (guard) $input is not a string or array
        if(false == is_string($input) && false == is_array($input)) {
            throw new PdfChipAssertionFailedException('input must be either a string or an array of strings');
        }

        // (guard) $inputFileType is not a string or array
        if(false == is_string($inputFileType) && false == is_array($inputFileType)) {
            throw new PdfChipAssertionFailedException('inputFileType must be either a string or an array of strings');
        }

        if(false == is_array($input)) {
            $input = [$input];
        }

        if(false == is_array($inputFileType)) {
            $inputFileType = array_pad([], count($input), $inputFileType);
        }

        $inputFiles = [];

        foreach($input as $ii => $contents) {

            // (guard) $contents is not a string
            if(false == is_string($contents)) {
                throw new PdfChipAssertionFailedException("input.{$ii} is not a valid string");
            }

            // (guard) $inputFileType[$ii] is not a string
            if(false == is_string($inputFileType[$ii])) {
                throw new PdfChipAssertionFailedException("inputFileType.{$ii} is not a valid string");
            }

            // Save the string contents to a tmp file then call self::process();
            $inputFiles[$ii] = tempnam(sys_get_temp_dir(), self::EXECUTABLE_NAME);

            // (guard) Unable to create a temporary file name
            if (false == $inputFiles[$ii]) {
                throw new PdfChipException('Unable to generate temporary file.');
            }

            // pdfChip requires a file extension otherwise it will refuse to load the file.
            // So, rename the temp file to include the specified extension
            if (false == rename($inputFiles[$ii], $inputFiles[$ii] .= ".{$inputFileType[$ii]}")) {
                throw new PdfChipException("Unable to generate temporary file. Failed to add .{$inputFileType[$ii]} extension.");
            }

            // (guard) Unable to save contents to temporary file
            if (false === file_put_contents($inputFiles[$ii], $contents)) {
                throw new PdfChipException("Unable to save input string to temporary file {$inputFiles[$ii]}.");
            }
        }

        return self::process($inputFiles, $outputFile, $options, $output, $errors, $flags);
    }

    public static function process($inputFiles, string $outputFile, array $options = [], ?string &$output = null, ?string &$errors = null, ?int $flags = null): string
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
