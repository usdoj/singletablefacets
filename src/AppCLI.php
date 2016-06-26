<?php
/**
 * @file
 * Class for preparing for usage of SingleTableFacets from the command line.
 */

namespace USDOJ\SingleTableFacets;

class AppCLI extends \USDOJ\SingleTableFacets\App
{
    private $action;
    private $sourceFile;

    public function __construct() {

        $configFile = $argv[1];
        $sourceFile = $argv[2];
        $action = $argv[3];

        if (empty($configFile) || empty($sourceFile) || empty($action)) {
            throw new Exception($this->getUsage());
        }

        if (!is_file($configFile)) {
            throw new Exception(sprintf('Config file not found at %s', $configFile));
        }

        if (!is_file($sourceFile)) {
            throw new Exception(sprintf('Source data not found at %s', $sourceFile));
        }

        if (!in_array($action, $this->getAllowedActions())) {
            throw new Exception(sprintf('Invalid action: %s. Allowed actions: %s', $action, implode(' ', $allowedActions)));
        }

        $this->action = $action;
        $this->sourceFile = $sourceFile;

        $config = new \USDOJ\SingleTableFacets\Config($configFile);
        parent::__construct($config);
    }

    private function getAction() {
        return $this->action;
    }

    private function getSourceFile() {
        return $this->sourceFile;
    }

    public function run() {

        if ('crawl' == $this->getAction()) {
            $this->crawl();
        }

        else {
            $this->refresh();
        }
    }

    private function crawl() {
        $crawler = new \USDOJ\SingleTableFacets\KeywordCrawler($this);
        $crawler->crawlKeywords();
    }

    private function getUsage() {
        $ret = 'Usage: singletablefacets [config file] [source file] [action]' . PHP_EOL;
        $ret .= '  config file: Path to .yml configuration file' . PHP_EOL;
        $ret .= '  source file: Path to source data, .csv or .xlsx' . PHP_EOL;
        $ret .= '  action: Allowed choices:' . PHP_EOL;
        $ret .= '    - refresh: Refresh all data from source' . PHP_EOL;
        $ret .= '    - crawl: Perform one batch of crawling of remote keyword files' . PHP_EOL;
        return $ret;
    }

    private function getAllowedActions() {
        return array('refresh', 'crawl');
    }
}
