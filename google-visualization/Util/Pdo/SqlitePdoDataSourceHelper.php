<?php
  namespace Google\Visualization\DataSource\Util\Pdo;

  use RuntimeException;
  use Google\Visualization\DataSource\Base\DataSourceException;
  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\TypeMismatchException;
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
  use Google\Visualization\DataSource\Query\SimpleColumn;
  use Google\Visualization\DataSource\Query\SortOrder;
  use Google\Visualization\DataSource\Query\ScalarFunctionColumn;
  use Google\Visualization\DataSource\Query\ScalarFunction\TimeComponent;

  class SqlitePdoDataSourceHelper extends PdoDataSourceHelper
  {
    protected static function buildSqlQuery(Query $query, $tableName)
    {
      $queryString = self::buildSelectClause($query);
      $queryString .= self::buildFromClause($query, $tableName);
      $queryString .= self::buildWhereClause($query);
      $queryString .= self::buildGroupByClause($query);
      $queryString .= self::buildOrderByClause($query);
      $queryString .= self::buildLimitAndOffsetClause($query);
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
      $fromClause = ' FROM "';
      $fromClause .= $tableName;
      $fromClause .= '"';
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
          $value2 = str_replace("'", "", $value2);
          $clause = $value1 . " LIKE '%" . $value2 . "%'";
          break;
        case ComparisonFilter::OPERATOR_STARTS_WITH:
          $value2 = str_replace("'", "", $value2);
          $clause = $value1 . " LIKE '" . $value2 . "%'";
          break;
        case ComparisonFilter::OPERATOR_ENDS_WITH:
          $value2 = str_replace("'", "", $value2);
          $clause = $value1 . " LIKE '%" . $value2 . "'";
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
        $columnId = '"' . $abstractColumn->getId() . '"';
      } else if ($abstractColumn instanceof AggregationColumn)
      {
        $columnId = self::getAggregationFunction($abstractColumn->getAggregationType()) . '("' . $abstractColumn->getAggregatedColumn() . '")';
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
          $operator = "||";
          break;
       	case "ConcatenationWithSeparator":
          $colIds = array();
          foreach ($col->getColumns() as $i => $column)
          {
            if ($i == 0) { $separator = self::getColumnId($column); continue; }
            $colIds[] = self::getColumnId($column);
          }
          return implode(" || " . $separator . " || ", $colIds);
        case "CurrentDateTime":
          $columnId = "NOW";
          break;
        case "DateDiff":
          $columnId = "AGE";
          break;
        case "Left":
          $columns = $col->getColumns();
          return "SUBSTR(" . self::getColumnId($columns[0]) . ", 1, " . self::getColumnId($columns[1]) . ")";
        case "Lower":
          $columnId = "LOWER";
          break;
        case "Right":
          $columns = $col->getColumns();
          return "SUBSTR(" . self::getColumnId($columns[0]). ",  " . (-self::getColumnId($columns[1])) . ")";
        case "Round":
          $columnId = "ROUND";
          break;
        case "TimeComponentExtractor":
          switch ($scalarFunction->getFunctionName())
          {
            case TimeComponent::YEAR:
              $columnId = "%Y";
              break;
            case TimeComponent::MONTH:
              $columnId = "%m";
              break;
            case TimeComponent::DAY:
              $columnId = "%d";
              break;
            case TimeComponent::HOUR:
              $columnId = "%H";
              break;
            case TimeComponent::MINUTE:
              $columnId = "%M";
              break;
            case TimeComponent::SECOND:
              $columnId = "%S";
              break;
            case TimeComponent::DAY_OF_WEEK:
              $columnId = "%w";
              break;
            default:
              throw new InvalidQueryException("Unsupported date/time function " . $scalarFunction->getFunctionName());
          }
          $columns = $col->getColumns();
          return "STRFTIME('" . $columnId . "', " . self::getColumnId($columns[0]) . ")";
          break;
        case "ToDate":
          $columnId = "DATE";
          break;
        case "Upper":
          $columnId = "UPPER";
          break;
        case "Constant":
          return str_replace('"', "'", $scalarFunction->getFunctionName());
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
        $columnIds = array();
        foreach ($columns as $column)
        {
          $columnIds[] = self::getColumnId($column);
        }
        $columnId = "(" . implode(" " . $operator . " ", $columnIds) . ")";
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
      return $columnId;
    }

    protected static function metaDataToValueType($metaData)
    {
      switch ($metaData["sqlite:decl_type"])
      {
        case "DATE":
          $valueType = ValueType::DATE;
          break;
        case "DATETIME":
          $valueType = ValueType::DATETIME;
          break;
        case "BOOLEAN":
          $valueType = ValueType::BOOLEAN;
          break;
        default:
          switch ($metaData["native_type"])
          {
            case "double":
            case "integer":
              $valueType = ValueType::NUMBER;
              break;
            case "string":
              $valueType = ValueType::TEXT;
              break;
            default:
              throw new TypeMismatchException("SQLite data type '" . $metaData["native_type"] . "' cannot be matched to a ValueType");
          }
      }
      return $valueType;
    }

    public static function validateDriver($driver)
    {
      return $driver == "sqlite";
    }
  }
?>
