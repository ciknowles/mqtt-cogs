<?php
  namespace Google\Visualization\DataSource\Query;

  class QuerySort
  {
    protected $sortColumns;

    public function __construct()
    {
      $this->sortColumns = array();
    }

    public function isEmpty()
    {
      return !count($this->sortColumns);
    }

    public function addSort(ColumnSort $columnSort)
    {
      $this->sortColumns[] = $columnSort;
      return $this;
    }

    public function getSortColumns()
    {
      return $this->sortColumns;
    }

    public function getColumns()
    {
      $result = array();
      foreach ($this->sortColumns as $columnSort)
      {
        $result[] = $columnSort->getColumn();
      }
      return $result;
    }

    public function getAggregationColumns()
    {
      $result = array();
      foreach ($this->sortColumns as $columnSort)
      {
        $col = $columnSort->getColumn();
        foreach ($col->getAllAggregationColumns() as $innerCol)
        {
          if (!in_array($innerCol, $result))
          {
            $result[] = $innerCol;
          }
        }
      }
      return $result;
    }

    public function getScalarFunctionColumns()
    {
      $result = array();
      foreach ($this->sortColumns as $columnSort)
      {
        $col = $columnSort->getColumn();
        foreach ($col->getAllScalarFunctionColumns() as $innercol)
        {
          if (!in_array($innerCol, $result))
          {
            $result[] = $innerCol;
          }
        }
      }
      return $result;
    }

    public function toQueryString()
    {
      $stringList = array();
      foreach ($this->sortColumns as $colSort)
      {
        $stringList[] = $colSort->toQueryString();
      }
      return implode(", ", $stringList);
    }
  }
?>
