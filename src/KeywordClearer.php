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

    public function run() {
        $table = $this->app->getConfig()->get('database table');
        $column = $this->app->getKeywordColumn();

        $affected = $this->app->getDb()->createQueryBuilder()
            ->update($table, $table)
            ->set($column, ':empty')
            ->setParameter(':empty', '')
            ->execute();
        if (!empty($affected)) {
            print sprintf('Cleared keywords from %s rows.', $affected);
            print PHP_EOL;
        }
    }
}
