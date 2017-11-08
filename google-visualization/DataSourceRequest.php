<?php
  namespace Google\Visualization\DataSource;

  use Google\Visualization\DataSource\Base\DataSourceException;
  use Google\Visualization\DataSource\Base\DataSourceParameters;
  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\Base\OutputType;

  class DataSourceRequest
  {
    protected $query;
    protected $dsParams;
    protected $userLocale;
    protected $sameOrigin;

    const SAME_ORIGIN_HEADER = "X-DataSource-Auth";
    const QUERY_REQUEST_PARAMETER = "tq";
    const DATASOURCE_REQUEST_PARAMETER = "tqx";

    public function __construct($create = TRUE)
    {
      if ($create)
      {
        $this->inferLocaleFromRequest();
        $this->sameOrigin = $this->determineSameOrigin();
        $this->createDataSourceParametersFromRequest();
        $this->createQueryFromRequest();
      }
    }

    public static function getDefaultDataSourceRequest()
    {
      $dataSourceRequest = new self(FALSE);
      $dataSourceRequest->inferLocaleFromRequest();
      $dataSourceRequest->sameOrigin = self::determineSameOrigin();
      try
      {
        $dataSourceRequest->createDataSourceParametersFromRequest();
      } catch (DataSourceException $e)
      {
        if (is_null($dataSourceRequest->dsParams))
        {
          $dataSourceRequest->dsParams = DataSourceParameters::getDefaultDataSourceParameters();
        }
        if (($dataSourceRequest->dsParams->getOutputType() == OutputType::JSON) && (!$dataSourceRequest->sameOrigin))
        {
          $dataSourceRequest->dsParams->setOutputType(OutputType::JSONP);
        }
      }
      try
      {
        $dataSourceRequest->createQueryFromRequest();
      } catch (InvalidQueryException $e)
      {
        // If we can't parse the 'tq' parameter, a null query is set.
      }
      return $dataSourceRequest;
    }

    public static function determineSameOrigin()
    {
      if (function_exists('getallheaders')) // Doesn't seem to work yet
      {
        $sameOrigin = FALSE;
        $headers = array_keys(getallheaders());
        foreach ($headers as $header)
        {
          if (strcasecmp($header, self::SAME_ORIGIN_HEADER) == 0)
          {
            $sameOrigin = TRUE;
            break;
          }
        }
      }
      $sameOrigin |= array_key_exists("HTTP_".strtoupper(str_replace("-", "_", self::SAME_ORIGIN_HEADER)), $_SERVER);
      return $sameOrigin;
    }

    protected function createQueryFromRequest()
    {
      $queryString = isset($_REQUEST[self::QUERY_REQUEST_PARAMETER]) ? $_REQUEST[self::QUERY_REQUEST_PARAMETER] : "";
      $this->query = DataSourceHelper::parseQuery($queryString, $this->userLocale);
      return $this;
    }

    protected function createDataSourceParametersFromRequest()
    {
      $this->dsParams = new DataSourceParameters(isset($_REQUEST[self::DATASOURCE_REQUEST_PARAMETER]) ? $_REQUEST[self::DATASOURCE_REQUEST_PARAMETER] : "");
      if ($this->dsParams->getOutputType() == OutputType::JSON && !$this->sameOrigin)
      {
        $this->dsParams->setOutputType(OutputType::JSONP);
      }
      return $this;
    }

    protected function inferLocaleFromRequest()
    {
      $this->userLocale = DataSourceHelper::getLocaleFromRequest();
      return $this;
    }

    public function getQuery()
    {
      return $this->query;
    }

    public function getDataSourceParameters()
    {
      return $this->dsParams;
    }

    public function setUserLocale($userLocale)
    {
      $this->userLocale = $userLocale;
      return $this;
    }

    public function getUserLocale()
    {
      return $this->userLocale;
    }

    public function isSameOrigin()
    {
      return $this->sameOrigin;
    }
  }
?>
