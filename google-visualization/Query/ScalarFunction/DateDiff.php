<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  class DateDiff implements ScalarFunction
  {
    const FUNCTION_NAME = "dateDiff";

    public function getFunctionName()
    {
      return self::FUNCTION_NAME;
    }

    public function evaluate($values)
    {
      $firstValue = $values[0];
      $secondValue = $values[1];

      if (is_null($firstValue) || is_null($secondValue))
      {
        return NumberValue::getNullValue();
      }

      $di = $firstValue->getDateTime->diff($secondValue->getDateTime());
      return new NumberValue($di->d);
    }

    public function getReturnType($types)
    {
      return ValueType::NUMBER;
    }

    public function validateParameters($types)
    {
      if (count($types) != 2)
      {
        throw new InvalidQueryException("Number of parameters for the dateDiff function is wrong: " . count($types));
      } else if (!$this->isDateOrDateTimeValue($types[0]) || !$this->isDateOrDateTimeValue($types[1]))
      {
        throw new InvalidQueryException("Can't perform the function 'dateDiff' on values that are not a Date or DateTime values");
      }
      return $this;
    }

    protected function isDateOrDateTimeValue($type)
    {
      return $type == ValueType::DATE || $type == ValueType::DATETIME;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return self::FUNCTION_NAME . "(" . $argumentsQueryStrings[0] . ", " . $argumentsQueryStrings[1] . ")";
    }
  }
?>
