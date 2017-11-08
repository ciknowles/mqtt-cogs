<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use DateTime;

  use Google\Visualization\DataSource\DataTable\Value\DateTimeValue;

  class CurrentDateTime implements ScalarFunction
  {
    const FUNCTION_NAME = "now";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      return new DateTimeValue("now");
    }

    public function getReturnType($types)
    {
      return ValueType::DATETIME;
    }

    public function validateParameters($types)
    {
      if (count($types) != 0)
      {
        throw new InvalidQueryException("The " . self::FUNCTION_NAME . " function should not get any parameters");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return self::FUNCTION_NAME . "()";
    }
  }
?>
