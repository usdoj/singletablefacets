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
            $numInserted = 0;
            foreach ($rows as $row) {
                $anonymousParameters = array();
                $insert = $this->getApp()->getDb()->createQueryBuilder()
                    ->insert($table);
                foreach ($row as $column => $value) {
                    $insert->setValue($column, '?');
                    $anonymousParameters[] = $value;
                }
                $numInserted += $insert
                    ->setParameters($anonymousParameters)
                    ->execute();
            }
            print sprintf('Imported %s rows.', $numInserted) . PHP_EOL;
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
        print 'Deleted all rows.' . PHP_EOL;
    }

    private function dataToArray($filePath) {

        $csvFile = new \Keboola\Csv\CsvFile($filePath);
        $header = $csvFile->getHeader();
        $data = array();
        foreach($csvFile as $row) {
            $data[] = array_combine($header, $row);
        }
        return $data;
    }
}
