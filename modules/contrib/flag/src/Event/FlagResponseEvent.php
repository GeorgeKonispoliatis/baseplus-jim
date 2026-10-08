<?php

namespace Drupal\flag\Event;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;

/**
 * The event instance used to alter the response of a flag action.
 */
class FlagResponseEvent extends FlagEventBase {

  /**
   * FlagResponseEvent constructor.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flaggable entity.
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response instance.
   */
  public function __construct(
    FlagInterface $flag,
    protected EntityInterface $entity,
    protected AjaxResponse $response,
  ) {
    parent::__construct($flag);
  }

  /**
   * Returns the flaggable entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The flaggable entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Sets a new response instance.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The new response instance.
   *
   * @return $this
   *   The current instance.
   */
  public function setResponse(AjaxResponse $response) {
    $this->response = $response;
    return $this;
  }

  /**
   * Returns the response instance.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response instance.
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * Returns the action type.
   *
   * @return string
   *   Either 'flag' or 'unflag'.
   */
  public function getActionType() {
    return $this->flag->isFlagged($this->entity) ? 'flag' : 'unflag';
  }

}
