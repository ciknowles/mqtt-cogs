<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;

  abstract class QueryFilter
  {
    abstract public function isMatch(DataTable $table, TableRow $row);
    abstract public function getAllColumnIds();
    abstract public function getScalarFunctionColumns();
    abstract public function getAggregationColumns();
    abstract public function toQueryString();
  }
?>
