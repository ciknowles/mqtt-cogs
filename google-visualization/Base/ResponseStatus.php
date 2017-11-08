<?php
  namespace Google\Visualization\DataSource\Base;

  class ResponseStatus
  {
    protected $statusType;
    protected $reasonType;
    protected $description;

    const SIGN_IN_MESSAGE_KEY = "SIGN_IN";

    public function __construct($statusType, $reasonType = NULL, $description = NULL)
    {
      $this->statusType = $statusType;
      $this->reasonType = $reasonType;
      $this->description = $description;
    }

    public static function createResponseStatus(DataSourceException $dse)
    {
      return new self(StatusType::ERROR, $dse->getReasonType(), $dse->getMessageToUser());
    }

    public static function getModifiedResponseStatus(ResponseStatus $responseStatus)
    {
      $signInString = LocaleUtil::getLocalizedMessageFromBundle(__NAMESPACE__ . "\ErrorMessages", self::SIGN_IN_MESSAGE_KEY, NULL);
      if ($responseStatus->getReasonType() == ReasonType::USER_NOT_AUTHENTICATED)
      {
        $msg = $responseStatus->getDescription();
        if (strpos($msg, " ") !== FALSE && (strpos($msg, "http://") === 0 || strpos($msg, "https://") === 0))
        {
          $sb = '<a target="_blank" href="'.$msg.'">'.$signInString.'</a>';
          $responseStatus = new ResponseStatus($responseStatus->getStatusType(), $responseStatus->getReasonType(), $sb);
        }
      }
      return $responseStatus;
    }

    public function getStatusType()
    {
      return $this->statusType;
    }

    public function getReasonType()
    {
      return $this->reasonType;
    }

    public function getDescription()
    {
      return $this->description;
    }
  }
?>
