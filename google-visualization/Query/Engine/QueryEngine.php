<?php
  namespace Google\Visualization\DataSource\Query\Engine;

  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\Warning;
  use Google\Visualization\DataSource\DataTable\ColumnDescription;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableCell;
  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\DataTable\ValueFormatter;
  use Google\Visualization\DataSource\Query\DataTableColumnLookup;
  use Google\Visualization\DataSource\Query\GenericColumnLookup;
  use Google\Visualization\DataSource\Query\Query;
  use Google\Visualization\DataSource\Query\ScalarFunctionColumn;
  use Google\Visualization\DataSource\Query\SimpleColumn;
  use Google\Visualization\DataSource\Util\Map;
  use Google\Visualization\DataSource\Util\Set;
  use Google\Visualization\DataSource\Util\TreeMap;
  use Google\Visualization\DataSource\Util\TreeSet;

  final class QueryEngine
  {
    protected static function createDataTable($groupByColumnIds, Set $columnTitles, DataTable $original, $scalarFunctionColumnTitles)
    {
      $result = new DataTable();
      foreach ($groupByColumnIds as $groupById)
      {
        $result->addColumn($original->getColumnDescription($groupById));
      }
      foreach ($columnTitles as $colTitle)
      {
        $result->addColumn($colTitle->createColumnDescription($original));
      }
      foreach ($scalarFunctionColumnTitles as $scalarFunctionColumnTitle)
      {
        $result->addColumn($scalarFunctionColumnTitle->createColumnDescription($original));
      }
      return $result;
    }

    public static function executeQuery(Query $query, DataTable $table, $locale)
    {
      $columnIndices = new ColumnIndices();
      $columnDescriptions = $table->getColumnDescriptions();
      for ($i = 0; $i < count($columnDescriptions); $i++)
      {
        $columnIndices->put(new SimpleColumn($columnDescriptions[$i]->getId()), $i);
      }
      $columnLookupsValueLists = array();
      $columnLookupsColumnLookups = array();
      $columnLookups = new TreeMap(GroupingComparators::valueListComparator());
      try
      {
        $table = self::performFilter($table, $query);
        $table = self::performGroupingAndPivoting($table, $query, $columnIndices, $columnLookups);
        $table = self::performSort($table, $query, $locale);
        $table = self::performSkipping($table, $query);
        $table = self::performPagination($table, $query);
        $table = self::performSelection($table, $query, $columnIndices, $columnLookups);
        $table = self::performLabels($table, $query, $columnIndices);
        $table = self::performFormatting($table, $query, $columnIndices, $locale);
      } catch (TypeMismatchException $e)
      {
        // Should not happen
      }
      return $table;
    }

    protected static function performSkipping(DataTable $table, Query $query)
    {
      $rowSkipping = $query->getRowSkipping();

      if ($rowSkipping <= 1)
      {
        return $table;
      }

      $numRows = $table->getNumberOfRows();
      $relevantRows = array();
      for ($rowIndex = 0; $rowIndex < $numRows; $rowIndex += $rowSkipping)
      {
        $relevantRows[] = $table->getRow($rowIndex);
      }

      $newTable = new DataTable();
      $newTable->addColumns($table->getColumnDescriptions());
      $newTable->addRows($relevantRows);

      return $newTable;
    }

    protected static function performPagination(DataTable $table, Query $query)
    {
      $rowOffset = $query->getRowOffset();
      $rowLimit = $query->getRowLimit();

      if (($rowLimit == -1 || count($table->getRows()) <= $rowLimit) && $rowOffset == 0)
      {
        return $table;
      }
      $numRows = $table->getNumberOfRows();
      $fromIndex = max(0, $rowOffset);
      $toIndex = $rowLimit == -1 ? $numRows : min($numRows, $rowOffset + $rowLimit);

      $relevantRows = array_slice($table->getRows(), $fromIndex, $toIndex - $fromIndex);
      $newTable = new DataTable();
      $newTable->addColumns($table->getColumnDescriptions());
      $newTable->addRows($relevantRows);

      if ($toIndex < $numRows)
      {
        $warning = new Warning(ReasonType::DATA_TRUNCATED, "Data has been truncated due to user request (LIMIT in query)");
        $newTable->addWarning($warning);
      }

      return $newTable;
    }

    protected static function performSort(DataTable $table, Query $query, $locale)
    {
      if (!$query->hasSort())
      {
        return $table;
      }
      $sortBy = $query->getSort();

      $columnLookup = new DataTableColumnLookup($table);
      $comparator = new TableRowComparator($sortBy, $locale, $columnLookup);
      $rows = $table->getRows();
      usort($rows, array($comparator, "compare"));
      $table->setRows($rows);
      return $table;
    }

    protected static function performFilter(DataTable $table, Query $query)
    {
      if (!$query->hasFilter())
      {
        return $table;
      }

      $newRowList = array();
      $filter = $query->getFilter();
      foreach ($table->getRows() as $inputRow)
      {
        if ($filter->isMatch($table, $inputRow))
        {
          $newRowList[] = $inputRow;
        }
      }
      $table->setRows($newRowList);
      return $table;
    }

    protected static function performSelection(DataTable $table, Query $query, ColumnIndices $columnIndices, TreeMap $columnLookups)
    {
      if (!$query->hasSelection())
      {
        return $table;
      }
      $oldColumnIndices = clone $columnIndices;

      $selectedColumns = $query->getSelection()->getColumns();
      $selectedIndices = array();

      $oldColumnDescriptions = $table->getColumnDescriptions();
      $newColumnDescriptions = array();
      $columnIndices->clear();
      $currIndex = 0;
      foreach ($selectedColumns as $col)
      {
        $colIndices = $oldColumnIndices->getColumnIndices($col);
        $selectedIndices = array_merge($selectedIndices, $colIndices);
        if (count($colIndices) == 0)
        {
          $newColumnDescriptions[] = new ColumnDescription(
            $col->getId(),
            $col->getValueType($table),
            ScalarFunctionColumnTitle::getColumnDescriptionLabel($table, $col)
          );
          $columnIndices->put($col, $currIndex++);
        } else
        {
          foreach ($colIndices as $colIndex)
          {
            $newColumnDescriptions[] = $oldColumnDescriptions[$colIndex];
            $columnIndices->put($col, $currIndex++);
          }
        }
      }

      $result = new DataTable();
      $result->addColumns($newColumnDescriptions);

      foreach ($table->getRows() as $sourceRow)
      {
        $newRow = new TableRow();
        foreach ($selectedColumns as $col)
        {
          $wasFound = FALSE;
          $pivotValuesSet = $columnLookups->keySet();
          foreach ($pivotValuesSet as $values)
          {
            if ($columnLookups->get($values)->containsColumn($col) && ((count($col->getAllAggregationColumns()) != 0) || !$wasFound))
            {
              $wasFound = TRUE;
              $newRow->addCell($sourceRow->getCell($columnLookups->get($values)->getColumnIndex($col)));
            }
          }
          if (!$wasFound)
          {
            $lookup = new DataTableColumnLookup($table);
            $newRow->addCell($col->getCell($lookup, $sourceRow));
          }
        }
        $result->addRow($newRow);
      }
      return $result;
    }

    protected static function queryHasAggregation(Query $query)
    {
      return $query->hasSelection() && count($query->getSelection()->getAggregationColumns()) > 0;
    }

    protected static function performGroupingAndPivoting(DataTable $table, Query $query, ColumnIndices $columnIndices, TreeMap $columnLookups)
    {
      if (!self::queryHasAggregation($query) || $table->getNumberOfRows() == 0)
      {
        return $table;
      }
      $group = $query->getGroup();
      $pivot = $query->getPivot();
      $selection = $query->getSelection();

      $groupByIds = array();
      if (!is_null($group))
      {
        $groupByIds = $group->getColumnIds();
      }

      $pivotByIds = array();
      if (!is_null($pivot))
      {
        $pivotByIds = $pivot->getColumnIds();
      }

      $groupAndPivotIds = array_merge($groupByIds, $pivotByIds);

      $tmpColumnAggregations = $selection->getAggregationColumns();
      $selectedScalarFunctionColumns = $selection->getScalarFunctionColumns();

      $columnAggregations = array();
      foreach ($tmpColumnAggregations as $aggCol)
      {
        if (!in_array($aggCol, $columnAggregations))
        {
          $columnAggregations[] = $aggCol;
        }
      }

      $aggregationIds = array();
      foreach ($columnAggregations as $col)
      {
        $aggregationIds[] = $col->getAggregatedColumn()->getId();
      }

      $groupAndPivotScalarFunctionColumns = array();
      if (!is_null($group))
      {
        $groupAndPivotScalarFunctionColumns = array_merge($groupAndPivotScalarFunctionColumns, $group->getScalarFunctionColumns());
      }
      if (!is_null($pivot))
      {
        $groupAndPivotScalarFunctionColumns = array_merge($groupAndPivotScalarFunctionColumns, $pivot->getScalarFunctionColumns());
      }

      $newColumnDescriptions = $table->getColumnDescriptions();

      foreach ($groupAndPivotScalarFunctionColumns as $column)
      {
        $newColumnDescriptions[] = new ColumnDescription($column->getId(), $column->getValueType($table), ScalarFunctionColumnTitle::getColumnDescriptionLabel($table, $column));
      }

      $tempTable = new DataTable();
      $tempTable->addColumns($newColumnDescriptions);

      $lookup = new DataTableColumnLookup($table);
      foreach ($table->getRows() as $sourceRow)
      {
        $newRow = new TableRow();
        foreach ($sourceRow->getCells() as $sourceCell)
        {
          $newRow->addCell($sourceCell);
        }
        foreach ($groupAndPivotScalarFunctionColumns as $column)
        {
          $newRow->addCell(new TableCell($column->getValue($lookup, $sourceRow)));
        }
        $tempTable->addRow($newRow);
      }
      $table = $tempTable;

      $aggregator = new TableAggregator($groupAndPivotIds, new Set($aggregationIds), $table);
      $paths = $aggregator->getPathsToLeaves();

      $rowTitles = new TreeSet(GroupingComparators::rowTitleComparator());
      $columnTitles = new TreeSet(GroupingComparators::getColumnTitleDynamicComparator($columnAggregations));

      $pivotValuesSet = new TreeSet(GroupingComparators::valueListComparator());
      $metaTable = new MetaTable();
      foreach ($columnAggregations as $columnAggregation)
      {
        foreach ($paths as $path)
        {
          $originalValues = $path->getValues();

          $rowValues = array_slice($originalValues, 0, count($groupByIds));
          $rowTitle = new RowTitle($rowValues);
          $rowTitles->add($rowTitle);

          $columnValues = array_slice($originalValues, count($groupByIds));
          $pivotValuesSet->add($columnValues);

          $columnTitle = new ColumnTitle($columnValues, $columnAggregation, count($columnAggregations) > 1);
          $columnTitles->add($columnTitle);
          $metaTable->put($rowTitle, $columnTitle, new TableCell($aggregator->getAggregationValue($path, $columnAggregation->getAggregatedColumn()->getId(), $columnAggregation->getAggregationType())));
        }
      }

      $scalarFunctionColumnTitles = array();
      foreach ($selectedScalarFunctionColumns as $scalarFunctionColumn)
      {
        if (count($scalarFunctionColumn->getAllAggregationColumn()) != 0)
        {
          foreach ($pivotValuesSet as $columnValues)
          {
            $scalarFunctionColumnTitles[] = new ScalarFunctionColumnTitle($columnValues, $scalarFunctionColumn);
          }
        }
      }
      $result = self::createDataTable($groupByIds, $columnTitles, $table, $scalarFunctionColumnTitles);
      $colDescs = $result->getColumnDescriptions();

      $columnIndices->clear();
      $columnIndex = 0;
      if (!is_null($group))
      {
        $emptyListOfValues = array();
        $columnLookups->put($emptyListOfValues, new GenericColumnLookup());
        foreach ($group->getColumns() as $column)
        {
          $columnIndices->put($column, $columnIndex);
          if (!($column instanceof ScalarFunctionColumn))
          {
            $columnLookups->get($emptyListOfValues)->put($column, $columnIndex);
            foreach ($pivotValuesSet as $columnValues)
            {
              if (!$columnLookups->containsKey($columnValues))
              {
                $columnLookups->put($columnValues, new GenericColumnLookup());
              }
              $columnLookups->get($columnValues)->put($column, $columnIndex);
            }
          }
          $columnIndex++;
        }
      }

      foreach ($columnTitles as $title)
      {
        $columnIndices->put($title->aggregation, $columnIndex);
        $values = $title->getValues();
        if (!$columnLookups->containsKey($values))
        {
          $columnLookups->put($values, new GenericColumnLookup());
        }
        $columnLookups->get($values)->put($title->aggregation, $columnIndex);
        $columnIndex++;
      }

      foreach ($rowTitles as $rowTitle)
      {
        $curRow = new TableRow();
        foreach ($rowTitle->values as $v)
        {
          $curRow->addCell(new TableCell($v));
        }
        $rowData = $metaTable->getRow($rowTitle);
        $i = 0;
        foreach ($columnTitles as $colTitle)
        {
          $cell = $rowData->get($colTitle);
          $curRow->addCell(!is_null($cell) ? $cell : new TableCell(Value::getNullValueFromValueType($colDescs[$i + count($rowTitle->values)]).getType()));
          $i++;
        }
        foreach ($scalarFunctionColumnTitles as $columnTitle)
        {
          $curRow->addCell(new TableCell($columnTitle->scalarFunctionColumn->getValue($columnLookups->get($columnTitle->getValues()), $curRow)));
        }
        $result->addRow($curRow);
      }

      foreach ($scalarFunctionColumnTitles as $scalarFunctionColumnTitle)
      {
        $columnIndices->put($scalarFunctionColumnTitle->scalarFunctionColumn, $columnIndex);
        $values = $scalarFunctionColumnTitle->getValue();
        if (!$columnLookups->containsKey($values))
        {
          $columnLookups->put($values, new GenericColumnLookup());
        }
        $columnLookups->get($values)->put($scalarFunctionColumnTitle->scalarFunctionColumn, $columnIndex);
        $columnIndex++;
      }

      return $result;
    }

    protected static function performLabels(DataTable $table, Query $query, ColumnIndices $columnIndices)
    {
      if (!$query->hasLabels())
      {
        return $table;
      }

      $labels = $query->getLabels();

      $columnDescriptions = $table->getColumnDescriptions();

      foreach ($labels->getColumns() as $column)
      {
        $label = $labels->getLabel($column);
        $indices = $columnIndices->getColumnIndices($column);
        if (count($indices) == 1)
        {
          $columnDescriptions[$indices[0]]->setLabel($label);
        } else
        {
          $columnId = $column->getId();
          foreach ($indices as $i)
          {
            $colDesc = $columnDescriptions[$i];
            $colDescId = $colDesc->getId();
            $specificLabel = substr($colDescId, 0, strlen($colDescId) - strlen($columnId)) . $label;
            $columnDescriptions[$i]->setLabel($specificLabel);
          }
        }
      }
      $table->setColumns($columnDescriptions);
      return $table;
    }

    protected static function performFormatting(DataTable $table, Query $query, ColumnIndices $columnIndices, $locale)
    {
      if (!$query->hasUserFormatOptions())
      {
        return $table;
      }

      $queryFormat = $query->getUserFormatOptions();
      $columnDescriptions = $table->getColumnDescriptions();
      $indexes = array();
      $formatters = array();
      foreach ($queryFormat->getColumns() as $col)
      {
        $pattern = $queryFormat->getPattern($col);
        $indices = $columnIndices->getColumnIndices($col);
        $allSucceeded = TRUE;
        foreach ($indices as $i)
        {
          $colDesc = $columnDescriptions[$i];
          $f = ValueFormatter::createFromPattern($colDesc->getType(), $pattern, $locale);
          if (is_null($f))
          {
            $allSucceeded = FALSE;
          } else
          {
            $indexes[] = $i;
            $formatters[] = $f;
            $columnDescriptions[$i]->setPattern($pattern);
          }
        }
        if (!$allSucceeded)
        {
          $warning = new Warning(ReasonType::ILLEGAL_FORMATTING_PATTERNS, "Illegal formatting pattern: " . $pattern ." requested on column: " . $col->getId());
          $table->addWarning($warning);
        }
      }
      $newTable = new DataTable();
      foreach ($table->getWarnings() as $warning)
      {
        $newTable->addWarning($warning);
      }
      $newTable->setColumns($columnDescriptions);

      foreach ($table->getRows() as $row)
      {
        for ($i = 0; $i < count($indexes); $i++)
        {
          $col = $indexes[$i];
          $cell = $row->getCell($col);
          $value = $cell->getValue();
          $formatter = $formatters[$i];
          $formattedValue = $formatter->format($value);
          $cell->setFormattedValue($formattedValue);
          $row->setCell($col, $cell);
        }
        $newTable->addRow($row);
      }
      return $newTable;
    }
  }
?>
