<?php
/**
 * @file
 * Class for creating facets using a single database table.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class AppWeb
 * @package USDOJ\SingleTableFacets
 *
 * A class for the HTML (web) version of this app.
 */
class AppWeb extends \USDOJ\SingleTableFacets\App {

    /**
     * The parameters present in $_GET that are relevant to us.
     *
     * @var array
     */
    private $parameters;

    /**
     * The child of ResultDisplay to use for this app.
     *
     * @var \USDOJ\SingleTableFacets\ResultDisplayList|\USDOJ\SingleTableFacets\ResultDisplayTable
     */
    private $display;

    /**
     * The user-input search terms.
     *
     * @var string
     */
    private $userKeywords;

    /**
     * Twig templates for search results.
     *
     * @var \Twig_Environment
     */
    private $twigForSearchResults;

    /**
     * Twig templates for facet items.
     *
     * @var \Twig_Environment
     */
    private $twigForFacetItems;

    /**
     * Array of all the columns in the database, for use later.
     *
     * @var array
     */
    private $allColumns;

    /**
     * Array of base data to pass into every Twig template.
     *
     * @var array
     */
    private $baseTemplateData;

    /**
     * AppWeb constructor.
     *
     * @param \USDOJ\SingleTableFacets\Config $configFile
     *   The config object to use with this app.
     *
     * @throws \Exception
     */
    public function __construct($configFile) {

        $config = new \USDOJ\SingleTableFacets\Config($configFile);
        parent::__construct($config);

        $this->parameters = $this->parseQueryString();

        // We may need to check for "prepopulated facet values".
        $default_values = $this->settings('prepopulated facet values');
        if (!empty($default_values)) {
            $active_facet_parameters = array();
            if (!empty($this->parameters)) {
                $possible_facets = array_keys($this->settings('facet labels'));
                foreach ($this->parameters as $key => $value) {
                    if (in_array($key, $possible_facets)) {
                        $active_facet_parameters[] = $key;
                    }
                }
            }
            if (empty($active_facet_parameters)) {
                foreach ($default_values as $column => $value) {
                    $this->parameters[$column] = array($value);
                }
            }
        }

        $uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
        $this->baseUrl = $uri_parts[0];

        $displayType = $this->settings('search result display');
        if ('list' == $displayType) {
            $display = new \USDOJ\SingleTableFacets\ResultDisplayList($this);
        }
        else {
            $display = new \USDOJ\SingleTableFacets\ResultDisplayTable($this);
        }
        $this->display = $display;

        // Load the Twig templates for later use.
        $templateFolder = $this->settings('template folder for search results');
        $functionFile = $this->settings('file with twig functions');
        $functionList = $this->settings('list of twig functions');
        $twigFunctions = array();
        if (file_exists($functionFile)) {
            include($functionFile);
            foreach ($functionList as $func) {
                $twigFunctions[] = new \Twig_SimpleFunction($func, $func);
            }
        }
        $functionList = $this->settings('list of twig functions');
        if (!empty($templateFolder) && file_exists($templateFolder)) {

            $loader = new \Twig_Loader_Filesystem($templateFolder);
            $this->twigForSearchResults = new \Twig_Environment($loader);
            foreach ($twigFunctions as $twigFunction) {
                $this->twigForSearchResults->addFunction($twigFunction);
            }
        }
        $templateFolder = $this->settings('template folder for facet items');
        if (!empty($templateFolder) && file_exists($templateFolder)) {

            $loader = new \Twig_Loader_Filesystem($templateFolder);
            $this->twigForFacetItems = new \Twig_Environment($loader);
            foreach ($twigFunctions as $twigFunction) {
                $this->twigForFacetItems->addFunction($twigFunction);
            }
        }

        // Save the total array of database columns for later use.
        $query = $this->getDb()->createQueryBuilder();
        $query
            ->from('information_schema.columns')
            ->select('column_name')
            ->where('table_schema = :database')
            ->andWhere('table_name = :table');
        $params = array(
            'database' => $this->settings('database name'),
            'table' => $this->settings('database table'),
        );
        $query->setParameters($params);
        $results = $query->execute()->fetchAll();
        if (!empty($results)) {
            foreach ($results as $result) {
                $this->allColumns[] = $result['column_name'];
            }
        }

        $this->baseTemplateData = array();
        $this->baseTemplateData['currentUrl'] = $this->getCurrentUrl();
        $this->baseTemplateData['parameterPrefix'] = $this->getParameterPrefix();
    }

    /**
     * Get the display being used for this app.
     *
     * @return \USDOJ\SingleTableFacets\ResultDisplayList|\USDOJ\SingleTableFacets\ResultDisplayTable
     */
    public function getDisplay() {
        return $this->display;
    }

    /**
     * Get the base URL of the current page, for use in building links.
     *
     * @return string
     */
    public function getBaseUrl() {
        return $this->baseUrl;
    }

    /**
     * An array of paramters to allow the user to set, other than db columns.
     *
     * @return array
     */
    public function getExtraParameters() {
        return array('keys', 'sort', 'sort_direction', 'page', 'full_text');
    }

    /**
     * Get a flat array of database columns to use for facets.
     *
     * @return array
     */
    private function getFacetColumns() {
        $facets = $this->settings('facet labels');
        return array_keys($facets);
    }

    /**
     * Get a flat array of all the possible paramters users are allowed to use.
     *
     * @return array
     */
    private function getAllowedParameters() {
        $extraParameters = $this->getExtraParameters();
        $facetColumnNames = $this->getFacetColumns();
        return array_merge($facetColumnNames, $extraParameters);
    }

    /**
     * Get a specific paramter if the user has requested it.
     *
     * @param $param
     *   The $_GET parameter to look for.
     *
     * @return bool
     */
    public function getParameter($param) {
        if (!empty($this->parameters[$param])) {
            return $this->parameters[$param];
        }
        return FALSE;
    }

    /**
     * Get all the user-requested parameters.
     *
     * @return array
     */
    public function getParameters() {
        return $this->parameters;
    }

    /**
     * Get the Twig templates for search results.
     *
     * @return \Twig_Environment
     */
    public function getTwigForSearchResults() {
        return $this->twigForSearchResults;
    }

    /**
     * Get the Twig templates for facet items.
     *
     * @return \Twig_Environment
     */
    public function getTwigForFacetItems() {
        return $this->twigForFacetItems;
    }

    /**
     * Get the base template data that will be passed into each Twig template.
     *
     * @return array
     *   An associative array, the keys of which can be used in Twig templates.
     */
    public function getBaseTemplateData() {
        return $this->baseTemplateData;
    }

    /**
     * Helper function to get the SQL for a full-text MATCH AGAINST query.
     *
     * @return string
     */
    public function getMatchSQL() {
        $keywordColumns = $this->getKeywordColumns();
        $matchSQL = "MATCH($keywordColumns) AGAINST(:keywords IN BOOLEAN MODE)";
        return $matchSQL;
    }

    /**
     * Parse $_GET to grab the variables we care about.
     *
     * @return array
     */
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

    /**
     * Render the keyword search widget.
     *
     * @return string
     */
    public function renderKeywordSearch() {
        $searchBar = new \USDOJ\SingleTableFacets\SearchBar($this);
        return $searchBar->render();
    }

    /**
     * Render the facets.
     *
     * @return string
     */
    public function renderFacets() {

        $output = '';
        foreach ($this->getFacetColumns() as $name) {
            $facet = new \USDOJ\SingleTableFacets\Facet($this, $name);
            $output .= $facet->render();
        }
        return $output;
    }

    /**
     * Render the search results.
     *
     * @return string
     */
    public function renderResults() {
        if ($this->settings('require input for search results')) {
            $params = $this->getParameters();
            if (empty($params)) {
                return '';
            }
        }
        return $this->getDisplay()->render();
    }

    /**
     * Render the pager.
     *
     * @return string
     */
    public function renderPager() {
        return $this->getDisplay()->renderPager();
    }

    /**
     * Helper function to split a string into an array of space-delimited tokens
     * taking double-quoted and single-quoted strings into account.
     *
     * @param string $string
     *   The string to parse into tokens.
     *
     * @param $quotationMarks string
     *   The characters to treat as quotes.
     *
     * @return array
     */
    public function tokenizeQuoted($string, $quotationMarks='"\'') {
        $tokens = array();
        for ($nextToken = strtok($string, ' '); $nextToken !== FALSE; $nextToken = strtok(' ')) {
            if (strpos($quotationMarks, $nextToken[0]) !== FALSE) {
                if (strpos($quotationMarks, $nextToken[strlen($nextToken)-1]) !== FALSE) {
                    $tokens[] = substr($nextToken, 1, -1);
                }
                else {
                    $tokens[] = '"' . substr($nextToken, 1) . ' ' . strtok($nextToken[0]) . '"';
                }
            }
            else {
                $tokens[] = $nextToken;
            }
        }
        return $tokens;
    }

    /**
     * Render the javascript.
     *
     * @return string
     */
    public function renderJavascript() {
        $location = $this->settings('location of assets');
        return '<script type="text/javascript" src="' . $location . '/singletablefacets.js"></script>';
    }

    /**
     * Render the CSS styles.
     *
     * @return string
     */
    public function renderStyles() {
        $location = $this->settings('location of assets');
        return '<link rel="stylesheet" href="' . $location . '/singletablefacets.css" />';
    }

    /**
     * Get (or parse and return) the user-requested keywords.
     *
     * @return string
     */
    public function getUserKeywords() {

        if (!empty($this->userKeywords)) {
            return $this->userKeywords;
        }

        $keywords = $this->getParameter('keys');
        $tokenized = $this->tokenizeQuoted($keywords);
        if ($this->settings('use AND for keyword logic by default')) {
            $ors = array();
            foreach ($tokenized as $index => $value) {
                if ('OR' == $value || 'or' == $value) {
                    $ors[] = $index;
                }
            }
            $addPlus = TRUE;
            foreach ($tokenized as $index => &$value) {
                if (in_array($index, $ors)) {
                    $value = '';
                    $addPlus = FALSE;
                    continue;
                }
                if ($addPlus) {
                    $otherOperators = '-~<>+';
                    if (strpos($otherOperators, substr($value, 0, 1)) === FALSE) {
                        $value = '+' . $value;
                    }
                }
                $addPlus = TRUE;
            }
            $tokenized = array_filter($tokenized);
            $keywords = implode(' ', $tokenized);
        }
        if ($this->settings('automatically put wildcards on keywords entered')) {
            foreach ($tokenized as &$value) {
                $otherOperators = '"\'*)';
                if (strpos($otherOperators, substr($value, -1)) === FALSE) {
                    $value = $value . '*';
                }
            }
            $keywords = implode(' ' , $tokenized);
        }

        $this->userKeywords = $keywords;
        return $keywords;
    }

    /**
     * Continuation of starting query, taking into account user input.
     *
     * @param string|null $ignoreColumn
     *   Specify a column to be ignored in the query.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function query($ignoreColumn = NULL) {

        $query = parent::query();
        $query->from($this->settings('database table'));

        // Keywords are handled by MySQL, mostly.
        $keywords = $this->getUserKeywords();
        if (!empty($keywords)) {

            $matchSQL = $this->getMatchSQL();
            $query->andWhere($matchSQL);
            $query->setParameter('keywords', $keywords);
        }

        // Add conditions for the facets. At this point, we consult the full query
        // string, minus any of our "extra" params.
        $parsedQueryString = $this->getParameters();
        if (!empty($ignoreColumn)) {
            unset($parsedQueryString[$ignoreColumn]);
        }
        foreach ($this->getExtraParameters() as $extraParameter) {
            unset($parsedQueryString[$extraParameter]);
        }
        if (!empty($parsedQueryString)) {
            $dateColumns = $this->getDateColumns();
            $additionalColumns = $this->settings('columns for additional values');
            foreach ($parsedQueryString as $facetName => $facetItemValues) {

                // Check to see if we need to include additional columns.
                $columnsToCheck = array($facetName);
                if (!empty($additionalColumns)) {
                    foreach ($additionalColumns as $additionalColumn => $mainColumn) {
                        if ($facetName == $mainColumn) {
                            $columnsToCheck[] = $additionalColumn;
                        }
                    }
                }

                // Create an AND statement to construct our WHERE for the facet.
                $facetWhere = $query->expr()->andX();

                // Date facets are unique in that they will have only a single
                // value that we interpret into a hierarchical display. This is
                // no way, for example, for a date facet to be both "2011" and
                // "2012", or both "2012-01" and "2012-02". Once you select a
                // year, month, or day, all the other years/months/days will
                // disappear. Since they are unusal, handle them first.
                if (in_array($facetName, $dateColumns)) {
                    // Date facets are essentially ranges, so we need to query
                    // a range of dates. Because we are assuming that date
                    // facet items will only have one at a time, we treat them
                    // as strings instead of arrays.
                    $facetItemValue = $facetItemValues;
                    $start = $this->normalizeDate($facetItemValue);
                    $end = $this->normalizeDate($facetItemValue, TRUE);
                    if ($start == $facetItemValue || $end == $facetItemValue) {
                        // If the facet value is the same as the normalized
                        // string, something is wrong, so skip it.
                        continue;
                    }

                    $startPlaceholder = $query->createNamedParameter($start);
                    $endPlaceholder = $query->createNamedParameter($end);
                    $dateOr = $query->expr()->orX();
                    foreach ($columnsToCheck as $columnToCheck) {
                        $dateOr->add("$columnToCheck BETWEEN $startPlaceholder AND $endPlaceholder");
                    }
                    $facetWhere->add($dateOr);
                }
                // Otherwise, non-date facets act completely differently. Most
                // notably, they are treated as arrays. Also, they can be
                // queried more simply by looking in all of the columns they
                // might be in.
                else {
                    foreach ($facetItemValues as $facetItemValue) {
                        $placeholder = $query->createNamedParameter($facetItemValue);
                        $columnsToCheckString = implode(',', $columnsToCheck);
                        $facetWhere->add("$placeholder IN ($columnsToCheckString)");
                    }
                }

                // Add the facet selects to the query.
                $query->andWhere($facetWhere);
            }
        }
        // Add conditions for any required columns.
        foreach ($this->settings('required columns') as $column) {
            $query->andWhere("($column <> '' AND $column IS NOT NULL)");
        }
        return $query;
    }

    /**
     * Helper method for creating links.
     *
     * @param $url
     *   The href to use for the link.
     * @param $label
     *   The text to use for the link.
     * @param $query
     *   Any query parameters to add to the link.
     * @param $class
     *   Any CSS class to apply to the link.
     *
     * @return string
     */
    public function getLink($url, $label, $query, $class) {

        $href = $this->getHref($url, $query);
        return sprintf('<a href="%s" class="%s">%s</a>', $href, $class, $label);
    }

    /**
     * Helper method to build a URL for a link.
     *
     * @param $url
     *   The base URL for the href.
     * @param $query
     *   Any query parameters to add to the href.
     *
     * @return string
     */
    public function getHref($url, $query) {
        $href = $url;
        $query_string = http_build_query($query);
        if (!empty($query_string)) {
            $href .= '?' . $query_string;
        }
        return $href;
    }

    /**
     * Normalize a date that could be year, year + month, or year + month + day.
     *
     * @param $date
     *   The unknown date.
     * @param bool|FALSE $endOfRange
     *   Are we looking for the beginning or end of a date range?
     *
     * @return string
     */
    public function normalizeDate($date, $endOfRange = FALSE) {
        $numChars = strlen($date);
        if (4 == $numChars) {
            // Year range.
            $start = $date . '-01-01 00:00:00';
            $end = $date . '-12-31 23:59:59';
        }
        elseif (7 == $numChars) {
            // Month range.
            $start = $date . '-01 00:00:00';
            $end = $date . '-31 23:59:59';
        }
        elseif (10 == $numChars) {
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';
        }
        if ($endOfRange) {
            return $end;
        }
        else {
            return $start;
        }

        return $date;
    }

    public function getAllColumns() {
        return $this->allColumns;
    }

    public function getCurrentUrl() {
        $parameters = $this->getParameters();
        $base = $this->getBaseUrl();
        return $this->getHref($base, $parameters);
    }

    public function getParameterPrefix() {
        $parameters = $this->getParameters();
        return (empty($parameters)) ? '?' : '&';
    }
}
