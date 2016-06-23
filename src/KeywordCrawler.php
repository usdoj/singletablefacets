<?php
/**
 * @file
 * Class for crawling documents for keywords for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use \USDOJ\SingletableFacets\Document;

class KeywordCrawler {

  private $db;
  private $table;
  private $urlColumn;
  private $keywordColumn;
  private $relativePrefix;
  private $tempPath;

  // When the app is instantiated, we can do all of the expensive things once
  // and then store them on the object.
  public function __construct($db, $table, $urlColumn, $keywordColumn, $relativePrefix, $tempPath = '') {

    $this->db = $db;
    $this->table = $table;
    $this->urlColumn = $urlColumn;
    $this->keywordColumn = $keywordColumn;
    $this->relativePrefix = $relativePrefix;
    $this->tempPath = $tempPath;
  }

  public function getDb() {
    return $this->db;
  }

  public function getTable() {
    return $this->table;
  }

  public function getUrlColumn() {
    return $this->urlColumn;
  }

  public function getKeywordColumn() {
    return $this->keywordColumn;
  }

  public function getRelativePrefix() {
    return $this->relativePrefix;
  }

  public function getTempPath() {
    return $this->tempPath;
  }

  public function crawlDocuments($limit = 20, $offset = 0) {
    $query = $this->getDb()->createQueryBuilder();
    $query->from($this->getTable());
    $query->select($this->getUrlColumn());
    if ($limit != -1) {
      $query->setMaxResults($limit);
      $query->setFirstResult($offset);
    }
    $result = $query->execute();
    foreach ($result as $row) {
      $url = $row[$this->getUrlColumn()];
      if (!empty($url)) {
        $document = new Document($url, $this->getRelativePrefix(), $this->getTempPath());
        $keywords = $document->getKeywords();
        if (!empty($keywords)) {
          $update = $this->getDb()->createQueryBuilder();
          $update->update($this->getTable(), $this->getTable())
            ->set($this->getKeywordColumn(), $keywords)
            ->where("{$this->getUrlColumn()} = $url");
          $affected = $update->execute();
        }
      }
    }
  }
}
