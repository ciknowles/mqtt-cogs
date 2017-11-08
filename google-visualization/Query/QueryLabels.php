<?php
  namespace Google\Visualization\DataSource\Query;

  class QueryLabels
  {
    protected $columns;
    protected $labels;

    public function __construct()
    {
      $this->columns = array();
      $this->labels = array();
    }

    public function addLabel(AbstractColumn $column, $label)
    {
      if (in_array($column, $this->columns))
      {
        $messageToLogAndUser = "Column [" . $column->toString() . "] is specified mor than once in LABEL.";
        //$this->log->error($messageToLogAndUser);
        throw new InvalidQueryException($messageToLogAndUser);
      }
      $this->columns[] = $column;
      $this->labels[] = $label;
      return $this;
    }

    public function getLabel(AbstractColumn $column)
    {
      return $this->labels[array_search($column, $this->columns)];
    }

    public function getColumns()
    {
      return $this->columns;
    }

    public function getScalarFunctionColumns()
    {
      $result = array();
      foreach ($this->columns as $col)
      {
        $result = array_merge($result, $col->getAllScalarFunctionColumns());
      }
      return $result;
    }

    public function getAggregationColumns()
    {
      $result = array();
      foreach ($this->columns as $col)
      {
        $result = array_merge($result, $col->getAllAggregationColumns());
      }
      return $result;
    }

    public function toQueryString()
    {
      $stringList = array();
      foreach ($this->columns as $i => $col)
      {
        $stringList[] = $col->toQueryString() . " " . Query::stringToQueryStringLiteral($this->labels[$i]);
      }
      return implode(", ", $stringList);
    }
  }
?>
