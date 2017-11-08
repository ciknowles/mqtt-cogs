Google Visualization Data Source for PHP
========================================

This is a near literal translation of [google-visualization-java](https://code.google.com/p/google-visualization-java/source/browse/trunk/src/main/java/com/google/visualization/datasource/) into PHP.
The `QueryParser` class was not translated, but written from scratch.
While its main purpose is to generate data formatted for Google Charts, it can also be used as an abstraction layer for accessing data from a variety of sources using a SQL-like query.
Thorough testing has not been performed, so bug reports are encouraged.  Enjoy!


Features
--------

- A PHP implementation of the [Google Chart Tools Datasource Protocol](https://developers.google.com/chart/interactive/docs/dev/implementing_data_source) (V0.6)
- Parses a [Google Visualization Query](https://developers.google.com/chart/interactive/docs/querylanguage) into a PHP object
- Executes the query on an existing `DataTable` or retrieves one from a database using a `Util\xxxDataSourceHelper` class, which performs automatic type casting:
    - PDO:
        - PostgreSQL
        - MS SQL Server / SQL Azure
        - MySQL
        - SQLite
    - MySQLi
- Outputs the result in the requested format
    - `csv` - Comma Separated Values
    - `html` - HyperText Markup Language
    - `json` - JavaScript Object Notation
    - `jsonp` - JSON with Padding
    - `php` - Serialized PHP object with class: DataTable (success) or ResponseStatus (error)
    - `tsv-excel` - Tab Separated Values for Excel
- Complete support of the [Google Visualization Query Language](https://developers.google.com/chart/interactive/docs/querylanguage) (V0.7), with some additional functions:
    - `ABS(number)` - absolute value
    - `CONCAT(string1, string2, ...)` - concatenate strings
    - `CONCAT_WS(separator, string1, string2, ...)` - concatenate strings with separator
    - `LEFT(string, length)` - left-most characters of a string
    - `RIGHT(string, length)` - right-most characters of a string
    - `ROUND(number, precision)` - round a number to a digit of precision

Dependencies
------------

- PHP 5.3+ (tested on 5.3.29 &amp; 5.4.32)
    - intl extension
    - PDO extension (optional, required for `Util\PdoDataSourceHelper` classes)
        - PDO database-specific driver extensions (required for each driver you need to use)
    - mysqli extension (optional, required for `Util\MysqliDataSourceHelper` class)
- ICU (to compile resource bundles)
    - See the [ICU ReadMe](http://source.icu-project.org/repos/icu/icu/trunk/readme.html) if `genrb` is not installed on your system


Installation
------------

1. Clone/extract repository.
2. Use the ICU tool `genrb` to compile each `*.txt` file in `Base\ErrorMessages`
    - This will compile `root.txt` into `root.res` (default locale resource bundle):

`user@localhost [/path/to/google-visualization-php/Base/ErrorMessages]# genrb root.txt`


Usage
-----

The usage is nearly similar to that of the [java library](https://developers.google.com/chart/interactive/docs/dev/dsl_about) (see that for further usage help).
- Include all the files in the path or use an autoloader, such as [AutoloadByNamespace-php](https://github.com/bggardner/AutoloadByNamespace-php).
- For usage with Google Charts:
    - Create a class that extends the `DataSource` class
    - Instantiate the class in a file that accepts the HTTP GET request from the Google Chart
- Useful stand-alone functions if using as an abstraction layer:
    - `DataSourceHelper::parseQuery($string)` - Returns a `Query` object from $string
    - `Util\Pdo\MySqlPdoDataSourceHelper::executeQuery(Query $query, PDO $pdo, $tableNmae)` - Returns a `DataTable` object by applying the query to a MySQL table 
    - `DataSourceHelper::applyQuery(Query $query, DataTable $dataTable, $locale)` - Returns a `DataTable` object by applying the query to an exsiting `DataTable`
- Optionally the resource bundle can be kept in a folder outside of the repository. In that case call `Google\Visualization\DataSource\Base\LocaleUtil::setResourceBundleDir($pathToResources);` where `ErrorMessages` is a sub-folder of `$pathToResources`.


Examples
--------

Query a table named "mytable" from a SQL database, using `AutoloadByNamespace`:
```php
<?php
  // Required to autoload the Google\Visualization\DataSource classes
  require_once "/path/to/AutoloadByNamespace.php";
  spl_autoload_register("AutoloadByNamespace::autoload");
  AutoloadByNamespace::register("Google\Visualization\DataSource", "/path/to/google-visualization-php");

  // The custom class that defines how the data is generated
  class MyDataSource extends Google\Visualization\DataSource\DataSource
  {
    public function getCapabilities() { return Google\Visualization\DataSource\Capabilities::SQL; }

    public function generateDataTable(Google\Visualization\DataSource\Query\Query $query)
    {
      // MySQL
      $pdo = new PDO("mysql:host=xxx;port=xxx;dbname=xxx", "username", "password");
      return Google\Visualization\DataSource\Util\Pdo\MysqlPdoDataSourceHelper::executeQuery($query, $pdo, "mytable");

      // MS SQL Server / SQL Azure
      $pdo = new PDO("sqlsrv:Server=xxx;Database=xxx", "username", "password");
      return Google\Visualization\DataSource\Util\Pdo\MssqlserverPdoDataSourceHelper::executeQuery($query, $pdo, "mytable");

      // PostgreSQL
      $pdo = new PDO("pgsql:host=xxx;port=xxx;dbname=xxx", "username", "password");
      return Google\Visualization\DataSource\Util\Pdo\PostgresqlPdoDataSourceHelper::executeQuery($query, $pdo, "mytable");

      // SQLite
      $pdo = new PDO("sqlite:/path/to/xxx.db");
      return Google\Visualization\DataSource\Util\Pdo\SqlitePdoDataSourceHelper::executeQuery($query, $pdo, "mytable");

      // MySQLi
      $db = new mysqli("host", "username", "password");
      return Google\Visualization\DataSource\Util\MysqliDataSourceHelper::executeQuery($query, $db, "mytable");
    }

    public function isRestrictedAccessMode() { return FALSE; }
  }

  // Instantiating the class parses the 'tq' and 'tqx' HTTP request parameters and outputs the resulting data
  new MyDataSource();
?>
```
Query a CSV file (with known column order and data types), using spl_autoload_register:
```php
<?php
  spl_autoload_register(function($class) {
    $class = str_replace('Google\\Visualization\\DataSource\\', '', $class);
    require_once '/path/to/google-visualization-php/' . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
  });

  class MyDataSource extends Google\Visualization\DataSource\DataSource
  {
    public function getCapabilities() { return Google\Visualization\DataSource\Capabilities::NONE; }

    public function generateDataTable(Google\Visualization\DataSource\Query\Query $query = NULL)
    {
      // Since Capabilities are NONE, the $query argument will be NULL as the data will be processed by DataSourceHelper

      // Create the DataTable and configure the columns (name and data type)
      $dataTable = new Google\Visualization\DataSource\DataTable\DataTable();
      $columnDescriptions = array();
      $columnDescriptions[] = new Google\Visualization\DataSource\DataTable\ColumnDescription("x", Google\Visualization\DataSource\DataTable\Value\ValueType::NUMBER, "x");
      $columnDescriptions[] = new Google\Visualization\DataSource\DataTable\ColumnDescription("y", Google\Visualization\DataSource\DataTable\Value\ValueType::NUMBER, "y");
      $dataTable->addColumns($columnDescriptions);

      // Populate the DataTable
      $fh = fopen('data.csv', 'r');
      while (($data = fgetcsv($fh)) !== FALSE)
      {
        $tableRow = new Google\Visualization\DataSource\DataTable\TableRow();
        foreach ($data as $datum)
        {
          $value = new Google\Visualization\DataSource\DataTable\Value\NumberValue($datum);
          $tableCell = new Google\Visualization\DataSource\DataTable\TableCell($value);
          $tableRow->addCell($tableCell);
        }
        $dataTable->addRow($tableRow);
      }
      return $dataTable;
    }

    public function isRestrictedAccessMode() { return FALSE; }
  }

  new MyDataSource();
?>
```
