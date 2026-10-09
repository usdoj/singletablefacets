<?php
/**
 * @file
 * Class for importing data from a source file.
 */

namespace USDOJ\SingleTableImporter;

/**
 * Class Importer
 * @package USDOJ\SingleTableImporter
 *
 * A class for importing data from a CSV or Excel file into a MySQL table.
 */
class Importer {

    /**
     * @var \Noodlehaus\Config
     *   The configuration object.
     */
    private $config;

    /**
     * @var string
     *   The location of the source file on disk.
     */
    private $sourceFile;

    /**
     * @var \Doctrine\DBAL\Connection
     *   The database connection.
     */
    private $db;

    /**
     * @var Array dateColumns
     *   An array of DATETIME columns in the database.
     */
    private $dateColumns;


    /**
     * Get the configuration object.
     *
     * @return \Noodlehaus\Config
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * Get the location of the source data file.
     *
     * @return string
     */
    public function getSourceFile() {
        return $this->sourceFile;
    }

    /**
     * Get the database connection.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Importer constructor.
     *
     * @param \Noodlehaus\Config $config
     *   The configuration object.
     * @param string $sourceFile
     *   The location of the source data file on disk.
     */
    public function __construct($config, $sourceFile) {

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

        // Catalog all the DATETIME columns for later.
        // Take note of all the datetime columns so that their facets can be
        // rendered hierarchically.
        $this->dateColumns = array();
        $statement = $this->getDb()->executeQuery('DESCRIBE ' . $this->settings('database table'));
        $result = $statement->fetchAllAssociative();
        foreach ($result as $column) {
            if ('datetime' == $column['Type']) {
                $this->dateColumns[] = $column['Field'];
            }
        }

        if (!is_file($sourceFile)) {
            // If the file does not exist, see if it can be downloaded.
            $request = \Httpful\Request::get($sourceFile);

            // Should we use a proxy?
            $proxy = $this->settings('proxy');
            if (!empty($proxy)) {
                $exceptions = $this->settings('proxy exceptions');
                if (!empty($exceptions)) {
                    foreach ($exceptions as $exception) {
                        if (strpos($sourceFile, $exception) !== FALSE) {
                            $proxy = NULL;
                            break;
                        }
                    }
                }
                // Rather than use Request::useProxy(), we use the less
                // abstracted addOnCurlOption, because we may actually want to
                // nullify a proxy usage that was inherited from Bash
                // environment variables, by passing in a $proxy of NULL.
                $request->addOnCurlOption(CURLOPT_PROXY, $proxy);
            }

            // Should we alter the user agent?
            $agent = $this->settings('user agent');
            if (!empty($agent)) {
                $request->withUserAgent($agent);
            }

            $response = $request->send();
            if (!$response->hasErrors()) {
                $fileName = basename($sourceFile);
                $sourceFile = '/tmp/' . $fileName;
                file_put_contents($sourceFile, strval($response));
            }
            else {
                // If it can't be downloaded either, abort.
                die(sprintf('Source data not found at %s', $sourceFile));
            }
        }
        $this->sourceFile = $sourceFile;
    }

    /**
     * Helper method to get a configuration setting by key.
     *
     * @param $key
     *   The key to get a config setting for.
     *
     * @return mixed
     *   Whatever the config value there is for that key.
     */
    public function settings($key) {
        return $this->getConfig()->get($key);
    }

    /**
     * Do a test run, which throws an exception if it would fail.
     */
    public function testRun() {

        $inputFileName = $this->getSourceFile();
        $rows = $this->dataToArray($inputFileName);
        if (empty($rows) || count($rows) <= 1) {
            throw new \Exception('Aborting SingleTableImporter run: insufficient source data.');
        }
    }

    /**
     * Run the importer.
     *
     * @throws \Exception
     */
    public function run() {

        if (!$this->settings('additive imports')) {
          $this->delete();
        }
        $this->insert();
    }

    /**
     * Insert the new data into the database table.
     *
     * @throws \Exception
     */
    private function insert() {

        $inputFileName = $this->getSourceFile();

        try {
            $rows = $this->dataToArray($inputFileName);

            $table = $this->settings('database table');
            $requiredColumns = $this->settings('required columns');
            $numInserted = 0;
            foreach ($rows as $row) {
                $anonymousParameters = array();
                $insert = $this->getDb()->createQueryBuilder()
                    ->insert($table);
                foreach ($row as $column => $value) {
                    $value = preg_replace('/[^\x20-\x7E]/', '', $value);
                    if (empty($column)) {
                        // In some cases the imported data mistakenly has
                        // empty header columns.
                        continue;
                    }
                    // Skip entire rows if there isn't a value in a required column.
                    if (is_array($requiredColumns) && in_array($column, $requiredColumns) && empty($value)) {
                        continue 2;
                    }
                    $insert->setValue('`' . $column . '`', '?');
                    $anonymousParameters[] = $value;
                }
                $numInserted += $insert
                    ->setParameters($anonymousParameters)
                    ->executeStatement();
            }
            print sprintf('Imported %s rows.', $numInserted) . PHP_EOL;
        } catch (Exception $e) {
            $pathInfo = pathinfo($inputFileName, PATHINFO_BASENAME);
            throw new \Exception(sprintf('Error loading file %s: %s', $pathInfo, $e->getMessage()));
        }
    }

    /**
     * Delete all the existing data from the database table.
     */
    private function delete() {

        $table = $this->settings('database table');
        $this->getDb()->createQueryBuilder()
            ->delete($table)
            ->executeStatement();
        print 'Deleted all rows.' . PHP_EOL;
    }

    /**
     * Helper method to convert a file into an array.
     *
     * @param string $filePath
     *   The path on disk to the CSV file.
     *
     * @return array
     */
    private function dataToArray($filePath) {

        $rows = NULL;
        $header = NULL;

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        // Support Excel files.
        if ('xls' == $extension || 'xlsx' == $extension) {
            //$objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            //$objPHPExcel = $objReader->load($filePath);
            foreach ($objPHPExcel->getWorksheetIterator() as $worksheet) {
                $rows = $worksheet->toArray();
                $header = $rows[0];
                // We only get the first worksheet.
                break;
            }
        }
        else {
            // If not an Excel file, assume it is CSV.
            $delimiter = $this->getConfig()->get('csv delimiter', ',');
            $enclosure = $this->getConfig()->get('csv enclosure', '"');
            $rows = new \Keboola\Csv\CsvReader($filePath, $delimiter, $enclosure);
            $header = $rows->getHeader();
        }

        $data = array();
        // Skip the first row since it is the headers.
        $skip = TRUE;
        foreach($rows as $row) {
            if ($skip) {
                $skip = FALSE;
                continue;
            }

            // Do we need to filter any of the text?
            $filteredRow = $row;
            // Look for text alerations specific to particular columns.
            $textAlterationsPerColumn = $this->settings('text alterations per column');
            if (!empty($textAlterationsPerColumn)) {
                foreach ($header as $index => $column) {
                    if (!empty($textAlterationsPerColumn[$column])) {
                        foreach ($textAlterationsPerColumn[$column] as $search => $replace) {
                            $filteredRow[$index] = str_replace($search, $replace, $filteredRow[$index]);
                        }
                    }
                }
            }
            // Look for global text alterations.
            $textAlterations = $this->settings('text alterations');
            if (!empty($textAlterations)) {
                foreach ($textAlterations as $search => $replace) {
                    $filteredRow = str_replace($search, $replace, $filteredRow);
                }
            }
            // Do we need to convert any dates?
            if (!empty($this->dateColumns)) {
                foreach ($header as $index => $column) {
                    if (in_array($column, $this->dateColumns)) {
                        $filteredRow[$index] = $this->convertDate($filteredRow[$index], $column);
                    }
                }
            }
            // Fix invalid rows.
            if (count($filteredRow) < count($header)) {
                for ($i = 0; $i < count($header) - count($filteredRow); $i++) {
                    $filteredRow[] = '';
                }
            }
            $data[] = array_combine($header, $filteredRow);
        }
        return $data;
    }

    /**
     * Convert dates into MySQL DATETIME values.
     *
     * This method needs to be very forgiving, because dates can be in a variety
     * of formats. This supports Excel dates, Unix timestamps, PHP-supported
     * date strings, and even some date formats that us humans like to use.
     *
     * @param int $dateString
     *   A raw date string to convert.
     *
     * @param string $column
     *   The name of the database column.
     *
     * @return string
     */
    private function convertDate($dateString, $column) {

        // What we're trying to get.
        $unixTimestamp = NULL;

        if (empty($dateString)) {
            return $dateString;
        }

        // First check to see if we have a specified format for this column.
        $dateFormats = $this->settings('date formats');
        if (is_array($dateFormats) && !empty($dateFormats[$column])) {
            foreach ($dateFormats[$column] as $format) {
                $dateObj = \DateTime::createFromFormat($format, $dateString);
                if (!empty($dateObj)) {
                    $unixTimestamp = $dateObj->getTimestamp();
                    break;
                }
            }
        }
        else if (is_numeric($dateString)) {
            $dateInt = (int) $dateString;

            // The easiest to recognize is Excel's unique date format, which is
            // the number of days since 1900. For 99% of cases this will be an
            // integer between -100,000 and 100,000. (If this generalization
            // ever stops working, we can complicate this library with some new
            // configuration options.)
            if ($dateInt > -100000 && $dateInt < 100000) {
                // Numbers of days between January 1, 1900 and 1970.
                $daysSince = 25569;
                // Numbers of second in a day:
                $secondsInDay = 86400;
                $unixTimestamp = ($dateInt - $daysSince) * $secondsInDay;
            }
            else {
                // For all other integer dates, we assume that it is already a
                // Unix timestamp.
                $unixTimestamp = $dateInt;
            }
        }
        else {
            // If not numeric, we assume it is a date string. First try to let
            // PHP figure it out.
            $unixTimestamp = strtotime($dateString);
            if (!$unixTimestamp) {
                // If that didn't work, try for some common human-reable formats
                // that maybe PHP doesn't support. For example, YYYY-MM is one,
                // and similarly, YYYY/MM.
                if (2 == count(explode('/', $dateString))) {
                    $unixTimestamp = strtotime($dateString . '/01');
                }
                elseif (2 == count(explode('-', $dateString))) {
                    $unixTimestamp = strtotime($dateString . '-01');
                }
            }
        }

        // If we still got nothing, return whatever it was, so that the
        // database exception will be useful.
        if (empty($unixTimestamp)) {
            return $dateString;
        }
        return date('Y-m-d H:i:s', $unixTimestamp);
    }
}
