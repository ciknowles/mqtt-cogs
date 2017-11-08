<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\DataTable\Value\Value;
  use Google\Visualization\DataSource\Query\ColumnLookup;
  use Google\Visualization\DataSource\Query\QuerySort;
  use Google\Visualization\DataSource\Query\SortOrder;

  class TableRowComparator
  {
    protected $sortColumns;
    protected $sortColumnOrder;
    protected $valueComparator;
    protected $columnLookup;

    public function __construct(QuerySort $sort, $locale, ColumnLookup $columnLookup)
    {
      $this->valueComparator = Value::getLocalizedComparator($locale);
      $this->columnLookup = $columnLookup;
      $columns = $sort->getSortColumns();
      $this->sortColumns = array();
      $this->sortColumnOrder = array();
      foreach ($columns as $columnSort)
      {
        $this->sortColumns[] = $columnSort->getColumn();
        $this->sortColumnOrder[] = $columnSort->getOrder();
      }
    }

    public function compare(TableRow $r1, TableRow $r2)
    {
      foreach ($this->sortColumns as $i=> $col)
      {
        $cc = $this->valueComparator->compare($col->getValue($this->columnLookup, $r1), $col->getValue($this->columnLookup, $r2));
        if ($cc != 0)
        {
          return $this->sortColumnOrder[$i] == SortOrder::ASCENDING ? $cc : -$cc;
        }
      }
      return 0;
    }
  }
?>
