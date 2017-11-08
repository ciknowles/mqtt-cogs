<?php

  namespace Google\Visualization\DataSource\Base;

  class Warning
  {
    protected $reasonType;
    protected $messageToUser;

    public function __construct($reasonType, $messageToUser)
    {
      $this->reasonType = $reasonType;
      $this->messageToUser = $messageToUser;
    }

    public function getReasonType()
    {
      return $this->reasonType;
    }

    public function getMessage()
    {
      return $this->messageToUser;
    }
  }
?>
