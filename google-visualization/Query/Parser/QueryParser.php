<?php
  namespace Google\Visualization\DataSource\Query\Parser;

  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\DataTable\Value\BooleanValue;
  use Google\Visualization\DataSource\DataTable\Value\DateValue;
  use Google\Visualization\DataSource\DataTable\Value\DateTimeValue;
  use Google\Visualization\DataSource\DataTable\Value\NumberValue;
  use Google\Visualization\DataSource\DataTable\Value\TextValue;
  use Google\Visualization\DataSource\DataTable\Value\TimeOfDayValue;
  use Google\Visualization\DataSource\DataTable\Value\Value;
  use Google\Visualization\DataSource\Query\AggregationColumn;
  use Google\Visualization\DataSource\Query\ColumnColumnFilter;
  use Google\Visualization\DataSource\Query\ColumnIsNullFilter;
  use Google\Visualization\DataSource\Query\ColumnValueFilter;
  use Google\Visualization\DataSource\Query\ColumnSort;
  use Google\Visualization\DataSource\Query\CompoundFilter;
  use Google\Visualization\DataSource\Query\NegationFilter;
  use Google\Visualization\DataSource\Query\Query;
  use Google\Visualization\DataSource\Query\QueryFilter;
  use Google\Visualization\DataSource\Query\QueryFormat;
  use Google\Visualization\DataSource\Query\QueryGroup;
  use Google\Visualization\DataSource\Query\QueryLabels;
  use Google\Visualization\DataSource\Query\QueryOptions;
  use Google\Visualization\DataSource\Query\QueryPivot;
  use Google\Visualization\DataSource\Query\QuerySelection;
  use Google\Visualization\DataSource\Query\QuerySort;
  use Google\Visualization\DataSource\Query\SimpleColumn;
  use Google\Visualization\DataSource\Query\ScalarFunctionColumn;
  use Google\Visualization\DataSource\Query\SortOrder;
  use Google\Visualization\DataSource\Query\ScalarFunction\AbsoluteValue;
  use Google\Visualization\DataSource\Query\ScalarFunction\Constant;
  use Google\Visualization\DataSource\Query\ScalarFunction\Concatenation;
  use Google\Visualization\DataSource\Query\ScalarFunction\ConcatenationWithSeparator;
  use Google\Visualization\DataSource\Query\ScalarFunction\CurrentDateTime;
  use Google\Visualization\DataSource\Query\ScalarFunction\DateDiff;
  use Google\Visualization\DataSource\Query\ScalarFunction\Difference;
  use Google\Visualization\DataSource\Query\ScalarFunction\Left;
  use Google\Visualization\DataSource\Query\ScalarFunction\Lower;
  use Google\Visualization\DataSource\Query\ScalarFunction\Modulo;
  use Google\Visualization\DataSource\Query\ScalarFunction\Product;
  use Google\Visualization\DataSource\Query\ScalarFunction\Quotient;
  use Google\Visualization\DataSource\Query\ScalarFunction\Right;
  use Google\Visualization\DataSource\Query\ScalarFunction\Round;
  use Google\Visualization\DataSource\Query\ScalarFunction\ToNumber;
  use Google\Visualization\DataSource\Query\ScalarFunction\ToDateBoundary;
  
  use Google\Visualization\DataSource\Query\ScalarFunction\Sum;
  use Google\Visualization\DataSource\Query\ScalarFunction\TimeComponentExtractor;
  use Google\Visualization\DataSource\Query\ScalarFunction\ToDate;
  use Google\Visualization\DataSource\Query\ScalarFunction\Upper;

  // TODO: Create ICU supported error messages

  class QueryParser
  {
    const DATE_FORMAT = "[0-9]{4}-[0-9]{2}-[0-9]{2}";
    const NOT_BACK_QUOTED = "(?=(?:[^`]*`[^`]*`)*[^`]*$)";
    const NAMED_SCALAR_FUNCTIONS_REGEXP = "(year)|(month)|(day)|(hour)|(minute)|(second)|(millisecond)|(quarter)|(dayOfWeek)|(now)|(dateDiff)|(toDate)|(upper)|(lower)|(concat)|(concat_ws)|(abs)|(round)|(right)|(left)|(toNumber)|(toDateBoundary)";
    const AGGREGATION_FUNCTIONS_REGEXP = "(count)|(sum)|(min)|(max)|(avg)";
    const TIME_FORMAT = "[0-9]{2}:[0-9]{2}:[0-9]{2}(?:.[0-9]{0-3})?";
    const COMPARISON_REGEXP = "(<=)|(?:(<)[^=>])|(?:[^<](>)[^=])|(>=)|(?:[^!<>](=))|(!=)|(<>)|(?:\s+(?:(contains)|(starts with)|(ends with)|(matches)|(like))\s+)";
    const UNQUOTED_LOOKAHEAD = "(?=(?:[^\"]*\"[^\"]*\")*[^\"]*$)(?=(?:[^']*'[^']*')*[^']*$)";
    const VALUE_PATTERN = "/^(?:\(?(\"(?:[^\"]|(?:\"\"))*\")|('(?:[^']|(?:''))*')|(-?[0-9]*\.?[0-9]+)|(true|false)|((?:date|timeofday|datetime)\s+(?:(?:\"[^\"]*\")|(?:'[^']*')))\)?)$/i";

    protected static $clauseSeparators = array(
      "select",
      "where",
      "group by",
      "group",
      "pivot",
      "order by",
      "skipping",
      "limit",
      "offset",
      "label",
      "format",
      "options"
    );

    public static function parseString($queryString)
    {
      return self::queryStatement($queryString);
    }

    protected static function queryStatement($queryString)
    {
  
      $query = new Query();
      $clauses = self::splitClauses($queryString);
      $query->setSelection(self::parseSelection($clauses["select"]));
      $query->setFilter(self::parseFilter($clauses["where"]));
      
      
      $query->setGroup(self::parseGroup($clauses["group by"]));
      if ($clauses["group by"] =='') {
	      $query->setGroup(self::parseGroup($clauses["group"]));
      }
      $query->setPivot(self::parsePivot($clauses["pivot"]));
      $query->setSort(self::parseSort($clauses["order by"]));
      if ($clauses["order by"]=='') {
            $query->setSort(self::parseSort($clauses["order"]));
      }
      $query->setRowSkipping(self::parseRowSkipping($clauses["skipping"]));
      $query->setRowLimit(self::parseRowLimit($clauses["limit"]));
      $query->setRowOffset(self::parseRowOffset($clauses["offset"]));
      $query->setLabels(self::parseLabels($clauses["label"]));
      $query->setUserFormatOptions(self::parseUserFormatOptions($clauses["format"]));
      $query->setOptions(self::parseOptions($clauses["options"]));
      return $query;
    }

    protected static function parseSelection($argumentString)
    {
      if ($argumentString == "*")
      {
        return;
      }
      $selectArgs = self::splitArguments($argumentString);
      $selection = new QuerySelection();
      foreach ($selectArgs as $arg)
      {
        $selection->addColumn(self::parseColumn($arg));
      }
      return $selection;
    }

    protected static function parseFilter($argumentString)
    {
      if (is_null($argumentString)) { return NULL; }
      // Check for unquoted curly braces (invalid syntax and used as subfilter placeholders in parser
      if (preg_match("/([\{\}])" . self::UNQUOTED_LOOKAHEAD . "/", $argumentString, $matches))
      {
        throw new InvalidQueryException("Invalid character [" . $matches[1] . "] in WHERE clause");
      }
      $a = array();
      $innerExp = "";
      $outerExp = "";
      $parensCount = 0;
      $sfCount = 0;
      // Step through each character and recurse if parenthesized expression is found
      for ($i = 0; $i < strlen($argumentString); $i++)
      {
        $c = substr($argumentString, $i, 1);
        if ($c == ")" && (($parensCount + $sfCount) == 0))
        {
          throw new InvalidQueryException("Unmatched parenthesis in WHERE clause");
        }
        if ($c == "(" && preg_match("/" . self::AGGREGATION_FUNCTIONS_REGEXP . "|" . self::NAMED_SCALAR_FUNCTIONS_REGEXP . "$/i", $innerExp))
        {
          $sfCount++;
        }
        if ($c == "(" && $sfCount == 0)
        {
          $parensCount++;
          if ($parensCount == 1)
          {
            $innerExp = "";
            continue;
          }
        } else if ($c == ")" && $sfCount == 0)
        {
          $parensCount--;
          if ($parensCount == 0)
          {
            if (preg_match("/" . self::COMPARISON_REGEXP . self::UNQUOTED_LOOKAHEAD . "/", $innerExp))
            {
              $filter = self::parseFilter($innerExp);
              $outerExp .= "{" . count($a) . "}";
              $a[] = $filter;
              $innerExp = "";
              continue;
            }
            // Not a comparison expression, must be column, so reinsert with parentheses
            $outerExp = "(" . $innerExp . ")";
            $innerExp = "";
            continue;
          }
        } else if ($parensCount == 0)
        {
          $outerExp .= $c;
        }
        if ($c == ")" && $sfCount > 0)
        {
          $sfCount--;
        }
        $innerExp .= $c;
      }
      if ($parensCount != 0 || $sfCount != 0)
      {
        throw new InvalidQueryException("Unmatched parenthesis in WHERE clause");
      }

      // Handle NOTs
      while (preg_match("/(?<!is)((^|\s+)not\s*)(\{\d+\})?" . self::UNQUOTED_LOOKAHEAD . "/i", $outerExp, $matches, PREG_OFFSET_CAPTURE))
      {
        $notMatch = $matches[1][0];
        $notOffset = $matches[1][1];
        $notExp = substr($outerExp, $notOffset + strlen($notMatch));
        if (preg_match("/\s+(and|or)\s+" . self::UNQUOTED_LOOKAHEAD . "/i", $notExp, $matches, PREG_OFFSET_CAPTURE))
        {
          $loOffset = $matches[0][1];
          $notExp = substr($notExp, 0, $loOffset);
        } else
        {
          $loOffset = strlen($outerExp);
        }
        if (preg_match("/\{(\d+)\}" . self::UNQUOTED_LOOKAHEAD . "/", $notExp, $matches))
        {
          $key = $matches[1] + 0;
          $a[$key] = new NegationFilter($a[$key]);
          $outerExp = substr($outerExp, 0, $notOffset) . " {" . $key . "} " . substr($outerExp, $notOffset + strlen($notMatch));
        } else {
          $a[] = new NegationFilter(self::parseNonCompoundFilter($notExp));
          $outerExp = substr($outerExp, 0, $notOffset) . " {" . (count($a) ? max(array_keys($a)) : 0) . "} " . substr($outerExp, $notOffset + strlen($notMatch) + $loOffset);
        }
      }

      // Handle ANDs
      while (preg_match("/\s+and\s+" . self::UNQUOTED_LOOKAHEAD . "/i", $outerExp, $matches, PREG_OFFSET_CAPTURE))
      {
        $andMatch = $matches[0][0];
        $andOffset = $matches[0][1];
        if (preg_match("/\s+or\s+" . self::UNQUOTED_LOOKAHEAD . "/i", substr($outerExp, 0, $andOffset), $matches, PREG_OFFSET_CAPTURE))
        {
          $orMatch = $matches[0][0];
          $orOffset = $matches[0][1];
          $startOffset = $orOffset + strlen($orMatch);
        } else
        {
          $startOffset = 0;
        }
        if (preg_match("/\s+(and|or)\s+" . self::UNQUOTED_LOOKAHEAD . "/i", $outerExp, $matches, PREG_OFFSET_CAPTURE, $andOffset + strlen($andMatch)))
        {
          $andOrOffset = $matches[0][1];
          $endOffset = $andOrOffset;
        } else
        {
          $endOffset = strlen($outerExp);
        }
        $andExp = substr($outerExp, $startOffset, $endOffset);
        $andArgs = explode($andMatch, $andExp);
        $andSubFilters = array();
        foreach ($andArgs as $andArg)
        {
          if (preg_match("/\{(\d+)\}" . self::UNQUOTED_LOOKAHEAD . "/i", $andArg, $matches))
          {
            $key = $matches[1] + 0;
            $andSubFilters[] = $a[$key];
            unset($a[$key]);
          } else
          {
            $andSubFilters[] = self::parseNonCompoundFilter($andArg);
          }
        }
        $a[] = new CompoundFilter(CompoundFilter::LOGICAL_OPERATOR_AND, $andSubFilters);
        $outerExp = substr($outerExp, 0, $startOffset) . " {" . (count($a) ? max(array_keys($a)) : 0) . "} " . substr($outerExp, $endOffset);
      }

      // Handle ORs
      while (preg_match("/\s+or\s+" . self::UNQUOTED_LOOKAHEAD . "/i", $outerExp, $matches, PREG_OFFSET_CAPTURE))
      {
        $orMatch = $matches[0][0];
        $orOffset = $matches[0][1];
        $startOffset = 0;
        if (preg_match("/\s+or\s+" . self::UNQUOTED_LOOKAHEAD . "/i", $outerExp, $matches, PREG_OFFSET_CAPTURE, $orOffset + strlen($orMatch)))
        {
          $nextOrOffset = $matches[0][1];
          $endOffset = $nextOrOffset;
        } else
        {
          $endOffset = strlen($outerExp);
        }
        $orExp = substr($outerExp, $startOffset, $endOffset);
        $orArgs = explode($orMatch, $orExp);
        $orSubFilters = array();
        foreach ($orArgs as $orArg)
        {
          if (preg_match("/\{(\d+)\}" . self::UNQUOTED_LOOKAHEAD . "/", $orArg, $matches))
          {
            $key = $matches[1] + 0;
            $orSubFilters[] = $a[$key];
            unset($a[$key]);
          } else
          {
            $orSubFilters[] = self::parseNonCompoundFilter($orArg);
          }
        }
        $a[] = new CompoundFilter(CompoundFilter::LOGICAL_OPERATOR_OR, $orSubFilters);
        $outerExp = substr($outerExp, 0, $startOffset) . " {" . (count($a) ? max(array_keys($a)) : 0) . "} " . substr($outerExp, $endOffset);
      }

      if (count($a) == 0)
      {
        $filter = self::parseNonCompoundFilter($outerExp);
      } else if (count($a) == 1)
      {
        $a = array_values($a);
        $filter = $a[0];
      }
      return $filter;
    }

    protected static function parseNonCompoundFilter($filterString)
    {
    	
      if (preg_match("/" . self::COMPARISON_REGEXP . self::UNQUOTED_LOOKAHEAD . "/i", $filterString, $matches))
      {
     
        $matches = array_values(array_filter($matches, "strlen"));
        $args = explode($matches[1], $filterString);
        $operator = strtoupper($matches[1]);
     
     	
     
        if (preg_match(self::VALUE_PATTERN, trim($args[0]), $matches))
        {
    
          $matches = array_values(array_filter($matches, "strlen"));
          $filter = new ColumnValueFilter(self::parseColumn($args[0]), self::parseValue($matches[1]), $operator, TRUE);
        } else if (preg_match(self::VALUE_PATTERN, trim($args[1]), $matches))
        {
          $matches = array_values(array_filter($matches, "strlen"));
          $filter = new ColumnValueFilter(self::parseColumn($args[0]), self::parseValue($matches[1]), $operator, FALSE);
        } else
        {
	       
          $filter = new ColumnColumnFilter(self::parseColumn($args[0]), self::parseColumn($args[1]), $operator);
        }
      } else if (preg_match("/(.*)\sis\s(?:not\s)?null" . self::UNQUOTED_LOOKAHEAD . "/i", $filterString, $matches))
      {
        $filter = new ColumnIsNullFilter(self::parseColumn($matches[1]));
        if (preg_match("/\sis\snot\snull" . self::UNQUOTED_LOOKAHEAD . "/i", $filterString))
        {
          $filter = new NegationFilter($filter);
        }
      } else
      {
        throw new InvalidQueryException("Encountered unknown filter [" . $filterString . "]");
      }
      return $filter;
    }

    protected static function parseValue($valueString)
    {
      $valueString = trim($valueString);
      if (preg_match("/^true|false$/i", $valueString))
      {
        if (strcasecmp($valueString, "false") == 0)
        {
          $value = new BooleanValue(FALSE);
        } else
        {
          $value = new BooleanValue(TRUE);
        }
      }
      else if (preg_match("/^-?[0-9]*\.?[0-9]+$/", $valueString))
      {
        $value = new NumberValue($valueString);
      } else if (preg_match("/^(?:(?:\"((?:[^\"]|(?:\"\"))*)\")|(?:'((?:[^']|(''))*)'))$/", $valueString, $matches))
      {
        $matches = array_values(array_filter($matches, "strlen"));
        $value = $matches[1];
        $value = strpos($valueString, "\"") === 0 ? str_replace("\"\"", "\"", $value) : $value; // Unescape double-quotes if double-quoted string
        $value = strpos($valueString, "'") === 0 ? str_replace("''", "'", $value) : $value; // Unescape single-quotes if single-quoted string
        $value = new TextValue($value);
      } else if (preg_match("/^date\s+(?:(?:\"(" . self::DATE_FORMAT . ")\")|(?:'(" . self::DATE_FORMAT . ")'))$/", $valueString, $matches))
      {
        $matches = array_values(array_filter($matches, "strlen"));
        try
        {
          $value = new DateValue($matches[1]);
        } catch (\Exception $e)
        {
          throw new InvalidQueryException("Encountered invalid date [" . $matches[1] . "]");
        }
      } else if (preg_match("/^timeofday\s+(?:(?:\"(" . self::TIME_FORMAT . ")\")|(?:'(" . self::TIME_FORMAT . ")'))$/", $valueString, $matches))
      {
        $matches = array_values(array_filter($matches, "strlen"));
        try
        {
          $value = new TimeOfDayValue($matches[1]);
        } catch (\Exception $e)
        {
          throw new InvalidQueryException("Encountered invalid time [" . $matches[1] . "]");
        }
      } else if (preg_match("/^datetime\s+(?:(?:\"(" . self::DATE_FORMAT . "\s+" . self::TIME_FORMAT . ")\")|(?:'(" . self::DATE_FORMAT . "\s+" . self::TIME_FORMAT . ")'))$/", $valueString, $matches))
      {
        $matches = array_values(array_filter($matches, "strlen"));
        $value = new DateTimeValue($matches[1]);
      } else
      {
        throw new InvalidQueryException("Encountered unknown value [" . $valueString . "]");
      }
      return $value;
    }

    protected static function parseGroup($argumentString)
    {
      $group = new QueryGroup();
      $args = self::splitArguments($argumentString);
      foreach ($args as $arg)
      {
        $group->addColumn(self::parseColumn($arg));
      }
      return $group;
    }

    protected static function parsePivot($argumentString)
    {
      $pivot = new QueryPivot();
      $args = self::splitArguments($argumentString);
      foreach ($args as $arg)
      {
        $pivot->addColumn(self::parseColumn($arg));
      }
      return $pivot;
    }

    protected static function parseSort($argumentString)
    {
      $sort = new QuerySort();
      $args = self::splitArguments($argumentString);
      foreach ($args as $arg)
      {
        if (preg_match("/\s+(" . SortOrder::ASCENDING . "|" . SortOrder::DESCENDING . ")$/i", $arg, $matches))
        {
          $col = substr($arg, 0, -strlen($matches[0]));
        } else
        {
          $col = $arg;
        }
        if (isset($matches[1]) && strcasecmp($matches[1], SortOrder::DESCENDING) === 0)
        {
          $order = SortOrder::DESCENDING;
        } else
        {
          $order = SortOrder::ASCENDING;
        }
        $sort->addSort(new ColumnSort(self::parseColumn($col), $order));
      }
      return $sort;
    }

    protected static function parseRowSkipping($argumentString)
    {
      return empty($argumentString) ? 0 : (int) $argumentString;
    }

    protected static function parseRowLimit($argumentString)
    {
      return empty($argumentString) ? -1 : (int) $argumentString;
    }

    protected static function parseRowOffset($argumentString)
    {
      return (int) $argumentString;
    }

    protected static function parseLabels($argumentString)
    {
      $labels = new QueryLabels();
      $args = self::splitArguments($argumentString);
      foreach ($args as $arg)
      {
        $a = preg_split("/\s+(?:(?:'([^']+)')|" . '(?:"([^"]+)")' . ")$/", $arg, -1 , PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (count($a) != 2)
        {
          throw new InvalidQueryException("Unable to parse label: " . $arg);
        }
        $labels->addLabel(self::parseColumn($a[0]), $a[1]);
      }
      return $labels;
    }

    protected static function parseUserFormatOptions($argumentString)
    {
      $format = new QueryFormat();
      $args = self::splitArguments($argumentString);
      foreach ($args as $arg)
      {
        $a = preg_split("/\s+(?:(?:'([^']+)')|" . '(?:"([^"]+)")' . ")$/", $arg, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        if (count($a) != 2)
        {
          throw new InvalidQueryException("Unable to parse format: " . $arg);
        }
        $format->addPattern(self::parseColumn($a[0]), $a[1]);
      }
      return $format;
    }

    protected static function parseOptions($argumentString)
    {
      $options = new QueryOptions();
      if (stripos($argumentString, "no_values") !== FALSE)
      {
        $options->setNoValues(TRUE);
      }
      if (stripos($argumentString, "no_format") !== FALSE)
      {
        $options->setNoFormat(TRUE);
      }
      return $options;
    }

    protected static function splitClauses($queryString)
    {
      $clauses = array();
      for ($i = 0; $i < count(self::$clauseSeparators); $i++)
      {
        $clauseSeparator = self::$clauseSeparators[$i];
        $clause = NULL;
        if (preg_match("/(?:^\s*(" . $clauseSeparator . ")\s+)|(?:\s+(" . $clauseSeparator . ")\s+(?:[^`]*`[^`]*`)*[^`]*$)/i", $queryString, $matches, PREG_OFFSET_CAPTURE))
        {
          $matches = end($matches);
          $clausePos = $matches[1] + strlen($clauseSeparator);
          unset($nextClauseSeparatorPos);
          for ($j = $i + 1; $j < count(self::$clauseSeparators); $j++)
          {
            $nextClauseSeparator = self::$clauseSeparators[$j];
            if (preg_match("/(?:^\s*(" . $nextClauseSeparator . ")\s+)|(?:\s+(" . $nextClauseSeparator . ")\s+(?:[^`]*`[^`]*`)*[^`]*$)/i", $queryString, $matches, PREG_OFFSET_CAPTURE))
            {
              $matches = end($matches);
              $nextClauseSeparatorPos = $matches[1];
              break;
            }
          }
          if (isset($nextClauseSeparatorPos) && $nextClauseSeparatorPos !== FALSE)
          {
            $clause = substr($queryString, $clausePos, $nextClauseSeparatorPos - $clausePos);
          } else
          {
            $clause = substr($queryString, $clausePos);
          }
        }
        $clauses[$clauseSeparator] = $clause ? trim($clause) : NULL;
      }
      return $clauses;
    }

    protected static function splitArguments($str, $pattern = "/,/")
    {
      $a = array();
      $word = "";
      $parensCount = 0;
      $bqEscaped = FALSE;
      $dqEscaped = FALSE;
      $sqEscaped = FALSE;
      for ($i = 0; $i < strlen($str); $i++)
      {
        $c = substr($str, $i, 1);
        if ($c == "`")
        {
          $bqEscaped = !$bqEscaped;
        } else if ($c == '"')
        {
          $dqEscaped = !$dqEscaped;
        } else if ($c == "'")
        {
          $sqEscaped = !$sqEscaped;
        } else if (!$bqEscaped && !$dqEscaped && !$sqEscaped)
        {
          if ($c == ")")
          {
            $parensCount--;
          } else if ($c == "(")
          {
            $parensCount++;
          } else if ($parensCount == 0 && preg_match($pattern, $c))
          {
            $word = trim($word);
            if (strlen($word) > 0)
            {
              $a[] = $word;
            }
            $word = "";
            continue;
          }
        }
        $word .= $c;
      }
      $word = trim($word);
      if (strlen($word) > 0)
      {
        $a[] = $word;
      }
      return $a;
    }

    protected static function parseArithmeticColumn($arg, $pattern)
    {
      $operators = self::getOuterOperators($arg);
      $offset = 0;
      foreach (self::splitArguments($arg, $pattern) as $colString)
      {
        $colStringLength = strlen($colString);
        $colString = preg_replace("/^\((.+)\)$/", "$1", $colString); // Strip off outer parentheses
        if (isset($column))
        {
            preg_match($pattern, $arg, $matches, PREG_OFFSET_CAPTURE, $offset);
            $operator = $matches[0][0];
            $offset = $matches[0][1] + 1;
            switch ($operator)
            {
              case "+":
                $sf = new Sum();
                break;
              case "-":
                $sf = new Difference();
                break;
              case "*":
                $sf = new Product();
                break;
              case "/":
                $sf = new Quotient();
                break;
              case "%":
                $sf = new Modulo();
                break;
            }
            $column = new ScalarFunctionColumn(array($column, self::parseColumn($colString)), $sf);
        } else
        {
          $column = self::parseColumn($colString);
        }
        $offset += $colStringLength;
      }
      return $column;
    }

    protected static function getOuterOperators($arg)
    {
      $a = array();
      $parensCount = 0;
      for ($i = 0; $i < strlen($arg); $i++)
      {
        $c = substr($arg, $i, 1);
        if ($c == "(")
        {
          $parensCount++;
          continue;
        }
        if ($c == ")")
        {
          $parensCount--;
          continue;
        }
        if ($parensCount == 0 && preg_match("/[\+\-\*\/%]/", $c))
        {
          $a[] = $c;
        }
      }
      return $a;
    }

    protected static function parseColumn($arg)
    {
      $arg = trim($arg);
      $newArg = preg_replace("/^(?:\(\s*(.+)\s*\))$/", "$1", $arg); // Remove outer parentheses
      if ($newArg != $arg)
      {
        return self::parseColumn($newArg);
      }
      if (preg_match("/^`([^`]+)`$/", $arg, $matches)) // Back-quoted identifier
      {
        if ($matches[1] == "")
        {
          throw new InvalidQueryException("Column name is required.");
        }
        $column = new SimpleColumn($matches[1]);
      } else if (preg_match("/^(([^\(]+)|([^\(]*\([^\)]*\)))\s*[\+\-\*\/%]" . self::UNQUOTED_LOOKAHEAD . "/", $arg)) // Arithmetic expression not enclosed in a function
      {
        $operators = self::getOuterOperators($arg);
        // Operator precedence
        if (preg_match("/[\*\/%]/", implode("", $operators))) // Multiply, divide, or modulo
        {
          $column = self::parseArithmeticColumn($arg, "/[\*\/%]/");
        } else if (preg_match("/[\+\-]/", implode("", $operators))) // Add or subtract
        {
          $column = self::parseArithmeticColumn($arg, "/[\+\-]/");
        }
      } else if (preg_match(self::VALUE_PATTERN, $arg, $matches)) // Constant
      {
        $matches = array_values(array_filter($matches, "strlen"));
        $column = new ScalarFunctionColumn(array(), new Constant(self::parseValue($matches[1])));
      } else if (($parenPos = strpos($arg, "(")) === FALSE) // Not a function
      {
        if ($arg == "")
        {
          throw new InvalidQueryException("Column name is required.");
        }
        $column = new SimpleColumn($arg);
      } else if (preg_match("/^" . self::AGGREGATION_FUNCTIONS_REGEXP . "|" . self::NAMED_SCALAR_FUNCTIONS_REGEXP . "\\([^)]*\\)" . self::UNQUOTED_LOOKAHEAD . "/i", $arg)) // Named function
      {
        $parenPos = strpos($arg, "(");
        $colFunc = strtoupper(substr($arg, 0, $parenPos));
        $colFuncArgs = substr($arg, $parenPos + 1, strrpos($arg, ")") - $parenPos - 1);
        $colFuncArgs = self::splitArguments($colFuncArgs);
        $aggTypeString = "Google\Visualization\DataSource\Query\AggregationType::" . $colFunc;
        if (defined($aggTypeString)) // Aggregation Function
        {
          if (count($colFuncArgs) > 1)
          {
            throw new InvalidQueryException("Aggregation functions can only contain one column");
          }
          $aggregatedColumn = self::parseColumn($colFuncArgs[0]);
          if (!($aggregatedColumn instanceof SimpleColumn))
          {
            throw new InvalidQueryException($colFunc . "() does not support expressions.  Only column names can be used.");
          }
          $column = new AggregationColumn($aggregatedColumn, constant($aggTypeString));
        } else // Scalar or uknown function
        {
          switch ($colFunc)
          {
            case "YEAR":
            case "QUARTER":
            case "MONTH":
            case "DAY":
            case "DAYOFWEEK":
            case "HOUR":
            case "MINUTE":
            case "SECOND":
            case "MILLISECOND":
              $scalarFunction = new TimeComponentExtractor(constant("Google\Visualization\DataSource\Query\ScalarFunction\TimeComponent::" . $colFunc));
              break;
            case "NOW":
              $scalarFunction = new CurrentDateTime();
              break;
            case "DATEDIFF":
              $scalarFunction = new DateDiff();
              break;
            case "TODATE":
              $scalarFunction = new ToDate();
              break;
            case "LOWER":
              $scalarFunction = new Lower();
              break;
            case "UPPER":
              $scalarFunction = new Upper();
              break;
            case "CONCAT":
              $scalarFunction = new Concatenation();
              break;
            case "CONCAT_WS":
              $scalarFunction = new ConcatenationWithSeparator();
              break;
            case "ABS":
              $scalarFunction = new AbsoluteValue();
              break;
            case "ROUND":
              $scalarFunction = new Round();
              break;
            case "RIGHT":
              $scalarFunction = new Right();
              break;
            case "LEFT":
              $scalarFunction = new Left();
              break;
            case "TONUMBER":
              $scalarFunction = new ToNumber();
              break;
           case "TODATEBOUNDARY":
              $scalarFunction = new ToDateBoundary();
              break;
            default:
              throw new InvalidQueryException("Unknown column function " . $colFunc);
          }
          $columns = array();
          foreach ($colFuncArgs as $arg)
          {
            $columns[] = self::parseColumn($arg);
          }
          $column = new ScalarFunctionColumn($columns, $scalarFunction);
        }
      } else
      {
        throw new InvalidQueryException("Unrecognized expression: " . $arg);
      }
      return $column;
    }
  }
?>
