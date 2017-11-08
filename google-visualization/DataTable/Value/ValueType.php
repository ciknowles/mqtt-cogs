<?php
  namespace Google\Visualization\DataSource\DataTable\Value;

  use ReflectionClass;

  class ValueType
  {
    const BOOLEAN = "boolean";
    const NUMBER = "number";
    const TEXT = "string";
    const DATE = "date";
    const TIMEOFDAY = "timeofday";
    const DATETIME = "datetime";

    public static function values()
    {
      $refl = new ReflectionClass(__CLASS__);
      return $refl->getConstants();
    }
  }
?>
