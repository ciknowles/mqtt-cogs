<?php
  namespace Google\Visualization\DataSource\Util;

  use mysqli;
  use mysqli_sql_exception;
  use mysqli_result;
  use RuntimeException;
  use Google\Visualization\DataSource\Base\DataSourceException;
  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\TypeMismatchException;
  use Google\Visualization\DataSource\DataTable\ColumnDescription;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableCell;
  use Google\Visualization\DataSource\DataTable\TableRow;
  use Google\Visualization\DataSource\DataTable\Value\BooleanValue;
  use Google\Visualization\DataSource\DataTable\Value\DateValue;
  use Google\Visualization\DataSource\DataTable\Value\DateTimeValue;
  use Google\Visualization\DataSource\DataTable\Value\NumberValue;
  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\TimeOfDayValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;
  use Google\Visualization\DataSource\Query\AbstractColumn;
  use Google\Visualization\DataSource\Query\AggregationColumn;
  use Google\Visualization\DataSource\Query\AggregationType;
  use Google\Visualization\DataSource\Query\ColumnColumnFilter;
  use Google\Visualization\DataSource\Query\ColumnIsNullFilter;
  use Google\Visualization\DataSource\Query\ComparisonFilter;
  use Google\Visualization\DataSource\Query\CompoundFilter;
  use Google\Visualization\DataSource\Query\NegationFilter;
  use Google\Visualization\DataSource\Query\Query;
  use Google\Visualization\DataSource\Query\QueryFilter;
  use Google\Visualization\DataSource\Query\QuerySelection;
  use Google\Visualization\DataSource\Query\SimpleColumn;
  use Google\Visualization\DataSource\Query\SortOrder;
  use Google\Visualization\DataSource\Query\ScalarFunctionColumn;
  use Google\Visualization\DataSource\Query\ScalarFunction\TimeComponent;

  class MysqliDataSourceHelper
  {
    public static function executeQuery(Query $query, mysqli $db, $tableName)
    {
      mysqli_report(MYSQLI_REPORT_STRICT);

      $queryString = static::buildSqlQuery($query, $tableName);
      $columnIdsList = NULL;
      if ($query->hasSelection())
      {
        $columnIdsList = self::getColumnIdsList($query->getSelection());
      }

      try
      {
        if (($result = $db->query($queryString)) === FALSE)
        {
          throw new mysqli_sql_exception($db->error);
        }
        return self::buildTable($stmt, $columnIdsList);
      } catch (mysqli_sql_exception $e)
      {
        $messageToUser = "Failed to execute MySQL query. MySQL error message: " . $e->getMessage();
        throw new DataSourceException(ReasonType::INTERNAL_ERROR, $messageToUser);
      }
    }

    public static function buildTable(mysqli_result $result, $columnIdsList = NULL)
    {
      $table = self::buildColumns($result, $columnIdsList);
      $table = self::buildRows($table, $result);
      return $table;
    }

    protected static function getColumnIdsList(QuerySelection $selection)
    {
      $columnIds = array();
      foreach ($selection->getColumns() as $col)
      {
        $columnIds[] = $col->getId();
      }
      return $columnIds;
    }

    protected static function buildColumns(mysqli_result $result, $columnIdsList)
    {
      $dataTable = new DataTable();
      foreach ($result->fetch_fields() as $field)
      {
        $id = is_null($columnIdsList) ? $field->name : $columnIdsList[$i];
        $valueType = self::fieldToValueType($field);
        $columnDescription = new ColumnDescription($id, $valueType, $field->name);
        $dataTable->addColumn($columnDescription);
      }
      return $dataTable;
    }

    protected static function buildRows(DataTable $dataTable, mysqli_result $result)
    {
      $columnDescriptionList = $dataTable->getColumnDescriptions();
      $numOfCols = $dataTable->getNumberOfColumns();

      $columnsTypeArray = array();
      foreach ($columnDescriptionList as $col)
      {
        $columnsTypeArray[] = $col->getType();
      }

      while ($row = $result->fetch_row())
      {
        $tableRow = new TableRow();
        for ($c = 0; $c < $numOfCols; $c++)
        {
          $tableRow->addCell(self::buildTableCell($row, $columnsTypeArray[$c], $c));
        }
        try
        {
          $dataTable->addRow($tableRow);
        } catch (TypeMismatchException $e) {}
      }
      return $dataTable;
    }

    protected static function buildTableCell($row, $valueType, $column)
    {
      switch ($valueType)
      {
        case ValueType::BOOLEAN:
          $value = new BooleanValue($row[$column]);
          break;
        case ValueType::NUMBER:
          $value = new NumberValue($row[$column]);
          break;
        case ValueType::DATE:
          $value = new DateValue($row[$column]);
          break;
        case ValueType::DATETIME:
          $value = new DateTimeValue($row[$column]);
          break;
        case ValueType::TIMEOFDAY:
          $value = new TimeOfDayValue($row[$column]);
          break;
        default:
          $value = new TextValue($row[$column]);
      }
      return new TableCell($value);
    }

    protected static function buildSqlQuery(Query $query, $tableName)
    {
      $queryString = self::buildSelectClause($query);
      $queryString .= self::buildFromClause($query, $tableName);
      $queryString .= self::buildWhereClause($query);
      $queryString .= self::buildGroupByClause($query);
      $queryString .= self::buildOrderByClause($query);
      $queryString .= self::buildLimitAndOffsetClause($query);
      if ($query->hasRowSkipping())
      {
        $queryString = "
SELECT * FROM
( SELECT `t`.*, @row := @row + 1 AS `rownum` FROM (SELECT @row := 0) AS `r`, (" . $queryString. ") AS `t`
) AS `ranked` WHERE `rownum` % " . $query->getRowSkipping() . " = 1";
      }
      return $queryString;
    }

    protected static function buildSelectClause(Query $query)
    {
      $selectClause = "SELECT ";

      if (!$query->hasSelection())
      {
        $selectClause .= "*";
        return $selectClause;
      }
      $columns = $query->getSelection()->getColumns();
      $colIds = array();
      foreach ($columns as $col)
      {
        $colIds[]= self::getColumnId($col);
      }
      $selectClause .= implode(", ", $colIds);
      return $selectClause;
    }

    protected static function buildFromClause(Query $query, $tableName)
    {
      if (empty($tableName))
      {
        //$log->error("No table name provided.");
        throw new DataSourceException(ReasonType::OTHER, "No table name provided.");
      }
      $fromClause = " FROM `";
      $fromClause .= $tableName;
      $fromClause .= "`";
      return $fromClause;
    }

    protected static function buildWhereClause(Query $query)
    {
      if (!$query->hasFilter())
      {
        return;
      }
      return " WHERE " . self::buildWhereClauseRecursively($query->getFilter()) . " ";
    }

    protected static function buildWhereClauseRecursively(QueryFilter $queryFilter)
    {
      $whereClause = "";
      if ($queryFilter instanceof ColumnIsNullFilter)
      {
        $whereClause .= self::buildWhereClauseForIsNullFilter($queryFilter);
      } else if ($queryFilter instanceof ComparisonFilter)
      {
        $whereClause .= self::buildWhereClauseForComparisonFilter($queryFilter);
      } else if ($queryFilter instanceof NegationFilter)
      {
        $whereClause .= "(NOT " . self::buildWhereClauseRecursively($queryFilter->getSubFilter()) . ")";
      } else // CompoundFilter
      {
        $compoundFilter = $queryFilter;
        $numberOfSubFilters = count($compoundFilter->getSubFilters());
        if ($numberOfSubFilters == 0)
        {
          if ($compoundFilter->getOperator() == CompoundFilter::LOGICAL_OPERATOR_AND)
          {
            $whereClause .= "true";
          } else // OR
          {
            $whereClause .= "false";
          }
        } else
        {
          $filterComponents = array();
          foreach ($compoundFilter->getSubFilters() as $filter)
          {
            $filterComponents[] = self::buildWhereClauseRecursively($filter);
          }
          $logicalOperator = self::getSqlLogicalOperator($compoundFilter->getOperator());
          $whereClause .= "(" . implode(" " . $logicalOperator . " ", $filterComponents) . ")";
        }
      }
      return $whereClause;
    }

    protected static function buildWhereClauseForIsNullFilter(ColumnIsNullFilter $filter)
    {
      return "(" . self::getColumnId($filter->getColumn()) . " IS NULL)";
    }

    protected static function buildWhereClauseForComparisonFilter(ComparisonFilter $filter)
    {
      $first = "";
      $second = "";

      if ($filter instanceof ColumnColumnFilter)
      {
        $first .= self::getColumnId($filter->getFirstColumn());
        $second .= self::getColumnId($filter->getSecondColumn());
      } else // ColumnValueFilter
      {
        $first .= self::getColumnId($filter->getColumn());
        $second .= $filter->getValue();
        if ($filter->getValue()->getType() == ValueType::TEXT
          || $filter->getValue()->getType() == ValueType::DATE
          || $filter->getValue()->getType() == ValueType::DATETIME
          || $filter->getValue()->getType() == ValueType::TIMEOFDAY)
        {
          $second = "\"" . str_replace("\"", "\\\"", $second) . "\"";
        }
      }
      return self::buildWhereClauseFromRightAndLeftParts($first, $second, $filter->getOperator());
    }

    protected static function getSqlLogicalOperator($operator)
    {
      switch ($operator)
      {
        case CompoundFilter::LOGICAL_OPERATOR_AND:
          return "AND";
        case CompoundFilter::LOGICAL_OPERATOR_OR:
          return "OR";
        default:
          throw new RuntimeException("Logical operator was not found: " . $operator);
      }
    }

    protected static function buildWhereClauseFromRightAndLeftParts($value1, $value2, $operator)
    {
      switch ($operator)
      {
        case ComparisonFilter::OPERATOR_EQ:
          $clause = $value1 . "=" . $value2;
          break;
        case ComparisonFilter::OPERATOR_NE1:
        case ComparisonFilter::OPERATOR_NE2:
          $clause = $value1 . "!=" . $value2;
          break;
        case ComparisonFilter::OPERATOR_LT:
          $clause = $value1 . "<" . $value2;
          break;
        case ComparisonFilter::OPERATOR_GT:
          $clause = $value1 . ">" . $value2;
          break;
        case ComparisonFilter::OPERATOR_LE:
          $clause = $value1 . "<=" . $value2;
          break;
        case ComparisonFilter::OPERATOR_GE:
          $clause = $value1 . ">=" . $value2;
          break;
        case ComparisonFilter::OPERATOR_CONTAINS:
          $value2 = str_replace("\"", "", $value2);
          $clause = $value1 . " LIKE \"%" . $value2 . "%\"";
          break;
        case ComparisonFilter::OPERATOR_STARTS_WITH:
          $value2 = str_replace("\"", "", $value2);
          $clause = $value1 . " LIKE \"" . $value2 . "%\"";
          break;
        case ComparisonFilter::OPERATOR_ENDS_WITH:
          $value2 = str_replace("\"", "", $value2);
          $clause = $value1 . " LIKE \"%" . $value2 . "\"";
          break;
        case ComparisonFilter::OPERATOR_MATCHES:
          $clause = $value1 . " REGEXP " . $value2;
          break;
        case ComparisonFilter::OPERATOR_LIKE:
          $clause = $value1 . " LIKE " . $value2;
          break;
        default:
          throw new RuntimeException("Operator was not found: ". $operator);
      }
      $clause = "(" . $clause . ")";
      return $clause;
    }

    protected static function buildGroupByClause(Query $query)
    {
      if (!$query->hasGroup())
      {
        return;
      }
      $groupByClause = " GROUP BY ";
      $queryGroup = $query->getGroup();
      $newColumnIds = array();
      foreach ($queryGroup->getColumns() as $groupColumn)
      {
        $newColumnIds[] = self::getColumnId($groupColumn);
      }
      $groupByClause .= implode(",", $newColumnIds);
      return $groupByClause;
    }

    protected static function buildOrderByClause(Query $query)
    {
      if (!$query->hasSort())
      {
        return;
      }
      $orderByClause = " ORDER BY ";
      $querySort = $query->getSort();
      $sortColumns = $querySort->getSortColumns();
      $columns = array();
      foreach ($sortColumns as $columnSort)
      {
        $column = self::getColumnId($columnSort->getColumn());
        if ($columnSort->getOrder() == SortOrder::DESCENDING)
        {
          $column .= " DESC";
        }
        $columns[] = $column;
      }
      $orderByClause .= implode(",", $columns);
      return $orderByClause;
    }

    protected static function buildLimitAndOffsetClause(Query $query)
    {
      $limitAndOffsetClause = "";
      if ($query->hasRowLimit())
      {
        $limitAndOffsetClause .= " LIMIT " . $query->getRowLimit();
      }
      if ($query->hasRowOffset())
      {
        $limitAndOffsetClause .= " OFFSET " . $query->getRowOffset();
      }
      return $limitAndOffsetClause;
    }

    protected static function getColumnId(AbstractColumn $abstractColumn)
    {
      if ($abstractColumn instanceof SimpleColumn)
      {
        $columnId = "`" . $abstractColumn->getId() . "`";
      } else if ($abstractColumn instanceof AggregationColumn)
      {
        $columnId = self::getAggregationFunction($abstractColumn->getAggregationType()) . "(" . self::getColumnId($abstractColumn->getAggregatedColumn()) . ")";
      } else
      {
        $columnId = self::getScalarFunction($abstractColumn);
      }
      return $columnId;
    }

    protected static function getAggregationFunction($type)
    {
      switch ($type)
      {
        case AggregationType::AVG:
          return "AVG";
        case AggregationType::COUNT:
          return "COUNT";
        case AggregationType::MAX:
          return "MAX";
        case AggregationType::MIN:
          return "MIN";
        case AggregationType::SUM:
          return "SUM";
        default:
          throw new InvalidQueryException("Unsupported aggregate function " . $type);
      }
    }

    protected static function getScalarFunction(ScalarFunctionColumn $col)
    {
      $scalarFunction = $col->getFunction();
      $sfClass = get_class($scalarFunction);
      switch ($sfClass = substr($sfClass, strrpos($sfClass, "\\") + 1)) // Drop namespace
      {
        case "AbsoluteValue":
          $columnId = "ABS";
          break;
        case "Concatenation":
          $columnId = "CONCAT";
          break;
        case "ConcatenationWithSeparator":
          $columnId = "CONCAT_WS";
          break;
        case "CurrentDateTime":
          $columnId = "NOW";
          break;
        case "DateDiff":
          $columnId = "DATEDIFF";
          break;
        case "Left":
          $columnId = "LEFT";
          break;
        case "Lower":
          $columnId = "LOWER";
          break;
        case "Right":
          $columnId = "RIGHT";
          break;
        case "Round":
          $columnId = "ROUND";
          break;
        case "TimeComponentExtractor":
          switch ($scalarFunction->getFunctionName())
          {
            case TimeComponent::YEAR:
              $columnId = "YEAR";
              break;
            case TimeComponent::MONTH:
              $columnId = "MONTH";
              break;
            case TimeComponent::DAY:
              $columnId = "DAYOFMONTH";
              break;
            case TimeComponent::HOUR:
              $columnId = "HOUR";
              break;
            case TimeComponent::MINUTE:
              $columnId = "MINUTE";
              break;
            case TimeComponent::SECOND:
              $columnId = "SECOND";
              break;
            case TimeComponent::QUARTER:
              $columnId = "QUARTER";
              break;
            case TimeComponent::DAY_OF_WEEK:
              $columnId = "DAYOFWEEK";
              break;
            case TimeComponent::MILLISECOND:
              $columnId = "MICROSECOND";
              break;
            default:
              throw new InvalidQueryException("Unsupported date/time function " . $scalarFunction->getFunctionName());
          }
          break;
        case "ToDate":
          $columnId = "DATE"; // Does not support milliseconds, only DATE or DATETIME data types
          break;
        case "Upper":
          $columnId = "UPPER";
          break;
        case "Constant":
          return $scalarFunction->getFunctionName();
        case "Difference":
          $operator = "-";
          break;
        case "Product":
          $operator = "*";
          break;
        case "Quotient":
          $operator = "/";
          break;
        case "Modulo":
          $operator = "%";
          break;
        case "Sum":
          $operator = "+";
          break;
        default:
          throw new InvalidQueryException("Unsupported scalar function " . $scalarFunction->getFunctionName());
      }
      $columns = $col->getColumns();
      if (isset($operator))
      {
        $columnId = "(" . self::getColumnId($columns[0]);
        $columnId .= " " . $operator . " ";
        $columnId .= self::getColumnId($columns[1]) . ")";
      } else
      {
        $columnId .= "(";
        $columnIds = array();
        foreach ($columns as $column)
        {
          $columnIds[] = self::getColumnId($column);
        }
        $columnId .= implode(",", $columnIds) . ")";
      }
      if ($scalarFunction->getFunctionName() == TimeComponent::MILLISECOND)
      {
        $columnId .= " * 1000";
      }
      return $columnId;
    }

    protected static function fieldToValueType($field)
    {
      switch ($field->type)
      {
        case 2:		// SMALLINT
        case 3:		// INTEGER
        case 4:		// FLOAT
        case 5:		// DOUBLE, REAL
        case 8:		// BIGINT, SERIAL (MySQL returns TRUE and FALSE as LONGLONG)
        case 9:		// MEDIUMINT
        case 246:	// DECIMAL, NUMERIC
            $valueType = ValueType::NUMBER;
            break;
        case 1:		// TINYINT, BOOLEAN
        case 16:	// BIT
          if ($field->length == 1)
          {
            $valueType = ValueType::BOOLEAN; // Assume boolean for len = 1
          } else
          {
            $valueType = ValueType::NUMBER;
          }
          break;
        case 10:	// DATE
          $valueType = ValueType::DATE;
          break;
        case 12:	// DATETIME
        case 7:		// TIMESTAMP
          $valueType = ValueType::DATETIME;
          break;
        case 11:	// TIME
          $valueType = ValueType::TIMEOFDAY;
          break;
        case 252:	// TINYTEXT, TEXT, MEDIUMTEXT, LONGTEXT
        case 253:	// VARCHAR
        case 254:	// CHAR, BINARY, ENUM
          $valueType = ValueType::TEXT;
          break;
        default:
          // Spatial data types not supported
          throw new TypeMismatchException("MySQL field type '" . $field->type . "' cannot be matched to a ValueType");
      }
      return $valueType;
    }

    public static function validateDriver($driver)
    {
      return $driver == "mysql";
    }
  }
?>
