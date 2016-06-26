<?php
/**
 * @file
 * Class for displaying results in a table with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use USDOJ\SingleTableFacets\ResultDisplay;

class ResultDisplayTable extends ResultDisplay {

  public function render($columns, $minWidths = array()) {
    $totalRows = 0;
    $hrefColumns = $this->getApp()->getOption('href_columns');
    $output = '<table class="doj-facet-search-results">' . PHP_EOL;
    $output .= '  <thead>' . PHP_EOL;
    $output .= '    <tr>' . PHP_EOL;
    foreach ($columns as $columnName => $columnLabel) {
      $label = $this->getTableHeaderLabel($columnName, $columnLabel);
      if (!empty($min_widths[$columnName])) {
        $min_width = ' style="min-width:' . $min_widths[$columnName] . ';"';
      }
      else {
        $min_width = '';
      }
      $output .= '      <th' . $min_width . '>' . $label . '</th>' . PHP_EOL;
    }
    $output .= '    </tr>' . PHP_EOL . '  </thead>' . PHP_EOL . '  <tbody>' . PHP_EOL;
    foreach ($this->getRows() as $row) {
      $rowMarkup = '  <tr>' . PHP_EOL;
      foreach (array_keys($columns) as $column) {
        $td = $row[$column];
        if (in_array($column, array_keys($hrefColumns))) {
          $hrefColumn = $hrefColumns[$column];
          if (!empty($row[$hrefColumn])) {
            $td = '<a href="' . $row[$hrefColumn] . '">' . $row[$column] . '</a>';
          }
        }
        $rowMarkup .= '    <td>' . $td . '</td>' . PHP_EOL;
      }
      $rowMarkup .= '  </tr>' . PHP_EOL;
      $output .= $rowMarkup;
      $totalRows += 1;
    }
    $output .= '  </tbody>' . PHP_EOL . '</table>' . PHP_EOL;
    if (empty($totalRows)) {
      $message = $this->getApp()->getOption('no_results_message');
      return "<p>$message</p>" . PHP_EOL;
    }
    return $output;
  }

  protected function getTableHeaderLabel($columnName, $columnLabel) {
    // If this is not a sorting column, just return the label.
    if (!in_array($columnName, array_keys($this->getApp()->getSortColumns()))) {
      return $columnLabel;
    }

    $query = $this->getApp()->getParameters();
    $query['sort'] = $columnName;
    $class = 'doj-facet-sort-link';
    // If this is the currently sorted field, then make the direction the
    // reverse of the default. Otherwise make it the default. We also take this
    // opportunity to add a class to show an up/down arrow.
    $direction = $this->getSortDirection($columnName);
    $currentSort = $this->getSortField();
    if ($columnName == $currentSort) {
      if ($direction == 'ASC') {
        $direction = 'DESC';
        $class .= ' doj-facet-sort-link-asc';
      }
      elseif ($direction == 'DESC') {
        $direction = 'ASC';
        $class .= ' doj-facet-sort-link-desc';
      }
    }
    $query['sort_direction'] = $direction;
    return Link::getHtml($this->getApp()->getBaseUrl(), $columnLabel, $query, $class);
  }
}
