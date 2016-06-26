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

    public function crawlDocuments() {

        $limit = 20;
        $processed = 0;
        $prefix = $this->app->getConfig()->get('prefix for relative keyword URLs');
        $table = $this->app->getConfig()->get('database table');
        $keywordColumn = $this->app->getKeywordColumn();
        $successes = $failures = array();

        foreach ($this->app->getConfig('database columns') as $column => $info) {

            $query = $this->app->getDb()->createQueryBuilder();
            $results = $query
                ->from($table)
                ->select($column)
                ->where($query->expr()->lte('LENGTH(' . $keywordColumn . ')', 0))
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
                        $update = $this->app->getDb()->createQueryBuilder();
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

        print sprintf('Crawling keywords: %d successes, %d failuers', count($successes), count($failures));

        // If $processed is the same as limit, we assume that this needs to be
        // run again.
        if ($processed == $limit) {
            return -1;
        }
        // Otherwise assume it is complete.
        else {
            return 0;
        }
    }
}
