<?php
/**
 * @file
 * Class for a facet item in SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class FacetItem
 * @package USDOJ\SingleTableFacets
 *
 * A class for an individual item in a particular facet.
 */
class FacetItem {

    /**
     * @var \USDOJ\SingleTableFacets\Facet
     *   Reference to the Facet object that this item belongs to.
     */
    private $facet;

    /**
     * @var string
     *   The value in the database for this item.
     */
    private $value;

    /**
     * @var int
     *   Given the current query, the number of matching rows for this item.
     */
    private $count;

    /**
     * @var \USDOJ\SingleTableFacets\AppWeb
     *   Reference to the main app.
     */
    private $app;

    /**
     * Get the Facet object this item belongs to.
     *
     * @return \USDOJ\SingleTableFacets\Facet
     */
    public function getFacet() {
        return $this->facet;
    }

    /**
     * Get the database value for this facet.
     *
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     * Get the number of matching rows for this item.
     *
     * @return int
     */
    public function getCount() {
        return $this->count;
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
     * FacetItem constructor.
     *
     * @param $app
     *   Reference to the main app.
     * @param $facet
     *   Reference to the Facet object this item belongs to.
     * @param $value
     *   The database value for the item.
     * @param $count
     *   The number of matching rows for the item.
     */
    public function __construct($app, $facet, $value, $count) {

        $this->facet = $facet;
        $this->value = $value;
        $this->count = $count;
        $this->app = $app;
    }

    /**
     * Check whether this particular facet item has been selected by the user.
     *
     * @return bool
     */
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

    /**
     * Render the markup for this facet item.
     *
     * @param string $dateFormat
     *   The data format to use if this item belongs to a date facet.
     *
     * @return string
     */
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
            $singleChoiceFacets = $this->getApp()->settings('facets limited to one choice');
            $singleChoice = (in_array($facet, $singleChoiceFacets));
            // If the current query already has the facet item we need to remove it
            // from the current query.
            if (!empty($parameters[$facet]) && in_array($value, $parameters[$facet])) {
                $key = array_search($value, $parameters[$facet]);
                unset($parameters[$facet][$key]);
                $class = 'stf-facet-item-active';
            }
            elseif ($singleChoice) {
                $parameters[$facet] = array($value);
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

        // If there is a Twig template for this facet column, use that.
        $twigTemplate = $facet . '.html.twig';
        if ($this->getApp()->getTwigForFacetItems() &&
            $this->getApp()->getTwigForFacetItems()->getLoader()->exists($twigTemplate)) {
            // If so, render it.
            $label = $this->getApp()->getTwigForFacetItems()->render($twigTemplate, array(
                'value' => $label,
                'count' => $this->getCount(),
            ));
        }
        else {
            // Otherwise we'll be using the raw value, possible with the item count.
            if ($this->getApp()->settings('show counts next to facet items')) {
                $label .= sprintf(' (%s)', $this->getCount());
            }
        }

        // Do not want page numbers to carry over into facets.
        unset($parameters['page']);

        return $this->getApp()->getLink($this->getApp()->getBaseUrl(), $label, $parameters, $class);
    }
}
