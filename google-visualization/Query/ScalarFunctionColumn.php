<?php
  namespace Google\Visualization\DataSource\Query;

  use RuntimeException;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableCell;
  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\Query\AbstractColumn;
  use Google\Visualization\DataSource\Query\ScalarFunction\ScalarFunction;

  class ScalarFunctionColumn extends AbstractColumn
  {
    const COLUMN_FUNCTION_TYPE_SEPARATOR = "_";
    const COLUMN_COLUMN_SEPARATOR = ",";

    protected $columns;
    protected $scalarFunction;

    public function __construct($columns, ScalarFunction $scalarFunction)
    {
      $this->columns = array();
      foreach ($columns as $column)
      {
        if (!($column instanceof AbstractColumn))
        {
          throw new RuntimeException("The first argument to ScalarFunctionColumn must be an array of AbstractColumns");
        }
        $this->columns[] = $column;
      }
      $this->scalarFunction = $scalarFunction;
    }

    public function getId()
    {
      $colIds = array();
      foreach ($this->columns as $col)
      {
        $colIds[] = $col->getId();
      }
      return $this->scalarFunction->getFunctionName() . self::COLUMN_FUNCTION_TYPE_SEPARATOR . implode(self::COLUMN_COLUMN_SEPARATOR, $colIds);
    }

    public function getAllSimpleColumnIds()
    {
      $columnIds = array();
      foreach ($this->columns as $column)
      {
        $columnIds = array_merge($columnIds, $column->getAllSimpleColumnIds());
      }
      return $columnIds;
    }

    public function getFunction()
    {
      return $this->scalarFunction;
    }

    public function getColumns()
    {
      return $this->columns;
    }

    public function getCell(ColumnLookup $lookup, TableRow $row)
    {
      if ($lookup->containsColumn($this))
      {
        $columnIndex = $lookup->getColumnIndex($this);
        return $row->getCell($columnIndex);
      }

      $functionParameters = array();
      foreach($this->columns as $column)
      {
        $functionParameters[] = $column->getValue($lookup, $row);
      }
      return new TableCell($this->scalarFunction->evaluate($functionParameters));
    }

    public function getAllSimpleColumns()
    {
      $simpleColumns = array();
      foreach ($this->columns as $col)
      {
        $simpleColumns = array_merge($simpleColumns, $col->getAllSimpleColumns());
      }
      return $simpleColumns;
    }

    public function getAllAggregationColumns()
    {
      $aggregationColumns = array();
      foreach ($this->columns as $col)
      {
        $aggregationColumns = array_merge($aggregationColumns, $col->getAllAggregationColumns());
      }
      return $aggregationColumns;
    }

    public function getAllScalarFunctionColumns()
    {
      $scalarFunctionColumns = array();
      foreach ($this->columns as $col)
      {
        $scalarFunctionColumns = array_merge($scalarFunctionColumns, $col->getAllScalarFunctionColumns());
      }
      return $scalarFunctionColumns;
    }

    public function validateColumn(DataTable $dataTable)
    {
      $types = array();
      foreach ($this->columns as $column)
      {
        $column->validateColumn($dataTable);
        $types[] = $column->getValueType($dataTable);
      }
      $this->scalarFunction->validateParameters($types);
      return $this;
    }

    public function getValueType(DataTable $dataTable)
    {
      if ($dataTable->containsColumn($this->getId()))
      {
        return $dataTable->getColumnDescription($this->getId())->getType();
      }
      $types = array();
      foreach ($this->columns as $column)
      {
        $types[] = $column->getValueType($dataTable);
      }
      return $this->scalarFunction->getReturnType($types);
    }

    public function equals($o)
    {
      if ($o instanceof ScalarFunctionColumn)
      {
        return $this->columns == $o->columns && $this->scalarFunction == $o->scalarFunction;
      }
      return FALSE;
    }

    public function toQueryString()
    {
      $columnQueryStrings = array();
      foreach ($this->columns as $column)
      {
        $columnQueryStrings[] = $column->toQueryString();
      }
      return $this->scalarFunction->toQueryString($columnQueryStrings);
    }
  }
?>
