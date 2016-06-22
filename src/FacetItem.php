<?php
/**
 * @file
 * Class for a facet item in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;


class FacetItem {

  private $facet;
  private $value;
  private $count;
  private $app;

  public function __construct($facet, $value, $count, $app) {

    $this->facet = $facet;
    $this->value = $value;
    $this->count = $count;
    $this->app = $app;

  }

  public function render() {

  }
}
