<?php
/**
 * @file
 * Class for preparing for usage of SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

/**
 * Class App
 * @package USDOJ\SingleTableFacets
 *
 * A base class for this app.
 */
class App
{
    /**
     * @var \Doctrine\DBAL\Connection
     *   The database connection.
     */
    private $db;

    /**
     * @var \USDOJ\SingleTableFacets\Config
     *   The config object for this app.
     */
    private $config;

    /**
     * @var string
     *   The unique column in the database.
     */
    private $uniqueColumn;

    /**
     * @var array
     *   All of the database columns that are part of the FULLTEXT indexes.
     */
    private $databaseKeywordColumns;

    /**
     * @var array
     *   All of the database columns that are DATETIME values.
     */
    private $dateColumns;

    /**
     * Get the database connection.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Get the config object.
     *
     * @return \USDOJ\SingleTableFacets\Config
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Get the unqiue column.
     *
     * @return string
     */
    public function getUniqueColumn() {
        return $this->uniqueColumn;
    }

    /**
     * Get the DATETIME columns.
     *
     * @return array
     */
    public function getDateColumns() {
        return $this->dateColumns;
    }

    /**
     * App constructor.
     *
     * @param \USDOJ\SingleTableFacets\Config $config
     *   The configuration object for the app.
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    public function __construct($config) {

        $this->config = $config;

        // Start the database connection.
        $dbConfig = new \Doctrine\DBAL\Configuration();
        $connectionParams = array(
            'dbname' => $this->settings('database name'),
            'user' => $this->settings('database user'),
            'password' => $this->settings('database password'),
            'host' => $this->settings('database host'),
            'port' => 3306,
            'charset' => 'utf8',
            'driver' => 'pdo_mysql',
        );
        $db = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $dbConfig);
        $this->db = $db;

        // One requirement is that the table has at least one unique column.
        $statement = $this->getDb()->query('DESCRIBE ' . $this->settings('database table'));
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($result as $column) {
            if (!empty($column['Key'])) {
                $this->uniqueColumn = $column['Field'];
                break;
            }
        }
        if (empty($this->uniqueColumn)) {
            throw new \Exception('The database table does not contain a unique index.');
        }

        // Take note of all the datetime columns so that their facets can be
        // rendered hierarchically.
        $this->dateColumns = array();
        $statement = $this->getDb()->query('DESCRIBE ' . $this->settings('database table'));
        $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($result as $column) {
            if ('datetime' == $column['Type']) {
                $this->dateColumns[] = $column['Field'];
            }
        }

        // Another requirement is that at least one column (stf_keywords) has a
        // FULLTEXT index. If something is not right, throw an Exception now.
        $keywordColumn = $this->getDocumentKeywordColumn();
        $query = $this->getDb()->createQueryBuilder();
        $query
            ->from('information_Schema.STATISTICS')
            ->select('DISTINCT index_name')
            ->where('table_schema = :database')
            ->andWhere('table_name = :table')
            ->andWhere('index_type = "FULLTEXT"')
            ->andWhere('column_name = :keyword_column');

        $params = array(
            'database' => $this->settings('database name'),
            'table' => $this->settings('database table'),
            'keyword_column' => $keywordColumn,
        );
        $query->setParameters($params);
        $results = $query->execute()->fetchAll();

        if (empty($results[0]['index_name'])) {
            $err = sprintf('The db table must have a %s column with a FULLTEXT index.', $keywordColumn);
            throw new \Exception($err);
        }

        // Also, save a list of other columns that are part of the same index,
        // since we'll need that info later.
        $indexName = $results[0]['index_name'];
        $query = $this->getDb()->createQueryBuilder();
        $query
            ->from('information_Schema.STATISTICS')
            ->select('DISTINCT column_name')
            ->where('table_schema = :database')
            ->andWhere('table_name = :table')
            ->andWhere('index_type = "FULLTEXT"')
            ->andWhere('index_name = :index_name')
            ->andWhere('column_name != :keyword_column');
        $params = array(
            'database' => $this->settings('database name'),
            'table' => $this->settings('database table'),
            'keyword_column' => $keywordColumn,
            'index_name' => $indexName,
        );
        $query->setParameters($params);
        $results = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
        $this->databaseKeywordColumns = $results;
    }

    /**
     * Get the keyword column for documents.
     *
     * @return string
     */
    public function getDocumentKeywordColumn() {
        return 'stf_keywords';
    }

    /**
     * Get all the columns in the FULLTEXT index as a comma-separated string.
     *
     * @return string
     */
    public function getKeywordColumns() {
        $excludeFullText = $this->settings('allow user to exclude full text from keyword search');
        $fullTextParam = $this->getParameter('full_text');

        $keywordColumns = $this->databaseKeywordColumns;
        if (!$excludeFullText || !empty($fullTextParam)) {
            $keywordColumns[] = $this->getDocumentKeywordColumn();
        }
        $keywordColumns = '`' . implode('`,`', $keywordColumns) . '`';
        return $keywordColumns;
    }

    /**
     * Get the pseudo-column name we're using for relevance.
     *
     * @return string
     */
    public function getRelevanceColumn() {
        return 'stf_score';
    }

    /**
     * Helper method for getting configuration settings.
     *
     * @param $key
     *   A key from the YAML configuration file to check the value for.
     * @return null|string
     */
    public function settings($key) {
        return $this->getConfig()->get($key);
    }

    /**
     * Start off a database query.
     *
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function query() {
        return $this->getDb()->createQueryBuilder();
    }
}
