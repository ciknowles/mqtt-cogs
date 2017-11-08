<?php
  namespace Google\Visualization\DataSource\DataTable;

  use Google\Visualization\DataSource\Base\TypeMismatchException;
  use Google\Visualization\DataSource\Base\Warning;
  use Google\Visualization\DataSource\DataTable\Value\Value;

  class DataTable
  {
    protected $columns;
    protected $columnIndexById;
    protected $rows;
    protected $customProperties;
    protected $warnings;
    protected $localeForUserMessages;

    public function __construct()
    {
      $this->columns = array();
      $this->columnIndexById = array();
      $this->rows = array();
      $this->warnings = array();
    }

    public function addRow(TableRow $row)
    {
      $cells = $row->getCells();
      if (count($cells) > count($this->columns))
      {
        throw new TypeMismatchException("Row has too many cells.  Should be at most of size: " . count($columns));
      }
      for ($i = 0; $i < count($cells); $i++)
      {
        if ($cells[$i]->getType() != $this->columns[$i]->getType())
        {
          throw new TypeMismatchException("Cell type does not mach column type, at index; " . $i .". Should be of type: " . $this->columns[$i]->getType());
        }
      }
      for ($i = count($cells); $i < count($this->columns); $i++)
      {
        $row->addCell(new TableCell(Value::getNullValueFromValueType($this->columns[$i]->getType())));
      }
      $this->rows[] = $row;
      return $this;
    }

    public function addRows($rows)
    {
      foreach ($rows as $row)
      {
        $this->addRow($row);
      }
      return $this;
    }

    public function setRows($rows)
    {
      $this->rows = array();
      return $this->addRows($rows);
    }

    public function getNumberOfRows()
    {
      return count($this->rows);
    }

    public function getNumberOfColumns()
    {
      return count($this->columns);
    }

    public function getColumnDescriptions()
    {
      return $this->columns;
    }

    public function getColumnDescription($columnId)
    {
      return $this->columns[$this->getColumnIndex($columnId)];
    }

    public function addColumn(ColumnDescription $columnDescription)
    {
      $columnId = $columnDescription->getId();
      if (array_key_exists($columnId, $this->columnIndexById))
      {
        throw new RuntimeException("Column Id [" . $columnId ."] already in table description");
      }

      $this->columnIndexById[] = $columnId;
      $this->columns[] = $columnDescription;
      $newRows = array();
      foreach ($this->rows as $row)
      {
        $newRows[] = $row->addCell(new TableCell(Value::getNullValueFromValueType($columnDescription->getType())));
      }
      $this->rows = $newRows;
      return $this;
    }

    public function addColumns($columns)
    {
      foreach ($columns as $column)
      {
        $this->addColumn($column);
      }
      return $this;
    }

    public function setColumns($columns)
    {
      $rows = $this->getRows();
      $this->rows = array();
      $this->columns = array();
      $this->addColumns($columns);
      $this->addRows($rows);
      return $this;
    }

    public function getColumnIndex($columnId)
    {
      return array_search($columnId, $this->columnIndexById);
    }

    public function getRows()
    {
      return $this->rows;
    }

    public function getRow($rowIndex)
    {
      return $this->rows[$rowIndex];
    }

    public function addWarning(Warning $warning)
    {
      $this->warnings[] = $warning;
    }

    public function getWarnings()
    {
      return $this->warnings;
    }

    public function containsColumn($columnId)
    {
      return in_array($columnId, $this->columnIndexById);
    }

    public function setLocaleForUserMessages($localeForUserMessages)
    {
      $this->localeForUserMessages = $localeForUserMessages;
      return $this;
    }

    public function getLocaleForUserMessages()
    {
      return $this->localeForUserMessages;
    }
  }
?>
