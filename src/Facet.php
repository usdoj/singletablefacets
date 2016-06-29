<?php
/**
 * @file
 * Class for a facet (group of facet items) in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class Facet {

    private $name;
    private $label;
    private $app;
    private $items;
    private $active;

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
    public function isActive() {
        return $this->active;
    }

    public function __construct($app, $name) {

        $this->name = $name;
        $this->app = $app;
        $this->items = array();

        $columns = $app->settings('database columns');
        $this->label = $columns[$name]['label for facet'];

        // Check to see if this facet needs to compile values from additional
        // columns.
        $additionalColumns = array();
        foreach ($columns as $column => $info) {
            if (!empty($info['contains additional values for'])) {
                if ($name == $info['contains additional values for']) {
                    $additionalColumns[] = $column;
                }
            }
        }

        $items = $this->queryFacetItems($name);
        foreach ($additionalColumns as $additional) {
            $items = array_merge($items, $this->queryFacetItems($additional));
        }

        // First make add all the duplicates. This is necessary because the same
        // item may be in more than one of the "additional" columns.
        $keyedByName = array();
        foreach ($items as $item) {
            if (empty($item['item'])) {
                continue;
            }
            if (empty($keyedByName[$item['item']])) {
                $keyedByName[$item['item']] = $item['count'];
                continue;
            }
            else {
                $newCount = $keyedByName[$item['item']] + $item['count'];
                $keyedByName[$item['item']] = $newCount;
            }
        }

        // Sort by name;
        ksort($keyedByName);
        if ($app->settings('sort facet items by popularity')) {
            arsort($keyedByName);
        }

        $this->active = FALSE;
        foreach ($keyedByName as $itemName => $itemCount) {
            if (!empty($itemCount)) {
                $item = new FacetItem($app, $name, $itemName, $itemCount);
                if ($item->isActive()) {
                    $this->active = TRUE;
                }
                $this->items[] = $item;
            }
        }
    }

    private function queryFacetItems($name) {

        $query = $this->getApp()->query();
        $query->addSelect("$name as item");
        $query->addSelect("COUNT(*) AS count");
        $query->addGroupBy($name);
        return $query->execute()->fetchAll();
    }

    private function meetsDependencies() {

        $columns = $this->getApp()->settings('database columns');
        if (!empty($columns[$this->getName()]['depends on'])) {
            $parent = $columns[$this->getName()]['depends on'];
            $parameter = $this->getApp()->getParameter($this->getName());

            // If there are no facet dependencies, this will always be TRUE.
            if (empty($parent)) {
                return TRUE;
            }
            // If the facet actually has a selected item, this should also be TRUE.
            if (!empty($parameter)) {
                return TRUE;
            }
            // Finally check for the actual dependency.
            if (empty($this->getApp()->getParameter($parent))) {
                return FALSE;
            }
        }
        // Otherwise the dependency is met.
        return TRUE;
    }

    private function getCollapse() {

        $columns = $this->getApp()->settings('database columns');

        // Decide on collapsing. Since 0 has a meaning, use -1 to indicate that no
        // collapsing should happen.
        $collapseAfter = -1;
        if (!empty($columns[$this->getName()]['collapse facet items after'])) {
            $collapseAfter = $columns[$this->getName()]['collapse facet items after'];
        }
        if ($collapseAfter >= count($this->getItems())) {
            // No need for collapsing if the number is lower.
            $collapseAfter = -1;
        }
        // Do not collapse active facets.
        if ($this->isActive()) {
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
        if ($this->getApp()->settings('use checkboxes for facets instead of links')) {
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

        // If the facet does not meet its dependencies, do not display it.
        if (!$this->meetsDependencies()) {
            return '';
        }

        // Check to see if this facet is a dependent.
        $columns = $this->getApp()->settings('database columns');
        $dependent = (!empty($columns[$this->getName()]['depends on']));

        $class = 'doj-facet';
        $showLabel = TRUE;
        if ($dependent && $this->getApp()->settings('show dependents indented to the right')) {
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
            $output .= '  <h2 class="doj-facet-label">' . $columns[$this->getName()]['label for facet'] . '</h2>' . PHP_EOL;
        }
        $output .= $list;
        $output .= '</div>' . PHP_EOL;
        return $output;
    }
}
