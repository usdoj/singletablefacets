<?php
/**
 * @file
 * Class to start a database query for SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class DatabaseQuery {

  public static function start($app) {
    $query = $app->getDb()->createQueryBuilder();
    $query->from($app->getTable());

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
     *
     * 5. All of the above can be applied to multiple columns...
     *
     *    Here is how requirement #5 might affect requirement #1:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo%')
     *    AND     (col1 LIKE '%bar%' OR col2 LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #2:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo%')
     *    OR     (col1 LIKE '%bar%' OR col2 LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #3:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo%' OR col2 LIKE '%foo'%')
     *    AND     (col1 NOT LIKE '%bar%' AND col2 NOT LIKE '%bar%') )
     *
     *    Here is how requirement #5 might affect requirement #4:
     *    SELECT * FROM tbl
     *    WHERE ( (col1 LIKE '%foo bar%' OR col2 LIKE '%foo bar%') )
     */
    $keywords = $app->getParameter('keys');
    if (!empty($keywords) && !empty($app->getKeywordColumns())) {

      // Keywords default to "AND" logic.
      $boolean = 'AND';

      // First parse out the keywords we need to search for.
      $keywords = $app->tokenizeQuoted($keywords);
      $parsedKeywords = array();
      foreach ($keywords as $keyword) {
        // Ignore the keywords "OR", "AND", and anything shorter than minimum.
        if (!empty($keyword)) {
          $keyword = trim($keyword);
          if ('AND' == $keyword) {
            $boolean = 'AND';
            continue;
          }
          elseif ('OR' == $keyword) {
            $boolean = 'OR';
            continue;
          }
          elseif (strlen($keyword) < $app->getOption('minimum_keyword_length')) {
            continue;
          }
          $parsedKeywords[] = $keyword;
        }
      }

      // Next, loop through the keywords (outer loop) and the columns (inner).
      if ('AND' == $boolean) {
        $keywordWhere = $query->expr()->andX();
      }
      else {
        $keywordWhere = $query->expr()->orX();
      }

      if (!empty($parsedKeywords)) {
        foreach ($parsedKeywords as $keyword) {

          if ('-' == substr($keyword, 0, 1)) {
            $operator = 'NOT LIKE';
            $keyword = substr($keyword, 1);
            $keywordColumnWhere = $query->expr()->andX();
          }
          else {
            $operator = 'LIKE';
            $keywordColumnWhere = $query->expr()->orX();
          }
          foreach ($app->getKeywordColumns() as $keywordColumn) {
            $keywordColumnWhere->add("$keywordColumn $operator ?");
            $anonymous_parameters[] = "%$keyword%";
          }
          $keywordWhere->add($keywordColumnWhere);
        }
        // Finally, add the big WHERE to the query.
        $query->andWhere($keywordWhere);
      }
    }

    // Add conditions for the facets. At this point, we consult the full query
    // string, minus any of our "extra" params.
    $parsedQueryString = $app->getParameters();
    foreach ($app->getExtraParameters() as $extraParameter) {
      unset($parsedQueryString[$extraParameter]);
    }
    if (!empty($parsedQueryString)) {
      foreach ($parsedQueryString as $facetName => $facetItemValues) {
        $in = str_repeat('?,', count($facetItemValues) - 1) . '?';
        foreach ($facetItemValues as $facetItem) {
          $anonymous_parameters[] = $facetItem;
        }
        $query->andWhere("$facetName IN ($in)");
      }
    }
    // Add conditions for any required columns.
    $requiredColumns = $app->getOption('required_columns');
    if (!empty($requiredColumns)) {
      foreach ($requiredColumns as $requiredColumn) {
        $query->andWhere("($requiredColumn <> '' AND $requiredColumn IS NOT NULL)");
      }
    }

    if (!empty($anonymous_parameters)) {
      $query->setParameters($anonymous_parameters);
    }
    return $query;
  }
}
