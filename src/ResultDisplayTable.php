<?php
/**
 * @file
 * Class for displaying results in a table with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class ResultDisplayTable
 * @package USDOJ\SingleTableFacets
 *
 * A class for displaying the search results as an HTML table.
 */
class ResultDisplayTable extends \USDOJ\SingleTableFacets\ResultDisplay {

    /**
     * Render the HTML for this table of results.
     *
     * @return string
     */
    public function render() {

        $totalRows = 0;
        $tableColumns = $this->getColumnsToDisplay();
        $tableColumns = array_keys($tableColumns);
        $rows = $this->getRows();

        $minimumWidths = $this->getApp()->settings('minimum column widths');

        $output = '<table class="stf-facet-search-results">' . PHP_EOL;
        $output .= '  <thead>' . PHP_EOL;
        $output .= '    <tr>' . PHP_EOL;
        foreach ($tableColumns as $columnName) {
            $label = $this->getTableHeaderLabel($columnName);
            if (!empty($minimumWidths[$columnName])) {
                $min_width = ' style="min-width:' . $minimumWidths[$columnName] . ';"';
            }
            else {
                $min_width = '';
            }
            $output .= '      <th' . $min_width . '>' . $label . '</th>' . PHP_EOL;
        }
        $output .= '    </tr>' . PHP_EOL . '  </thead>' . PHP_EOL . '  <tbody>' . PHP_EOL;

        // If we are using "grouping" then we need to do several loops - one for
        // each distinct value in the grouping column.
        $groups = $this->getDistinctGroupValues($rows);
        if (empty($groups)) {
            // If we are not using grouping, then make up a fake group so that
            // the following code doesn't need to be duplicated.
            $groups[] = '<fake>';
        }
        $groupingColumn = $this->getApp()->settings('search result grouping column');

        foreach ($groups as $group) {

            if ('<fake>' != $group) {
                $output .= '  <tr class="stf-facet-search-result-group">' . PHP_EOL;
                $output .= '    <td colspan="' . count($tableColumns) . '">' . $group . '</td>' . PHP_EOL;
                $output .= '  </tr>' . PHP_EOL;
            }
            foreach ($rows as $row) {

                if ('<fake>' != $group) {
                    if ($group != $this->getCellContent($row, $groupingColumn)) {
                        continue;
                    }
                }
                $rowMarkup = '  <tr>' . PHP_EOL;
                foreach ($tableColumns as $column) {
                    $td = $this->getCellContent($row, $column);
                    $rowMarkup .= '    <td>' . $td . '</td>' . PHP_EOL;
                }
                $rowMarkup .= '  </tr>' . PHP_EOL;
                $output .= $rowMarkup;
                $totalRows += 1;
            }
        }
        $output .= '  </tbody>' . PHP_EOL . '</table>' . PHP_EOL;
        if (empty($totalRows)) {
            $message = $this->getApp()->settings('no results message');
            return "<p>$message</p>" . PHP_EOL;
        }
        return $output;
    }

    /**
     * Helper method to get the markup for the header for a table column.
     *
     * @param $columnName
     *   The name of the column in the database.
     *
     * @return string
     */
    protected function getTableHeaderLabel($columnName) {

        $labels = $this->getApp()->settings('search result labels');
        $label = $labels[$columnName];

        $sortDirections = $this->getApp()->settings('sort directions');
        if (empty($sortDirections[$columnName])) {
            return $label;
        }

        $query = $this->getApp()->getParameters();
        $query['sort'] = $columnName;

        $class = 'stf-facet-sort-link';
        // If this is the currently sorted field, then make the direction the
        // reverse of the default. Otherwise make it the default. We also take this
        // opportunity to add a class to show an up/down arrow.
        $direction = $this->getSortDirection($columnName);
        $currentSort = $this->getSortField();
        if ($columnName == $currentSort) {
            if ($direction == 'ASC') {
                $direction = 'DESC';
                $class .= ' stf-facet-sort-link-asc';
            }
            elseif ($direction == 'DESC') {
                $direction = 'ASC';
                $class .= ' stf-facet-sort-link-desc';
            }
        }
        $query['sort_direction'] = $direction;
        // We don't need to carry over the page number.
        if (isset($query['page'])) {
            unset($query['page']);
        }
        $baseUrl = $this->getApp()->getBaseUrl();
        return $this->getApp()->getLink($baseUrl, $label, $query, $class);
    }
}
