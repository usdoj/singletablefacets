<?php
/**
 * @file
 * Base class for displaying results with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

abstract class ResultDisplay {

    private $app;

    public function getApp() {
        return $this->app;
    }

    public function __construct($app) {
        $this->app = $app;
    }

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

    protected function getPage() {
        $page = $this->getApp()->getParameter('page');
        if (empty($page)) {
            $page = 1;
        }
        return $page;
    }

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

    public function getRows() {

        $query = $this->getApp()->query();
        $relevanceColumn = $this->getApp()->getRelevanceColumn();
        $searchResultLabels = $this->getApp()->settings('search result labels');
        foreach ($searchResultLabels as $column => $label) {
            // Special case for "stf_score", which gets a fancy MATCH
            // expression later.
            if ($column == $relevanceColumn) {
                continue;
            }
            // Otherwise do a normal SELECT.
            $query->addSelect($column);
        }

        // Also make sure any URL columns are queried.
        $urlColumns = $this->getApp()->settings('output as links');
        foreach ($urlColumns as $labal => $url) {
            if (empty($searchResultLabels[$url])) {
                $query->addSelect($url);
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
            $query->orderBy($sortField, $sortDirection);
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
                    $row[$main] .= ', ' . $row[$additional];
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

        return '<div class="doj-facet-pager">' . $paginator->toHtml() . '</div>';
    }
}
