<?php
  namespace Google\Visualization\DataSource\Query;

  class GenericColumnLookup implements ColumnLookup
  {
    protected $columns;
    protected $indices;

    public function __construct()
    {
      $this->columns = array();
      $this->indices = array();
    }

    public function clear()
    {
      $this->columns = array();
      $this->indices = array();
      return $this;
    }

    public function put(AbstractColumn $col, $index)
    {
      $this->columns[] = $col;
      $this->indices[] = $index;
      return $this;
    }

    public function getColumnIndex(AbstractColumn $column)
    {
      return $this->indices[array_search($column, $this->columns)];
    }

    public function containsColumn(AbstractColumn $column)
    {
      return in_array($column, $this->columns);
    }
  }
?>
