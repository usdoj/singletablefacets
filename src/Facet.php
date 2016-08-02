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
    public function isDate($name = NULL) {
        if (empty($name)) {
            $name = $this->getName();
        }
        $dateColumns = $this->getApp()->getDateColumns();
        return in_array($this->getName(), $dateColumns);
    }

    public function __construct($app, $name) {

        $this->name = $name;
        $this->app = $app;
        $this->items = array();

        $labels = $this->getApp()->settings('facet labels');
        $this->label = $labels[$name];

        $this->items = $this->fetchItems($name);
    }

    private function fetchItems($name = NULL, $dateGranularity = 'year') {

        $app = $this->getApp();
        if (empty($name)) {
            $name = $this->getName();
        }

        // Check to see if this facet needs to compile values from additional
        // columns.
        $additionalColumns = array();
        $columns = $this->getApp()->settings('columns for additional values');
        if (!empty($columns)) {
            foreach ($columns as $additionalColumn => $mainColumn) {
                if ($name == $mainColumn) {
                    $additionalColumns[] = $additionalColumn;
                }
            }
        }

        // Query the database to get all the items.
        $items = $this->queryFacetItems($name, $dateGranularity);
        foreach ($additionalColumns as $additional) {
            $items = array_merge($items, $this->queryFacetItems($additional, $dateGranularity));
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
        $items = array();
        foreach ($keyedByName as $itemName => $itemCount) {
            if (!empty($itemCount)) {
                $item = new FacetItem($app, $this, $itemName, $itemCount);
                if ($item->isActive()) {
                    $this->active = TRUE;
                }
                $items[] = $item;
            }
        }

        return $items;
    }

    private function queryFacetItems($name = NULL, $dateGranularity = 'year') {

        if (empty($name)) {
            $name = $this->getName();
        }
        $query = $this->getApp()->query();

        // Special select for date facets.
        if ($this->isDate($name)) {

            // Because the date facets are hierarchical, here we only get the
            // years. We will have to make additional queries later to get the
            // months and days.
            $tokens = array(
                'year' => '%Y',
                'month' => '%Y-%m',
                'day' => '%Y-%m-%d',
            );
            $token = $tokens[$dateGranularity];
            $query->addSelect("DATE_FORMAT($name, '$token') AS item");
        }
        else {
            $query->addSelect("$name as item");
        }

        $query->addSelect("COUNT(*) AS count");
        $query->addGroupBy('item');
        return $query->execute()->fetchAll();
    }

    private function meetsDependencies() {

        $dependentColumns = $this->getApp()->settings('dependent columns');
        if (!empty($dependentColumns[$this->getName()])) {
            $parent = $dependentColumns[$this->getName()];
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
            $active = $this->getApp()->getParameter($parent);
            if (empty($active)) {
                return FALSE;
            }
        }
        // Otherwise the dependency is met.
        return TRUE;
    }

    private function getCollapse() {

        $collapseColumns = $this->getApp()->settings('collapse facet items');

        // Decide on collapsing. Since 0 has a meaning, use -1 to indicate that no
        // collapsing should happen.
        $collapseAfter = -1;
        if (isset($collapseColumns[$this->getName()])) {
            $collapseAfter = $collapseColumns[$this->getName()];
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

        $items = $this->getItems();
        if (empty($items)) {
            return '';
        }

        $collapseAfter = $this->getCollapse();
        $class = 'stf-facet-items';
        if ($this->getApp()->settings('use checkboxes for facets instead of links')) {
            $class .= ' stf-facet-checkboxes';
        }
        if ($collapseAfter > -1) {
            $class .= ' stf-facet-collapse-outer';
        }
        if ($collapseAfter === 0) {
            $class .= ' stf-facet-collapse-all';
        }
        if ($this->isDate()) {
            $class .= ' stf-facet-date';
        }
        $output = '  <ul class="' . $class . '">' . PHP_EOL;

        $listItems = $this->getListItems($this->getItems(), $collapseAfter);
        $output .= $listItems;

        // If this is a date facet, we may need to add more "children" of the
        // date hierarcy.
        if ($this->isDate()) {
            $currentValue = $this->getApp()->getParameter($this->getName());
            $showMonths = FALSE;
            $showDays = FALSE;
            if (!empty($currentValue)) {

                $numChars = strlen($currentValue);
                if ($numChars >= 4) {
                    $showMonths = TRUE;
                }
                if ($numChars >= 7) {
                    $showDays = TRUE;
                }
            }
            // If the months should be visible, we add a nested list.
            $dateGranularities = $this->getApp()->getDateGranularities();
            if (!empty($dateGranularities[$this->getName()])) {
                $dateGranularities = $dateGranularities[$this->getName()];
            }
            else {
                $dateGranularities = array();
            }
            if ($showMonths && in_array('2month', $dateGranularities)) {
                $monthItems = $this->fetchItems($this->getName(), 'month');
                $listItems = $this->getListItems($monthItems, $collapseAfter, 'F');
                $output .= '<ul class="stf-facet-months">' . $listItems;
                // If the days should be visible, we add another nested list.
                if ($showDays && in_array('3day', $dateGranularities)) {
                    $dayItems = $this->fetchItems($this->getName(), 'day');
                    $listItems = $this->getListItems($dayItems, $collapseAfter, 'j');
                    $output .= '<ul class="stf-facet-days">' . $listItems;
                    $output .= '  </ul>' . PHP_EOL;

                }
                $output .= '  </ul>' . PHP_EOL;
            }
        }
        $output .= '  </ul>' . PHP_EOL;
        return $output;
    }

    private function getListItems($facetItems, $collapseAfter, $dateFormat = 'Y') {
        $numDisplayed = 0;
        $output = '';
        foreach ($facetItems as $item) {
            $link = $item->render($dateFormat);
            $itemClass = '';
            $numDisplayed += 1;
            if ($collapseAfter > -1 && $numDisplayed > $collapseAfter) {
                $itemClass .= ' class="stf-facet-item-collapsed"';
            }
            $output .= '    <li' . $itemClass . '>' . $link . '</li>' . PHP_EOL;
        }
        return $output;
    }

    public function render() {

        // If the facet does not meet its dependencies, do not display it.
        if (!$this->meetsDependencies()) {
            return '';
        }

        // Check to see if this facet is a dependent.
        $dependentColumns = $this->getApp()->settings('dependent columns');
        $dependent = (!empty($dependentColumns[$this->getName()]));

        $class = 'stf-facet';
        $showLabel = TRUE;
        if ($dependent && $this->getApp()->settings('show dependents indented to the right')) {
            $showLabel = FALSE;
            $class .= ' stf-facet-dependent';
        }

        // Get the actual list of facet items.
        $list = $this->getList();
        if (empty($list)) {
            return '';
        }

        $output = '<div class="' . $class . '">' . PHP_EOL;
        if ($showLabel) {
            $output .= '  <h2 class="stf-facet-label">' . $this->getLabel() . '</h2>' . PHP_EOL;
        }
        $output .= $list;
        $output .= '</div>' . PHP_EOL;
        return $output;
    }
}
