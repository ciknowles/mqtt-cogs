<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\NumberValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class AbsoluteValue implements ScalarFunction
  {
    const FUNCTION_NAME = "abs";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      return new NumberValue(abs($values[0]->getValue()));
    }

    public function getReturnType($types)
    {
      return ValueType::Number;
    }

    public function validateParameters($types)
    {
      if (count($types) != 1)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " requires 1 parameter");
      }
      if ($types[0] != ValueType::NUMBER)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " takes a number paramter");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryString)
    {
      return self::FUNCTION_NAME + "(" + $argumentsQueryStrings[0] + ")";
    }
  }
?>
