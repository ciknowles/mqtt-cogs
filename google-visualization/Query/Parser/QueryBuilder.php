<?php
  namespace Google\Visualization\DataSource\Query\Parser;

  use Google\Visualization\DataSource\Base\InvalidQueryException;
  use Google\Visualization\DataSource\Base\MessagesEnum;
  use Google\Visualization\DataSource\Query\Query;

  class QueryBuilder
  {
    public static function parseQuery($tqValue, $ulocale)
    {
      if (empty($tqValue))
      {
        $query = new Query();
      } else
      {
        try
        {
          $query = QueryParser::parseString($tqValue);
        } catch (InvalidQueryException $ex)
        {
          $messageToUserAndLog = $ex->getMessage();
          throw new InvalidQueryException(MessagesEnum::getMessageWithArgs(MessagesEnum::PARSE_ERROR, $ulocale, $messageToUserAndLog));
        }
        $query->setLocaleForUserMessages($ulocale);
        $query->validate();
      }
      return $query;
    }
  }
?>
