<?php

namespace Drupal\webprofiler\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Interface EventDispatcherTraceableInterface
 *
 * @package Drupal\webprofiler\EventDispatcher
 */
interface EventDispatcherTraceableInterface extends EventDispatcherInterface {

  /**
   * Get called-listeners.
   *
   * @return array
   *   An array of listener data.
   */
  public function getCalledListeners();

  /**
   * Get not-called listeners.
   *
   * @return array
   *   An array of listener data.
   */
  public function getNotCalledListeners();

}
