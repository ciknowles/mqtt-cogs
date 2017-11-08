<?php
  namespace Google\Visualization\DataSource\Query;

  class QueryGroup
  {
    protected $columns;

    public function __construct()
    {
      $this->columns = array();
    }

    public function addColumn(AbstractColumn $column)
    {
      $this->columns[] = $column;
      return $this;
    }

    public function getColumnIds()
    {
      $columnIds = array();
      foreach ($this->columns as $col)
      {
        $columnIds[] = $col->getId();
      }
      return $columnIds;
    }

    public function getSimpleColumnIds()
    {
      $columnIds = array();
      foreach ($this->columns as $col)
      {
        $columnIds = array_merge($columnIds, $col->getAllSimpleColumnIds());
      }
      return $columnIds;
    }

    public function getScalarFunctionColumns()
    {
      $scalarFunctionColumns = array();
      foreach ($this->columns as $col)
      {
        $scalarFunctionColumns = array_merge($scalarFunctionColumns, $col->getAllScalarFunctionColumns());
      }
      return $scalarFunctionColumns;
    }

    public function getColumns()
    {
      return $this->columns;
    }

    public function toQueryString()
    {
      return Query::columnListToQueryString($this->columns);
    }
  }
?>
