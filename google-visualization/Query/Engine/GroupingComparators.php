<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\Util\Comparator;

  class GroupingComparators
  {
    public static function valueListComparator()
    {
      $comparator = new Comparator();
      $comparator->compare = function($l1, $l2)
      {
        for ($i = 0; $i < min(count($l1), count($l2)); $i++)
        {
          $localCompare = $l1[$i]->compareTo($l2[$i]);
          if ($localCompare != 0)
          {
            return $localCompare;
          }
        }

        if ($i < count($l1))
        {
          $localCompare = 1;
        } else if ($i < count($l2))
        {
          $localCompare = -1;
        } else
        {
          $localCompare = 0;
        }
        return $localCompare;
      };
      return $comparator;
    }

    public static function rowTitleComparator()
    {
      $comparator = new Comparator();
      $comparator->compare = function(RowTitle $col1, RowTitle $col2)
      {
        return self::valueListComparator($col1->values, $col2->values);
      };
      return $comparator;
    }

    public static function getColumnTitleDynamicComparator($columnAggregations)
    {
      $comparator = new Comparator();
      $comparator->compare = function(ColumnTitle $col1, ColumnTitle $col2) use ($columnAggregations)
      {
        $listCompare = self::valueListComparator()->compare($col1->getValues(), $col2->getValues());
        if ($listCompare != 0)
        {
          return $listCompare;
        }
        $i1 = array_search($col1->aggregation, $columnAggregations);
        $i2 = array_search($col2->aggregation, $columnAggregations);
        return strcmp($i1, $i2);
      };
      return $comparator;
    }
  }
?>
