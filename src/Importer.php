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

            $table = $this->getApp()->settings('database table');
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

        $table = $this->getApp()->settings('database table');
        $this->getApp()->getDb()->createQueryBuilder()
            ->delete($table)
            ->execute();
        print 'Deleted all rows.' . PHP_EOL;
    }

    private function dataToArray($filePath) {

        $csvFile = new \Keboola\Csv\CsvFile($filePath);
        $header = $csvFile->getHeader();
        $data = array();
        // Skip the first row since it is the headers.
        $skip = TRUE;
        foreach($csvFile as $row) {
            if ($skip) {
                $skip = FALSE;
                continue;
            }
            // Do we need to filter any of the text?
            $filteredRow = $row;
            if (!empty($this->getApp()->settings('text alterations'))) {
                foreach ($this->getApp()->settings('text alterations') as $search => $replace) {
                    $filteredRow = str_replace($search, $replace, $filteredRow);
                }
            }
            // Skip invalid rows.
            if (count($filteredRow) != count($header)) {
                continue;
            }
            $data[] = array_combine($header, $filteredRow);
        }
        return $data;
    }
}
