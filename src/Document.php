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

        // Add an http prefix if the document is only a relative link.
        if (strpos($document, 'http') !== 0) {
            $prefix = $this->getApp()->settings('prefix for relative keyword URLs');
            $document = $prefix . rawurlencode($document);
        }

        try {
            print 'Fetching ' . $document . PHP_EOL;
            $response = @$this->file_get_contents_curl($document);
        }
        catch (\Exception $e) {
            // Error checking?
            print '-- Failed: ' . $e->getMessage() . PHP_EOL;
        }

        // Save the file locally to make it easier to do the other things.
        file_put_contents($this->getTempPath(), $response);
    }

    private function file_get_contents_curl($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_VERBOSE, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36");

        if (!empty($this->getApp()->settings('proxy'))) {
            $proxy = $this->getApp()->settings('proxy');
            if (!empty($this->getApp()->settings('proxy exceptions'))) {
                $exceptions = $this->getApp()->settings('proxy exceptions');
                foreach ($exceptions as $exception) {
                    if (strpos($url, $exception) !== FALSE) {
                        $proxy = NULL;
                        break;
                    }
                }
            }
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    private function fetchText() {

        $ret = '';
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
        }
        else {
            // Assume binary files are .pdf, since that's all we support.
            try {
                $reader = new \Asika\Pdf2text;
                $reader->setFilename($this->getTempPath());
                $reader->decodePDF();
                $ret = $reader->output();
            }
            catch (\Exception $e) {
                // Anything?
            }
        }
        if (!empty($ret)) {
            $words = explode(' ', $ret);
            print sprintf('-- Success: %s keywords', count($words)) . PHP_EOL;
        }
        return $ret;
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
