<?php
/**
 * @file
 * Class for rendering the links for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class Link {

  public static function render($url, $label, $query, $class) {

    $href = $url;
    $query_string = http_build_query($query);
    if (!empty($query_string)) {
      $href .= '?' . $query_string;
    }
    return sprintf('<a href="%s" class="%s">%s</a>', $href, $class, $label);
  }
}