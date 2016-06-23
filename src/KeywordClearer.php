<?php
/**
 * @file
 * Class for clearing the saved keywords from SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordClearer {

  private $db;
  private $table;
  private $keywordColumn;

  // When the app is instantiated, we can do all of the expensive things once
  // and then store them on the object.
  public function __construct($db, $table, $keywordColumn) {

    $this->db = $db;
    $this->table = $table;
    $this->keywordColumn = $keywordColumn;
  }

  public function getDb() {
    return $this->db;
  }

  public function getTable() {
    return $this->table;
  }

  public function getKeywordColumn() {
    return $this->keywordColumn;
  }

  public function clearKeywords() {
    $affected = $this->getDb()->createQueryBuilder()
      ->update($this->getTable(), $this->getTable())
      ->set($this->getKeywordColumn(), ':empty')
      ->setParameter(':empty', '')
      ->execute();
    if (!empty($affected)) {
      print sprintf('Cleared keywords from %s rows.', $affected);
      print PHP_EOL;
    }
  }
}
