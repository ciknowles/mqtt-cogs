<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\DataTable\Value\Value;

  class ColumnValueFilter extends ComparisonFilter
  {
    protected $column;
    protected $value;
    protected $isComparisonOrderReversed;

    public function __construct(AbstractColumn $column, Value $value, $operator, $isComparisonOrderReversed = FALSE)
    {
      parent::__construct($operator);
      $this->column = $column;
      $this->value = $value;
      $this->isComparisonOrderReversed = $isComparisonOrderReversed;
    }

    public function isMatch(DataTable $table, TableRow $row)
    {
      $lookup = new DataTableColumnLookup($table);
      $columnValue = $this->column->getValue($lookup, $row);
      return $this->isComparisonOrderReversed ? $this->isOperatorMatch($this->value, $columnValue) : $this->isOperatorMatch($columnValue, $this->value);
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

    public function getColumn()
    {
      return $this->column;
    }

    public function getValue()
    {
      return $this->value;
    }

    public function toQueryString()
    {
      if ($this->isComparisonOrderReversed)
      {
        return $this->value->toQueryString() . " " . $this->operator . " " . $this->column->toQueryString();
      } else
      {
        return $this->column->toQueryString() . " " . $this->operator . " " . $this->value->toQueryString();
      }
    }
  }
?>
