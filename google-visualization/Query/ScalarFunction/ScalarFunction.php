<?php
  namespace Google\Visualization\DataSource\Query\ScalarFunction;

  interface ScalarFunction
  {
    public function getFunctionName();
    public function evaluate($values);
    public function getReturnType($types);
    public function validateParameters($types);
    public function toQueryString($argumentQueryStrings);
  }
?>
