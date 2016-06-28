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

        $limit = 20;
        $processed = 0;
        $prefix = $this->getApp()->settings('prefix for relative keyword URLs');
        $table = $this->getApp()->settings('database table');
        $keywordColumn = $this->getApp()->getKeywordColumn();
        $successes = $failures = array();

        foreach ($this->getApp()->settings('database columns') as $column => $info) {

            $query = $this->getApp()->query();
            $results = $query
                ->from($table)
                ->select($column)
                ->where($keywordColumn . ' IS NULL')
                ->execute();
            foreach ($results as $row) {

                $processed += 1;
                if ($processed > $limit) {
                    break 2;
                }

                $url = $row[$column];
                $newValue = '';
                if (!empty($url)) {
                    $document = new Document($url, $prefix);
                    $keywords = $document->getKeywords();
                    $errors = $document->getErrorMessages();
                    if (empty($errors) && !empty($keywords)) {
                        $newValue = $keywords;
                        $successes[] = sprintf('-- Updated keywords for %s.', $document->getAbsolutePath());
                    }
                    else {
                        $failure = '-- ' . $document->getAbsolutePath();
                        if (!empty($errors)) {
                            foreach ($errors as $error) {
                                if (!empty($error)) {
                                    $failure .= PHP_EOL . '   ' . $error;
                                }
                            }
                        }
                        $failures[] = $failure;

                        // So that we don't keep trying to do these, we save a
                        // value in the column. But we keep it consistent so we
                        // can search it intentionally.
                        $newValue = 'crawling error';
                    }

                    // Update the database.
                    if (!empty($newValue)) {
                        $update = $this->getApp()->query();
                        $affected = $update
                            ->update($table, $table)
                            ->set($keywordColumn, ':keywords')
                            ->where($update->expr()->eq($column, ':url'))
                            ->setParameter(':keywords', $newValue)
                            ->setParameter(':url', $url)
                            ->execute();
                    }
                }
            }
        }

        //print sprintf('Crawling keywords: %d successes, %d failuers', count($successes), count($failures));

        // If $processed is the same as limit, we assume that this needs to be
        // run again.
        if ($processed == $limit) {
            print '-1';
        }
        // Otherwise assume it is complete.
        else {
            print '0';
        }
    }
}
