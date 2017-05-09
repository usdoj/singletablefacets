<?php
/**
 * @file
 * Class for configuration options for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class Config
 * @package USDOJ\SingleTableFacets
 *
 * A class for our configuration object.
 */
class Config extends \Noodlehaus\Config
{
    /**
     * Provide all the defaults for optional settings.
     *
     * @return array
     */
    protected function getDefaults() {
        return array(
            'show counts next to facet items' => TRUE,
            'search button text' => 'Search',
            'no results message' => '<p>Sorry, no results could be found for those keywords.</p>',
            'use checkboxes for facets instead of links' => TRUE,
            'number of items per page' => 20,
            'number of pager links to show' => 5,
            'show dependents indented to the right' => TRUE,
            'keyword help' => '
                <ul>
                    <li>Use the checkboxes on the left to refine your search, or enter new keywords to start over.</li>
                    <li>Enter multiple keywords to get fewer results, eg: cat dogs</li>
                    <li>Use OR to get more results, eg: cats OR dogs</li>
                    <li>Put a dash (-) before a keyword to exclude it, eg: dogs -lazy</li>
                    <li>Use "" (double-quotes) to match specific phrases, eg: "the quick brown fox"</li>
                </ul>
            ',
            'keyword help label' => "Need help searching?",
            'sort facet items by popularity' =>  FALSE,
            'text alterations' => array(),
            'keywords in files' => array(),
            'collapse facet items' => array(),
            'required columns' => array(),
            'facet labels' => array(),
            'search result labels' => array(),
            'location of assets' => 'assets',
            'search result display' => 'table',
            'template folder for search results' => NULL,
            'template folder for facet items' => NULL,
            'date facet granularity' => array(),
            'search result grouping column' => NULL,
            'sort directions' => array(),
            'require input for search results' => FALSE,
            'file with twig functions' => NULL,
            'list of twig functions' => array(),
            'columns with natural sorting' => array(),
            'prepopulated facet values' => array(),
            'facets limited to one choice' => array(),
        );
    }
}
