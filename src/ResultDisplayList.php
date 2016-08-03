<?php
/**
 * @file
 * Class for displaying results in an HTML list with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class ResultDisplayList extends \USDOJ\SingleTableFacets\ResultDisplay {

    public function render() {

        $totalRows = 0;
        $tableColumns = $this->getApp()->settings('search result labels');
        // Special case. If there are no keywords being searched, do not show
        // the relevance column.
        $keywords = $this->getApp()->getUserKeywords();
        if (empty($keywords)) {
            unset($tableColumns[$this->getApp()->getRelevanceColumn()]);
        }

        $hrefColumns = $this->getApp()->settings('output as links');

        $output = '<ul class="stf-facet-search-results">' . PHP_EOL;
        foreach ($this->getRows() as $row) {
            $rowMarkup = '  <li>' . PHP_EOL;
            foreach ($tableColumns as $column => $label) {
                $baseClass = 'stf-column-' . $column;
                $labelMarkup = '';
                if (!empty($label)) {
                    $labelMarkup = '<div class="' . $baseClass . '-label' . '">';
                    $labelMarkup .= $label . '</div>' . PHP_EOL;
                }
                $contentMarkup = $row[$column];
                if (!empty($hrefColumns[$column])) {
                    $hrefColumn = $hrefColumns[$column];
                    if (!empty($row[$hrefColumn])) {
                        $contentMarkup = '<a href="' . $row[$hrefColumn] . '">' . $row[$column] . '</a>';
                    }
                }
                $contentMarkup = '<div class="' . $baseClass . '-content' . '">' . $contentMarkup . '</div>';

                $rowMarkup .= '    ' . $labelMarkup . $contentMarkup . PHP_EOL;
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
