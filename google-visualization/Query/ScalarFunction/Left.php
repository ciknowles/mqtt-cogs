<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class Left implements ScalarFunction
  {
    const FUNCTION_NAME = "left";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      return new TextValue(substr($values[0]->getValue(), 0, $values[1]->getValue()));
    }

    public function getReturnType($types)
    {
      return ValueType::TEXT;
    }

    public function validateParameters($types)
    {
      if (count($types) != 2)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " requires 2 parameter");
      }
      if ($types[0] != ValueType::TEXT)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " takes a text parameter");
      }
      if ($types[1] != ValueType::NUMBER)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " takes a numeric parameter");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return self::FUNCTION_NAME . "(" . $argumentsQueryStrings[0] . ", " . $argumentsQueryStrings[1] . ")";
    }
  }
?>
