<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class Concatenation implements ScalarFunction
  {
    const FUNCTION_NAME = "concat";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      $c = "";
      foreach ($values as $value)
      {
        if ($value->isNull())
        {
          return TextValue::getNullValue();
        }
        $c .= $value->getValue();
      }
      return new TextValue($c);
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
      return "CONCAT(" . implode(",", $argumentsQueryStrings) . ")";
    }
  }
?>
