<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;

  class DataTableColumnLookup implements ColumnLookup
  {
    protected $table;

    public function __construct(DataTable $table)
    {
      $this->table = $table;
    }

    public function getColumnIndex(AbstractColumn $column)
    {
      return $this->table->getColumnIndex($column->getId());
    }

    public function containsColumn(AbstractColumn $column)
    {
      return $this->table->containsColumn($column->getId());
    }
  }
?>
