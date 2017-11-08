<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class ConcatenationWithSeparator implements ScalarFunction
  {
    const FUNCTION_NAME = "concat_ws";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      $separator = array_shift($values);
      return new TextValue(implode($separator, $values));
    }

    public function getReturnType($types)
    {
      return ValueType::TEXT;
    }

    public function validateParameters($types)
    {
      if (count($types) == 0)
      {
        throw new InvalidQueryException("The function " . self::FUNCTION_NAME . " requires at least one parameter");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return "CONCAT_WS(" . implode(",", $argumentsQueryStrings) . ")";
    }
  }
?>
