<?php
/**
 * @file
 * Class for rendering the links for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class Link {

  public static function getHtml($url, $label, $query, $class) {

    $href = self::getHref($url, $query);
    return sprintf('<a href="%s" class="%s">%s</a>', $href, $class, $label);
  }

  public static function getHref($url, $query) {
    $href = $url;
    $query_string = http_build_query($query);
    if (!empty($query_string)) {
      $href .= '?' . $query_string;
    }
    return $href;
  }
}