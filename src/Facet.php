<?php
/**
 * @file
 * Class for a facet (group of facet items) in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use USDOJ\SingleTableFacets\DatabaseQuery,
    USDOJ\SingleTableFacets\FacetItem;

class Facet {

  private $name;
  private $label;
  private $app;
  private $items;

  public function getName() {
    return $this->name;
  }
  public function getLabel() {
    return $this->label;
  }
  public function getApp() {
    return $this->app;
  }
  public function getItems() {
    return $this->items;
  }

  public function __construct($name, $label, $app) {

    $this->name = $name;
    $this->label = $label;
    $this->app = $app;
    $this->items = array();

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
        $this->items[] = new FacetItem($name, $row['item'], $row['count'], $app);
      }
    }
  }

  private function meetsDependencies() {

    $dependencies = $this->getApp()->getOption('facet_dependencies');
    $parameter = $this->getApp()->getParameter($this->getName());

    // If there are no facet dependencies, this will always be TRUE.
    if (empty($dependencies[$this->getName()])) {
      return TRUE;
    }
    // If the facet actually has a selected item, this should also be TRUE.
    if (!empty($parameter)) {
      return TRUE;
    }
    // Finally check for the actual dependency.
    $dependency = $dependencies[$this->getName()];
    if (empty($this->getApp()->getParameter($dependency))) {
      return FALSE;
    }
    // Otherwise the dependency is met.
    return TRUE;
  }

  private function showLabel() {
    $dependent = (!empty($this->options['facet_dependencies'][$facet]));
  }

  private function getCollapse() {

    $collapseOptions = $this->getApp()->getOption('collapse_facets');

    // Decide on collapsing. Since 0 has a meaning, use -1 to indicate that no
    // collapsing should happen.
    $collapseAfter = -1;
    if (isset($collapseOptions[$this->getName()])) {
      $collapseAfter = $collapseOptions[$this->getName()];
    }
    if ($collapseAfter >= count($this->getItems())) {
      // No need for collapsing if the number is lower.
      $collapseAfter = -1;
    }
    return $collapseAfter;
  }

  protected function getList() {

    if (empty($this->getItems())) {
      return '';
    }

    $collapseAfter = $this->getCollapse();
    $class = 'facet-items';
    if ($this->getApp()->getOption('checkboxes')) {
      $class .= ' facet-checkboxes';
    }
    if ($collapseAfter > -1) {
      $class .= ' doj-facet-collapse-outer';
    }
    if ($collapseAfter === 0) {
      $class .= ' doj-facet-collapse-all';
    }
    $output = '  <ul class="' . $class . '">' . PHP_EOL;

    $numDisplayed = 0;
    foreach ($this->getItems() as $item) {
      $link = $item->render();
      $itemClass = '';
      $numDisplayed += 1;
      if ($collapseAfter > -1 && $numDisplayed > $collapseAfter) {
        $itemClass .= ' class="doj-facet-item-collapsed"';
      }
      $output .= '    <li' . $itemClass . '>' . $link . '</li>' . PHP_EOL;
    }
    $output .= '  </ul>' . PHP_EOL;
    return $output;
  }

  public function render() {

    // If there is only one item and it is not active, hide it.
    if ($this->app->getOption('hide_single_item_facets')) {
      if (count($this->items) === 1 && !$this->items[0]->isActive()) {
        return '';
      }
    }

    // If the facet does not meet its dependencies, do not display it.
    if (!$this->meetsDependencies()) {
      return '';
    }

    // Check to see if this facet is a dependent.
    $dependencies = $this->getApp()->getOption('facet_dependencies');
    $dependent = (!empty($dependencies[$this->getName()]));

    $class = 'doj-facet';
    $showLabel = TRUE;
    if ($dependent && $this->getApp()->getOption('nested_dependents')) {
      $showLabel = FALSE;
      $class .= ' doj-facet-dependent';
    }

    // Get the actual list of facet items.
    $list = $this->getList();
    if (empty($list)) {
      return '';
    }

    $output = '<div class="' . $class . '">' . PHP_EOL;
    if ($showLabel) {
      $output .= '  <h2 class="doj-facet-label">' . $this->getLabel() . '</h2>' . PHP_EOL;
    }
    $output .= $list;
    $output .= '</div>' . PHP_EOL;
    return $output;
  }
}
