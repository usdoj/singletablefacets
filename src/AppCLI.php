<?php
/**
 * @file
 * Class for preparing for usage of SingleTableFacets from the command line.
 */

namespace USDOJ\SingleTableFacets;

class AppCLI extends \USDOJ\SingleTableFacets\App
{
    private $sourceFile;

    public function __construct($args) {

        $configFile = empty($args[1]) ? '' : $args[1];
        $sourceFile = empty($args[2]) ? '' : $args[2];

        if (empty($configFile) || empty($sourceFile)) {
            die($this->getUsage());
        }

        if (!is_file($configFile)) {
            die(sprintf('Config file not found at %s', $configFile));
        }

        if (!is_file($sourceFile)) {
            die(sprintf('Source data not found at %s', $sourceFile));
        }

        $this->sourceFile = $sourceFile;
        $config = new \USDOJ\SingleTableFacets\Config($configFile);
        parent::__construct($config);
    }

    private function getSourceFile() {
        return $this->sourceFile;
    }

    public function run() {

        // First import the source data.
        $importer = new \USDOJ\CsvToMysql\Importer($this->getConfig(), $this->getSourceFile());
        $importer->run();

        // Next make sure there is nothing left in our special keyword column.
        $clearer = new \USDOJ\SingleTableFacets\KeywordClearer($this);
        $clearer->run();

        // Now crawl for remote keywords.
        $crawler = new \USDOJ\SingleTableFacets\KeywordCrawler($this);
        $crawler->run();
    }

    private function getUsage() {
        $ret = 'Usage: singletablefacets [config file] [source file]' . PHP_EOL;
        $ret .= '  config file: Path to .yml configuration file' . PHP_EOL;
        $ret .= '  source file: Path to source data (must be a csv file with a header matching the database columns)' . PHP_EOL;
        return $ret;
    }
}
