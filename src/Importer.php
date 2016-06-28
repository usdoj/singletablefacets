<?php
/**
 * @file
 * Class for importing data from a source file.
 */

namespace USDOJ\SingleTableFacets;

class Importer {

    private $app;
    private $sourceFile;

    public function getSourceFile() {
        return $this->sourceFile;
    }

    public function getApp() {
        return $this->app;
    }

    public function __construct($app, $sourceFile) {

        $this->app = $app;
        $this->sourceFile = $sourceFile;
    }

    public function run() {

        $this->delete();
        $this->insert();
    }

    private function insert() {

        $inputFileName = $this->getSourceFile();

        try {
            $rows = $this->dataToArray($inputFileName);

            $table = $this->getApp()->getTable();
            foreach ($rows as $row) {
                $this->getApp()->getDb()->createQueryBuilder()
                    ->insert($table)
                    ->values($row)
                    ->execute();
            }
        } catch (Exception $e) {
            $pathInfo = pathinfo($inputFileName, PATHINFO_BASENAME);
            throw new \Exception(sprintf('Error loading file %s: %s', $pathInfo, $e->getMessage()));
        }
    }

    private function delete() {

        $table = $this->getApp()->getTable();
        $this->getApp()->getDb()->createQueryBuilder()
            ->delete($table)
            ->execute();
    }

    private function dataToArray($filePath) {

        $csvFile = new \Keboola\Csv\CsvFile($filePath);
        $data = array();
        foreach($csvFile as $row) {
            $data[] = $row;
        }
        return $data;
    }
}
