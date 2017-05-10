<?php
/**
 * @file
 * Example HTML for usage of singletablefacets.
 */

// First you need to include the autoload file that Composer generated.
require_once dirname(__FILE__) . '/../vendor/autoload.php';

// Next, reference your config file. Note that this should NOT be publicly
// accessible, so put it outside the document root.
$configFile = dirname(__FILE__) . '/../singletablefacets.yml';

// Next create an instance of the AppWeb object.
$app = new \USDOJ\SingleTableFacets\AppWeb($configFile);

// Finally you can layout your page as needed. The php sections below render
// the various bits of the singletablefacets system, by invoking methods on
// the AppWeb object you created above and printing the return value.
?>
<html>
<head>
  <title>Testing Facets</title>
  <?php print $app->renderStyles() ?>
  <style>
    .my-facets { float: left; width: 30% }
    .my-results { float: left; width: 70% }
    .stf-facet-pager { clear: both }
  </style>
</head>
<body>
  <?php print $app->renderKeywordSearch(); ?>
  <div class="my-facets">
    <?php print $app->renderFacets(); ?>
  </div>
  <div class="my-results">
    <?php print $app->renderResults(); ?>
  </div>
  <?php print $app->renderPager(); ?>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
  <?php print $app->renderJavascript() ?>
</body>
</html>
