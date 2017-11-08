<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\TableCell;
  use Google\Visualization\DataSource\Util\Map;

  class MetaTable
  {
    protected $data;

    public function __construct()
    {
      $this->data = new Map();
    }

    public function put(RowTitle $rowTitle, ColumnTitle $columnTitle, TableCell $cell)
    {
      $rowData = $this->data->get($rowTitle);
      if (is_null($rowData))
      {
        $rowData = new Map();
        $this->data->put($rowTitle, $rowData);
      }
      $rowData->put($columnTitle, $cell);
      return $this;
    }

    public function getRow(RowTitle $rowTitle)
    {
      return $this->data->get($rowTitle);
    }
  }
?>
