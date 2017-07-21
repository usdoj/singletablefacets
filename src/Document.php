<?php
/**
 * @file
 * Class for crawling a document and returning keyword text.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class Document
 * @package USDOJ\SingleTableFacets
 *
 * A class for a remote document (HTML or PDF) to be crawled for keywords.
 */
class Document {

    /**
     * @var \USDOJ\SingleTableFacets\App $app
     *   Reference to the app.
     */
    private $app;

    /**
     * @var string
     *   The URL of the document.
     */
    private $document;

    /**
     * @return \USDOJ\SingleTableFacets\App
     */
    public function getApp() {
        return $this->app;
    }

    /**
     * A hardcoded location to do things in the temp folder.
     *
     * @return string
     */
    public function getTempPath() {
        return '/tmp/singletablefacets';
    }


    /**
     * Get the URL for this document.
     *
     * @return string
     */
    public function getDocument() {
        return $this->document;
    }

    /**
     * Document constructor.
     *
     * @param $app
     *   Reference to the app.
     * @param $document
     *   The URL for the document.
     */
    public function __construct($app, $document) {

        $this->document = $document;
        $this->app = $app;

        // Add an http prefix if the document is only a relative link.
        if (strpos($document, 'http') !== 0) {
            $prefix = $this->getApp()->settings('prefix for relative keyword URLs');
            // We have to decide whether to encode the URL. Some URLs are
            // already encoded, and some are not. We decide this by looking for
            // common characters that would be encoded: spaces.
            if (strpos($document, ' ') !== FALSE) {
              $document = rawurlencode($document);
            }
            $document = $prefix . $document;
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

    /**
     * A version of file_get_contents that uses curl (needed because of proxy).
     *
     * @param $url
     *   The URL of the document.
     *
     * @return string
     *   Get the content of the response.
     */
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

        $proxy = $this->getApp()->settings('proxy');
        if (!empty($proxy)) {
            $exceptions = $this->getApp()->settings('proxy exceptions');
            if (!empty($exceptions)) {
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

    /**
     * Get the text content of a remote file, either PDF or HTML.
     *
     * @return string
     */
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
                // Deal with multibyte characters.
                $ret = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
                  '|[\x00-\x7F][\x80-\xBF]+' .
                  '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
                  '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
                  '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
                '', $ret);
                $ret = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]' .
                  '|\xED[\xA0-\xBF][\x80-\xBF]' .
                  '|\xEF\xBF\xBD/S',
                '', $ret);
                // Deal with HTML entities.
                $ret = preg_replace('/&#?[a-z0-9]+;/i', '', $ret);
            }
            catch (\Exception $e) {
                // Anything?
            }
        }
        if (!empty($ret)) {
            $words = explode(' ', $ret);
            print sprintf('-- Success: %s keywords', count($words)) . PHP_EOL;
        }

        // Always clean up after ourselves.
        unlink($this->getTempPath());

        return $ret;
    }

    /**
     * A public wrapper for fetchText().
     *
     * @return string
     */
    public function getKeywords() {

        return $this->fetchText();
    }

    /**
     * Helper method to test whether a document is text or binary.
     *
     * @return bool
     *   TRUE if the document is text, FALSE otherwise.
     */
    private function isText() {
        $finfo = finfo_open(FILEINFO_MIME);
        return (substr(finfo_file($finfo, $this->getTempPath()), 0, 4) == 'text');
    }
}
