<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\Query\AbstractColumn;
  use Google\Visualization\DataSource\Query\AggregationColumn;

  class ScalarFunctionColumnTitle
  {
    public static function getColumnDescriptionLabel(DataTable $originalTable, AbstractColumn $column)
    {
      $label = "";
      if ($originalTable->containsColumn($column->getId()))
      {
        $label .= $originalTable->getColumnDescription($column->getId())->getLabel();
      } else
      {
        if ($column instanceof AggregationColumn)
        {
          $label .= $originalTable->getColumnDescription($column->getAggregatedColumn()->getId())->getLabel();
        } else
        {
          $scalarFunctionColumn = $column;
          $columns = $scalarFunctionColumn->getColumns();
          $label .= $scalarFunctionColumn->getFunction()->getFunctionName() . "(";
          foreach ($columns as $abstractColumn)
          {
            $label .= self::getColumnDescriptionLabel($originalTable, $abstractColumn);
          }
          $label .= ")";
        }
      }
      return $label;
    }
  }
?>
