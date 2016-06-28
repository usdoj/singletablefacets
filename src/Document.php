<?php
/**
 * @file
 * Class for crawling a document and returning keyword text.
 */

namespace USDOJ\SingleTableFacets;

class Document {

    private $app;

    private $documents;
    private $text;

    public function getApp() {
        return $this->app;
    }

    public function getTempPath() {
        return '/tmp/singletablefacets';
    }

    public function getDocument() {
        return $this->document;
    }

    public function __construct($app, $document) {

        $this->document = $document;
        $this->app = $app;

        // Spoof a browser so that we can download from certain servers.
        $options  = array(
            'http' => array(
                'user_agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36',
            ),
        );
        $context  = stream_context_create($options);

        // Add an http prefix if the document is only a relative link.
        if (strpos($document, 'http') !== 0) {
            $prefix = $this->getApp()->settings('prefix for relative keyword URL');
            $document = $prefix . rawurlencode($document);
        }

        try {
            $response = @file_get_contents($document, false, $context);
        }
        catch (\Exception $e) {
            // Error checking?
        }

        // Save the file locally to make it easier to do the other things.
        file_put_contents($this->getTempPath(), $response);
    }

    private function fetchText() {

        if ($this->isText()) {
            // Assume text files are .html, since it also works OK for .txt.
            $text = file_get_contents($this->getTempPath());
            $dom = new \DOMDocument();
            $ret = $text;
            try {
                @$dom->loadHTML($text);
                // Remove script tags.
                while (($r = $dom->getElementsByTagName("script")) && $r->length) {
                    $r->item(0)->parentNode->removeChild($r->item(0));
                }
                // Remove style tags.
                while (($r = $dom->getElementsByTagName("style")) && $r->length) {
                    $r->item(0)->parentNode->removeChild($r->item(0));
                }
                // Get the text content.
                $ret = $dom->textContent;
                // Strip new-lines.
                $ret = trim(preg_replace('/\s\s+/', ' ', $ret));
            }
            catch (\Exception $e) {
                // Anything?
            }
            return $ret;
        }
        else {
            // Assume binary files are .pdf, since that's all we support.
            $ret = '';
            try {
                $reader = new \Asika\Pdf2text;
                $reader->setFilename($this->getTempPath());
                $reader->decodePDF();
                $ret = $reader->output();
            }
            catch (\Exception $e) {
                // Anything?
            }
            return $ret;
        }
    }

    public function getKeywords() {

        $text = $this->fetchText();
        $keywords = '';
        try {
            $ranker = new \USDOJ\SingleTableFacets\KeywordRanker($this->getApp(), $text);
            $keywords = $ranker->run();
        }
        catch (\Exception $e) {
            // Anything?
        }

        return $keywords;
    }

    private function isText() {
        $finfo = finfo_open(FILEINFO_MIME);
        return (substr(finfo_file($finfo, $this->getTempPath()), 0, 4) == 'text');
    }
}
