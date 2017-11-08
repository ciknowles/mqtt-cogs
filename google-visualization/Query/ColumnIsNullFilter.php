<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;

  class ColumnIsNullFilter extends QueryFilter
  {
    protected $column;

    public function __construct(AbstractColumn $column)
    {
      $this->column = $column;
    }

    public function getColumn()
    {
      return $this->column;
    }

    public function getAllColumnIds()
    {
      return $this->column->getAllSimpleColumnIds();
    }

    public function getScalarFunctionColumns()
    {
      return $this->column->getAllScalarFunctionColumns();
    }

    public function getAggregationColumns()
    {
      return $this->column->getAllAggregationColumns();
    }

    public function isMatch(DataTable $table, TableRow $row)
    {
      $lookup = new DataTableColumnLookup($table);
      return is_null($this->column->getValue($lookup, $row));
    }

    public function toQueryString()
    {
      return $this->column->toQueryString() . " IS NULL";
    }
  }
?>
