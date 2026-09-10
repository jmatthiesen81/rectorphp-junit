<?php

declare(strict_types=1);

namespace Devable\RectorphpJunit;

use Rector\ChangesReporting\Contract\Output\OutputFormatterInterface;
use Rector\ValueObject\Configuration;
use Rector\ValueObject\ProcessResult;

final class JunitOutputFormatter implements OutputFormatterInterface
{
    public const NAME = 'junit';

    public function __construct(private readonly ?string $outputFile = null)
    {
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function report(ProcessResult $processResult, Configuration $configuration): void
    {
        $fileDiffs = $processResult->getFileDiffs();

        \ksort($fileDiffs);

        $systemErrors = $processResult->getSystemErrors();
        $dom          = new \DOMDocument('1.0', 'UTF-8');

        $dom->formatOutput = true;

        $testsuites = $dom->createElement('testsuites');

        $dom->appendChild($testsuites);

        $testsuite = $dom->createElement('testsuite');

        $testsuite->setAttribute('name', 'rector');
        $testsuite->setAttribute('tests', (string) (\count($fileDiffs) + \count($systemErrors)));
        $testsuite->setAttribute('failures', (string) \count($fileDiffs));
        $testsuite->setAttribute('errors', (string) \count($systemErrors));
        $testsuites->appendChild($testsuite);

        foreach ($fileDiffs as $fileDiff) {
            $filePath = $configuration->isReportingWithRealPath()
                ? ($fileDiff->getAbsoluteFilePath() ?? $fileDiff->getRelativeFilePath())
                : $fileDiff->getRelativeFilePath()
            ;

            $testcase = $dom->createElement('testcase');

            $testcase->setAttribute('classname', $filePath);
            $testcase->setAttribute('name', $filePath);

            $rectorClasses = \implode(', ', $fileDiff->getRectorShortClasses());
            $failure       = $dom->createElement('failure');

            $failure->setAttribute('type', 'RectorDiff');
            $failure->setAttribute('message', $rectorClasses !== '' ? $rectorClasses : 'Rector suggests changes');
            $failure->appendChild($dom->createCDATASection($fileDiff->getDiff()));
            $testcase->appendChild($failure);

            $testsuite->appendChild($testcase);
        }

        foreach ($systemErrors as $systemError) {
            $filePath = $configuration->isReportingWithRealPath()
                ? ($systemError->getAbsoluteFilePath() ?? $systemError->getRelativeFilePath() ?? 'unknown')
                : ($systemError->getRelativeFilePath() ?? 'unknown')
            ;

            $testcase = $dom->createElement('testcase');

            $testcase->setAttribute('classname', $filePath);
            $testcase->setAttribute(
                'name',
                $systemError->getLine() !== null ? \sprintf('%s:%d', $filePath, $systemError->getLine()) : $filePath
            );

            $error = $dom->createElement('error');

            $error->setAttribute('type', $systemError->getRectorShortClass() ?? 'SystemError');
            $error->setAttribute('message', $systemError->getMessage());
            $error->appendChild($dom->createCDATASection($systemError->getMessage()));
            $testcase->appendChild($error);

            $testsuite->appendChild($testcase);
        }

        $xml = $dom->saveXML();

        if ($this->outputFile !== null) {
            \file_put_contents($this->outputFile, $xml);

            return;
        }

        echo $xml;
    }
}
