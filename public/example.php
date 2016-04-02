<?php
/**
 * @file
 * Proof of concept for a single-table faceted search.
 */

include_once dirname(__FILE__) . '/../private/SingleTableFacets.inc';

// This is just an example. It can be customized depending on needs.
$table = 'mytable';
$facet_columns = array(
  'tag' => 'Filter by tag',
  'author' => 'Filter by author',
  'coauthor' => 'Filter by coauthor',
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
  'checkboxes' => TRUE,
  'facet_dependencies' => array('coauthor' => 'author'),
  'nested_dependents' => TRUE,
);

$facets = new SingleTableFacets($table, $facet_columns, $keyword_columns, $sort_columns, $options);
?>
<html>
  <head>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>
    <?php print $facets->getStyles(); ?>
  </head>
  <body>
    <!-- Keyword search widget -->
    <div>
      <?php print $facets->getKeywordWidget(); ?>
    </div>

    <!-- Facet blocks -->
    <div>
      <?php print $facets->getFacets(); ?>
    </div>

    <!-- Search results as a table. -->
    <div>
      <?php
      $table_columns = array(
        'title' => 'Title',
        'author' => 'Author',
        'date' => 'Date',
        'teaser' => 'Description',
      );
      print $facets->getRowsAsTable($table_columns);
      ?>
    </div>

    <!-- Pager -->
    <div>
      <?php print $facets->getPager(); ?>
    </div>

    <!-- Javascript -->
    <?php print $facets->getJavascript(); ?>
  </body>
</html>
