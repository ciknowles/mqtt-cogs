<?php
  namespace Google\Visualization\DataSource;

  use Locale;
  use RuntimeException;
  use Google\Visualization\DataSource\Base\DataSourceException;
  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\Base\MessagesEnum;
  use Google\Visualization\DataSource\Base\OutputType;
  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\StatusType;
  use Google\Visualization\DataSource\Base\ResponseStatus;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\Query\Query;
  use Google\Visualization\DataSource\Query\Engine\QueryEngine;
  use Google\Visualization\DataSource\Query\Parser\QueryBuilder;
  use Google\Visualization\DataSource\Render\CsvRenderer;
  use Google\Visualization\DataSource\Render\HtmlRenderer;
  use Google\Visualization\DataSource\Render\JsonRenderer;

  class DataSourceHelper
  {
    const LOCALE_REQUEST_PARAMETER = "hl";

    public static function executeDataSource(DataTableGenerator $dtGenerator, $isRestrictedAccessMode = TRUE)
    {
      try
      {
        $dsRequest = new DataSourceRequest();

        if ($isRestrictedAccessMode)
        {
          self::verifyAccessApproved($dsRequest);
        }

        $query = self::splitQuery($dsRequest->getQuery(), $dtGenerator->getCapabilities());

        $dataTable = $dtGenerator->generateDataTable($query->getDataSourceQuery());

        $newDataTable = DataSourceHelper::applyQuery($query->getCompletionQuery(), $dataTable, $dsRequest->getUserLocale());

        self::setResponse(self::generateResponse($newDataTable, $dsRequest), $dsRequest);
      } catch (DataSourceException $e)
      {
        if (!isset($dsRequest))
        {
          $dsRequest = DataSourceRequest::getDefaultDataSourceRequest();
        }
        $responseStatus = ResponseStatus::createResponseStatus($e);
        $responseStatus = ResponseStatus::getModifiedResponseStatus($responseStatus);
        $responseMessage = self::generateErrorResponse($responseStatus, $dsRequest);
        self::setResponse($responseMessage, $dsRequest);
      } catch (RuntimeException $e)
      {
        //$log->error("A runtime exception has occurred", $e);
        $status = new ResponseStatus(StatusType::ERROR, ReasonType::INTERNAL_ERROR, $e->getMessage());
        if (is_null($dsRequest))
        {
          $dsRequest = DataSourceRequest::getDefaultDataSourceRequest();
        }
        self::setResponse(self::generateErrorResponse($status, $dsRequest), $dsRequest);
      }
    }

    public static function verifyAccessApproved(DataSourceRequest $req)
    {
      $outType = $req->getDataSourceParameters()->getOutputType();
      if ($outType != OutputType::CSV && $outType != OutputType::TSV_EXCEL && $outType != OutputType::HTML && !$req->isSameOrigin())
      {
        throw new DataSourceException(ReasonType::ACCESS_DENIED, "Unauthorized request. Cross domain requests are not supported.");
      }
    }

    public static function setResponse($responseMessage, DataSourceRequest $dataSourceRequest)
    {
      $dataSourceParameters = $dataSourceRequest->getDataSourceParameters();
      ResponseWriter::setResponse($responseMessage, $dataSourceParameters);
    }

    public static function generateResponse(DataTable $dataTable, DataSourceRequest $dataSourceRequest)
    {
      $responseStatus = NULL;
      if (count($dataTable->getWarnings()))
      {
        $responseStatus = new ResponseStatus(StatusType::WARNING);
      }
      switch ($dataSourceRequest->getDataSourceParameters()->getOutputType())
      {
        case OutputType::CSV:
          $response = CsvRenderer::renderDataTable($dataTable, $dataSourceRequest->getUserLocale(), ",");
          break;
        case OutputType::TSV_EXCEL:
          $response = CsvRenderer::renderDataTable($dataTable, $dataSourceRequest->getUserLocale(), "\t");
          break;
        case OutputType::HTML:
          $response = HtmlRenderer::renderDataTable($dataTable, $dataSourceRequest->getUserLocale());
          break;
     /*   case OutputType::JSONP:
          $response = "// Data table response\n" . JsonRenderer::renderJsonResponse($dataSourceRequest->getDataSourceParameters(), $responseStatus, $dataTable);
          break;*/
        case OutputType::JSONP:
          $response = JsonRenderer::renderJsonResponse($dataSourceRequest->getDataSourceParameters(), $responseStatus, $dataTable);
          break;
        case OutputType::JSON:
          $response = JsonRenderer::renderJsonResponse($dataSourceRequest->getDataSourceParameters(), $responseStatus, $dataTable);
          break;
        case OutputType::PHP:
          $response = serialize($dataTable);
          break;
        default:
          throw new RuntimeException("Unhandled output type.");
      }
      return (string) $response;
    }

    public static function generateErrorResponse(ResponseStatus $responseStatus, DataSourceRequest $dsRequest)
    {
      $dsParameters = $dsRequest->getDataSourceParameters();
      switch ($dsParameters->getOutputType())
      {
        case OutputType::CSV:
        case OutputType::TSV_EXCEL:
          $response = CsvRenderer::renderCsvError($responseStatus);
          break;
        case OutputType::HTML:
          $response = HtmlRenderer::renderHtmlError($responseStatus);
          break;
        case OutputType::JSONP:
        case OutputType::JSON:
          $response = JsonRenderer::renderJsonResponse($dsParameters, $responseStatus, NULL);
          break;
        case OutputType::PHP:
          $response = serialize($responseStatus);
          break;
        default:
          throw new RuntimeException("Unhandled output type.");
      }
      return (string) $response;
    }

    public static function parseQuery($queryString, $userLocale = NULL)
    {
      return QueryBuilder::parseQuery($queryString, $userLocale);
    }

    public static function applyQuery(Query $query, DataTable $dataTable, $locale)
    {
      $dataTable->setLocaleForUserMessages($locale);
      self::validateQueryAgainstColumnStructure($query, $dataTable);
      $dataTable = QueryEngine::executeQuery($query, $dataTable, $locale);
      $dataTable->setLocaleForUserMessages($locale);
      return $dataTable;
    }

    public static function splitQuery(Query $query, $capabilities)
    {
      return QuerySplitter::splitQuery($query, $capabilities);
    }

    public static function validateQueryAgainstColumnStructure(Query $query, DataTable $dataTable)
    {
      $mentionedColumnIds = $query->getAllColumnIds();
      foreach ($mentionedColumnIds as $columnId)
      {
        if (!$dataTable->containsColumn($columnId))
        {
          $messageToLogAndUser = MessagesEnum::getMessageWithArgs(MessagesEnum::NO_COLUMN, $dataTable->getLocaleForUserMessages(), $columnId);
          //$log->error($messageToLogAndUser);
          throw new InvalidQueryException($messageToLogAndUser);
        }
      }

      $mentionedAggregations = $query->getAllAggregations();
      foreach ($mentionedAggregations as $agg)
      {
        try
        {
          $agg->validateColumn($dataTable);
        } catch (RuntimeException $e)
        {
          //$log->error("A runtime exception has occurred", $e);
          throw new InvalidQueryException($e->getMessage());
        }
      }

      $mentionedScalarColumns = $query->getAllScalarFunctionsColumns();
      foreach ($mentionedScalarColumns as $col)
      {
        $col->validateColumn($dataTable);
      }
    }

    public static function getLocaleFromRequest()
    {
      if (isset($_GET[self::LOCALE_REQUEST_PARAMETER]))
      {
        $locale = $_GET[self::LOCALE_REQUEST_PARAMETER];
      } else
      {
        $locale = Locale::acceptFromHttp($_SERVER["HTTP_ACCEPT_LANGUAGE"]);
      }
      return $locale;
    }
  }
?>
