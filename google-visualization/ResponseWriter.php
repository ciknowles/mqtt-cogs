<?php
  namespace Google\Visualization\DataSource;

  use RuntimeException;
  use Google\Visualization\DataSource\Base\DataSourceParameters;
  use Google\Visualization\DataSource\Base\OutputType;

  class ResponseWriter
  {
    const UTF_16LE_BOM = "\xFF\xFE";

    public static function setResponse($responseMessage, DataSourceParameters $dataSourceParameters)
    {
      $type = $dataSourceParameters->getOutputType();
      switch ($type)
      {
        case OutputType::CSV:
          self::setResponseCSV($dataSourceParameters);
          self::writeResponse($responseMessage);
          break;
        case OutputType::TSV_EXCEL:
          self::setResponseTSVExcel($dataSourceParameters);
          self::writeResponse($responseMessage, "UTF-16LE", self::UTF_16LE_BOM);
          break;
        case OutputType::HTML:
          self::setResponseHTML($dataSourceParameters);
          self::writeResponse($responseMessage);
          break;
        case OutputType::JSONP:
          self::setResponseJSONP($dataSourceParameters);
          self::writeResponse($responseMessage);
          break;
        case OutputType::JSON:
          self::setResponseJSON($dataSourceParameters);
          self::writeResponse($responseMessage);
          break;
        case OutputType::PHP:
          self::setResponsePHP($dataSourceParameters);
          self::writeResponse($responseMessage);
          break;
        default:
          throw new RuntimeException("Unhandled output type.");
      }
    }

    public static function setResponseCSV(DataSourceParameters $dataSourceParameters)
    {
      header("Content-Type: text/csv; charset=UTF-8");
      $outFileName = $dataSourceParameters->getOutFileName();
      if (substr(strtolower($outFileName), -4) != ".csv")
      {
        $outFileName = $outFileName . ".csv";
      }
      header("Content-Disposition: attachment; filename=" . $outFileName);
    }

    public static function setResponseTSVExcel(DataSourceParameters $dataSourceParameters)
    {
      header("Content-Type: text/csv; charset=UTF-16LE");
      $outFileName = $dataSourceParameters->getOutFileName();
      header("Content-Disposition: attachment; filename=" . $outFileName);
    }

    public static function setResponseHTML(DataSourceParameters $dataSourceParameters)
    {
      header("Content-Type: text/html; charset=UTF-8");
    }

    public static function setResponseJSONP(DataSourceParameters $dataSourceParameters)
    {
      header("Content-Type: text/javascript; charset=UTF-8");
    }

    public static function setResponseJSON(DataSourceParameters $dataSourceParameters)
    {
      header("Content-Type: application/json; charset=UTF-8");
    }

    public static function setResponsePHP(DataSourceParameters $dataSourceParameters)
    {
      header("Content-type: text/plain; charset=UTF-8");
    }

    public static function writeResponse($responseMessage, $charset = "UTF-8", $byteOrderMark = NULL)
    {
      if (!is_null($byteOrderMark))
      {
        echo $byteOrderMark;
      }
      mb_convert_variables($charset, NULL, $responseMessage);
      echo $responseMessage;
    }
  }
?>
