<?php
/**
 * @file
 * Class for preparing for usage of SingleTableFacets.
 */

namespace USDOJ\SingleTableFacets;

class App
{
    private $db;
    private $config;
    private $uniqueColumn;
    private $databaseKeywordColumns;

    public function getDb() {
        return $this->db;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getDocumentKeywordColumn() {
        return 'stf_keywords';
    }

    public function getKeywordColumns() {
        $excludeFullText = $this->settings('allow user to exclude full text from keyword search');
        $fullTextParam = $this->getParameter('full_text');

        $keywordColumns = $this->databaseKeywordColumns;
        if (!$excludeFullText || !empty($fullTextParam)) {
            $keywordColumns[] = $this->getDocumentKeywordColumn();
        }
        $keywordColumns = implode(',', $keywordColumns);
        return $keywordColumns;
    }

    public function getRelevanceColumn() {
        return 'stf_score';
    }

    public function getUniqueColumn() {
        return $this->uniqueColumn;
    }

    public function settings($key) {
        return $this->getConfig()->get($key);
    }

    public function query() {
        return $this->getDb()->createQueryBuilder();
    }

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
        // Also, if there are more than one FULLTEXT index, something may not
        // be right, so throw an error about that too.
        elseif (count($results) > 1) {
            print_r($results);
            $err = 'The db table has more than one FULLTEXT index. There should be only a single FULLTEXT text that contains all searchable columns.';
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
}
