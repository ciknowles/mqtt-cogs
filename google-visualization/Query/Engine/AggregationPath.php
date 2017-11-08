<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\Value\Value;

  class AggregationPath
  {
    protected $values;

    public function __construct()
    {
      $this->values = array();
    }

    public function add(Value $value)
    {
      $this->values[] = $value;
      return $this;
    }

    public function getValues()
    {
      return $this->values;
    }

    public function reverse()
    {
      $this->values = array_reverse($this->values);
      return $this;
    }
  }
?>
