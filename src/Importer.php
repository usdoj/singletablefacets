<?php
/**
 * @file
 * Class for importing data from a source file.
 */

namespace USDOJ\SingleTableFacets;

class Importer {

    private $fileName;

    public function getFileName() {

        return $this->fileName;
    }

    public function __construct($fileName) {

        $this->fileName = $fileName;
    }

    public function import() {

        $inputFileName = $this->getFileName();

        // Read your Excel workbook
        try {
            $inputFileType = PHPExcel_IOFactory::identify($inputFileName);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFileName);
        } catch (Exception $e) {
            $pathInfo = pathinfo($inputFileName, PATHINFO_BASENAME);
            throw new Exception(sprintf('Error loading file %s: %s', $pathInfo, $e->getMessage()));
        }

        // Get worksheet dimensions
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // Loop through each row of the worksheet in turn
        for ($row = 1; $row <= $highestRow; $row++) {
            // Read a row of data into an array
            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE);
            print_r($rowData);
        }
    }
}
