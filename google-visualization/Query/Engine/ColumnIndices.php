<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\Query\AbstractColumn;

  class ColumnIndices
  {
    protected $columns;
    protected $indices;

    public function __construct()
    {
      $this->columns = array();
      $this->indices = array();
    }

    public function put(AbstractColumn $col, $index)
    {
      $this->columns[] = $col;
      $this->indices[] = $index;
      return $this;
    }

    public function getColumnIndices(AbstractColumn $col)
    {
      $a = array();
      foreach (array_keys($this->columns, $col) as $i)
      {
        $a[] = $this->indices[$i];
      }
      return $a;
    }

    public function clear()
    {
      $this->columns = array();
      $this->indices = array();
      return $this;
    }
  }
?>
