<?php
/**
 * @file
 * Class for crawling documents for keywords for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class KeywordCrawler
 * @package USDOJ\SingleTableFacets
 *
 * A class for grabbing the keywords from remote documents.
 */
class KeywordCrawler {

    /**
     * @var \USDOJ\SingleTableFacets\AppCLI
     *   Reference to the main app.
     */
    private $app;

    /**
     * KeywordCrawler constructor.
     *
     * @param $app
     *   Reference to the main app.
     */
    public function __construct($app) {

        $this->app = $app;
    }

    /**
     * Get the main app object.
     *
     * @return \USDOJ\SingleTableFacets\AppCLI
     */
    public function getApp() {
        return $this->app;
    }

    /**
     * Execute the crawl and save the results in the database.
     */
    public function run() {

        $table = $this->getApp()->settings('database table');

        $affected = $this->getApp()->getDb()->createQueryBuilder()
            ->update($table)
            ->set($this->getApp()->getDocumentKeywordColumn(), ':empty')
            ->setParameter(':empty', '')
            ->execute();

        if (!empty($affected)) {
            print sprintf('Cleared keywords from %s rows.', $affected);
            print PHP_EOL;
        }

        print 'Fetching keywords from files...' . PHP_EOL;

        $prefix = $this->getApp()->settings('prefix for relative keyword URLs');
        $keywordColumn = $this->getApp()->getDocumentKeywordColumn();
        $idColumn = $this->getApp()->getUniqueColumn();
        $total = 0;

        $urlColumns = $this->getApp()->settings('keywords in files');
        foreach ($urlColumns as $urlColumn) {

            $query = $this->getApp()->query();
            $rows = $query
                ->from($table)
                ->addSelect($urlColumn)
                ->addSelect($keywordColumn)
                ->addSelect($idColumn)
                ->execute()
                ->fetchAll();
            foreach ($rows as $row) {

                $url = $row[$urlColumn];
                $newValue = $row[$keywordColumn];
                $id = $row[$idColumn];

                if (!empty($url)) {
                    $document = new \USDOJ\SingleTableFacets\Document($this->getApp(), $url);
                    $keywords = $document->getKeywords();
                    if (!empty($keywords)) {
                        $newValue .= ' ' . $keywords;
                        $total += 1;
                    }

                    // Update the database.
                    if (!empty($newValue)) {
                        $update = $this->getApp()->query();
                        $affected = $update
                            ->update($table, $table)
                            ->set($keywordColumn, ':keywords')
                            ->where($update->expr()->eq($idColumn, ':id'))
                            ->setParameter(':keywords', $newValue)
                            ->setParameter(':id', $id)
                            ->execute();
                    }
                }
            }
        }
        print PHP_EOL . sprintf('Fetched keywords from %s URLs.', $total) . PHP_EOL;
    }
}
