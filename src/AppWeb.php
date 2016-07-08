<?php
/**
 * @file
 * Class for creating facets using a single database table.
 */

namespace USDOJ\SingleTableFacets;

class AppWeb extends \USDOJ\SingleTableFacets\App {

    private $parameters;
    private $facets;
    private $display;

    public function __construct($configFile) {

        $config = new \USDOJ\SingleTableFacets\Config($configFile);
        parent::__construct($config);

        $this->parameters = $this->parseQueryString();

        $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
        $this->baseUrl = $uri_parts[0];

        // For now, there is only one type of display, but in the future we may
        // want to make this configurable.
        $this->display = new \USDOJ\SingleTableFacets\ResultDisplayTable($this);
    }

    public function getDisplay() {
        return $this->display;
    }

    public function getBaseUrl() {
        return $this->baseUrl;
    }

    public function getExtraParameters() {
        return array('keys', 'sort', 'sort_direction', 'page', 'full_text');
    }

    private function getFacetColumns() {
        $facets = $this->settings('facet labels');
        return array_keys($facets);
    }

    private function getAllowedParameters() {
        $extraParameters = $this->getExtraParameters();
        $facetColumnNames = $this->getFacetColumns();
        return array_merge($facetColumnNames, $extraParameters);
    }

    public function getParameter($param) {
        if (!empty($this->parameters[$param])) {
            return $this->parameters[$param];
        }
        return FALSE;
    }

    public function getParameters() {
        return $this->parameters;
    }

    private function parseQueryString() {
        $params = $_GET;
        $currentQuery = array();
        $allowedParams = $this->getAllowedParameters();
        foreach ($allowedParams as $allowedParam) {
            if (!empty($params[$allowedParam])) {
                if (is_array($params[$allowedParam])) {
                    foreach ($params[$allowedParam] as $param) {
                        $currentQuery[$allowedParam][] = $param;
                    }
                }
                elseif (is_string($params[$allowedParam])) {
                    $currentQuery[$allowedParam] = $params[$allowedParam];
                }
            }
        }
        return $currentQuery;
    }

    public function renderKeywordSearch() {
        $searchBar = new \USDOJ\SingleTableFacets\SearchBar($this);
        return $searchBar->render();
    }

    public function renderFacets() {

        $output = '';
        foreach ($this->getFacetColumns() as $name) {
            $facet = new \USDOJ\SingleTableFacets\Facet($this, $name);
            $output .= $facet->render();
        }
        return $output;
    }

    public function renderResults() {
        return $this->getDisplay()->render();
    }

    public function renderPager() {
        return $this->getDisplay()->renderPager();
    }

    /**
    * Helper function to split a string into an array of space-delimited tokens
    * taking double-quoted and single-quoted strings into account.
    */
    public function tokenizeQuoted($string, $quotationMarks='"\'') {
        $tokens = array();
        for ($nextToken = strtok($string, ' '); $nextToken !== FALSE; $nextToken = strtok(' ')) {
            if (strpos($quotationMarks, $nextToken[0]) !== FALSE) {
                if (strpos($quotationMarks, $nextToken[strlen($nextToken)-1]) !== FALSE) {
                    $tokens[] = substr($nextToken, 1, -1);
                }
                else {
                    $tokens[] = substr($nextToken, 1) . ' ' . strtok($nextToken[0]);
                }
            }
            else {
                $tokens[] = $nextToken;
            }
        }
        return $tokens;
    }

    public function renderJavascript() {
        return '<script type="text/javascript" src="assets/singletablefacets.js"></script>';
    }

    public function renderStyles() {
        return '<link rel="stylesheet" href="assets/singletablefacets.css" />';
    }

    public function query() {

        $query = parent::query();
        $query->from($this->settings('database table'));

        // Keep track of the parameters. We'll compile them below and then we
        // be adding them onto the query at the end of the function.
        $anonymous_parameters = array();

        /*
        * Keywords are a special case. Here are the requirements for keyword
        * search behavior:
        * 1. Defaults to an "AND" query.
        *    Eg, a search for 'foo bar' would result in:
        *    SELECT * FROM tbl WHERE col LIKE '%foo%' AND col LIKE '%bar%'
        *
        * 2. User can specify "OR" instead.
        *
        *    Eg, a search for 'foo OR bar' would result in:
        *    SELECT * FROM tbl WHERE col LIKE '%foo%' OR col LIKE '%bar%'
        *
        * 3. User can enter "-" to exclude a keyword.
        *
        *    Eg, a search for 'foo -bar' would result in:
        *    SELECT * FROM tbl WHERE col LIKE '%foo%' AND col NOT LIKE '%bar%'
        *
        * 4. User can put double-quotes around phrases to treat it as a single word
        *
        *    Eg, a search for '"foo bar"' would result in:
        *    SELECT * FROM tbl WHERE col LIKE '%foo bar%'
        */
        $keywords = $this->getParameter('keys');
        if (!empty($keywords)) {

            $excludeFullText = $this->settings('allow user to exclude full text from keyword search');
            $fullTextParam = $this->getParameter('full_text');
            $excludeFullText &= empty($fullTextParam);

            if ($excludeFullText) {
                $fullTextIndex = 'stf_doc_keywords';
            }
            else {
                $fullTextIndex = 'stf_doc_keywords';
            }

            $query->andWhere("MATCH($fullTextIndex) AGAINST(? IN BOOLEAN MODE)");
            $anonymous_parameters[] = "%$keywords%";
        }

        // Add conditions for the facets. At this point, we consult the full query
        // string, minus any of our "extra" params.
        $parsedQueryString = $this->getParameters();
        foreach ($this->getExtraParameters() as $extraParameter) {
            unset($parsedQueryString[$extraParameter]);
        }
        if (!empty($parsedQueryString)) {
            $additionalColumns = $this->settings('columns for additional values');
            foreach ($parsedQueryString as $facetName => $facetItemValues) {
                // Create our sequences of question marks for the anon params.
                $in = str_repeat('?,', count($facetItemValues) - 1) . '?';
                $columnsToCheck = array($facetName);
                // Check to see if we need to include additional columns.
                if (!empty($additionalColumns)) {
                    foreach ($additionalColumns as $additionalColumn => $mainColumn) {
                        if ($facetName == $mainColumn) {
                            $columnsToCheck[] = $additionalColumn;
                        }
                    }
                }
                // Build the "where" for the facet.
                $facetWhere = $query->expr()->orX();
                foreach ($columnsToCheck as $columnToCheck) {
                    foreach ($facetItemValues as $facetItem) {
                        $anonymous_parameters[] = $facetItem;
                    }
                    $facetWhere->add("$columnToCheck IN ($in)");
                }
                $query->andWhere($facetWhere);
            }
        }
        // Add conditions for any required columns.
        foreach ($this->settings('required columns') as $column) {
            $query->andWhere("($column <> '' AND $column IS NOT NULL)");
        }

        if (!empty($anonymous_parameters)) {
            $query->setParameters($anonymous_parameters);
        }

        return $query;
    }

    public function getLink($url, $label, $query, $class) {

        $href = $this->getHref($url, $query);
        return sprintf('<a href="%s" class="%s">%s</a>', $href, $class, $label);
    }

    public function getHref($url, $query) {
        $href = $url;
        $query_string = http_build_query($query);
        if (!empty($query_string)) {
            $href .= '?' . $query_string;
        }
        return $href;
    }
}
