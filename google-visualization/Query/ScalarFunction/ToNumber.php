<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  class ToNumber implements ScalarFunction
  {
    const FUNCTION_NAME = "toNumber";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      $value = $values[0];
      if (is_null($value))
      {
        return  NumberValue::getNullValue();
      }

      switch($value->getType())
      {
       case ValueType::TEXT:
       	echo $value;
          $numberValue = new NumberValue($value);       
          echo 'done';
       break;
       
        default:
          throw new RuntimeException("Value type was not found: " . $value->getType());
      }
      return  $numberValue;
    }

    public function getReturnType($types)
    {
      return ValueType::NUMBER;
    }

    public function validateParameters($types)
    {
      if ($types[0] != ValueType::TEXT)
      {
        throw new InvalidQueryException(self::FUNCTION_NAME . " takes a text parameter");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return "cast(".$argumentsQueryStrings[0]." as DECIMAL)";
     /* self::FUNCTION_NAME . "(" . $argumentsQueryStrings[0] . ")";*/
    }
  }
?>
