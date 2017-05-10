<?php
/**
 * @file
 * Class for preparing for usage of SingleTableFacets from the command line.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class AppCLI
 * @package USDOJ\SingleTableFacets
 *
 * A class for the command-line version of this app.
 */
class AppCLI extends \USDOJ\SingleTableFacets\App
{
    /**
     * The source file (.csv) to import.
     *
     * @var string
     */
    private $sourceFile;

    /**
     * AppCLI constructor.
     *
     * @param \USDOJ\SingleTableFacets\Config $args
     *   The config object for this app.
     *
     * @throws \Exception
     */
    public function __construct($args) {

        $configFile = empty($args[1]) ? '' : $args[1];
        $sourceFile = empty($args[2]) ? '' : $args[2];

        if (empty($configFile) || empty($sourceFile)) {
            die($this->getUsage());
        }

        if (!is_file($configFile)) {
            die(sprintf('Config file not found at %s', $configFile));
        }

        $this->sourceFile = $sourceFile;
        $config = new \USDOJ\SingleTableFacets\Config($configFile);
        parent::__construct($config);
    }

    /**
     * Get the source file to import.
     *
     * @return string
     */
    private function getSourceFile() {
        return $this->sourceFile;
    }

    /**
     * Perform the import.
     */
    public function run() {

        $importer = new \USDOJ\SingleTableImporter\Importer($this->getConfig(), $this->getSourceFile());

        // First do a test run to make sure there will not be an error.
        $importer->testRun();

        // Next import the source data.
        $importer->run();

        // Now crawl for remote keywords.
        $crawler = new \USDOJ\SingleTableFacets\KeywordCrawler($this);
        $crawler->run();
    }

    /**
     * Get the example usage for this CLI command.
     *
     * @return string
     */
    private function getUsage() {
        $ret = 'Usage: singletablefacets [config file] [source file]' . PHP_EOL;
        $ret .= '  config file: Path to .yml configuration file' . PHP_EOL;
        $ret .= '  source file: Path to source data (must be a CSV or Excel file with a header matching the database columns)' . PHP_EOL;
        return $ret;
    }
}
