<?php
/**
 * @file
 * Class for rendering the search bar for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class SearchBar {

    private $app;

    public function __construct($app) {
        $this->app = $app;
    }

    public function getApp() {
        return $this->app;
    }

    public function render() {

        $keys = $this->getApp()->getParameter('keys');
        if (!empty($keys)) {
            $keys = htmlentities(stripslashes($keys));
        }
        else {
            $keys = '';
        }

        $showFullTextOption = $this->getApp()->settings('allow user to exclude full text from keyword search');
        $fullTextOption = '';
        if ($showFullTextOption) {
            $fullTextParam = $this->getApp()->getParameter('full_text');
            $checked = (!empty($fullTextParam)) ? 'checked="checked"' : '';
            $fullTextOption = '
                <input type="checkbox" id="stf-full-text" name="full_text" value="1" ' . $checked . '>
                <label for="stf-full-text">Search contents of documents?</label>
            ';
        }

        $help = $this->getApp()->settings('keyword help');
        $labelHelp = $this->getApp()->settings('keyword help label');
        if (!empty($help)) {
            $help = '
                <div class="stf-facet-help stf-facet-collapse">
                <span class="stf-facet-collapse-trigger">' . $labelHelp . '</span>
                <div class="stf-facet-collapse-inner">' . $help . '</div>
                </div>
            ';
        }

        $widget = '
        <form method="get">
            <label for="stf-facet-keys">Keywords</label>
            <input type="text" name="keys" id="stf-facet-keys" value="' . $keys . '" size="50" />
            ' . $fullTextOption . '
            <input type="submit" value="' . $this->getApp()->settings('search button text') . '" />
            <input type="button" onclick="location.href=\'' . $this->getApp()->getBaseUrl() . '\';" value="Reset" />
        </form>
        ';
        return $widget . PHP_EOL . $help . PHP_EOL;
    }
}