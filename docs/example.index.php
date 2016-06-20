<?php
/**
 * This is an example of how you might use implement this class.
 */

// Include the autoload file so that you can use the class.
require_once __DIR__ . '/vendor/autoload.php';

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

// This is just an example. It can be customized depending on needs.
$table = 'mytablename';

// This determines which database columns will show up as facets.
$facet_columns = array(
  'DocumentType' => 'Filter by document type',
  'SubdocumentType' => 'Filter by subdocument type',
  'Category' => 'Filter by category',
  'Circuit' => 'Filter by circuit',
  'District' => 'Filter by district',
);

// This determines which database columns will be consulted during keyword
// searches.
$keyword_columns = array(
  'Title',
  'Statute',
  'Keyword',
  'DocumentType',
  'SubdocumentType',
);

// This determines the default sort, and the options for sorting when using the
// table display.
$sort_columns = array(
  'Title' => 'ASC',
  'Statute' => 'ASC',
);

// These are a variety of optional settings. For the full list, see the comments
// at the top of the class.
$options = array(
  'checkboxes' => TRUE,
  'facet_dependencies' => array('SubdocumentType' => 'DocumentType'),
  'nested_dependents' => TRUE,
  'pager_limit' => 20,
  'required_columns' => array('Title'),
  'href_columns' => array('Title' => 'FileUrl'),
  'collapse_facets' => array('District' => 5, 'Circuit' => 5),
);

// This determines which columns show up in the table-style results.
$table_columns = array(
  'Title' => 'Title',
  'Statute' => 'Statute',
  'DocumentType' => 'Document Type',
);

// Finally, instantiate the object.
$facets = new \USDOJ\SingleTableFacets($db, $table, $facet_columns, $keyword_columns, $sort_columns, $options);

// Now all that is left is to display the various bits. In the HTML below, note
// these scattered PHP snippets:
// $facets->getStyles();
// $facets->getKeywordWidget();
// $facets->getFacets();
// $facets->getResultsAsTable();
// $facets->getPager();
// $facets->getJavascript();
?>
<html>
  <head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
    <?php print $facets->getStyles(); ?>
    <style>
      .my-facet-blocks { float: left; width: 20%; }
      .my-search-results { float: right; width: 80%; }
      .my-pager { clear: right; }
    </style>
  </head>
  <body>
    <!-- Keyword search widget -->
    <div class="my-search-widget">
      <?php print $facets->getKeywordWidget(); ?>
    </div>

    <!-- Facet blocks -->
    <div class="my-facet-blocks">
      <?php print $facets->getFacets(); ?>
    </div>

    <!-- Search results as a table. -->
    <div class="my-search-results">
      <?php
      print $facets->getRowsAsTable($table_columns);
      ?>
    </div>

    <!-- Pager -->
    <div class="my-pager">
      <?php print $facets->getPager(); ?>
    </div>

    <!-- Javascript -->
    <?php print $facets->getJavascript(); ?>
  </body>
</html>