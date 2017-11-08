<?php
  namespace Google\Visualization\DataSource;

  use Google\Visualization\DataSource\Query\Query;

  interface DataTableGenerator
  {
    public function generateDataTable(Query $query);
    public function getCapabilities();
  }
?>
