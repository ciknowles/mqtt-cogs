<?php
  namespace Google\Visualization\DataSource;

  use Google\Visualization\Datasource\Base\DataSourceException;
  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Query\AggregationColumn;
  use Google\Visualization\DataSource\Query\AggregationType;
  use Google\Visualization\DataSource\Query\Query;
  use Google\Visualization\DataSource\Query\QueryFormat;
  use Google\Visualization\DataSource\Query\QueryGroup;
  use Google\Visualization\DataSource\Query\QueryLabels;
  use Google\Visualization\DataSource\Query\QuerySelection;
  use Google\Visualization\DataSource\Query\SimpleColumn;

  final class QuerySplitter
  {
    public static function splitQuery(Query $query, $capabilities)
    {
      switch ($capabilities)
      {
        case Capabilities::ALL:
          return self::splitAll($query);
        case Capabilities::NONE:
          return self::splitNone($query);
        case Capabilities::SQL:
          return self::splitSQL($query);
        case Capabilities::SORT_AND_PAGINATION:
          return self::splitSortAndPagination($query);
        case Capabilities::SELECT:
          return self::splitSelect($query);
      }
      //$log->error("Capabilities not supported.");
      throw new DataSourceException(ReasonType::NOT_SUPPORTED, "Capabilities not supported.");
    }

    protected static function splitAll(Query $query)
    {
      $dataSourceQuery = new Query();
      $dataSourceQuery->copyFrom($query);
      $completionQuery = new Query();
      return new QueryPair($dataSourceQuery, $completionQuery);
    }

    protected static function splitNone(Query $query)
    {
      $completionQuery = new Query();
      $completionQuery->copyFrom($query);
      return new QueryPair(NULL, $completionQuery);
    }

    protected static function splitSQL(Query $query)
    {
      $dataSourceQuery = new Query();
      $completionQuery = new Query();

      if ($query->hasPivot())
      {
        $pivotColumns = $query->getPivot()->getColumns();

        $dataSourceQuery->copyFrom($query);
        $dataSourceQuery->setPivot(NULL);
        $dataSourceQuery->setSort(NULL);
        $dataSourceQuery->setOptions(NULL);
        $dataSourceQuery->setLabels(NULL);
        $dataSourceQuery->setUserFormatOptions(NULL);

        try
        {
          $dataSourceQuery->setRowLimit(-1);
          $dataSourceQuery->setRowOffset(0);
        } catch (InvalidQueryException $e) {}

        $newGroupColumns = array();
        $newSelectionColumns = array();
        if ($dataSourceQuery->hasGroup())
        {
          $newGroupColumns = array_merge($newGroupColumns, $dataSourceQuery->getGroup->getColumns());
        }
        $newGroupColumns = array_merge($newGroupColumns, $pivotColumns);
        if ($dataSourceQuery->hasSelection())
        {
          $newSelectionColumns = array_merge($newSelectionColumns, $dataSourceQuery->getSelection()->getColumns());
        }
        $newSelectionColumns = array_merge($newSelectionColumns, $pivotColumns);
        $group = new QueryGroup();
        foreach ($newGroupColumns as $col)
        {
          $group->addColumn($col);
        }
        $dataSourceQuery->setGroup($group);
        $selection = new QuerySelection();
        foreach ($newSelectionColumns as $col)
        {
          $selection->addColumn($col);
        }
        $dataSourceQuery->setSelection($selection);

        $completionQuery->copyFrom($query);
        $completionQuery->setFilter(NULL);

        $completionSelection = new QuerySelection();
        $originalSelectedColumns = $query->getSelection()->getColumns();
        foreach ($originalSelectedColumns as $col)
        {
          if (in_array($col, $query->getGroup()->getColumns()))
          {
            $completionSelection->addColumn($col);
          } else
          {
            $completionSelection->addColumn(new AggregationColumn(new SimpleColumn($col->getId()), AggregationType::MIN));
          }
        }

        $completionQuery->setSelection($completionSelection);
      } else
      {
        $dataSourceQuery->copyFrom($query);
        $dataSourceQuery->setOptions(NULL);
        $dataSourceQuery->setOptions($query->getOptions());
        try
        {
          if ($query->hasLabels())
          {
            $dataSourceQuery->setLabels(NULL);
            $labels = $query->getLabels();
            $newLabels = new QueryLabels();
            foreach ($labels->getColumns() as $column)
            {
              $newLabels->addLabel(new SimpleColumn($column->getId()), $labels->getLabel($column));
            }
            $completionQuery->setLabels($newLabels);
          }
          if ($query->hasUserFormatOptions())
          {
            $dataSourceQuery->setUserFormatOptions(NULL);
            $formats = $query->getUserFormatOptions();
            $newFormats = new QueryFormat();
            foreach ($formats->getColumns() as $column)
            {
              $newFormats->addPattern(new SimpleColumn($column->getId()), $formats->getPattern($column));
            }
            $completionQuery->setUserFormatOptions($newFormats);
          }
        } catch (InvalidQueryException $e) {}
      }
      return new QueryPair($dataSourceQuery, $completionQuery);
    }

    protected static function splitSortAndPagination(Query $query)
    {
      if (count($query->getAllScalarFunctionsColumns()) > 0)
      {
        $completionQuery = new Query();
        $completionQuery->copyFrom($query);
        return new QueryPair(new Query(), $completionQuery);
      }

      $dataSourceQuery = new Query();
      $completionQuery = new Query();
      if ($query->hasFilter() || $query->hasGroup() || $query->hasPivot())
      {
        $completionQuery->copyFrom($query);
      } else
      {
        $dataSourceQuery->setSort($query->getSort());
        if ($query->hasRowSkipping())
        {
          $completionQuery->copyRowSkipping($query);
          $completionQuery->copyRowLimit($query);
          $completionQuery->copyRowOffset($query);
        } else
        {
          $dataSourceQuery->copyRowLimit($query);
          $dataSourceQuery->copyRowOffset($query);
        }

        $completionQuery->setSelection($query->getSelection());
        $completionQuery->setOptions($query->getOptions());
        $completionQuery->setLabels($query->getLabels());
        $completionQuery->setUserFormatOptions($query->getUserFormatOptions());
      }
      return new QueryPair($dataSourceQuery, $completionQuery);
    }

    protected static function splitSelect(Query $query)
    {
      $dataSourceQuery = new Query();
      $completionQuery = new Query();
      if (!is_null($query->getSelection()))
      {
        $selection = new QuerySelection();
        foreach ($query->getAllColumnIds() as $simpleColumnId)
        {
          $selection->addColumn(new SimpleColumn($simpleColumnId));
        }
        $dataSourceQuery->setSelection($selection);
      }
      $completionQuery->copyFrom($query);
      return new QueryPair($dataSourceQuery, $completionQuery);
    }
  }
?>
