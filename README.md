# Single Table Facets

This class is intended as a simple faceted search solution for PHP applications where the data source is a single MySQL table. It does not require any joins or relationships. If you want faceted search, but you want the data source to be as simple as an Excel spreadsheet imported into a MySQL database, this class should help.

## Dependencies

* PHP/MySQL
* jQuery (for checkbox-style facets)

## Usage

See example.php for a more detailed example, but here is the basic idea:

```
$table = 'mytable';
$facet_columns = array(
  'tag' => 'Filter by tag',
  'author' => 'Filter by author',
);
$keyword_columns = array(
  'title',
  'teaser',
  'body',
);
$sort_columns = array(
  'title' => 'ASC',
  'author' => 'ASC',
  'date' => 'DESC',
);
$options = array(
  // Put optional settings here.
);

$facets = new SingleTableFacets($table, $facet_columns, $keyword_columns, $sort_columns, $options);

// Print the CSS.
print $facets->getStyles();

// Print the keyword search widget.
print $facets->getKeywordWidget();

// Print the facet blocks.
print $facets->getFacets();

// Print the results as a table.
$table_columns = array(
  'title' => 'Title',
  'author' => 'Author',
  'date' => 'Date',
  'teaser' => 'Description',
);
print $facets->getRowsAsTable($table_columns);

// Print the pager.
print $facets->getPager();

// Print the javascript.
print $facets->getJavascript();
```

## Options

The `$options` parameter is an associative array which can have any number of the following options:

* minumum_keyword_length: Minimum number of characters a keyword must have to be considered. Default: `3`
* facet_dependencies: An associative array of facets that depend on other facets before they are displayed. Eg:	array('child_facet' => 'parent_facet'). Default: `array()`
* active_prefix: A string to prepend to all active facet items. Default: `''`
* show_counts: Whether to display the counts of facet items. Default: `TRUE`
* search_button_text: A string to use for the search button text. Default: `'Search'`
* hide_single_item_facets: Hide non-active facets with less than 2 items. Default: `FALSE`
* no_results_message: Message to display when no results are found. Default: `'Sorry, no results could be found for those keywords'`
* checkboxes: Whether to use javascript-powered checkboxes or stick with ordinary links. Default: `FALSE`
* pager_limit: The number items per page, or 0 to disable paging. Default: `10`
* href_columns: A mapping of columns for cases where some columns need to be treated as link hrefs, where another column is the link labels. Must be an associative array of $label_column => $href_column. Default: `array()`
* required_columns: An array of columns which cannot be NULL or empty. Default: `array()`
* pager_radius: The maximum number of pager pages to show on each side of the current page. If there are more pager pages than this max, then the extra pages will be replaced with a "...". Use 0 to display all pages. Default: `2`
* nested_dependents: Whether to hide the labels of the facet blocks for dependents and indent them slightly to make them look "nested". Note that this may appear confusing if the parent facet still has multiple items in it. (The child facets will appear to be nested from the last parent item.) This also looks weird unless the child facet is directly after the parent facet in the $facet_columns parameter. Default: `array()`
