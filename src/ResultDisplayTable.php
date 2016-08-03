<?php
/**
 * @file
 * Class for displaying results in a table with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class ResultDisplayTable extends \USDOJ\SingleTableFacets\ResultDisplay {

    public function render() {

        $totalRows = 0;
        $tableColumns = $this->getColumnsToDisplay();
        $tableColumns = array_keys($tableColumns);

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
        foreach ($this->getRows() as $row) {
            $rowMarkup = '  <tr>' . PHP_EOL;
            foreach ($tableColumns as $column) {
                $td = $this->getCellContent($row, $column);
                $rowMarkup .= '    <td>' . $td . '</td>' . PHP_EOL;
            }
            $rowMarkup .= '  </tr>' . PHP_EOL;
            $output .= $rowMarkup;
            $totalRows += 1;
        }
        $output .= '  </tbody>' . PHP_EOL . '</table>' . PHP_EOL;
        if (empty($totalRows)) {
            $message = $this->getApp()->settings('no results message');
            return "<p>$message</p>" . PHP_EOL;
        }
        return $output;
    }

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
        $baseUrl = $this->getApp()->getBaseUrl();
        return $this->getApp()->getLink($baseUrl, $label, $query, $class);
    }
}
