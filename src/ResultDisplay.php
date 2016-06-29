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
        $query->select("COUNT(*) as count");
        $result = $query->execute();
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
        $allowedSorts = array();
        foreach ($this->getApp()->settings('database columns') as $column => $info) {
            if (!empty($info['use for sorting'])) {
                $allowedSorts[] = $column;
            }
        }
        if (in_array($field, $allowedSorts)) {
            return $field;
        }
        // Otherwise default to the global default sort.
        return $this->getApp()->settings('default sort column');
    }

    protected function getSortDirection($sortField = NULL) {

        $columns = $this->getApp()->settings('database columns');

        // If $sortField was specified, that means that we want the sort direction
        // of that specific field. Ie, if that is not the current sort, we should
        // return the default sort for that field.
        if (!empty($sortField) && $sortField != $this->getSortField()) {
            if (!empty($columns[$sortField]['sort direction'])) {
                return $columns[$sortField]['sort direction'];
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
        if (!empty($currentSort) && !empty($columns[$currentSort]['sort direction'])) {
            return $columns[$currentSort]['sort direction'];
        }
        return FALSE;
    }

    public function getRows() {

        $query = $this->getApp()->query();
        $query->select('*');
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

        return $query->execute();
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
