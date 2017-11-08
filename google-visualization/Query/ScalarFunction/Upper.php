<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class Upper implements ScalarFunction
  {
    const FUNCTION_NAME = "upper";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      return new TextValue(strtoupper($values[0]->getValue()));
    }

    public function getReturnType($types)
    {
      return ValueType::TEXT;
    }

    public function validateParameters($types)
    {
      if (count($types) != 1)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " requires 1 parameter");
      }
      if ($types[0] != ValueType::TEXT)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " takes a text paramter");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return self::FUNCTION_NAME . "(" . $argumentsQueryStrings[0] . ")";
    }
  }
?>
