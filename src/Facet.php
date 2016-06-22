<?php
/**
 * @file
 * Class for a facet (group of facet items) in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use USDOJ\SingleTableFacets\DatabaseQuery;

class Facet {

  private $name;
  private $label;
  private $app;
  private $items;

  public function __construct($name, $label, $app) {

    $this->name = $name;
    $this->label = $label;
    $this->app = $app;
    $this->items = array();

    if ($this-isAllowed()) {

      $query = DatabaseQuery::start($app);
      $query->addSelect("$name AS item, COUNT($name) AS count");
      $query->groupBy($name);
      if ($app->getOption('sort_facet_items_by_popularity')) {
        $query->orderBy('count', 'DESC');
      }
      else {
        $query->orderBy('item', 'ASC');
      }
      $result = $query->execute();

      foreach ($result as $row) {
        if (!empty($row['item'])) {
          $this->items[] = new FacetItem($name, $row['item'], $row['count']);
        }
      }
    }
  }

  private function isAllowed() {

  }

  public function render() {
    if (!$this-isAllowed()) {
      return array();
    }
  }
}
