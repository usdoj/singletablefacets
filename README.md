# Single Table Facets

This class is intended as a simple faceted search solution for PHP applications where the data source is a single MySQL table. It does not require any joins or relationships. If you want faceted search, but you want the data source to be as simple as an Excel spreadsheet imported into a MySQL database, this class should help.

## Dependencies

* PHP/MySQL
* jQuery (for checkbox-style facets)
* Composer

## Installation

Use composer to bring this into your PHP project. The composer.json should look like this:

```
{
    "require": {
        "usdoj/singletablefacets": "dev-master"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/usdoj/singletablefacets.git"
        }
    ]
}
```

## Usage

See docs/example.index.php for a more detailed example, but here is the basic idea:

```
// First you instantiate the object using array-based parameters and options.

// Connect to the database.
// Get database connection.
$config = new \Doctrine\DBAL\Configuration();
$connectionParams = array(
    'dbname' => 'mydatabase',
    'user' => 'myuser',
    'password' => 'mypassword',
    'host' => 'localhost',
    'port' => 3306,
    'charset' => 'utf8',
    'driver' => 'pdo_mysql',
);
$db = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $config);

// Pick a database table.
$table = 'mytable';

// Pick the columns from the database table that will act as facets. For each,
// assign a human-readable label that will display above the facet block.
$facet_columns = array(
  'tag' => 'Filter by tag',
  'author' => 'Filter by author',
);

// Pick the columns from the database table that will be consulted during
// keyword searches.
$keyword_columns = array(
  'title',
  'teaser',
  'body',
);

// Pick the columns from the database table that should be allowed as sortable
// fields. For each, choose a default direction, 'ASC' or 'DESC'.
$sort_columns = array(
  'title' => 'ASC',
  'author' => 'ASC',
  'date' => 'DESC',
);

// Any number of optional settings go here. (See later in this README.)
$options = array();

// Instantiate the object.
$facets = new SingleTableFacets($db, $table, $facet_columns, $keyword_columns, $sort_columns, $options);

// Now you can use these methods on the object to output the markup on your
// search page, wherever you would like.

// Output the CSS.
print $facets->getStyles();

// Output the keyword search widget.
print $facets->getKeywordWidget();

// Output the facet blocks.
print $facets->getFacets();

// Output the results as an HTML table. Pick the database columns you would
// like displayed as HTML columns, and for each, assign a human-readable header.
$table_columns = array(
  'title' => 'Title',
  'author' => 'Author',
  'date' => 'Date',
  'teaser' => 'Description',
);
print $facets->getRowsAsTable($table_columns);

// Output the pager.
print $facets->getPager();

// Output the javascript.
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
