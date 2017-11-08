<?php
  namespace Google\Visualization\DataSource\Query;

  class QueryFormat
  {
    protected $columns;
    protected $columnPatterns;

    public function __construct()
    {
      $this->columns = array();
      $this->columnPatterns = array();
    }

    public function addPattern(AbstractColumn $column, $pattern)
    {
      if (in_array($column, $this->columns))
      {
        $messageToLogAndUser = "Column [" . $column . "] is specified more than once in FORMAT.";
        //$this->log->error($messageToLogAndUser);
        throw new InvalidQueryException($messageToLogAndUser);
      }
      $this->columns[] = $column;
      $this->columnPatterns[] = $pattern;
      return $this;
    }

    public function getPattern(AbstractColumn $column)
    {
      return $this->columnPatterns[array_search($column, $this->columns)];
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
        foreach ($col->getAllScalarFunctionColumns() as $innerCol)
        {
          if (!in_array($innerCol, $result))
          {
            $result[] = $innerCol;
          }
        }
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
  }
?>
