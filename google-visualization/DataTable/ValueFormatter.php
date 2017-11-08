<?php
  namespace Google\Visualization\DataSource\DataTable;

  use DateTime;
  use IntlDateFormatter;
  use NumberFormatter;
  use Google\Visualization\DataSource\Base\BooleanFormat;
  use Google\Visualization\DataSource\Base\TextFormat;
  use Google\Visualization\DataSource\Base\LocaleUtil;
  use Google\Visualization\DataSource\DataTable\Value\Value;
  use Google\Visualization\DataSource\DataTable\Value\ValueType;
  use Google\Visualization\DataSource\Util\Map;

  class ValueFormatter
  {
    const DEFAULT_TEXT_DUMMY_PATTERN = "dummy";
    const DEFAULT_DATETIME_PATTERN = "yyyy-MM-DD HH:mm:ss";
    const DEFAULT_DATE_PATTERN = "yyyy-MM-dd";
    const DEFAULT_TIMEOFDAY_PATTERN = "HH:mm:ss";
    const DEFAULT_BOOLEAN_PATTERN = "true:false";
    const DEFAULT_NUMBER_PATTERN = "";

    protected $uFormat;
    protected $pattern;
    protected $locale;
    protected $type;

    public function __construct($pattern, $uFormat, $type, $locale)
    {
      $this->pattern = $pattern;
      $this->uFormat=  $uFormat;
      $this->type = $type;
      $this->locale = $locale;
    }

    public static function createFromPattern($type, $pattern = NULL, $locale = NULL)
    {
      if (is_null($pattern))
      {
        $pattern = self::getDefaultPatternByType($type);
      }

      if (is_null($locale))
      {
        $locale = LocaleUtil::getDefaultLocale();
      }

      try
      {
        switch ($type)
        {
          case ValueType::BOOLEAN:
            $uFormat = new BooleanFormat($pattern);
            $uFormat->format(TRUE);
            break;
          case ValueType::TEXT:
            $uFormat = new TextFormat();
            break;
          case ValueType::DATE:
            $uFormat = new IntlDateFormatter($locale, NULL, NULL, NULL, NULL, $pattern);
            $uFormat->format(new DateTime());
            break;
          case ValueType::TIMEOFDAY:
            $uFormat = new IntlDateFormatter($locale, NULL, NULL, NULL, NULL, $pattern);
            $uFormat->format(new DateTime());
            break;
          case ValueType::DATETIME:
            $uFormat = new IntlDateFormatter($locale, NULL, NULL, NULL, NULL, $pattern);
            $uFormat->format(new DateTime());
            break;
          case ValueType::NUMBER:
            $uFormat = new NumberFormatter($locale, NumberFormatter::PATTERN_DECIMAL, $pattern);
            $uFormat->format(-12.3);
            break;
        }
      } catch (RuntimeException $e)
      {
        return NULL;
      }
      return new self($pattern, $uFormat, $type, $locale);
    }

    public static function createDefault($type, $locale)
    {
      $pattern = self::getDefaultPatternByType($type);
      return self::createFromPattern($type, $pattern, $locale);
    }

    public static function createDefaultFormatters($locale)
    {
      $formatters = new Map();
      foreach (ValueType::values() as $type)
      {
        $formatters->put($type, self::createDefault($type, $locale));
      }
      return $formatters;
    }

    public function format(Value $value)
    {
      if ($value->isNull())
      {
        return "";
      }
      return $this->uFormat->format($value->getObjectToFormat());
    }

    protected static function getDefaultPatternByType($type)
    {
      switch ($type)
      {
        default:
          $defaultPattern = NULL;
      }
      return $defaultPattern;
    }
  }
?>
