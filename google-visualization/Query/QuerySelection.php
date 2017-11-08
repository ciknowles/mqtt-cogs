<?php
  namespace Google\Visualization\Datasource\Query;

  class QuerySelection
  {
    protected $columns;

    public function __construct()
    {
      $this->columns = array();
    }

    public function isEmpty()
    {
      return !count($this->columns);
    }

    public function addColumn(AbstractColumn $column)
    {
      $this->columns[] = $column;
      return $this;
    }

    public function getColumns()
    {
      return $this->columns;
    }

    public function getAggregationColumns()
    {
      $result = array();
      foreach ($this->columns as $col)
      {
        $result = array_merge($result, $col->getAllAggregationColumns());
      }
      return $result;
    }

    public function getSimpleColumns()
    {
      $result = array();
      foreach ($this->columns as $col)
      {
        $result = array_merge($result, $col->getAllSimpleColumns());
      }
      return $result;
    }

    public function getScalarFunctionColumns()
    {
      $result = array();
      foreach ($this->columns as $col)
      {
        $result = array_merge($result, $col->getAllScalarFunctionColumns());
      }
      return $result;
    }

    public function toQueryString()
    {
      return Query::columnListToQueryString($this->columns);
    }
  }
?>
