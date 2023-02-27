<?php

namespace Drupal\reena_event_example\Event;

use Drupal\Component\EventDispatcher\Event;

class ExampleEvent extends Event {

  const SUBMIT = 'event.submit';
  protected $referenceID;

  public function __construct($referenceID)
  {
    $this->referenceID = $referenceID;
  }

  public function getReferenceID()
  {
    return $this->referenceID;
  }

  public function myEventDescription() {
    return "This is as an example event";
  }
}

