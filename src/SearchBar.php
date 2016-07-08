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
                <input type="checkbox" id="doj-full-text" name="full_text" value="1" ' . $checked . '>
                <label for="doj-full-text">Search contents of documents?</label>
            ';
        }

        $help = $this->getApp()->settings('keyword help');
        $labelHelp = $this->getApp()->settings('keyword help label');
        if (!empty($help)) {
            $help = '
                <div class="doj-facet-help doj-facet-collapse">
                <span class="doj-facet-collapse-trigger">' . $labelHelp . '</span>
                <div class="doj-facet-collapse-inner">' . $help . '</div>
                </div>
            ';
        }

        $widget = '
        <form method="get">
            <label for="doj-facet-keys">Keywords</label>
            <input type="text" name="keys" id="doj-facet-keys" value="' . $keys . '" size="50" />
            ' . $fullTextOption . '
            <input type="submit" value="' . $this->getApp()->settings('search button text') . '" />
            <input type="button" onclick="location.href=\'' . $this->getApp()->getBaseUrl() . '\';" value="Reset" />
        </form>
        ';
        return $widget . PHP_EOL . $help . PHP_EOL;
    }
}