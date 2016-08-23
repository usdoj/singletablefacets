<?php
/**
 * @file
 * Class for displaying results in an HTML list with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class ResultDisplayList
 * @package USDOJ\SingleTableFacets
 *
 * A class for displaying search results as an HTML list.
 */
class ResultDisplayList extends \USDOJ\SingleTableFacets\ResultDisplay {

    /**
     * Render the HTML for this list of search results.
     *
     * @return string
     */
    public function render() {

        $totalRows = 0;
        $tableColumns = $this->getColumnsToDisplay();
        $rows = $this->getRows();

        // If we are using "grouping" then we need to do several loops - one for
        // each distinct value in the grouping column.
        $groups = $this->getDistinctGroupValues($rows);
        if (empty($groups)) {
            // If we are not using grouping, then make up a fake group so that
            // the following code doesn't need to be duplicated.
            $groups[] = '<fake>';
        }
        $groupingColumn = $this->getApp()->settings('search result grouping column');

        $output = '<div class="stf-facet-search-results">' . PHP_EOL;
        foreach ($groups as $group) {

            $output .= '  <div class="stf-facet-search-result-group">' . PHP_EOL;

            // If this is a real "group", add a group header.
            if ('<fake>' != $group) {
                $output .= '    <h2>' .
                    $group . '</h2>' . PHP_EOL;
            }

            $output .= '    <ul>' . PHP_EOL;
            foreach ($rows as $row) {

                // Skip rows that are not in the right group.
                if ('<fake>' != $group) {
                    if ($group != $this->getCellContent($row, $groupingColumn)) {
                        continue;
                    }
                }

                $rowMarkup = '      <li>' . PHP_EOL;
                foreach ($tableColumns as $column => $label) {

                    $cellContent = $this->getCellContent($row, $column);
                    $baseClass = 'stf-column-' . $column;

                    $labelMarkup = '';
                    if (!empty($label)) {
                        $labelMarkup = '<span class="' . $baseClass . '-label' . '">';
                        $labelMarkup .= $label . ':&nbsp;</span>' . PHP_EOL;
                    }

                    $contentMarkup = $labelMarkup . $cellContent;
                    $contentMarkup = '<div class="' . $baseClass . '-content' . '">' . $contentMarkup . '</div>';

                    $rowMarkup .= '    ' . $contentMarkup . PHP_EOL;
                }
                $rowMarkup .= '      </li>' . PHP_EOL;
                $output .= $rowMarkup;
                $totalRows += 1;
            }
            $output .= '    </ul>' . PHP_EOL;
            $output .= '  </div>' . PHP_EOL;
        }
        if (empty($totalRows)) {
            $message = $this->getApp()->settings('no results message');
            return "<p>$message</p>" . PHP_EOL;
        }
        $output .= '</div>' . PHP_EOL;
        return $output;
    }
}
