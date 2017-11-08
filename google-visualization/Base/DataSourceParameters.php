<?php
  namespace Google\Visualization\DataSource\Base;

  class DataSourceParameters
  {
    const REQUEST_ID_PARAM_NAME = "reqId";
    const SIGNATURE_PARAM_NAME = "sig";
    const OUTPUT_TYPE_PARAM_NAME = "out";
    const RESPONSE_HANDLER_PARAM_NAME = "responseHandler";
    const REQUEST_OUTFILENAME_PARAM_NAME = "outFileName";

    const DEFAULT_ERROR_MSG = "Internal error";

    protected $tqxValue;
    protected $requestId;
    protected $signature;
    protected $outputType;
    protected $responseHandler = "google.visualization.Query.setResponse";
    protected $outFileName = "data.csv";

    public function __construct($tqxValue)
    {
      $this->outputType = OutputType::defaultValue();
      if (empty($tqxValue)) { return; }
      $this->tqxValue = $tqxValue;
      if (strpos($tqxValue, ";") === FALSE)
      {
        $parts = array($tqxValue);
      } else
      {
        $parts = explode(";", $tqxValue);
      }
      foreach ($parts as $part)
      {
        $nameValuePair = explode(":", $part);
        if (count($nameValuePair) != 2)
        {
          //$log->error("Invalid name-value pair: " + part);
          throw new DataSourceException(ReasonType::INVALID_REQUEST, self::DEFAULT_ERROR_MSG . " (malformed)");
        }
        $name = $nameValuePair[0];
        $value = $nameValuePair[1];
        if ($name == self::REQUEST_ID_PARAM_NAME)
        {
          $this->requestId = $value;
        } else if ($name == self::SIGNATURE_PARAM_NAME)
        {
          $this->signature = $value;
        } else if ($name == self::OUTPUT_TYPE_PARAM_NAME)
        {
          $this->outputType = OutputType::findByCode($value);
          if (is_null($this->outputType))
          {
            $this->outputType = OutputType::defaultValue();
          }
        } else if ($name == self::RESPONSE_HANDLER_PARAM_NAME)
        {
          $this->responseHandler = $value;
        } else if ($name == self::REQUEST_OUTFILENAME_PARAM_NAME)
        {
          $this->outFileName = $value;
        }
      }
    }

    public static function getDefaultDataSourceParameters()
    {
      try
      {
        $dsParams = new DataSourceParameters(NULL);
      } catch (DataSourceException $e)
      {
        // Shouldn't be here
      }
      return $dsParams;
    }

    public function getRequestId()
    {
      return $this->requestId;
    }

    public function getSignature()
    {
      return $this->signature;
    }

    public function setSignature($signature)
    {
      $this->signature = $signature;
    }

    public function getOutputType()
    {
      return $this->outputType;
    }

    public function setOutputType($outputType)
    {
      $this->outputType = $outputType;
    }

    public function getResponseHandler()
    {
      return preg_replace("/[^a-zA-Z0-9\\.]/", "", $this->responseHandler);
    }

    public function getOutFileName()
    {
      return $this->outFileName;
    }

    public function getTqxValue()
    {
      return $this->tqxValue;
    }
  }
?>
