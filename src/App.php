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

    public function getDb() {
        return $this->db;
    }

    public function getConfig() {
        return $this->config;
    }

    public function getDatabaseKeywordColumn() {
        return 'stf_data_keywords';
    }

    public function getDocumentKeywordColumn() {
        return 'stf_doc_keywords';
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
    }
}
