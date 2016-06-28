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

    public function __construct($args) {

        $configFile = empty($args[1]) ? '' : $args[1];
        $sourceFile = empty($args[2]) ? '' : $args[2];
        $action = empty($args[3]) ? '' : $args[3];

        if (empty($configFile) || empty($sourceFile) || empty($action)) {
            throw new \Exception($this->getUsage());
        }

        if (!is_file($configFile)) {
            throw new \Exception(sprintf('Config file not found at %s', $configFile));
        }

        if (!is_file($sourceFile)) {
            throw new \Exception(sprintf('Source data not found at %s', $sourceFile));
        }

        if (!in_array($action, $this->getAllowedActions())) {
            throw new \Exception(sprintf('Invalid action: %s. Allowed actions: %s', $action, implode(' ', $allowedActions)));
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
        $crawler->run();
    }

    private function refresh() {

        // First import the source data.
        $importer = new \USDOJ\SingleTableFacets\Importer($this, $this->getSourceFile());
        $importer->run();

        // Next make sure there is nothing left in our special keyword column.
        $clearer = new \USDOJ\SingleTableFacets\KeywordClearer($this);
        $clearer->run();

        // Next repeatedly crawl in new threads.
        $command = 'something';
        // As a safety check, don't do more than the number of rows in the db,
        // divided by 10. (The row limit per run is 20, so this is plenty).
        /*
        $maxRuns = $this->getDb()->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->getTable())
            ->execute()
            ->fetchColumn();
        $maxRuns = $maxRuns / 10;
        $crawlResult = exec($command);
        while ($crawlResult == 0 && $maxRuns >= 0) {
            $maxRuns -= 1;
            $crawlResult = exec($command);
        }
        */

        // Finally consolidate keywords from other columns into our main column.
        $consolidator = new \USDOJ\SingleTableFacets\KeywordConsolidator($this);
        $consolidator->run();
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
