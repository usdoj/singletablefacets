<?php
/**
 * @file
 * Base class for displaying results with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class ResultDisplay
 * @package USDOJ\SingleTableFacets
 *
 * An abstract class for ways to display the search results.
 */
abstract class ResultDisplay {

    /**
     * @var \USDOJ\SingleTableFacets\AppWeb
     *   Reference to the main app.
     */
    private $app;

    /**
     * Get the main app object.
     *
     * @return \USDOJ\SingleTableFacets\AppWeb
     */
    public function getApp() {
        return $this->app;
    }

    /**
     * ResultDisplay constructor.
     *
     * @param $app
     *   Reference to the main app.
     */
    public function __construct($app) {
        $this->app = $app;
    }

    /**
     * Get the total number of results given the current query.
     *
     * @return int
     */
    protected function getRowCount() {

        $query = $this->getApp()->query();
        $query->addSelect("COUNT(*) as count");
        try {
            $result = $query->execute();
        }
        catch (\Exception $e) {
            die('Database error. Please alert the site administrators.');
        }
        foreach ($result as $row) {
            return $row['count'];
        }
        return 0;
    }

    /**
     * Get the current page (from the pager) that the user is on.
     *
     * @return int
     */
    protected function getPage() {
        $page = $this->getApp()->getParameter('page');
        if (empty($page)) {
            $page = 1;
        }
        return $page;
    }

    /**
     * Figure out which database column should be used for sorting.
     *
     * @return string
     */
    protected function getSortField() {
        $field = $this->getApp()->getParameter('sort');
        $sortDirections = $this->getApp()->settings('sort directions');

        // Special case, remove stf_score if there are no keywords searched.
        $keywords = $this->getApp()->getUserKeywords();
        if (empty($keywords)) {
            unset($sortDirections[$this->getApp()->getRelevanceColumn()]);
        }

        $allowedSorts = array_keys($sortDirections);
        if (in_array($field, $allowedSorts)) {
            return $field;
        }
        // Otherwise default to first one.
        if (!empty($allowedSorts[0])) {
            return $allowedSorts[0];
        }
        // Last resort, use the unique column.
        return $this->getApp()->getUniqueColumn();
    }

    /**
     * Figure out which direction (ASC or DESC) the sorting should go.
     *
     * @param null $sortField
     *   Optional database column to consider, rather than the current sort.
     *
     * @return bool|string
     */
    protected function getSortDirection($sortField = NULL) {

        $sortDirections = $this->getApp()->settings('sort directions');

        // If $sortField was specified, that means that we want the sort direction
        // of that specific field. Ie, if that is not the current sort, we should
        // return the default sort for that field.
        if (!empty($sortField) && $sortField != $this->getSortField()) {
            if (!empty($sortDirections[$sortField])) {
                return $sortDirections[$sortField];
            }
            return 'ASC';
        }
        // Otherwise, return whatever is in the URL, if anything.
        $allowed = array('ASC', 'DESC');
        $direction = $this->getApp()->getParameter('sort_direction');
        if (in_array($direction, $allowed)) {
            return $direction;
        }
        // Otherwise return the default for the current sort.
        $currentSort = $this->getSortField();
        if (!empty($currentSort) && !empty($sortDirections[$currentSort])) {
            return $sortDirections[$currentSort];
        }
        return FALSE;
    }

    /**
     * Query the database to fetch the rows of search results.
     *
     * @return array
     *   Array of associative arrays, one for each row of results.
     */
    public function getRows() {

        $query = $this->getApp()->query();
        $relevanceColumn = $this->getApp()->getRelevanceColumn();

        // Query all the columns. Because of SQL quirks, we can't use '*'.
        $allColumns = $this->getApp()->getAllColumns();
        foreach ($allColumns as $column) {
            // No need to query the keywords column, though.
            if ($this->getApp()->getDocumentKeywordColumn() != $column) {
                $query->addSelect('`' . $column . '`');
            }
        }

        // Now make sure that the query gets relevance if needed.
        $keywords = $this->getApp()->getUserKeywords();
        if (!empty($keywords)) {
            $matchSQL = $this->getApp()->getMatchSQL();
            $query->setParameter('keywords', $keywords);
            $query->addSelect($matchSQL . ' AS ' . $relevanceColumn);
        }

        $limit = $this->getApp()->settings('number of items per page');
        if ($limit !== 0) {
            $page = intval($this->getPage()) - 1;
            $query->setMaxResults($limit);
            $query->setFirstResult($limit * $page);
        }
        $sortField = $this->getSortField();
        if (!empty($sortField)) {
            $sortDirection = $this->getSortDirection();
            // If this column has been specified for "natural sorting", then we
            // must sort first by length.
            $naturalColumns = $this->getApp()->settings('columns with natural sorting');
            if (in_array($sortField, $naturalColumns)) {
                $query->orderBy("LENGTH(TRIM($sortField))", $sortDirection);
            }
            $query->addOrderBy($sortField, $sortDirection);
        }
        try {
            $results = $query->execute()->fetchAll();
        }
        catch (\Exception $e) {
            die('Database error. Please alert the site administrators.');
        }

        // Do we need to consolidate any "additional" values?
        $additionalColumns = $this->getApp()->settings('columns for additional values');
        if (!empty($additionalColumns)) {
            foreach ($additionalColumns as $additional => $main) {
                foreach ($results as &$row) {
                    if (!empty($row[$additional])) {
                        $row[$main] .= ', ' . $row[$additional];
                    }
                }
            }
        }

        // We need to make sense of the "Relevance" column if it is there.
        if (!empty($results[0][$relevanceColumn])) {
            $maxRelevance = 0;
            foreach ($results as $result) {
                if ($result[$relevanceColumn] > $maxRelevance) {
                    $maxRelevance = $result[$relevanceColumn];
                }
            }
            foreach ($results as &$result) {
                $relevance = $result[$relevanceColumn] / $maxRelevance;
                $relevance = floor($relevance * 100);
                $result[$relevanceColumn] = $relevance . '%';
            }
        }

        return $results;
    }

    /**
     * Render the HTML for the pager.
     *
     * @return string
     */
    public function renderPager() {

        $itemsPerPage = $this->getApp()->settings('number of items per page');
        if (empty($itemsPerPage)) {
            return '';
        }

        $totalItems = $this->getRowCount();
        $currentPage = intval($this->getPage());

        $parameters = $this->getApp()->getParameters();

        // Set overwrite the page parameter with a token for Paginator to replace.
        if (empty($parameters['page'])) {
            $parameters['page'] = $currentPage;
        }
        $urlPattern = $this->getApp()->getHref($this->getApp()->getBaseUrl(), $parameters);

        // We need to get Paginator's token into the URLs.
        $search = 'page=' . $currentPage;
        $replace = 'page=(:num)';
        $urlPattern = str_replace($search, $replace, $urlPattern);

        $paginator = new \JasonGrimes\Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow($this->getApp()->settings('number of pager links to show'));

        return '<div class="stf-facet-pager">' . $paginator->toHtml() . '</div>';
    }

    /**
     * Decide which database columns need to display for each result.
     *
     * @return array
     */
    protected function getColumnsToDisplay() {
        $tableColumns = $this->getApp()->settings('search result labels');
        // Special case. If there are no keywords being searched, do not show
        // the relevance column.
        $keywords = $this->getApp()->getUserKeywords();
        if (empty($keywords)) {
            unset($tableColumns[$this->getApp()->getRelevanceColumn()]);
        }
        return $tableColumns;
    }

    /**
     * Given a row+column combination, get the cell content.
     *
     * @param $row
     *   The row data from the search results.
     * @param $column
     *   The name of the database column we are looking for.
     *
     * @return string
     */
    protected function getCellContent($row, $column) {

        $content = '';

        if (!empty($row[$column])) {
            $content = $row[$column];
        }

        // Does a Twig template exist?
        $twigTemplate = $column . '.html.twig';
        if ($this->getApp()->getTwigForSearchResults() &&
            $this->getApp()->getTwigForSearchResults()->getLoader()->exists($twigTemplate)) {
            // If so, render it.
            $templateData = $this->getApp()->getBaseTemplateData();
            $templateData['row'] = $row;
            $templateData['value'] = $content;
            $content = $this->getApp()->getTwigForSearchResults()->render($twigTemplate, $templateData);
        }

        return $content;
    }

    /**
     * Given a row of results, get the distinct values from the grouping column.
     *
     * @param $row
     *   The row data from the search results.
     *
     * @return array
     *   An array of distinct values.
     */
    protected function getDistinctGroupValues($rows) {
        $groupingColumn = $this->getApp()->settings('search result grouping column');
        $values = array();
        if (!empty($groupingColumn)) {
            foreach ($rows as $row) {
                $cellContent = $this->getCellContent($row, $groupingColumn);
                $values[$cellContent] = TRUE;
            }
        }
        $values = array_keys($values);
        sort($values);
        return $values;
    }
}
