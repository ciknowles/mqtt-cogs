<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\Value\Value;
  use Google\Visualization\DataSource\Util\Map;
  use Google\Visualization\DataSource\Util\Set;

  class AggregationNode
  {
    protected $parent;
    protected $value;
    protected $columnAggregators;
    protected $children;

    public function __construct(Set $columnsToAggregate, DataTable $table)
    {
      $this->columnAggregators = new Map();
      $this->children = new Map();
      foreach ($columnsToAggregate as $columnId)
      {
        $this->columnAggregators->put($columnId, new ValueAggregator($table->getColumnDescription($columnId)->getType()));
      }
    }

    public function aggregate(Map $valuesByColumn)
    {
      foreach ($valuesByColumn->keySet() as $columnId)
      {
        $this->columnAggregators->get($columnId)->aggregate($valuesByColumn->get($columnId));
      }
      return $this;
    }

    public function getAggregationValue($columnId, $type)
    {
      $valuesAggregator = $this->columnAggregators->get($columnId);
      if (is_null($valuesAggregator))
      {
        throw new IllegalArgumentException("Column " . $columnId . " is not aggregated");
      }
      return $valuesAggregator->getValue($type);
    }

    public function getChild(Value $v)
    {
      $result = $this->children->get($v);
      if (is_null($result))
      {
        throw new NoSuchElementException("Value " . $v . " is not a child.");
      }
      return $result;
    }

    public function containsChild(Value $v)
    {
      return $this->children->containsKey($v);
    }

    public function addChild(Value $key, $columnsToAggregate, DataTable $table)
    {
      if ($this->children->containsKey($key))
      {
        throw new IllegalArgumentException("A child with key: " . $key . " already exists.");
      }
      $node = new AggregationNode($columnsToAggregate, $table);
      $node->parent = $this;
      $node->value = $key;
      $this->children->put($key, $node);
      return $this;
    }

    public function getChildren()
    {
      return $this->children;
    }

    public function getValue()
    {
      return $this->value;
    }

    public function getParent()
    {
      return $this->parent;
    }
  }
?>
