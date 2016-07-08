<?php
/**
 * @file
 * Class for clearing the saved keywords from SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordClearer
{

    private $app;

    public function __construct($app) {

        $this->app = $app;
    }

    public function getApp() {
        return $this->app;
    }

    public function run() {
        $table = $this->getApp()->settings('database table');

        $affected = $this->getApp()->getDb()->createQueryBuilder()
            ->update($table)
            ->set($this->getApp()->getDatabaseKeywordColumn(), ':empty')
            ->set($this->getApp()->getDocumentKeywordColumn(), ':empty')
            ->setParameter(':empty', '')
            ->execute();

        if (!empty($affected)) {
            print sprintf('Cleared keywords from %s rows.', $affected);
            print PHP_EOL;
        }
    }
}
