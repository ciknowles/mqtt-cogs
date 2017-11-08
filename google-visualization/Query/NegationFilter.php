<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;

  class NegationFilter extends QueryFilter
  {
    protected $subFilter;

    public function __construct(QueryFilter $subFilter)
    {
      $this->subFilter = $subFilter;
    }

    public function isMatch(DataTable $table, TableRow $row)
    {
      return !$this->subFilter->isMatch($table, $row);
    }

    public function getAllColumnIds()
    {
      return $this->subFilter->getAllColumnIds();
    }

    public function getScalarFunctionColumns()
    {
      return $this->subFilter->getScalarFunctionColumns();
    }

    public function getAggregationColumns()
    {
      return $this->subFilter->getAggregationColumns();
    }

    public function getSubFilter()
    {
      return $this->subFilter;
    }

    public function toQueryString()
    {
      return "NOT (" . $this->subFilter->toQueryString() . ")";
    }

    public function equals($o)
    {
      if ($this == $o)
      {
        return TRUE;
      }
      if (is_null($o))
      {
        return FALSE;
      }
      if (get_class($this) != get_class($o))
      {
        return FALSE;
      }
      if (is_null($this->subFilter))
      {
        if (!is_null($o->subFilter))
        {
          return FALSE;
        }
        return TRUE;
      }
    }
  }
?>
