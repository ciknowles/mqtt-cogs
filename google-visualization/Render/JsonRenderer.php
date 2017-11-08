<?php
  namespace Google\Visualization\DataSource\Render;

  use stdClass;
  use Google\Visualization\DataSource\Base\DataSourceParameters;
  use Google\Visualization\DataSource\Base\OutputType;
  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\ResponseStatus;
  use Google\Visualization\DataSource\Base\StatusType;
  use Google\Visualization\DataSource\DataTable\ColumnDescription;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\TableCell;
  use Google\Visualization\DataSource\DataTable\Value\BooleanValue;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class JsonRenderer
  {
    public static function getSignature(DataTable $data)
    {
//      $tableAsString = self::renderDataTable($data, TRUE, FALSE, TRUE);
$tableAsString = rand();
      return md5($tableAsString);
    }

    protected static function getFaultObject($reasonType, $description)
    {
      $fault = new stdClass();
      if (!empty($reasonType))
      {
        $fault->reason = strtolower($reasonType);
        $fault->message = ReasonType::getMessageForReasonType($reasonType, NULL);
      }
      if (!empty($description))
      {
        $fault->detailed_message = $description;
      }
      return $fault;
    }

    public static function renderJsonResponse(DataSourceParameters $dsParams, $responseStatus, DataTable $data = NULL)
    {
      $response = new stdClass();
      $isJsonp = $dsParams->getOutputType() == OutputType::JSONP;

      $response->version = 0.6;

      $requestId = $dsParams->getRequestId();
      if (!is_null($requestId))
      {
        $response->reqId = $requestId;
      }

      $previousSignature = $dsParams->getSignature();
      if (is_null($responseStatus))
      {
        if (!empty($previousSignature) && !is_null($data) && (self::getSignature($data) == $previousSignature))
        {
          $responseStatus = new ResponseStatus(StatusType::ERROR, ReasonType::NOT_MODIFIED, NULL);
        } else
        {
          $responseStatus = new ResponseStatus(StatusType::OK, NULL, NULL);
        }
      }

      $statusType = $responseStatus->getStatusType();
      $response->status = $statusType;

      if ($statusType != StatusType::OK)
      {
        if ($statusType == StatusType::WARNING)
        {
          $warnings = $data->getWarnings();
          $warningObjects = array();
          if (!is_null($warnings))
          {
            foreach ($warnings as $warning)
            {
              $warningObjects[] = self::getFaultObject($warning->getReasonType(), $warning->getMessage());
            }
            $response->warnings = $warningObjects;
          }
        } else
        {
          $response->errors = array(self::getFaultObject($responseStatus->getReasonType(), $responseStatus->getDescription()));
        }
      }

      if (($statusType != StatusType::ERROR) && !is_null($data))
      {
        $response->sig = self::getSignature($data);
        $response->table = self::objectifyDataTable($data, TRUE, TRUE, $isJsonp);
      }

      if ($isJsonp)
      {
        return $dsParams->getResponseHandler()."(".json_encode($response, JSON_NUMERIC_CHECK).")";
      }
      $json = json_encode($response, JSON_NUMERIC_CHECK);
      // Remove quotes from Date object
      return preg_replace("/\"v\":\"(Date\([0-9,]+\))\"/", "\"v\":new $1", $json);
    }

    public static function renderDataTable(DataTable $dataTable, $includeValues, $includeFormatting, $renderDateAsDateConstructor)
    {
      return json_encode(self::objectifyDataTable($dataTable, $includeValues, $includeFormatting, $renderDateAsDataConstructor), JSON_NUMERIC_CHECK);
    }

    protected static function objectifyDataTable(DataTable $dataTable, $includeValues, $includeFormatting, $renderDateAsDateConstructor)
    {
      $columnDescriptions = $dataTable->getColumnDescriptions();
      if (count($columnDescriptions) == 0)
      {
        return "";
      }
      $table = new stdClass();
      $table->cols = array();
      foreach ($columnDescriptions as $col)
      {
        $table->cols[] = self::objectifyColumnDescriptionJson($col);
      }

      $table->rows = array();
      foreach ($dataTable->getRows() as $tableRow)
      {
        $row = new stdClass();
        $row->c = array();
        foreach ($tableRow->getCells() as $cell)
        {
          $row->c[] = self::objectifyCellJson($cell, $includeFormatting, $renderDateAsDateConstructor);
        }
        $table->rows[] = $row;
      }

      return $table;
    }

    public static function objectifyCellJson(TableCell $cell, $includeFormatting, $renderDateAsDateConstructor)
    {
      $value = $cell->getValue();
      $type = $cell->getType();
      $valueJson = "";
      if (is_null($value) || (method_exists($value, "getValue") && is_null($value->getValue())))
      {
        $o = NULL;
      } else {
        switch ($type)
        {
          case ValueType::BOOLEAN:
            $valueJson = $value->getValue();;
            break;
          case ValueType::DATE:
            if ($renderDateAsDateConstructor)
            {
              $valueJson .= "new ";
            }
            $valueJson .= "Date(";
            $valueJson .= $value->getYear() . ",";
            $valueJson .= $value->getMonth() . ",";
            $valueJson .= $value->getDayOfMonth();
            $valueJson .= ")";
            break;
          case ValueType::NUMBER:
            $valueJson .= $value->getValue();
            break;
          case ValueType::TEXT:
            $valueJson .= $value;
            break;
          case ValueType::DATETIME:
            $dateTime = $value->getDateTime();
            if ($renderDateAsDateConstructor)
            {
              $valueJson .= "new ";
            }
            $valueJson .= "Date(";
            $valueJson .= $value->getYear() . ",";
            $valueJson .= $value->getMonth() . ","; // PHP months are not zero-based
            $valueJson .= $value->getDayOfMonth() . ",";
            $valueJson .= $value->getHourOfDay() . ",";
            $valueJson .= $value->getMinute() . ",";
            $valueJson .= $value->getSecond();
            $valueJson .= ")";
            break;
          case ValueType::TIMEOFDAY:
            $valueJson .= "[";
            $valueJson .= $value->getHours() . ",";
            $valueJson .= $value->getMinute() . ",";
            $valueJson .= $value->getSecond() . ",";
            $valueJson .= $value->getMillisecond();
            $valueJson .= "]";
            break;
          default:
            exit("Illegal value Type " + $type);
        }
      }

      $formattedValue = $cell->getFormattedValue();
      if (!is_null($value) && !is_null($formattedValue))
      {
        if ($type == ValueType::TEXT && $value->__toString() == $formattedValue)
        {
          $formattedValue = "";
        }
      }

      $c = new stdClass();
      $c->v = $valueJson;
      if ($includeFormatting && !empty($formattedValue))
      {
        $c->f = $formattedValue;
      }
      $customPropertiesString = self::getPropertiesMapString($cell->getCustomProperties());
      if (!is_null($customPropertiesString))
      {
        $c->p = $customPropertiesString;
      }
      return $c;
    }

    public static function objectifyColumnDescriptionJson(ColumnDescription $col)
    {
      $c = new stdClass();
      $c->id = $col->getId();
      $c->label = $col->getLabel();
      $c->type = $col->getType();
      $c->pattern = $col->getPattern();

      $customPropertiesString = self::getPropertiesMapString($col->getCustomProperties());
      if (!is_null($customPropertiesString))
      {
        $c->p = $customPropertiesString;
      }

      return $c;
    }

    protected static function getPropertiesMapString($propertiesMap)
    {
      $customPropertiesString = NULL;
      if (!empty($propertiesMap))
      {
        $customPropertiesStrings = array();
        foreach ($propertiesMap as $entry)
        {
          $customPropertiesStrings[] = $entry->getKey() . ":" . $entry->getValue();
        }
        $customPropertiesObjectString = "{" . implode(",", $customPropertiesStrings) . "}";
      }
      return $customPropertiesString;
    }
  }
?>
