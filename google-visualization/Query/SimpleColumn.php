<?php
  namespace Google\Visualization\DataSource\Query;

  use Google\Visualization\DataSource\DataTable\DataTable;

  class SimpleColumn extends AbstractColumn
  {
    protected $columnId;

    public function __construct($columnId)
    {
      $this->columnId = $columnId;
    }

    public function getColumnId()
    {
      return $this->columnId;
    }

    public function getId()
    {
      return $this->columnId;
    }

    public function __toString()
    {
      return $this->columnId;
    }

    public function getAllSimpleColumnIds()
    {
      return array($this->columnId);
    }

    public function equals($o)
    {
      if ($o instanceof SimpleColumn)
      {
        return $this->columnId == $o->columnId;
      }
      return FALSE;
    }

    public function toString() { return $this->columnId; }

    public function getAllAggregationColumns()
    {
      return array();
    }

    public function getAllSimpleColumns()
    {
      return array($this);
    }

    public function getAllScalarFunctionColumns()
    {
      return array();
    }

    public function getValueType(DataTable $dataTable)
    {
      return $dataTable->getColumnDescription($this->columnId)->getType();
    }

    public function toQueryString()
    {
      if (strpos($this->columnId, "`") !== FALSE)
      {
        throw new \RuntimeException("Column ID cannot contain backtick (`)");
      }
      return "`" . $this->columnId . "`";
    }
  }
?>
