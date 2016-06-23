<?php
/**
 * @file
 * Class for crawling documents for keywords for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use \USDOJ\SingleTableFacets\Document;

class KeywordCrawler {

  private $db;
  private $table;
  private $urlColumn;
  private $keywordColumn;
  private $relativePrefix;
  private $tempPath;

  // When the app is instantiated, we can do all of the expensive things once
  // and then store them on the object.
  public function __construct($db, $table, $urlColumn, $keywordColumn, $relativePrefix, $tempPath = '/tmp/keywordcrawler') {

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
    $query->where($query->expr()->lte('LENGTH(' . $this->getKeywordColumn() . ')', 0));
    if ($limit != -1) {
      $query->setMaxResults($limit);
      $query->setFirstResult($offset);
    }
    $result = $query->execute();
    $successes = $failures = array();
    if (!empty($result)) {
      foreach ($result as $row) {
        $url = $row[$this->getUrlColumn()];
        $newValue = '';
        if (!empty($url)) {
          $document = new Document($url, $this->getRelativePrefix(), $this->getTempPath());
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

            // So that we don't keep trying to do these, we actually save a
            // value in the column. But we keep it short and consistent so we
            // can search it intentionally.
            $newValue = 'crawling error';
          }

          // Update the database.
          if (!empty($newValue)) {
            $update = $this->getDb()->createQueryBuilder();
            $update->update($this->getTable(), $this->getTable())
              ->set($this->getKeywordColumn(), ':keywords')
              ->where($update->expr()->eq($this->getUrlColumn(), ':url'))
              ->setParameter(':keywords', $newValue)
              ->setParameter(':url', $url);
            $affected = $update->execute();
          }
        }
      }
    }
    else {
      print sprintf('No more keywords need to be updated.');
      print PHP_EOL;
    }

    if (!empty($successes)) {
      print PHP_EOL . '**** Successes ****' . PHP_EOL . PHP_EOL;
      foreach ($successes as $success) {
        print $success . PHP_EOL;
      }
      print PHP_EOL . PHP_EOL;
    }
    if (!empty($failures)) {
      print '**** Failures ****' . PHP_EOL . PHP_EOL;
      foreach ($failures as $failure) {
        print $failure . PHP_EOL;
      }
    }

    print PHP_EOL . 'Totals:' . PHP_EOL;
    print count($successes) . ' success.' . PHP_EOL;
    print count($failures) . ' failures.' . PHP_EOL;
  }
}
