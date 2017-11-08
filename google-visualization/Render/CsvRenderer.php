<?php
  namespace Google\Visualization\DataSource\Render;

  use Google\Visualization\DataSource\Base\ReasonType;
  use Google\Visualization\DataSource\Base\ResponseStatus;
  use Google\Visualization\DataSource\DataTable\DataTable;
  use Google\Visualization\DataSource\DataTable\ValueFormatter;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;

  class CsvRenderer
  {
    protected function __construct() {}

    public static function renderDataTable(DataTable $dataTable, $locale, $separator)
    {
      if (is_null($separator))
      {
        $separator = ",";
      }

      $columns = $dataTable->getColumnDescriptions();
      if (count($columns) == 0)
      {
        return "";
      }

      $sb = "";
      foreach ($columns as $column)
      {
        $sb .= self::escapeString($column->getLabel()) . $separator;
      }

      $sb = substr($sb, 0, -1) . "\n";

      $formatters = ValueFormatter::createDefaultFormatters($locale);

      $rows = $dataTable->getRows();
      foreach ($rows as $row)
      {
        $cells = $row->getCells();
        foreach ($cells as $cell)
        {
          $formattedValue = $cell->getFormattedValue();
          if (is_null($formattedValue))
          {
            $formattedValue = $formatters->get($cell->getType())->format($cell->getValue());
          }
          if (is_null($cell))
          {
            $sb .= "null";
          } else
          {
            $type = $cell->getType();
            if (strpos($formattedValue, ",") !== FALSE || $type == ValueType::TEXT)
            {
              $sb .= self::escapeString($formattedValue);
            } else
            {
              $sb .= $formattedValue;
            }
          }
          $sb .= $separator;
        }
        $sb = substr($sb, 0, -1) . "\n";
      }
      return $sb;
    }

    protected static function escapeString($input)
    {
      $sb = "\"";
      $sb .= str_replace("\"", "\"\"", $input);
      $sb .= "\"";
      return $sb;
    }

    public static function renderCsvError(ResponseStatus $responseStatus)
    {
      $sb = "Error: " . ReasonType::getMessageForReasonType($responseStatus->getReasonType(), NULL);
      $sb .= ". " . $responseStatus->getDescription();
      return self::escapeString($sb);
    }
  }
?>
