<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\DateValue;
  use Google\Visualization\DataSource\DataTable\Value\DateTimeValue;
  use Google\Visualization\DataSource\DataTable\Value\NumberValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class ToDateBoundary implements ScalarFunction
  {
    const FUNCTION_NAME = "toDateBoundary";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      if ($values[0]->isNull() || $values[1]->isNull())
      {
        return DateTimeValue::getNullValue();
      }
      return  new DateTimeValue($values[0]);    
    }

    public function getReturnType($types)
    {
      return ValueType::DATETIME;
    }

    public function validateParameters($types)
    {
      if (count($types) != 2)
      {
        throw new InvalidQueryException("The function " . self::FUNCTION_NAME . " requires two parameters");
      }
      
      if (($types[0] != ValueType::DATE) || ($types[0] != ValueType::DATETIME)) {
          throw new InvalidQueryException("Can't perform the function " . self::FUNCTION_NAME . " parameter one should be a date or datetime");
      }
 
      if ($types[1] != ValueType::NUMBER){
      	throw new InvalidQueryException("Can't perform the function " . self::FUNCTION_NAME . " parameter two should be a number of seconds");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return "FROM_UNIXTIME(UNIX_TIMESTAMP(" . $argumentsQueryStrings[0]. ") - UNIX_TIMESTAMP(" . $argumentsQueryStrings[0]. ") mod ". $argumentsQueryStrings[1]. ")" ;
      // return "(" . $argumentsQueryStrings[0] . " - " . $argumentsQueryStrings[1] . ")";
//      return $argumentsQueryStrings[0];
    }
  }
?>
