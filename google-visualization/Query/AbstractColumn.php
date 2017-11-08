<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\TableRow;

  abstract class AbstractColumn
  {
    public function getValue(ColumnLookup $lookup, TableRow $row)
    {
      return $this->getCell($lookup, $row)->getValue();
    }

    public function getCell(ColumnLookup $lookup, TableRow $row)
    {
      $columnIndex = $lookup->getColumnIndex($this);
      return $row->getCell($columnIndex);
    }
  }
?>
