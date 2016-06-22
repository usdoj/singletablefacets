<?php
/**
 * @file
 * Base class for displaying results with SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

use USDOJ\SingleTableFacets\DatabaseQuery,
    JasonGrimes\Paginator;

abstract class ResultDisplayBase {

  private $app;

  abstract public function renderResults();

  public function getApp() {
    return $this->app;
  }

  public function __construct($app) {
    $this->app = $app;
  }

  protected function getRowCount() {

    $query = DatabaseQuery::start($this->getApp());
    $query->select("COUNT(*) as count");
    $result = $query->execute();
    foreach ($result as $row) {
      return $row['count'];
    }
    return 0;
  }

  protected function getPage() {
    return $this->getApp()->getParameter('page');
  }

  protected function getSortField() {
    $field = $this->getApp()->getParameter('sort');
    $allowedSorts = array_keys($this->getApp()->getSortColumns());
    if (in_array($field, $allowedSorts)) {
      return $field;
    }
    // Otherwise default to the first.
    if (!empty($allowedSorts)) {
      return $allowedSorts[0];
    }
    return FALSE;
  }

  protected function getSortDirection($sortField = NULL) {

    // If $sortField was specified, that means that we want the sort direction
    // of that specific field. Ie, if that is not the current sort, we should
    // return the default sort for that field.
    $sortColumns = $this->getApp()->getSortColumns();
    if (!empty($sortField) && $sortField != $this->getSortField()) {
      return $sortColumns[$sortField];
    }
    // Otherwise, return whatever is in the URL, if anything.
    $allowed = array('ASC', 'DESC');
    $direction = $this->getApp()->getParameter('sort_direction');
    if (in_array($direction, $allowed)) {
      return $direction;
    }
    // Otherwise return the default for the current sort.
    $currentSort = $this->getSortField();
    if (!empty($currentSort)) {
      return $sortColumns[$currentSort];
    }
    return FALSE;
  }

  public function getRows() {

    $query = DatabaseQuery::start($this->getApp());
    $query->select('*');
    $limit = $this->getApp()->getOption('pager_limit');
    if ($limit !== 0) {
      $page = $this->getPage();
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

    $itemsPerPage = $this->getApp()->getOption('pager_limit');
    if (empty($itemsPerPage)) {
      return '';
    }

    $totalItems = $this->getRowCount();
    $currentPage = $this->getPage();

    $parameters = $this->getApp()->getParameters();
    // Set overwrite the page parameter with a token for Paginator to replace.
    $parameters['page'] = '(:num)';
    $urlPattern = Link::getHref($this->getApp()->getBaseUrl(), $parameters);

    return new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
  }
}
