<?php
  namespace Google\Visualization\DataSource\Query;

  interface ColumnLookup
  {
    public function getColumnIndex(AbstractColumn $column);
    public function containsColumn(AbstractColumn $column);
  }
?>
