<?php
  namespace Google\Visualization\DataSource\Query;

  class ColumnSort
  {
    protected $order;

    public function __construct(AbstractColumn $column, $order)
    {
      $this->column = $column;
      $this->order = $order;
    }

    public function getColumn()
    {
      return $this->column;
    }

    public function getOrder()
    {
      return $this->order;
    }

    public function toQueryString()
    {
      return $this->column->toQueryString() . ($this->order == SortOrder::DESCENDING ? " DESC" : "");
    }
  }
?>
