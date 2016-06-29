<?php
/**
 * @file
 * Class for configuration options for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class Config extends \Noodlehaus\Config
{
    protected function getDefaults() {
        return array(
            'minimum valid keyword length' => 3,
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
            'remove common keywords' => TRUE,
        );
    }
}
