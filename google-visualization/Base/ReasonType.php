<?php
  namespace Google\Visualization\DataSource\Base;

  class ReasonType
  {
    const ACCESS_DENIED = "ACCESS_DENIED";
    const USER_NOT_AUTHENTICATED = "USER_NOT_AUTHENTICATED";
    const UNSUPPORTED_QUERY_OPERATION = "UNSUPPORTED_QUERY_OPERATION";
    const INVALID_QUERY = "INVALID_QUERY";
    const INVALID_REQUEST = "INVALID_REQUEST";
    const INTERNAL_ERROR = "INTERNAL_ERROR";
    const NOT_SUPPORTED = "NOT_SUPPORTED";
    const DATA_TRUNCATED = "DATA_TRUNCATED";
    const NOT_MODIFIED = "NOT_MODIFIED";
    const TIMEOUT = "TIMEOUT";
    const ILLEGAL_FORMATTING_PATTERNS = "ILLEGAL_FORMATTING_PATTERNS";
    const OTHER = "OTHER";

    public static function getMessageForReasonType($type, $locale)
    {
      return LocaleUtil::getLocalizedMessageFromBundle(__NAMESPACE__ . "\ErrorMessages", $type, $locale);
    }
  }
?>
