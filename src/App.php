<?php
/**
 * @file
 * Class for creating facets using a single database table.
 */

namespace USDOJ\SingleTableFacets;

use USDOJ\SingleTableFacets\Facet,
    USDOJ\SingleTableFacets\SearchBar,
    USDOJ\SingleTableFacets\Link;

class App {

  private $db;
  private $table;
  private $facetColumns;
  private $keywordColumns;
  private $sortColumns;
  private $options;
  private $parameters;
  private $facets;

  // When the app is instantiated, we can do all of the expensive things once
  // and then store them on the object.
  public function __construct($db, $table, $facetColumns, $keywordColumns, $sortColumns, $options) {

    $this->db = $db;
    $this->table = $table;
    $this->facetColumns = $facetColumns;
    $this->keywordColumns = $keywordColumns;
    $this->sortColumns = $sortColumns;
    $this->options = $options + $this->getDefaultOptions();
    $this->parameters = $this->parseQueryString();

    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $this->baseUrl = $uri_parts[0];
  }

  private function getDefaultOptions() {
    return array(
      'minimum_keyword_length' => 3,
      'facet_dependencies' => array(),
      'show_counts' => TRUE,
      'search_button_text' => 'Search',
      'hide_single_item_facets' => FALSE,
      'no_results_message' => 'Sorry, no results could be found for those keywords',
      'checkboxes' => TRUE,
      'pager_limit' => 20,
      'href_columns' => array(),
      'required_columns' => array(),
      'pager_radius' => 2,
      'nested_dependents' => FALSE,
      'keyword_help' => '
        <ul>
          <li>Use the checkboxes on the left to refine your search, or enter new keywords to start over.</li>
          <li>Enter multiple keywords to get fewer results, eg: cat dogs</li>
          <li>Use OR to get more results, eg: cats OR dogs</li>
          <li>Put a dash (-) before a keyword to exclude it, eg: dogs -lazy</li>
          <li>Use "" (double-quotes) to match specific phrases, eg: "the brown fox"</li>
        </ul>',
      'keyword_help_label' => 'Need help searching?',
      'collapse_facets' => array(),
      'optional_keyword_column' => '',
      'optional_keyword_column_label' => '',
      'sort_facet_items_by_popularity' => FALSE,
    );
  }

  public function getDb() {
    return $this->db;
  }

  public function getBaseUrl() {
    return $this->baseUrl;
  }

  public function getTable() {
    return $this->table;
  }

  public function getOption($option) {
    return $this->options[$option];
  }

  private function getFacetColumns() {
    return $this->facetColumns;
  }

  public function getKeywordColumns() {
    return $this->keywordColumns;
  }

  public function getExtraParameters() {
    return array('keys', 'sort', 'sort_direction', 'full_keys');
  }

  private function getAllowedParameters() {
    $extraParameters = $this->getExtraParameters();
    $facetColumnNames = array_keys($this->getFacetColumns());
    return array_merge($facetColumnNames, $extraParameters);
  }

  public function getParameter($param) {
    if (!empty($this->currentQuery[$param])) {
      return $this->currentQuery[$param];
    }
    return FALSE;
  }

  public function getParameters() {
    return $this->parameters;
  }

  private function parseQueryString() {
    $params = $_GET;
    $current_query = array();
    $allowed_params = $this->getAllowedParameters();
    foreach ($allowed_params as $allowed_param) {
      if (!empty($params[$allowed_param])) {
        if (is_array($params[$allowed_param])) {
          foreach ($params[$allowed_param] as $param) {
            $current_query[$allowed_param][] = $param;
          }
        }
        elseif (is_string($params[$allowed_param])) {
          $current_query[$allowed_param] = $params[$allowed_param];
        }
      }
    }
    return $current_query;
  }

  public function renderKeywordSearch() {
    return SearchBar::render($this);
  }

  public function renderFacets() {

    $output = '';
    foreach ($this->getFacetColumns() as $name => $label) {
      $facet = new Facet($name, $label, $this);
      $output .= $facet->render();
    }
    return $output;
  }

  /**
   * Helper function to split a string into an array of space-delimited tokens
   * taking double-quoted and single-quoted strings into account.
   */
  public function tokenizeQuoted($string, $quotationMarks='"\'') {
    $tokens = array();
    for ($nextToken=strtok($string, ' '); $nextToken!==false; $nextToken=strtok(' ')) {
      if (strpos($quotationMarks, $nextToken[0]) !== false) {
        if (strpos($quotationMarks, $nextToken[strlen($nextToken)-1]) !== false) {
          $tokens[] = substr($nextToken, 1, -1);
        }
        else {
          $tokens[] = substr($nextToken, 1) . ' ' . strtok($nextToken[0]);
        }
      }
      else {
        $tokens[] = $nextToken;
      }
    }
    return $tokens;
  }

  public function getJavascript() {
    return '<script type="text/javascript" src="assets/singletablefacets.js"></script>';
  }

  public function getStyles() {
    return '<link rel="stylesheet" href="assets/singletablefacets.css" />';
  }
}
