<?php
/**
 * @file
 * Class for crawling documents for keywords for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordCrawler {

    private $app;

    public function __construct($app) {

        $this->app = $app;
    }

    public function getApp() {
        return $this->app;
    }

    public function run() {

        print 'Fetching keywords from files...' . PHP_EOL;

        $prefix = $this->getApp()->settings('prefix for relative keyword URLs');
        $table = $this->getApp()->settings('database table');
        $keywordColumn = $this->getApp()->getKeywordColumn();
        $idColumn = $this->getApp()->getUniqueColumn();
        $total = 0;

        foreach ($this->getApp()->settings('database columns') as $urlColumn => $info) {
            if (empty($info['contains URLs to files for indexing keywords'])) {
                continue;
            }

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
