<?php
/**
 * @file
 * Class for rendering the search bar for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class SearchBar {

  public static function render($app) {

    $keys = $app->getParameter('keys');
    if (!empty($keys)) {
      $keys = htmlentities(stripslashes($keys));
    }
    else {
      $keys = '';
    }

    $help = $app->getOption('keyword_help');
    $labelHelp = $app->getOption('keyword_help_label');
    if (!empty($help)) {
      $help = '
        <div class="doj-facet-help doj-facet-collapse">
          <span class="doj-facet-collapse-trigger">' . $labelHelp . '</span>
          <div class="doj-facet-collapse-inner">' . $help . '</div>
        </div>
      ';
    }

    $optional = '';
    if (!empty($app->getOption('optional_keyword_column'))) {
      $labelOptional = $app->getOption('optional_keyword_column_label');
      $checked = '';
      if ($app->getParameter('full_keys')) {
        $checked = 'checked="checked"';
      }
      $optional = '
        <input type="checkbox" id="doj-extra-col" name="full_keys" value="1" ' . $checked . '>
        <label for="doj-extra-col">' . $labelOptional . '</label>
      ';
    }
    $widget = '
      <form method="get">
        <label for="doj-facet-keys">Keywords</label>
        <input type="text" name="keys" id="doj-facet-keys" value="$value" size="50" />
        ' . $optional . '
        <input type="submit" value="' . $app->getOption('search_button_text') . '" />
        <input type="button" onclick="location.href=\'' . $app->getBaseUrl() . '\';" value="Reset" />
      </form>
    ';
    return $widget . PHP_EOL . $help . PHP_EOL;
  }
}