<?php
  namespace Google\Visualization\DataSource\Base;

  class MessagesEnum
  {
    const NO_COLUMN = "NO_COLUMN";
    const AVG_SUM_ONLY_NUMERIC = "AVG_SUM_ONLY_NUMERIC";
    const INVALID_AGG_TYPE = "INVALID_AGG_TYPE";
    const PARSE_ERROR = "PARSE_ERROR";
    const CANNOT_BE_IN_GROUP_BY = "CANNOT_BE_IN_GROUP_BY";
    const CANNOT_BE_IN_PIVOT = "CANNOT_BE_IN_PIVOT";
    const CANNOT_BE_IN_WHERE = "CANNOT_BE_IN_WHERE";
    const SELECT_WITH_AND_WITHOUT_AGG = "SELECT_WITH_AND_WITHOUT_AGG";
    const COL_AGG_NOT_IN_SELECT = "COL_AGG_NOT_IN_SELECT";
    const CANNOT_GROUP_WITHOUT_AGG = "CANNOT_GROUP_WITHOUT_AGG";
    const CANNOT_PIVOT_WITHOUT_AGG = "CANNOT_PIVOT_WITHOUT_AGG";
    const AGG_IN_SELECT_NO_PIVOT = "AGG_IN_SELECT_NO_PIVOT";
    const FORMAT_COL_NOT_IN_SELECT = "FORMAT_COL_NOT_IN_SELECT";
    const LABEL_COL_NOT_IN_SELECT = "LABEL_COL_NOT_IN_SELECT";
    const ADD_COL_TO_GROUP_BY_OR_AGG = "ADD_COL_TO_GROUP_BY_OR_AGG";
    const AGG_IN_ORDER_NOT_IN_SELECT = "AGG_IN_ORDER_NOT_IN_SELECT";
    const NO_AGG_IN_ORDER_WHEN_PIVOT = "NO_AGG_IN_ORDER_WHEN_PIVOT";
    const COL_IN_ORDER_MUST_BE_IN_SELECT = "COL_IN_ORDER_MUST_BE_IN_SELECT";
    const NO_COL_IN_GROUP_AND_PIVOT = "NO_COL_IN_GROUP_AND_PIVOT";
    const INVALID_LIMIT = "INVALID_LIMIT";
    const INVALID_OFFSET = "INVALID_OFFSET";
    const INVALID_SKIPPING = "INVALID_SKIPPING";
    const COLUMN_ONLY_ONCE = "COLUMN_ONLY_ONCE";

    public static function getMessageWithArgs($type, $locale, $args)
    {
      if (!is_array($args)) { $args = array($args); }
      return LocaleUtil::getLocalizedMessageFromBundleWithArguments(__NAMESPACE__ . "\ErrorMessages", $type, $args, $locale);
    }

    public static function getMessage($type, $locale)
    {
      return LocaleUtil::getLocalizedMessageFromBundle(__NAMESPACE__ . "\ErrorMessages", $type, $locale);
    }
  }
?>
