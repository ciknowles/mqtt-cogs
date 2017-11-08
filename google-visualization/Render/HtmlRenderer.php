<?php
  namespace Google\Visualization\DataSource\Render;

  use DOMDocument;
  use DOMElement;

  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\ResponseStatus;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\ValueFormatter;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class HtmlRenderer
  {
    const DETAILED_MESSAGE_A_TAG_REGEXP = "/([^<]*<a(( )*target=\"_blank\")*(( )*target='_blank')*(( )*href=\"[^\"]*\")*(( )*href='[^']*')*>[^<]*<\/a>)+[^<]*/i";
    const BAD_JAVASCRIPT_REGEXP = "/javascript(( )*):/i";

    public static function renderDataTable(DataTable $dataTable, $locale)
    {
      $document = self::createDocument();
      $bodyElement = self::appendHeadAndBody($document);

      $tableElement = $document->createElement("table");
      $bodyElement->appendChild($tableElement);
      $tableElement->setAttribute("border", "1");
      $tableElement->setAttribute("cellpadding", "2");
      $tableElement->setAttribute("cellspacing", "0");

      $columnDescriptions = $dataTable->getColumnDescriptions();
      $trElement = $document->createElement("tr");
      $trElement->setAttribute("style", "font-weight: bold; background-color: #aaa;");
      foreach ($columnDescriptions as $columnDescription)
      {
        $tdElement = $document->createElement("td", $columnDescription->getLabel());
        $trElement->appendChild($tdElement);
      }
      $tableElement->appendChild($trElement);

      $formatters = ValueFormatter::createDefaultFormatters($locale);
      $rowCount = 0;
      foreach ($dataTable->getRows() as $row)
      {
        $rowCount++;
        $trElement = $document->createElement("tr");
        $backgroundColor = $rowCount % 2 != 0 ? "#f0f0f0" : "#ffffff";
        $trElement->setAttribute("style", "background-color: " . $backgroundColor);
        foreach ($row->getCells() as $c => $cell)
        {
          $valueType = $columnDescriptions[$c]->getType();
          $cellFormattedText = $cell->getFormattedValue();
          if (is_null($cellFormattedText))
          {
            $cellFormattedText = $formatters->get($cell->getType())->format($cell->getValue());
          }

          $tdElement = $document->createElement("td");
          if ($cell->isNull())
          {
            $tdElement->appendChild($document->createCDATASection("&#x00a0;"));
          } else
          {
            switch ($valueType)
            {
              case ValueType::NUMBER:
                $tdElement->setAttribute("style", "text-align: right;");
                $tdElement->appendChild($document->createTextNode($cellFormattedText));
                break;
              case ValueType::BOOLEAN:
                $booleanValue = $cell->getValue();
                $tdElement->setAttribute("style", "text-align: center;");
                if ($booleanValue->getValue())
                {
                  $tdElement->appendChild($document->createCDATASection("&#x2714;"));
                } else
                {
                  $tdElement->appendChild($document->createCDATASection("&#x2717;"));
                }
                break;
              default:
                if (strlen($cellFormattedText) == 0)
                {
                  $tdElement->appendChild($document->createCDATASection("&#x00a0;"));
                } else
                {
                  $tdElement->appendChild($document->createTextNode($cellFormattedText));
                }
            }
          }
          $trElement->appendChild($tdElement);
        }
        $tableElement->appendChild($trElement);
      }
      $bodyElement->appendChild($tableElement);

      foreach ($dataTable->getWarnings() as $warning)
      {
        $bodyElement->appendChild($document->createElement("br"));
        $bodyElement->appendChild($document->createElement("br"));
        $messageElement = $document->createElement("div");
        $messageElement->appendChild($document->createTextNode(ReasonType::getMessageForReasonType($warning->getReasonType()) . ". " . $warning->getMessage()));
        $bodyElement->appendChild($messageElement);
      }

      return self::transformDocumentToHtmlString($document);
    }

    protected static function transformDocumentToHtmlString(DOMDocument $document)
    {
      return $document->saveHTML();
    }

    protected static function sanitizeDetailedMessage($detailedMessage)
    {
      if (strlen($detailedMessage) == 0)
      {
        return "";
      }

      if (preg_match(self::DETAILED_MESSAGE_A_TAG_REGEXP, $detailedMessage) && !preg_match(self::BAD_JAVASCRIPT_REGEXP, $detailedMessage))
      {
        return $detailedMessage;
      } else
      {
        return htmlspecialchars($detailedMessage);
      }
    }

    public static function renderHtmlError(ResponseStatus $responseStatus)
    {
      $status = $responseStatus->getStatusType();
      $reason = $responseStatus->getReasonType();
      $detailedMessage = $responseStatus->getDescription();

      $document = self::createDocument();
      $bodyElement = self::appendHeadAndBody($document);

      $oopsElement = $document->createElement("h3", "Oops, an error occured.");
      $bodyElement->appendChild($oopsElement);

      if (!is_null($status))
      {
        $text = "Status: " . strtolower($status);
        self::appendSimpleText($document, $bodyElement, $text);
      }

      if (!is_null($reason))
      {
        $text = "Reason: " . ReasonType::getMessageForReasonType($reason, NULL);
        self::appendSimpleText($document, $bodyElement, $text);
      }

      if (!is_null($detailedMessage))
      {
        $text = "Status: " . self::sanitizeDetailedMessage($detailedMessage);
        self::appendSimpleText($document, $bodyElement, $text);
      }

      return self::transformDocumentToHtmlString($document);
    }

    protected static function createDocument()
    {
      return new DOMDocument();
    }

    protected static function appendHeadAndBody(DOMDocument $document)
    {
      $htmlElement = $document->createElement("html");
      $document->appendChild($htmlElement);
      $headElement = $document->createElement("head");
      $htmlElement->appendChild($headElement);
      $titleElement = $document->createElement("title", "Google Visualization");
      $headElement->appendChild($titleElement);
      $bodyElement = $document->createElement("body");
      $htmlElement->appendChild($bodyElement);
      return $bodyElement;
    }

    protected static function appendSimpleText(DOMDocument $document, DOMElement $bodyElement, $text)
    {
      $statusElement = $document->createElement("div", $text);
      $bodyElement->appendChild($statusElement);
    }
  }
?>
