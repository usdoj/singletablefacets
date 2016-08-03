<?php
/**
 * @file
 * Class for displaying results in an HTML list with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class ResultDisplayList extends \USDOJ\SingleTableFacets\ResultDisplay {

    public function render() {

        $totalRows = 0;
        $tableColumns = $this->getColumnsToDisplay();
        $output = '<ul class="stf-facet-search-results">' . PHP_EOL;
        foreach ($this->getRows() as $row) {
            $rowMarkup = '  <li>' . PHP_EOL;
            foreach ($tableColumns as $column => $label) {

                $cellContent = $this->getCellContent($row, $column);

                $labelMarkup = '';
                if (!empty($label)) {
                    $labelMarkup = '<span class="' . $baseClass . '-label' . '">';
                    $labelMarkup .= $label . ':&nbsp;</span>' . PHP_EOL;
                }

                $baseClass = 'stf-column-' . $column;
                $contentMarkup = $labelMarkup . $cellContent;
                $contentMarkup = '<div class="' . $baseClass . '-content' . '">' . $contentMarkup . '</div>';

                $rowMarkup .= '    ' . $contentMarkup . PHP_EOL;
            }
            $rowMarkup .= '  </li>' . PHP_EOL;
            $output .= $rowMarkup;
            $totalRows += 1;
        }
        $output .= '</ul>' . PHP_EOL;
        if (empty($totalRows)) {
            $message = $this->getApp()->settings('no results message');
            return "<p>$message</p>" . PHP_EOL;
        }
        return $output;
    }
}
