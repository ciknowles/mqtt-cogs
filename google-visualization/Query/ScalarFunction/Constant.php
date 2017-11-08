<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  use Google\Visualization\DataSource\DataTable\Value\Value;

  class Constant implements ScalarFunction
  {
    protected $value;

    public function __construct(Value $value)
    {
      $this->value = $value;
    }

    public function getFunctionName()
    {
      return $this->value->toQueryString();
    }

    public function evaluate($values)
    {
      return $this->value;
    }

    public function getReturnType($types)
    {
      return $this->value->getType();
    }

    public function validateParameters($types)
    {
      if (count($types) != 0)
      {
        throw new InvalidQueryException("The constant function should not get any parameters");
      }
      return $this;
    }

    public function toQueryString($argumentsQueryStrings)
    {
      return $this->value->toQueryString();
    }
  }
?>
