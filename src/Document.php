<?php
/**
 * @file
 * Class for crawling a document and returning keyword text.
 */

namespace USDOJ\SingleTableFacets;

use \Asika\Pdf2text,
    \crodas\TextRank\Config,
    \crodas\TextRank\TextRank,
    \crodas\TextRank\Stopword;


class Document {

  private $documents;
  private $relativePrefix;
  private $tempPath;
  private $text;
  private $errorMessages;
  private $absolutePath;

  public function getRelativePrefix() {
    return $this->relativePrefix;
  }

  public function getTempPath() {
    return $this->tempPath;
  }

  public function getErrorMessages() {
    return $this->errorMessages;
  }

  public function addErrorMessage($message) {
    $this->errorMessages[] = $message;
  }

  public function getDocument() {
    return $this->document;
  }

  public function getAbsolutePath() {
    return $this->absolutePath;
  }

  public function __construct($document, $relativePrefix, $tempPath) {

    $this->document = $document;
    $this->relativePrefix = $relativePrefix;
    $this->tempPath = $tempPath;

    // Spoof a browser so that we can download from certain servers.
    $options  = array(
      'http' => array(
        'user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
      ),
    );
    $context  = stream_context_create($options);

    // Add an http prefix if the document is only a relative link.
    if (strpos($document, 'http') !== 0) {
      $document = $this->getRelativePrefix() . rawurlencode($document);
    }

    // Save this full path for error messages later.
    $this->absolutePath = $document;

    try {
      $response = @file_get_contents($document, false, $context);
      if ($response === FALSE) {
        if (!empty($http_response_header[0])) {
          $this->addErrorMessage($http_response_header[0]);
        }
      }
    }
    catch (\Exception $e) {
      $this->addErrorMessage($e->getMessage());
    }

    // Save the file locally to make it easier to do the other things.
    file_put_contents($this->getTempPath(), $response);
  }

  private function getText() {

    if ($this->isText()) {
      // Assume text files are .html, since it also works OK for .txt.
      $text = file_get_contents($this->tempPath);
      $domDocument = new \DOMDocument();
      try {
        @$domDocument->loadHTML($text);
        $this->text = $domDocument->textContent;
      }
      catch (\Exception $e) {
        $this->text = $text;
      }
    }
    else {
      // Assume binary files are .pdf, since that's all we support.
      try {
        $reader = new Pdf2text;
        $reader->setFilename($this->tempPath);
        $reader->decodePDF();
        $this->text = $reader->output();
      }
      catch (\Exception $e) {
        $this->addErrorMessage($e->getMessage());
      }
    }
  }

  public function getKeywords() {

    $keywords = '';
    $this->getText();
    try {
        $ranker = new \USDOJ\SingleTableFacets\KeywordRanker($this->getApp(), $this->text);
        $keywords = $ranker->run();
    }
    catch (\Exception $e) {
      $this->addErrorMessage($e->getMessage());
    }

    return $keywords;
  }

  private function isText() {
    $finfo = finfo_open(FILEINFO_MIME);
    return (substr(finfo_file($finfo, $this->tempPath), 0, 4) == 'text');
  }
}