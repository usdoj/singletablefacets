<?php
/**
 * @file
 * Class for a facet (group of facet items) in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class Facet
 * @package USDOJ\SingleTableFacets
 *
 * A class for a column from the database to be used as a facet.
 */
class Facet {

    /**
     * @var string
     *   The column name in the database for this facet.
     */
    private $name;

    /**
     * @var string
     *   The human-readable label to display above the facet.
     */
    private $label;

    /**
     * @var \USDOJ\SingleTableFacets\AppWeb
     *   Reference to the main app.
     */
    private $app;

    /**
     * @var array
     *   All of the \USDOJ\SingleTableFacets\FacetItem objects for this facet.
     */
    private $items;

    /**
     * @var bool
     *   Whether or not an item from this facet has been selected by the user.
     */
    private $active;

    /**
     * Get the database column name for the facet.
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Get the human-readable label for the facet.
     *
     * @return string
     */
    public function getLabel() {
        return $this->label;
    }

    /**
     * Get the main app.
     *
     * @return \USDOJ\SingleTableFacets\AppWeb
     */
    public function getApp() {
        return $this->app;
    }

    /**
     * Get the array of FacetItem objects.
     *
     * @return array
     */
    public function getItems() {
        return $this->items;
    }

    /**
     * Check to see if this facet is active.
     *
     * @return bool
     */
    public function isActive() {
        return $this->active;
    }

    /**
     * Whether this facet is a date facet.
     *
     * Or optionally, if $name is passed, whether that facet is a date facet.
     *
     * @param null $name
     * @return bool
     */
    public function isDate($name = NULL) {
        if (empty($name)) {
            $name = $this->getName();
        }
        $dateColumns = $this->getApp()->getDateColumns();
        return in_array($this->getName(), $dateColumns);
    }

    /**
     * Facet constructor.
     *
     * @param $app
     *   Reference to the main app.
     * @param $name
     *   The column name for the facet.
     */
    public function __construct($app, $name) {

        $this->name = $name;
        $this->app = $app;
        $this->items = array();

        $labels = $this->getApp()->settings('facet labels');
        $this->label = $labels[$name];

        $this->items = $this->fetchItems($name);
    }

    /**
     * Build the array of FacetItem objects for this facet.
     *
     * Or optionally if $name is passed, build the FacetItems for that facet.
     *
     * @param null $name
     * @param string $dateGranularity
     * @return array
     */
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
            $items = array_merge($items, $this->queryFacetItems($additional, $dateGranularity, $name));
        }

        // First remove all the duplicates. This is necessary because the same
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

        // Sort the facets alphabetically first.
        if (!$this->isDate()) {
            // Should the sort be natural?
            $naturalColumns = $this->getApp()->settings('columns with natural sorting');
            if (in_array($name, $naturalColumns)) {
                $naturalSort = array();
                $naturalKeys = array_keys($keyedByName);
                natsort($naturalKeys);
                foreach ($naturalKeys as $naturalKey) {
                    $naturalSort[$naturalKey] = $keyedByName[$naturalKey];
                }
                $keyedByName = $naturalSort;
            }
            else {
                // If not natural, a simple alphabetical sort.
                ksort($keyedByName);
            }
        }
        else {
            // But date facets get reverse order.
            krsort($keyedByName);
        }
        // Then sort by item count ("popularity") if the config says to.
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

    /**
     * Execute the database query to find facet item values for this facet.
     *
     * Or optionally, if $name is passed, query the values for that facet.
     *
     * @param null $name
     * @param string $dateGranularity
     * @param string $originalName
     *   If this is an "additional column", the name of the original column.
     * @return mixed
     */
    private function queryFacetItems($name = NULL, $dateGranularity = 'year', $originalName = NULL) {

        if (empty($name)) {
            $name = $this->getName();
        }

        $singleChoiceFacets = $this->getApp()->settings('facets limited to one choice');
        $ignoreColumn = NULL;
        $facetToCheckFor = (!empty($originalName)) ? $originalName : $name;
        if (in_array($facetToCheckFor, $singleChoiceFacets)) {
            $ignoreColumn = $facetToCheckFor;
        }
        $query = $this->getApp()->query($ignoreColumn);

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

    /**
     * If this facet has any dependencies, check to see if they are met.
     *
     * @return bool
     */
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

    /**
     * Get the point at which the facet items should be hidden with "View more".
     *
     * @return int
     */
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

    /**
     * Get the HTML markup for the unordered list of facet items.
     *
     * @return string
     */
    protected function getList() {

        $items = $this->getItems();
        if (empty($items)) {
            return '';
        }

        $collapseAfter = $this->getCollapse();
        $class = 'stf-facet-items';
        if ($this->getApp()->settings('use checkboxes for facets instead of links')) {
            $singleChoiceFacets = $this->getApp()->settings('facets limited to one choice');
            if (in_array($this->getName(), $singleChoiceFacets)) {
                $class .= ' stf-facet-radios';
            }
            else {
                $class .= ' stf-facet-checkboxes';
            }
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
            $dateGranularity = $this->getApp()->settings('date facet granularity');
            if (!empty($dateGranularity[$this->getName()])) {
                $dateGranularity = $dateGranularity[$this->getName()];
            }
            else {
                $dateGranularity = 'month';
            }
            if ($showMonths && 'year' != $dateGranularity) {
                $monthItems = $this->fetchItems($this->getName(), 'month');
                $listItems = $this->getListItems($monthItems, $collapseAfter, 'F');
                $output .= '<ul class="stf-facet-months">' . $listItems;
                // If the days should be visible, we add another nested list.
                if ($showDays && 'day' == $dateGranulary) {
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

    /**
     * Create <li> tags for each of the passed facet items.
     *
     * @param array $facetItems
     *   An array of \USDOJ\SingleTableFacets\FacetItem objects.
     * @param $collapseAfter
     *   The point after which the "View more" should appear.
     * @param string $dateFormat
     *   How to render the date values if the facet is a date facet.
     *
     * @return string
     */
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

    /**
     * Render the full HTML for this facet.
     *
     * @return string
     */
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
