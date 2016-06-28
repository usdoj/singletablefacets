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
        foreach ($this->getApp()->getConfig()->get('database columns') as $column => $info) {
            if (!empty($info['consult during keyword searches'])) {
                $sourceColumns[] = $column;
            }
        }

        $result = $this->getApp()->getDb()->createQueryBuilder()
            ->from($this->getTable())
            ->select('*')
            ->execute();

        $changed = 0;
        $destinationColumn = $this->getApp()->getKeywordColumn();
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
// Make the update if something changed.
if ($before != $after) {
$update = $this->getDb()->createQueryBuilder();
$update->update($this->getTable(), $this->getTable())
->set($this->getDestinationColumn(), ':after')
->where($update->expr()->eq($this->getIdColumn(), ':id'))
->setParameter(':after', $after)
->setParameter(':id', $row[$this->getIdColumn()]);
$affected = $update->execute();
if (!empty($affected)) {
$changed += 1;
print sprintf('Consolidated keywords in %s.', $row[$this->getIdColumn()]);
print PHP_EOL;
}
}
}

print PHP_EOL . 'Totals:' . PHP_EOL;
print $changed . ' rows updated.' . PHP_EOL;
    }
}
