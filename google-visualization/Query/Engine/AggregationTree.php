<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\Util\Map;
  use Google\Visualization\DataSource\Util\Set;

  class AggregationTree
  {
    protected $root;
    protected $columnsToAggregate;
    protected $table;

    public function __construct(Set $columnsToAggregate, DataTable $table)
    {
      $this->columnsToAggregate = $columnsToAggregate;
      $this->table = $table;
      $this->root = new AggregationNode($columnsToAggregate, $table);
    }

    public function aggregate(AggregationPath $path, Map $valuesToAggregate)
    {
      $curNode = $this->root;
      $this->root->aggregate($valuesToAggregate);
      foreach ($path->getValues() as $curValue)
      {
        if (!$curNode->containsChild($curValue))
        {
          $curNode->addChild($curValue, $this->columnsToAggregate, $this->table);
        }
        $curNode = $curNode->getChild($curValue);
        $curNode->aggregate($valuesToAggregate);
      }
      return $this;
    }

    public function getNode(AggregationPath $path)
    {
      $curNode = $this->root;
      foreach ($path->getValues() as $curValue)
      {
        $curNode = $curNode->getChild($curValue);
      }
      return $curNode;
    }

    public function getPathsToLeaves()
    {
      $result = new Set();
      $this->getPathsToLeavesInternal($this->root, $result);
      return $result;
    }

    protected function getPathsToLeavesInternal(AggregationNode $node, Set $result)
    {
      $children = $node->getChildren();
      if ($children->isEmpty())
      {
        $result->add($this->getPathToNode($node));
      } else
      {
        foreach ($children->values() as $curNode)
        {
          self::getPathsToLeavesInternal($curNode, $result);
        }
      }
      return $this;
    }

    final protected static function getPathToNode(AggregationNode $node)
    {
      $result = new AggregationPath();
      $curNode = $node;
      while (!is_null($curNode->getValue()))
      {
        $result->add($curNode->getValue());
        $curNode = $curNode->getParent();
      }
      $result->reverse();
      return $result;
    }
  }
?>
