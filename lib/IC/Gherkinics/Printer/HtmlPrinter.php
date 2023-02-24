<?php
/**
 * @copyright 2013 Instaclick Inc.
 */
namespace IC\Gherkinics\Printer;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * File Printer
 *
 * This printer produces reports in HTML and export them to a specified location.
 *
 * @author Juti Noppornpitak <jnopporn@shiroyuki.com>
 */
class HtmlPrinter
{
    /**
     * @var string
     */
    private $scannedPath;

    /**
     * @var string
     */
    private $resourcePath;

    /**
     * @var string
     */
    private $outputPath;

    /**
     * @var Environment
     */
    private $environment;

    /**
     * Constructor
     *
     * @param string $loaderPath
     * @param string $resourcePath
     * @param string $outputPath
     * @param string $scannedPath
     * @param string $cachePath
     */
    public function __construct($loaderPath, $resourcePath, $outputPath, $scannedPath, $cachePath = null)
    {
        $environmentOption = $cachePath === null
            ? array()
            : array('cache' => $cachePath);

        $this->scannedPath  = $scannedPath;
        $this->resourcePath = $resourcePath;
        $this->outputPath   = $outputPath;
        $this->environment  = new Environment(
            new FilesystemLoader($loaderPath),
            $environmentOption
        );

        if (file_exists($outputPath) && ! is_dir($outputPath)) {
            throw new \RuntimeException('The path (' . $outputPath . ') exists but is not a directory.');
        }

        if ( ! file_exists($outputPath)) {
            mkdir($outputPath) or die('Unable to create the report folder');
        }
    }

    /**
     * Display feedback
     *
     * @param array $pathToFeedbackMap
     */
    public function doPrint(array $pathToFeedbackMap)
    {
        $pathToDataMap    = array();
        $prefixLength     = strlen($this->scannedPath) + 1;
        $fileScanningMode = count($pathToFeedbackMap) === 1;

        foreach ($pathToFeedbackMap as $filePath => $lineToViolationsMap) {
            $relativePath      = $fileScanningMode ? $filePath : substr($filePath, $prefixLength);
            $violatedLineCount = count($lineToViolationsMap->all());

            $segmentList = array();

            preg_match(
                '/(?P<directory>.+)\/(?P<name>[^\.]+)\.(?P<extension>.+)$/',
                $relativePath,
                $segmentList
            );

            if ( ! $segmentList) {
                preg_match(
                    '/(?P<name>[^\.]+)\.(?P<extension>.+)$/',
                    $relativePath,
                    $segmentList
                );

                $segmentList['directory'] = '';
            }

            $pathToDataMap[$filePath] = array(
                'path'              => $relativePath,
                'directory'         => $segmentList['directory'],
                'name'              => $segmentList['name'],
                'extension'         => $segmentList['extension'],
                'hash'              => sha1($relativePath),
                'violatedLineCount' => $violatedLineCount,
            );
        }

        $this->printSummary($pathToFeedbackMap, $pathToDataMap);
        $this->printMultipleReports($pathToFeedbackMap, $pathToDataMap);
        $this->copyStaticResource();
    }

    /**
     * Copy static resource
     */
    private function copyStaticResource()
    {
        exec(sprintf('cp -vr %s %s', $this->resourcePath, $this->outputPath));
    }

    /**
     * Render template
     *
     * @param string $templatePath
     * @param array  $contextVariableMap
     *
     * @return string
     */
    private function render($templatePath, $contextVariableMap = array())
    {
        return $this->environment->render($templatePath, $contextVariableMap);
    }

    /**
     * Export the rendered report to file
     *
     * @param string $templatePath
     * @param string $outputName
     * @param array  $contextVariableMap
     */
    private function export($templatePath, $outputName, $contextVariableMap = array())
    {
        $filePath = $this->outputPath . '/' . $outputName;
        $output   = $this->render($templatePath, $contextVariableMap);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        file_put_contents($filePath, $output);
    }

    /**
     * Print summary
     *
     * @param array $pathToFeedbackMap
     * @param array $pathToDataMap
     */
    private function printSummary(array $pathToFeedbackMap, array $pathToDataMap)
    {
        $relativePathToDataMap    = array();
        $maximumViolatedLineCount = 0;

        foreach ($pathToDataMap as $filePath => $dataMap) {
            if ($maximumViolatedLineCount < $dataMap['violatedLineCount']) {
                $maximumViolatedLineCount = $dataMap['violatedLineCount'];
            }

            $relativePathToDataMap[$dataMap['path']] = $dataMap;
        }

        $this->export(
            'index.html.twig',
            'index.html',
            array(
                'relativePathToDataMap'    => $relativePathToDataMap,
                'maximumViolatedLineCount' => $maximumViolatedLineCount,
            )
        );
    }

    /**
     * Print multiple per-file reports
     *
     * @param array $pathToFeedbackMap
     * @param array $pathToDataMap
     */
    private function printMultipleReports(array $pathToFeedbackMap, array $pathToDataMap)
    {
        foreach ($pathToDataMap as $filePath => $dataMap) {
            $this->export(
                'file_feedback.html.twig',
                $dataMap['hash'] . '.html',
                array(
                    'feedbackMap' => $pathToFeedbackMap[$filePath],
                    'dataMap'     => $dataMap,
                )
            );
        }
    }
}
