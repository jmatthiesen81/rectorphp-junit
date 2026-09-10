<?php

declare(strict_types=1);

namespace Devable\RectorphpJunit\Tests;

use Devable\RectorphpJunit\JunitOutputFormatter;
use PHPUnit\Framework\TestCase;
use Rector\ChangesReporting\ValueObject\RectorWithLineChange;
use Rector\ValueObject\Configuration;
use Rector\ValueObject\Error\SystemError;
use Rector\ValueObject\ProcessResult;
use Rector\ValueObject\Reporting\FileDiff;

final class JunitOutputFormatterTest extends TestCase
{
    public function testGetName(): void
    {
        self::assertSame('junit', (new JunitOutputFormatter())->getName());
    }

    public function testReportToStdoutWithFileDiffAndSystemError(): void
    {
        $fileDiff = new FileDiff(
            'src/Foo.php',
            "--- Original\n+++ New\n@@ -1,1 +1,1 @@\n-old\n+new\n",
            'console formatted diff',
            [new RectorWithLineChange(\stdClass::class, 1)]
        );

        $systemError   = new SystemError('Parse error', 'src/Broken.php', 12, \stdClass::class);
        $processResult = new ProcessResult([$systemError], [$fileDiff], 1);
        $configuration = new Configuration(reportingWithRealPath: false);

        $formatter = new JunitOutputFormatter();

        \ob_start();

        $formatter->report($processResult, $configuration);

        $xml = \ob_get_clean();
        $dom = new \DOMDocument();

        self::assertTrue($dom->loadXML($xml), 'Produced output must be well-formed XML');

        $testsuite = $dom->getElementsByTagName('testsuite')->item(0);

        self::assertNotNull($testsuite);
        self::assertSame('2', $testsuite->getAttribute('tests'));
        self::assertSame('1', $testsuite->getAttribute('failures'));
        self::assertSame('1', $testsuite->getAttribute('errors'));

        $failures = $dom->getElementsByTagName('failure');

        self::assertSame(1, $failures->length);
        self::assertStringContainsString('-old', $failures->item(0)->textContent);
        self::assertStringContainsString('+new', $failures->item(0)->textContent);

        $errors = $dom->getElementsByTagName('error');

        self::assertSame(1, $errors->length);
        self::assertStringContainsString('Parse error', $errors->item(0)->textContent);

        $testcases = $dom->getElementsByTagName('testcase');

        self::assertSame(2, $testcases->length);
        self::assertSame('src/Foo.php', $testcases->item(0)->getAttribute('classname'));
        self::assertSame('src/Broken.php:12', $testcases->item(1)->getAttribute('name'));
    }

    public function testReportWithEmptyProcessResultProducesEmptyTestsuite(): void
    {
        $processResult = new ProcessResult([], [], 0);
        $configuration = new Configuration();

        $formatter = new JunitOutputFormatter();

        \ob_start();

        $formatter->report($processResult, $configuration);

        $xml = \ob_get_clean();
        $dom = new \DOMDocument();

        self::assertTrue($dom->loadXML($xml));

        $testsuite = $dom->getElementsByTagName('testsuite')->item(0);

        self::assertSame('0', $testsuite->getAttribute('tests'));
        self::assertSame(0, $dom->getElementsByTagName('testcase')->length);
    }

    public function testReportToFileWritesValidXml(): void
    {
        $fileDiff      = new FileDiff('src/Foo.php', "--- a\n+++ b\n", 'formatted', []);
        $processResult = new ProcessResult([], [$fileDiff], 1);
        $configuration = new Configuration();

        $outputFile = \tempnam(\sys_get_temp_dir(), 'rectorphp-junit-test-');

        self::assertIsString($outputFile);

        try {
            $formatter = new JunitOutputFormatter($outputFile);

            $formatter->report($processResult, $configuration);

            $dom = new \DOMDocument();

            self::assertTrue($dom->load($outputFile));
            self::assertSame(1, $dom->getElementsByTagName('testcase')->length);
        } finally {
            \unlink($outputFile);
        }
    }
}
