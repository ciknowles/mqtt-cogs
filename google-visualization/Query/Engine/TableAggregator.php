<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\Util\Map;
  use Google\Visualization\DataSource\Util\Set;

  class TableAggregator
  {
    protected $groupByColumns;
    protected $aggregateColumns;
    protected $tree;

    public function __construct($groupByColumns, Set $aggregateColumns, DataTable $table)
    {
      $this->groupByColumns = $groupByColumns;
      $this->aggregateColumns = $aggregateColumns;
      $this->tree = new AggregationTree($aggregateColumns, $table);

      foreach ($table->getRows() as $row)
      {
        $this->tree->aggregate($this->getRowPath($row, $table, count($groupByColumns) - 1), $this->getValuesToAggregate($row, $table));
      }
    }

    public function getRowPath(TableRow $row, DataTable $table, $depth)
    {
      $result = new AggregationPath();
      for ($i = 0; $i <= $depth; $i++)
      {
        $columnId = $this->groupByColumns[$i];
        $curValue = $row->getCell($table->getColumnIndex($columnId))->getValue();
        $result->add($curValue);
      }
      return $result;
    }

    public function getPathsToLeaves()
    {
      return $this->tree->getPathsToLeaves();
    }

    public function getValuesToAggregate(TableRow $row, DataTable $table)
    {
      $result = new Map();
      foreach ($this->aggregateColumns as $columnId)
      {
        $curValue = $row->getCell($table->getColumnIndex($columnId))->getValue();
        $result->put($columnId, $curValue);
      }
      return $result;
    }

    public function getAggregationValue(AggregationPath $path, $columnId, $type)
    {
      return $this->tree->getNode($path)->getAggregationValue($columnId, $type);
    }
  }
?>
