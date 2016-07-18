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

        // Catch an edge case where the final column is empty.
        $lastHeader = count($header) - 1;
        if (isset($header[$lastHeader]) && empty($header[$lastHeader])) {
            array_pop($header);
        }

        $data = array();
        // Skip the first row since it is the headers.
        $skip = TRUE;
        foreach($csvFile as $row) {
            if ($skip) {
                $skip = FALSE;
                continue;
            }
            // If the number of items is not correct, we'll get errors later,
            // so skip those.
            if (count($row) < count($header)) {
                print 'Warning, this row did not have enough cells.' . PHP_EOL;
                print_r($row);
                continue;
            }

            // Do we need to filter any of the text?
            $filteredRow = $row;
            $textAlterations = $this->getApp()->settings('text alterations');
            if (!empty($textAlterations)) {
                foreach ($textAlterations as $search => $replace) {
                    $filteredRow = str_replace($search, $replace, $filteredRow);
                }
            }
            // Do we need to convert any Excel dates?
            $excelDateColumns = $this->getApp()->settings('convert from excel dates');
            if (!empty($excelDateColumns)) {
                foreach ($header as $index => $column) {
                    if (in_array($column, $excelDateColumns)) {
                        $filteredRow[$index] = $this->convertExcelDate($filteredRow[$index]);
                    }
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

    private function convertExcelDate($excelDate) {

        // Numbers of days between January 1, 1900 and 1970 (including 19 leap years).
        $daysSince = 25569;

        // Numbers of second in a day:
        $secondsInDay = 86400;

        if ($excelDate <= $daysSince) {
            return 0;
        }

        $unixTimestamp = ($excelDate - $daysSince) * $secondsInDay;
        return date('Y-m-d H:i:s', $unixTimestamp);
    }
}
