<?php
  namespace Google\Visualization\DataSource\Base;

  use ReflectionClass;

  class OutputType
  {
    const CSV = "csv";
    const HTML = "html";
    const JSON = "json";
    const JSONP = "jsonp";
    const PHP = "php";
    const TSV_EXCEL = "tsv-excel";

    public static function defaultValue()
    {
      return self::JSON;

    }

    public static function findByCode($code)
    {
      $refl = new ReflectionClass(new self());
      if (in_array($code, $refl->getConstants()))
      {
        return $code;
      }
    }
  }
?>
