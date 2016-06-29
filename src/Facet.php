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

        $query = $app->query();
        $query->addSelect("$name AS item, COUNT($name) AS count");
        $query->groupBy($name);
        if ($app->settings('sort facet items by popularity')) {
            $query->orderBy('count', 'DESC');
        }
        else {
            $query->orderBy('item', 'ASC');
        }
        $result = $query->execute();

        $this->active = FALSE;
        foreach ($result as $row) {
            if (!empty($row['item'])) {
                $item = new FacetItem($app, $name, $row['item'], $row['count']);
                if ($item->isActive()) {
                    $this->active = TRUE;
                }
                $this->items[] = $item;
            }
        }
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
