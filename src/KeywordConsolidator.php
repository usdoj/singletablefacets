<?php
/**
 * @file
 * Class for consolidating keywords into a single column for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordConsolidator {

  private $db;
  private $table;
  private $destinationColumn;
  private $sourceColumns;
  private $idColumn;

  // When the app is instantiated, we can do all of the expensive things once
  // and then store them on the object.
  public function __construct($db, $table, $destinationColumn, $sourceColumns, $idColumn) {

    $this->db = $db;
    $this->table = $table;
    $this->destinationColumn = $destinationColumn;
    $this->sourceColumns = $sourceColumns;
    $this->idColumn = $idColumn;
  }

  public function getDb() {
    return $this->db;
  }

  public function getTable() {
    return $this->table;
  }

  public function getDestinationColumn() {
    return $this->destinationColumn;
  }

  public function getSourceColumns() {
    return $this->sourceColumns;
  }

  public function getIdColumn() {
    return $this->idColumn;
  }

  public function consolidateKeywords() {
    $result = $this->getDb()->createQueryBuilder()
      ->from($this->getTable())
      ->select('*')
      ->execute();

    $changed = 0;
    foreach ($result as $row) {
      // Build a concatenation of all the keywords.
      $after = $before = $row[$this->getDestinationColumn()];
      foreach ($this->getSourceColumns() as $sourceColumn) {
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
