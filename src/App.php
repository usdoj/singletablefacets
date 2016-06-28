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

    public function getKeywordColumn() {
        return 'stf_keywords';
    }

    public function getTable() {
        return $this->getConfig()->get('database table');
    }

    public function getUniqueColumn() {
        return $this->uniqueColumn;
    }

    public function __construct($config) {

        $this->config = $config;

        // Start the database connection.
        $dbConfig = new \Doctrine\DBAL\Configuration();
        $connectionParams = array(
            'dbname' => $config->get('database name'),
            'user' => $config->get('database user'),
            'password' => $config->get('database password'),
            'host' => $config->get('database host'),
            'port' => 3306,
            'charset' => 'utf8',
            'driver' => 'pdo_mysql',
        );
        $db = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $dbConfig);
        $this->db = $db;

        // One requirement is that the table has at least one unique column.
        $statement = $this->getDb()->query('DESCRIBE ' . $this->getTable());
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($result as $column) {
            print_r($column);
        }
    }
}
