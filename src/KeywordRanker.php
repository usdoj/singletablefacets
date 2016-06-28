<?php
/**
 * @file
 * Class for converting text into useful keywords for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class KeywordRanker
{

    private $text;
    private $app;

    public function __construct($app, $text) {

        $this->text = $text;
        $this->app = $app;
    }

    public function getApp() {
        return $this->app;
    }

    private function getOriginalText() {
        return $this->text;
    }

    private function getProcessedText() {
        // Hacky way to force English: add some obviously English words.
        // Without this, strange abbreviations and characters can make the
        // TextRank library think that it is another language.
        $hack = 'a about above after again against all am an and any are as at be because been before being below between both but by cannot could did do does doing down during each e.g. few for from further had has have having he her here my myself no nor not of off on once only or other ought';
        return $hack . $this->text;
    }

    public function run() {

        $text = '';
        if ($this->getApp()->getConfig()->get('remove common keywords')) {
            $config = new \crodas\TextRank\Config;
            $config->addListener(new \crodas\TextRank\Stopword);
            $textrank = new \crodas\TextRank\TextRank($config);
            $keywords = $textrank->getKeywords($this->getProcessedText(), -1);
            return implode(' ', array_keys($keywords));
        }
        else {
            return $this->getOriginalText();
        }
    }
}
