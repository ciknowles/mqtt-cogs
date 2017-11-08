<?php
  namespace Google\Visualization\DataSource\Query;

  class QueryPivot
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
        $columnIds[] = $col->getId();
      }
      return $columnIds;
    }

    public function getColumns()
    {
      return $this->columns;
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
  }
?>
