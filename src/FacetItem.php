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
        $parameter = $this->getApp()->getParameter($this->getFacet()->getName());
        if (empty($parameter)) {
            return FALSE;
        }
        if (is_string($parameter)) {
            return $parameter == $this->getValue();
        }
        else {
            return in_array($this->getValue(), $parameter);
        }
    }

    public function render($dateFormat = 'Y') {

        $parameters = $this->getApp()->getParameters();
        $class = 'stf-facet-item-inactive';
        $facet = $this->getFacet()->getName();
        $value = $this->getValue();

        $valueTokens = array(
            'Y' => 'Y',
            'F' => 'Y-m',
            'j' => 'Y-m-d',
        );
        $valueToken = $valueTokens[$dateFormat];

        // For date facets, we assume that there should only be one active facet
        // item at a time. This is a possible improvement later, but the use-
        // case for items having multiple dates is pretty niche.
        if ($this->getFacet()->isDate()) {

            $normalized = $this->getApp()->normalizeDate($value);
            $unix = strtotime($normalized);
            $newDateValue = date($valueToken, $unix);

            // Check to see if this is the active facet.
            if (!empty($parameters[$facet]) && $newDateValue == $parameters[$facet]) {
                unset($parameters[$facet]);
                $class = 'stf-facet-item-active';
            }
            else {
                $parameters[$facet] = $newDateValue;
            }
        }
        // For all non-date facets, we treat them as arrays.
        else {
            // If the current query already has the facet item we need to remove it
            // from the current query.
            if (!empty($parameters[$facet]) && in_array($value, $parameters[$facet])) {
                $key = array_search($value, $parameters[$facet]);
                unset($parameters[$facet][$key]);
                $class = 'stf-facet-item-active';
            }
            // Otherwise we need to add it to the current query.
            else {
                $parameters[$facet][] = $value;
            }
        }

        // Add the item count if necessary.
        $label = $this->getValue();
        // If this is a date facet, we need to format the value.
        if ($this->getFacet()->isDate()) {
            $normalizedDate = $this->getApp()->normalizeDate($label);
            $unix = strtotime($normalizedDate);
            $label = date($dateFormat, $unix);
        }

        if ($this->getApp()->settings('show counts next to facet items')) {
            $label .= sprintf(' (%s)', $this->getCount());
        }

        // Do not want page numbers to carry over into facets.
        unset($parameters['page']);

        return $this->getApp()->getLink($this->getApp()->getBaseUrl(), $label, $parameters, $class);
    }
}
