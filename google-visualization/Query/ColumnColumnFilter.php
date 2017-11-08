<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;

  class ColumnColumnFilter extends ComparisonFilter
  {
    protected $firstColumn;
    protected $secondColumn;

    public function __construct(AbstractColumn $firstColumn, AbstractColumn $secondColumn, $operator)
    {
      parent::__construct($operator);
      $this->firstColumn = $firstColumn;
      $this->secondColumn = $secondColumn;
    }

    public function isMatch(DataTable $table, TableRow $row)
    {
      $lookup = new DataTableColumnLookup($table);
      $firstValue = $this->firstColumn->getValue($lookup, $row);
      $secondValue = $this->secondColumn->getValue($lookup, $row);
      return $this->isOperatorMatch($firstValue, $secondValue);
    }

    public function getAllColumnIds()
    {
      return array_merge($this->firstColumn->getAllSimpleColumnIds(), $this->secondColumn->getAllSimpleColumnIds());
    }

    public function getScalarFunctionColumns()
    {
      return array_merge($this->firstColumn->getScalarFunctionColumns(), $this->secondColumn->getScalarFunctionColumns());
    }

    public function getAggregationColumns()
    {
      return array_merge($this->firstColumn->getAllAggregationColumns(), $this->secondColumn->getAllAggregationColumns());
    }

    public function getFirstColumn()
    {
      return $this->firstColumn;
    }

    public function getSecondColumn()
    {
      return $this->secondColumn;
    }

    public function toQueryString()
    {
      return $this->firstColumn->toQueryString() . " " . $this->operator . " " . $this->secondColumn->toQueryString();
    }
  }
?>
