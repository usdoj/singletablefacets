<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';
$configFile = dirname(__FILE__) . '/../singletablefacets.yml';
$app = new \USDOJ\SingleTableFacets\AppWeb($configFile);
?>
<html>
<head>
  <title>Testing Facets</title>
  <?php print $app->renderStyles() ?>
</head>
<body>
  <?php print $app->renderKeywordSearch(); ?>
  <?php print $app->renderFacets(); ?>
  <?php print $app->renderResults(); ?>
  <?php print $app->renderPager(); ?>
  <script   src="https://code.jquery.com/jquery-1.12.4.min.js"   integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ="   crossorigin="anonymous"></script>
  <?php print $app->renderJavascript() ?>
</body>
</html>
