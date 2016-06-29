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

    public function getFacet() {
        return $this->facet;
    }

    public function getValue() {
        return $this->value;
    }

    public function getCount() {
        return $this->count;
    }

    public function getApp() {
        return $this->app;
    }

    public function __construct($app, $facet, $value, $count) {

        $this->facet = $facet;
        $this->value = $value;
        $this->count = $count;
        $this->app = $app;
    }

    public function isActive() {
        $parameter = $this->getApp()->getParameter($this->getFacet());
        if (empty($parameter)) {
            return FALSE;
        }
        return in_array($this->getValue(), $parameter);
    }

    public function render() {

        $parameters = $this->getApp()->getParameters();
        $class = 'doj-facet-item-inactive';
        $facet = $this->getFacet();
        $value = $this->getValue();

        // If the current query already has the facet item we need to remove it
        // from the current query.
        if (!empty($parameters[$facet]) && in_array($value, $parameters[$facet])) {
            $key = array_search($value, $parameters[$facet]);
            unset($parameters[$facet][$key]);
            $class = 'doj-facet-item-active';
        }
        // Otherwise we need to add it to the current query.
        else {
            $parameters[$facet][] = $value;
        }

        // Add the item count if necessary.
        $label = $this->getValue();
        if ($this->getApp()->settings('show facet counts')) {
            $label .= sprintf(' (%s)', $this->getCount());
        }

        return $this->getApp()->getLink($this->getApp()->getBaseUrl(), $label, $parameters, $class);
    }
}
