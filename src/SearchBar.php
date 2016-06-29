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
            <input type="submit" value="' . $this->getApp()->settings('search button text') . '" />
            <input type="button" onclick="location.href=\'' . $this->getApp()->getBaseUrl() . '\';" value="Reset" />
        </form>
        ';
        return $widget . PHP_EOL . $help . PHP_EOL;
    }
}