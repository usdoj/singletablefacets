<?php
/**
 * @file
 * Class for consolidating keywords into a single column for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordConsolidator {

    private $app;

    // When the app is instantiated, we can do all of the expensive things once
    // and then store them on the object.
    public function __construct($app) {

        $this->app = $app;
    }

    public function getApp() {
        return $this->app;
    }

    public function run() {

        $sourceColumns = array();
        foreach ($this->getApp()->settings('database columns') as $column => $info) {
            if (!empty($info['consult during keyword searches'])) {
                $sourceColumns[] = $column;
            }
        }

        $result = $this->getApp()->getDb()->createQueryBuilder()
            ->from($this->getApp()->settings('database table'))
            ->select('*')
            ->execute();

        $changed = 0;
        $destinationColumn = $this->getApp()->getKeywordColumn();
        $uniqueColumn = $this->getApp()->getUniqueColumn();
        foreach ($result as $row) {
            // Build a concatenation of all the keywords.
            $after = $before = $row[$destinationColumn];
            foreach ($sourceColumns as $sourceColumn) {
                $part = $row[$sourceColumn];
                // Only concatenate if it's not already there.
                if (!empty($part)) {
                    if (strpos($after, $part) === FALSE) {
                        $after .= ' ' . $part;
                    }
                }
            }
            if ($before != $after && !empty($after)) {

                // Run the keywords through the filter thingy.
                try {
                    $ranker = new \USDOJ\SingleTableFacets\KeywordRanker($this->getApp(), $after);
                    $after = $ranker->run();
                } catch(\Exception $e) {
                    print $e->getMessage() . PHP_EOL;
                }

                $update = $this->getApp()->getDb()->createQueryBuilder();
                $affected = $update
                    ->update($this->getApp()->settings('database table'))
                    ->set($destinationColumn, ':after')
                    ->where($update->expr()->eq($uniqueColumn, ':id'))
                    ->setParameter(':after', $after)
                    ->setParameter(':id', $row[$uniqueColumn])
                    ->execute();
                if (!empty($affected)) {
                    $changed += 1;
                }
            }
        }
        print sprintf('Consolidated keywords in %s rows.', $changed) . PHP_EOL;
    }
}
