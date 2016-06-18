<?php
/**
 * @file
 * Class for creating facets using a single database table.
 */

require __DIR__ . '/vendor/autoload.php';

class SingleTableFacets {

  protected $db;
  protected $table;
  protected $facet_columns;
  protected $keyword_columns;
  protected $sort_columns;
  protected $options;
  protected $current_query;
  protected $base_url;

  /**
   * Constructor function for singleTableFacets.
   * @param string $table
   *        The name of the database table.
   * @param array $facet_columns
   *        Array of column names to treat as facets. MUST be an associative
   *        array of column name keyed to human-readable name.
   * @param array $keyword_columns
   *        Array of column names to treat as keywords.
   * @param array $sort_column
   *        Array of column names to allow sorting by. MUST be an associative
   *        array of column name keyed to a default direction, ASC or DESC.
   * @param array $options
   *        Associative array of optional options. Options include:
   *        	- minumum_keyword_length: Minimum number of characters a keyword
   *        		must have to be considered.
   *        	- facet_dependencies: An associative array of facets that depend
   *        		on other facets before they are displayed. Eg:
   *        		array('child_category' => 'parent_category')
   *        	- active_prefix: A string to prepend to all active facet items.
   *        	- show_counts: Whether to display the counts of facet items.
   *        	- search_button_text: A string to use for the search button text.
   *        	- hide_single_item_facets: Hide non-active facets with <2 items.
   *        	- no_results_message: Message to display when no results are found.
   *        	- checkboxes: Whether to use checkboxes or ordinary links.
   *        	- pager_limit: The number items per page, or 0 to disable paging.
   *        	- href_columns: A mapping of columns for if some columns need to
   *        		be treated as hrefs in links, where another column is the label.
   *        		MUST be an associative array of $label_column => $href_column.
   *          - required_columns: An array of columns which cannot be NULL.
   *          - pager_radius: The maximum number of pager pages to show on each
   *            side of the current page. If there are more pager pages than
   *            this max, then the extra pages will be replaced with a "...".
   *            Use 0 to display all pages.
   *          - nested_dependents: Whether to hide the labels of the facet
   *            blocks for dependents and indent them slightly to make them
   *            look "nested". Note that this may appear confusing if the parent
   *            facet has multiple items in it. (The child facets will appear
   *            to be nested from the last parent item.) This also assumes that
   *            the child facet is directly after the parent facet in the
   *            $facet_columns parameter.
   *          - default_keyword_logic: Can be 'OR' or 'AND'. Determines the
   *            default logic that will be used for multiple keywords. Users
   *            will be able to specify 'OR' or 'AND' in their searches to
   *            override this.
   *          - keyword_help: If specified, will display expandable help
   *            text beneath the keyword search box.
   *          - keyword_help_label: The label for the above option.
   *          - collapse_facets: If specified, will collapsed certain facets
   *            after the indicated number. Must be an array in the form of
   *            column machine name => number of items to before collapsing.
   *            If 0 is specified, the facet label itself will expand the items.
   *            Otherwise, a "Show more" link serves that purpose.
   *          - optional_keyword_column: This gives the user the option to
   *            include one additional column in the keyword search. The main
   *            use of this is if one column contains a massive amount of data
   *            and you don't want searches to include it by default, but still
   *            want to allow the option to search it. This should be the
   *            machine name of the column.
   *          - optional_keyword_column_label: The label for the above option.
   *          - unique_column_for_row_merging: This specifies an optional column
   *            that is guaranteed to be unique, and allows for multiple rows
   *            to refer to the same document. This is the only way that a row
   *            could have multiple values for the same column. For example, if
   *            there were a "tag" column, and a document needed to have 2 tags,
   *            this would be the only way to do that. The first row would have
   *            all the columns filled out, and the second row would have only
   *            the unique column and "tag" filled out. To illustrate this, the
   *            table would look something like this:
   *            ---------------------------------------------------------------
   *            | unique_id | tag     | title     | author       | other_data |
   *            | 1         | foo     | My Title  | John Doe     | 1234       |
   *            | 1         | bar     |           |              |            |
   *            ---------------------------------------------------------------
   *            In this way, document "1" could have 2 tags, "foo" and "bar".
   *            Note that this carries a performance cost, so should only be
   *            used if absolutely necessary.
   */
  public function __construct($table, $facet_columns, $keyword_columns = NULL, $sort_columns = NULL, $options = NULL) {

    // Set up the database.
    $db_info = parse_ini_file(dirname(__FILE__) . '/config.ini');
    $config = new \Doctrine\DBAL\Configuration();
    $connection_params = array(
      'dbname' => $db_info['dbname'],
      'user' => $db_info['username'],
      'password' => $db_info['password'],
      'host' => $db_info['dbhost'],
      'port' => 3306,
      'charset' => 'utf8',
      'driver' => 'pdo_mysql',
    );
    $this->db = \Doctrine\DBAL\DriverManager::getConnection($connection_params, $config);

    $this->table = $table;
    $this->facet_columns = $facet_columns;
    $this->keyword_columns = $keyword_columns;
    $this->sort_columns = $sort_columns;
    $this->current_query = $this->getCurrentQuery();
    $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
    $this->base_url = $uri_parts[0];
    // Read the optional options, or use defaults.
    $default_help = <<<HELP
<ul>
  <li>Use the checkboxes on the left to refine your search, or enter new keywords to start over.</li>
  <li>Enter multiple keywords to get fewer results, eg: cat dogs</li>
  <li>Use OR to get more results, eg: cats OR dogs</li>
  <li>Put a dash (-) before a keyword to exclude it, eg: dogs -lazy</li>
  <li>Use "" (double-quotes) to match specific phrases, eg: "the brown fox"</li>
</ul>
HELP;
    $defaults = array(
      'minimum_keyword_length' => 3,
      'facet_dependencies' => array(),
      'active_prefix' => '',
      'show_counts' => TRUE,
      'search_button_text' => 'Search',
      'hide_single_item_facets' => FALSE,
      'no_results_message' => 'Sorry, no results could be found for those keywords',
      'checkboxes' => FALSE,
      'pager_limit' => 10,
      'href_columns' => array(),
      'required_columns' => array(),
      'pager_radius' => 2,
      'nested_dependents' => FALSE,
      'default_keyword_logic' => 'AND',
      'keyword_help' => $default_help,
      'keyword_help_label' => 'Need help searching?',
      'collapse_facets' => array(),
      'optional_keyword_column' => '',
      'optional_keyword_column_label' => '',
      'unique_column_for_row_merging' => '',
    );
    $this->options = array();
    foreach ($defaults as $option => $default) {
      if (!empty($options[$option])) {
        $this->options[$option] = $options[$option];
      }
      else {
        $this->options[$option] = $default;
      }
    }

    // Do we need to search an extra keyword column?
    if (!empty($this->getParameter('full_keys')) && !empty($this->options['optional_keyword_column'])) {
      if (!in_array($this->options['optional_keyword_column'], $this->keyword_columns)) {
        $this->keyword_columns[] = $this->options['optional_keyword_column'];
      }
    }
  }

  /**
   * Get the machine-readable facet column names.
   * @return array Machine-readable column names for facets.
   */
  protected function getFacetColumns() {
    return array_keys($this->facet_columns);
  }

  /**
   * Get the human-readable facet labels.
   * @return array Human-readable labels.
   */
  protected function getFacetLabels() {
    return $this->facet_columns;
  }

  /**
   * Checks whether a facet is allowed for this page.
   * @param  string  $facet The machine-name of the facet.
   * @return boolean        TRUE if the facet is allowed.
   */
  protected function isFacetAllowed($facet) {
    return in_array($facet, $this->getFacetColumns());
  }

  /**
   * Checks whether a facet item is active.
   * @param  string  $facet      Machine name of facet.
   * @param  string  $facet_item Value of facet item.
   * @return boolean             Where are not the item is active.
   */
  protected function isFacetItemActive($facet, $facet_item) {
    if (empty($this->current_query[$facet])) {
      return FALSE;
    }
    return in_array($facet_item, $this->current_query[$facet]);
  }

  /**
   * Get a MYSQL where statement for this page.
   * @return string The WHERE statement for inclusion in a MYSQL query.
   */
  protected function getWhereStatement() {
    $current_query = $this->current_query;
    // For the purposes of the WHERE statement, we don't care about sorting.
    unset($current_query['sort']);
    unset($current_query['sort_direction']);
    $wheres = array();
    /*
     * Keywords are a special case. Here are the requirements for keyword
     * search behavior:
     * 1. Defaults to an "AND" query.
     *    Eg, a search for 'foo bar' would result in:
     *    SELECT * FROM tbl WHERE col LIKE '%foo%' AND col LIKE '%bar%'
     *
     * 2. User can specify "OR" instead.
     *
     *    Eg, a search for 'foo OR bar' would result in:
     *    SELECT * FROM tbl WHERE col LIKE '%foo%' OR col LIKE '%bar%'
     *
     * 3. User can enter "-" to exclude a keyword.
     *
     *    Eg, a search for 'foo -bar' would result in:
     *    SELECT * FROM tbl WHERE col LIKE '%foo%' AND col NOT LIKE '%bar%'
     *
     * 4. User can put double-quotes around phrases to treat it as a single word
     *
     *    Eg, a search for '"foo bar"' would result in:
     *    SELECT * FROM tbl WHERE col LIKE '%foo bar%'
     *
     * 5. All of the above can be applied to multiple columns...
     *
     *    Here is how requirement #5 might affect requirement #1:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo%')
     *    AND     (col1 LIKE '%bar%' OR col2 LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #2:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo%')
     *    OR     (col1 LIKE '%bar%' OR col2 LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #3:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo'%')
     *    AND     (col1 NOT LIKE '%bar%' AND col2 NOT LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #4:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo bar%' OR col2 LIKE '%foo bar%') )
     */
    if (!empty($current_query['keys'])) {
      $keywords = $current_query['keys'];
      $boolean = $this->options['default_keyword_logic'];
      if (!empty($keywords) && !empty($this->keyword_columns)) {

        // First parse out the keywords we need to search for.
        $parsed_keywords = array();
        $current_quoted_phrase = '';
        foreach (explode(' ', $keywords) as $keyword) {
          if (empty($keyword)) {
            continue;
          }
          if ('AND' == $keyword) {
            $boolean = 'AND';
            continue;
          }
          if ('OR' == $keyword) {
            $boolean = 'OR';
            continue;
          }
          if (empty($current_quoted_phrase) && '\"' == substr($keyword, 0, 2)) {
            // If this is a single word double-quoted, treat it as a regular
            // keyword.
            if ('\"' == substr($keyword, -2)) {
              $keyword = str_replace('\"', '', $keyword);
            }
            // Otherwise start a quoted phrase and continue.
            else {
              $current_quoted_phrase = $keyword;
              continue;
            }
          }
          if (!empty($current_quoted_phrase)) {
            if ('\"' == substr($keyword, -2)) {
              $current_quoted_phrase .= ' ' . $keyword;
              $keyword = str_replace('\"', '', $current_quoted_phrase);
              $current_quoted_phrase = '';
            }
            else {
              $current_quoted_phrase .= ' ' . $keyword;
              continue;
            }
          }
          if (strlen($keyword) < $this->options['minimum_keyword_length']) {
            continue;
          }
          $keyword = str_replace('%', '', $keyword);
          $parsed_keywords[] = trim($keyword);
        }

        // Next, loop through the keywords (outer loop) and the columsn (inner).
        $keyword_wheres = array();
        foreach ($parsed_keywords as $keyword) {
          $operator = 'LIKE';
          $keyword_column_boolean = 'OR';
          if ('-' == substr($keyword, 0, 1)) {
            $operator = 'NOT LIKE';
            $keyword_column_boolean = 'AND';
            $keyword = substr($keyword, 1);
          }
          $keyword_column_wheres = array();
          foreach ($this->keyword_columns as $keyword_column) {
            $keyword_column_wheres[] = "LOWER($keyword_column) $operator LOWER('%$keyword%')";
          }
          $keyword_column_glue = ') ' . $keyword_column_boolean . ' (';
          $keyword_wheres[] = '(' . implode($keyword_column_glue, $keyword_column_wheres) . ')';
        }
        $keyword_glue = ') ' . $boolean . '(';
        $keyword_where = '(' . implode($keyword_glue, $keyword_wheres) . ')';

        // Finally, add the big WHERE to the query.
        $wheres[] = $keyword_where;
      }
      unset($current_query['keys']);
      unset($current_query['full_keys']);
    }
    // Adjust the query as needed given the current request.
    if (!empty($current_query)) {
      foreach ($current_query as $current_facet => $current_items) {
        $item_array = '"';
        $item_array .= implode('","', $current_items);
        $item_array .= '"';
        $wheres[] = "$current_facet IN ($item_array)";
      }
    }
    // Add conditions for any required columns.
    if (!empty($this->options['required_columns'])) {
      foreach ($this->options['required_columns'] as $required_column) {
        $wheres[] = "($required_column <> '' AND $required_column IS NOT NULL)";
      }
    }
    $ret = '';
    if (!empty($wheres)) {
      $ret .= ' WHERE (';
      $ret .= implode(') AND (', $wheres);
      $ret .= ')';
    }
    return $ret;
  }

  /**
   * Get the distinct list of facet items for a facet.
   * @param  string $facet Machine name of facet.
   * @return array        Array of strings for facet items.
   */
  protected function getFacetItems($facet) {
    if (!$this->isFacetAllowed($facet)) {
      return array();
    }
    $facet_items = array();
    $query = "SELECT $facet AS item, COUNT($facet) AS count FROM `$this->table`";
    $where = $this->getWhereStatement();
    $group = " GROUP BY $facet";
    $result = $this->db->query($query . $where . $group);
    foreach ($result as $row) {
      if (!empty($row['item'])) {
        $facet_items[$row['item']] = $row['count'];
      }
    }
    // Allow for option to hide single-item facets.
    if ($this->options['hide_single_item_facets']) {
      if (count($facet_items) === 1) {
        foreach ($facet_items as $facet_item => $count) {
          if (!$this->isFacetItemActive($facet, $facet_item)) {
            // If there is only one item and it is not active, hide it.
            return array();
          }
        }
      }
    }
    return $facet_items;
  }

  /**
   * Get the associative array representing the facets/filters for the page.
   * @return array Array of facet/filter keys to facet/filter item values.
   */
  protected function getCurrentQuery() {
    $params = $_GET;
    $current_query = array();
    $allowed_params = $this->getFacetColumns();
    // We want to allow the facet links to carry-over keywords, sorting, and
    // sort direction.
    $allowed_params[] = 'keys';
    $allowed_params[] = 'sort';
    $allowed_params[] = 'sort_direction';
    $allowed_params[] = 'full_keys';
    foreach ($allowed_params as $allowed_param) {
      if (!empty($params[$allowed_param])) {
        if (is_array($params[$allowed_param])) {
          foreach ($params[$allowed_param] as $param) {
            $current_query[$allowed_param][] = $this->db->escape($param);
          }
        }
        elseif (is_string($params[$allowed_param])) {
          $current_query[$allowed_param] = $this->db->escape($params[$allowed_param]);
        }
      }
    }
    return $current_query;
  }

  /**
   * Get the output-friendly version of getCurrentQuery().
   * @return array Array of facet/filter keys to facet/filter item values.
   */
  protected function getCurrentQueryForOutput() {
    $query = $this->current_query;
    if (!empty($query['keys'])) {
      $query['keys'] = str_replace('\"', '"', $query['keys']);
    }
    return $query;
  }


  /**
   * Checks to see if a facet can be displayed.
   * @param  string $facet Machine name of facet.
   * @return boolean        TRUE if the facet can be displayed.
   */
  protected function facetDependencyMet($facet) {
    // If there are no facet dependencies, this will always be TRUE.
    if (empty($this->options['facet_dependencies'][$facet])) {
      return TRUE;
    }
    // If the facet actually has a selected item, this should also be TRUE.
    if (!empty($this->current_query[$facet])) {
      return TRUE;
    }
    // Finally check for the actual dependency.
    $dependency = $this->options['facet_dependencies'][$facet];
    if (empty($this->current_query[$dependency])) {
      return FALSE;
    }
    // Otherwise the dependency is met.
    return TRUE;
  }

  /**
   * Construct an <a> tag for a facet link.
   * @param  string $facet Machine name of facet.
   * @param  string $item  Value for this facet item.
   * @param  int    $count Numeric count for this facet item.
   * @return string        <a> tag for the facet link.
   */
  protected function constructFacetLink($facet, $item, $count) {

    $query = $this->getCurrentQueryForOutput();
    $class = 'doj-facet-item-inactive';
    $prefix = '';

    // If the current query already has the facet item we need to remove it
    // from the current query.
    if (!empty($query[$facet]) && in_array($item, $query[$facet])) {
      $key = array_search($item, $query[$facet]);
      unset($query[$facet][$key]);
      $class = 'doj-facet-item-active';
      if (!empty($this->options['active_prefix'])) {
        $prefix = $this->options['active_prefix'];
      }
    }
    // Otherwise we need to add it to the current query.
    else {
      $query[$facet][] = $item;
    }

    $href = $this->base_url;
    $query_string = http_build_query($query);
    if (!empty($query_string)) {
      $href .= '?' . $query_string;
    }
    $item = $prefix . $item;
    if ($this->options['show_counts']) {
      $item = $item . " ($count)";
    }
    return '<a href="' . $href . '" class="' . $class . '">' . $item . '</a>';
  }

  /**
   * Helper function to get the facet collapsing rules for a facet.
   * @param string $facet
   *   Machine name for facet.
   * @param int $num_items
   *   Number of items for this facet.
   * @return int
   *   -1 if no collapsing, 0 if collapse everything, 1 or more otherwise.
   */
  protected function getFacetCollapse($facet, $num_items) {

    // Decide on collapsing. Since 0 has a meaning, use -1 to indicate that no
    // collapsing should happen.
    $collapse_after = -1;
    if (isset($this->options['collapse_facets'][$facet])) {
      $collapse_after = $this->options['collapse_facets'][$facet];
    }
    if ($collapse_after >= $num_items) {
      // No need for collapsing if the number is lower.
      $collapse_after = -1;
    }
    return $collapse_after;
  }

  /**
   * Get all facet links for a facet in an unordered HTML list.
   * @param  string $facet Machine name for facet.
   * @return string        Unordered list of facet links.
   */
  protected function getFacetLinks($facet) {
    $items = $this->getFacetItems($facet);
    if (empty($items)) {
      return '';
    }

    $collapse_after = $this->getFacetCollapse($facet, count($items));
    $class = 'facet-items';
    if ($collapse_after > -1) {
      $class .= ' doj-facet-collapse-outer';
    }
    if ($collapse_after === 0) {
      $class .= ' doj-facet-collapse-all';
    }
    $output = '  <ul class="' . $class . '">' . PHP_EOL;

    $num_displayed = 0;
    foreach ($items as $item => $count) {
      $link = $this->constructFacetLink($facet, $item, $count);
      $item_class = '';
      $num_displayed += 1;
      if ($collapse_after > -1 && $num_displayed > $collapse_after) {
        $item_class .= ' class="doj-facet-item-collapsed"';
      }
      $output .= '    <li' . $item_class . '>' . $link . '</li>' . PHP_EOL;
    }
    $output .= '  </ul>' . PHP_EOL;
    return $output;
  }

  /**
   * Get the full HTML output for a facet block.
   * @param  string $facet      Machine name of facet.
   * @param  boolean $show_label Where to show the facet label.
   * @return string             HTML of facet block.
   */
  public function getFacetOutput($facet, $show_label = TRUE) {
    // First check for dependencies.
    if (!$this->facetDependencyMet($facet)) {
      return '';
    }
    // Check to see if we should hide the label.
    $dependent = (!empty($this->options['facet_dependencies'][$facet]));
    $class = 'doj-facet';
    if ($dependent && $this->options['nested_dependents']) {
      $show_label = FALSE;
      $class .= ' doj-facet-dependent';
    }
    $links = $this->getFacetLinks($facet);
    if (empty($links)) {
      return '';
    }

    $output = '<div class="' . $class . '">' . PHP_EOL;
    if ($show_label) {
      $labels = $this->getFacetLabels();
      $output .= '  <h2 class="doj-facet-label">' . $labels[$facet] . '</h2>' . PHP_EOL;
    }
    $output .= $links;
    $output .= '</div>' . PHP_EOL;
    return $output;
  }

  /**
   * Get the full HTML for all the facet blocks.
   * @return string Full HTML of all facet blocks.
   */
  public function getFacets() {
    $output = '';
    foreach ($this->getFacetColumns() as $facet) {
      $output .= $this->getFacetOutput($facet);
    }
    return $output;
  }

  /**
   * Get the HTML for the keyword widget.
   * @return string HTML of keyword widget.
   */
  public function getKeywordWidget() {
    $value = '';
    if (!empty($this->current_query['keys'])) {
      $value = htmlentities(stripslashes($this->current_query['keys']));
    }
    $help = '';
    if (!empty($this->options['keyword_help'])) {
      $help = <<<HELP
<div class="doj-facet-help doj-facet-collapse">
  <span class="doj-facet-collapse-trigger">{$this->options['keyword_help_label']}</span>
  <div class="doj-facet-collapse-inner">
    {$this->options['keyword_help']}
  </div>
</div>
HELP;
    }
    $extra_column = '';
    if (!empty($this->options['optional_keyword_column']) &&
        !empty($this->options['optional_keyword_column_label'])) {
      $checked = (!empty($this->getParameter('full_keys'))) ? 'checked="checked"' : '';
      $extra_column = <<<EXTRA
  <input type="checkbox" id="doj-extra-col" name="full_keys" value="1" {$checked}>
  <label for="doj-extra-col">{$this->options['optional_keyword_column_label']}</label>
EXTRA;
    }
    $widget = <<<FORM
<form method="get">
  <label for="doj-facet-keys">Keywords</label>
  <input type="text" name="keys" id="doj-facet-keys" value="$value" size="50" />
  {$extra_column}
  <input type="submit" value="{$this->options['search_button_text']}" />
  <input type="button" onclick="location.href='{$this->base_url}';" value="Reset" />
</form>
FORM;
    return $widget . PHP_EOL . $help . PHP_EOL;
  }

  /**
   * Get a certain query parameter.
   * @return string Query parameter by name.
   */
  protected function getParameter($param) {
    if (!empty($_GET[$param])) {
      return $_GET[$param];
    }
    return FALSE;
  }

  /**
   * Get the current page, as an integer.
   * @return int Current page.
   */
  protected function getPage() {
    return intval($this->getParameter('page'));
  }

  /**
   * Get the database rows of all matching items given this query.
   * @return array Array of row arrays.
   */
  public function getRows() {
    $query = "SELECT * FROM $this->table";
    $where = $this->getWhereStatement();
    $limit = '';
    if ($this->options['pager_limit'] !== 0) {
      $page = $this->getPage();
      $limit = ' LIMIT ' . $this->options['pager_limit'];
      $limit .= ' OFFSET ' . $this->options['pager_limit'] * $page;
    }
    $sort_field = $this->getSortField();
    $sort_direction = $this->getSortDirection();
    $order = '';
    if (!empty($sort_field) && !empty($sort_direction)) {
      $order = ' ORDER BY ' . $sort_field . ' ' . $sort_direction;
    }
    $result = $this->db->query($query . $where . $order . $limit);
    return $result;
  }

  /**
   * Get the database rows in table form, using specified columns.
   * @param  array $columns
   *   Associative array of machine-readable column names keyed to
   *   human-readable column headers.
   * @param  array $min_widths
   *   Associative array of machine-readable column names keyed to integers
   *   representing the number of pixels.
   *
   * @return string          HTML of table.
   */
  public function getRowsAsTable($columns, $min_widths = array()) {
    $total_rows = 0;
    $output = '<table class="doj-facet-search-results">' . PHP_EOL;
    $output .= '  <thead>' . PHP_EOL;
    $output .= '    <tr>' . PHP_EOL;
    foreach ($columns as $column_name => $column_label) {
      $label = $this->getTableHeaderLabel($column_name, $column_label);
      if (!empty($min_widths[$column_name])) {
        $min_width = ' style="min-width:' . $min_widths[$column_name] . ';"';
      }
      else {
        $min_width = '';
      }
      $output .= '      <th' . $min_width . '>' . $label . '</th>' . PHP_EOL;
    }
    $output .= '    </tr>' . PHP_EOL . '  </thead>' . PHP_EOL . '  <tbody>' . PHP_EOL;
    foreach ($this->getRows() as $row) {
      $row_markup = '  <tr>' . PHP_EOL;
      foreach (array_keys($columns) as $column) {
        $td = $row[$column];
        if (in_array($column, array_keys($this->options['href_columns']))) {
          $href_column = $this->options['href_columns'][$column];
          if (!empty($row[$href_column])) {
            $td = '<a href="' . $row[$href_column] . '">' . $row[$column] . '</a>';
          }
        }
        $row_markup .= '    <td>' . $td . '</td>' . PHP_EOL;
      }
      $row_markup .= '  </tr>' . PHP_EOL;
      $output .= $row_markup;
      $total_rows += 1;
    }
    $output .= '  </tbody>' . PHP_EOL . '</table>' . PHP_EOL;
    if (empty($total_rows)) {
      return "<p>{$this->options['no_results_message']}</p>" . PHP_EOL;
    }
    return $output;
  }

  /**
   * Get the HTML to add as a header label in table output.
   * @param  string $column_name  Machine name of column.
   * @param  string $column_label Human name of column.
   * @return string               HTML to put into table header.
   */
  protected function getTableHeaderLabel($column_name, $column_label) {
    // If this is not a sorting column, just return the label.
    if (!in_array($column_name, array_keys($this->sort_columns))) {
      return $column_label;
    }
    return $this->constructSortingLink($column_name, $column_label);
  }

  /**
   * Get the HTML for the javascript.
   * @return string HTML for the javascript.
   */
  public function getJavascript() {
    $output = '';
    if ($this->options['checkboxes']) {
      $output .= '<script type="text/javascript" src="assets/facet-checkboxes.js"></script>' . PHP_EOL;
    }
    $output .= '<script type="text/javascript" src="assets/facet-collapsing.js"></script>' . PHP_EOL;
    return $output;
  }

  /**
   * Get the HTML for the CSS.
   * @return string HTML for the CSS.
   */
  public function getStyles() {
    $output = '';
    $output .= '<link rel="stylesheet" href="assets/facet-styles.css" />' . PHP_EOL;
    return $output;
  }

  /**
   * Get the HTML for the pager.
   * @return string HTML for the pager.
   */
  public function getPager() {
    if ($this->options['pager_limit'] === 0) {
      return '';
    }
    $total = $this->getRowCount();
    $limit = $this->options['pager_limit'];
    $page = $this->getPage();
    $total_pages = ceil($total / $limit);

    if ($total_pages == 1) {
      return '';
    }

    $pager = '<ul class="doj-facet-pager">' . PHP_EOL;
    // Add a "First" link.
    $pager .= '  <li>' . $this->constructPagerLink(0, '&laquo; first') . '</li>' . PHP_EOL;
    // Add a "Previous" link.
    $previous = $page - 1;
    if ($previous < 0) {
      $previous = 0;
    }
    $pager .= '  <li>' . $this->constructPagerLink($previous, '&lsaquo; previous') . '</li>' . PHP_EOL;
    // Add all the page links.
    $dotdotdot = FALSE;
    for ($i = 0; $i < $total_pages; $i++) {
      $link = $this->constructPagerLink($i);
      if (!$link && !$dotdotdot) {
        $pager .= '  <li>...</li>' . PHP_EOL;
        // Avoid having lots of ... ... ... ... etc.
        $dotdotdot = TRUE;
      }
      elseif ($link) {
        $pager .= '  <li>' . $link . '</li>' . PHP_EOL;
        // Set dotdotdot to FALSE so that more dots can be shown afterwards.
        $dotdotdot = FALSE;
      }
    }

    // Add a "Next" link.
    $next = $page + 1;
    if ($next >= $total_pages) {
      $next = $total_pages - 1;
    }
    $pager .= '  <li>' . $this->constructPagerLink($next, 'next &rsaquo;') . '</li>' . PHP_EOL;
    // Add a "Last" link.
    $pager .= '  <li>' . $this->constructPagerLink($total_pages - 1, 'last &raquo;') . '</li>' . PHP_EOL;
    $pager .= '</ul>' . PHP_EOL;
    return $pager;
  }

  /**
   * Get the total number of rows matching the current query.
   * Regardless of paging limits/offsets.
   *
   * @return int Number of matching rows.
   */
  protected function getRowCount() {
    $query = "SELECT COUNT(*) as count FROM $this->table";
    $where = $this->getWhereStatement();
    $result = $this->db->query($query . $where);
    foreach ($result as $row) {
      return $row['count'];
    }
    return 0;
  }

  /**
   * Construct a link to a new page.
   * @param  int $page  Page number.
   * @param  string $label Human-readable label for the link.
   * @return string        HTML for an <a> tag.
   */
  protected function constructPagerLink($page, $label = NULL) {

    $current_page = $this->getPage();
    // First check to see if this is outside our "pager_radius" and was not
    // given a specific label. If so, return nothing.
    $radius = abs($page - $current_page);
    if (empty($label) && $radius > $this->options['pager_radius']) {
      return FALSE;
    }

    $query = $this->getCurrentQueryForOutput();
    if ($page > 0) {
      $query['page'] = $page;
    }
    $href = $this->base_url;
    $query_string = http_build_query($query);
    if (!empty($query_string)) {
      $href .= '?' . $query_string;
    }
    $active = FALSE;
    if ($page == $current_page) {
      $active = TRUE;
    }
    // Human-readable page number.
    if (empty($label)) {
      $label = $page + 1;
    }
    if ($active) {
      return '<span class="doj-facet-pager-link-active">' . $label . '</span>';
    }
    return '<a href="' . $href . '" class="doj-facet-pager-link">' . $label . '</a>';
  }

  /**
   * Construct a link for a new sort.
   * @param  string $sort_field Machine name of column.
   * @param  string $label      Human-readable label for link.
   * @return string             HTML for an <a> tag.
   */
  protected function constructSortingLink($sort_field, $label) {

    $query = $this->getCurrentQueryForOutput();
    $query['sort'] = $sort_field;
    $class = 'doj-facet-sort-link';
    // If this is the currently sorted field, then make the direction the
    // reverse of the default. Otherwise make it the default. We also take this
    // opportunity to add a class to show an up/down arrow.
    $direction = $this->getSortDirection($sort_field);
    $current_sort = $this->getSortField();
    if ($sort_field == $current_sort) {
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
    $href = $this->base_url;
    $query_string = http_build_query($query);
    if (!empty($query_string)) {
      $href .= '?' . $query_string;
    }
    return '<a href="' . $href . '" class="' . $class . '">' . $label . '</a>';
  }

  /**
   * Get the column name of the currently sorted field.
   * @return string Machine name of sorted field.
   */
  protected function getSortField() {
    $field = $this->getParameter('sort');
    if (in_array($field, array_keys($this->sort_columns))) {
      return $field;
    }
    // Otherwise default to the first.
    if (!empty($this->sort_columns)) {
      $column_names = array_keys($this->sort_columns);
      return $column_names[0];
    }
    return FALSE;
  }

  /**
   * Get the current sort.
   * @param  string $sort_field Machine name of sorting column to look for.
   * @return string 'ASC' or 'DESC'.
   */
  protected function getSortDirection($sort_field = NULL) {

    // If $sort_field was specified, that means that we want the sort direction
    // of that specific field. Ie, if that is not the current sort, we should
    // return the default sort for that field.
    if (!empty($sort_field) && $sort_field != $this->getSortField()) {
      return $this->sort_columns[$sort_field];
    }
    // Otherwise, return whatever is in the URL, if anything.
    $allowed = array('ASC', 'DESC');
    $direction = $this->getParameter('sort_direction');
    if (in_array($direction, $allowed)) {
      return $direction;
    }
    // Otherwise return the default for the current sort.
    $current_sort = $this->getSortField();
    if (!empty($current_sort)) {
      return $this->sort_columns[$current_sort];
    }
    return FALSE;
  }
}
