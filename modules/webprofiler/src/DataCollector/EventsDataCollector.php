<?php

namespace Drupal\webprofiler\DataCollector;

use Drupal\webprofiler\EventDispatcher\EventDispatcherTraceableInterface;
use Drupal\webprofiler\Panel\EventsPanel;
use Drupal\webprofiler\Panel\PanelInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

/**
 * DataCollector for Drupal events.
 *
 * @package Drupal\webprofiler\DataCollector
 */
class EventsDataCollector extends DataCollector implements DrupalDataCollectorInterface, LateDataCollectorInterface {

  use DataCollectorTrait;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * EventsDataCollector constructor.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EventDispatcherInterface $event_dispatcher) {
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function collect(Request $request, Response $response, \Exception $exception = NULL) {
    $this->data = [
      'called_listeners' => [],
      'called_listeners_count' => 0,
      'not_called_listeners' => [],
      'not_called_listeners_count' => 0,
    ];
  }

  /**
   * Get called listeners.
   *
   * @return array
   *   An array of listeners.
   */
  public function getCalledListeners() {
    return $this->data['called_listeners'];
  }

  /**
   * Return the count of listeners called.
   *
   * @return int
   *   The count.
   */
  public function getCalledListenersCount() {
    return $this->data['called_listeners_count'];
  }

  /**
   * Get data.
   *
   * @return mixed
   *   The data.
   */
  public function getData() {
    return $this->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'events';
  }

  /**
   * Get listeners not called.
   *
   * @return array
   *   An array of listeners.
   */
  public function getNotCalledListeners() {
    return $this->data['not_called_listeners'];
  }

  /**
   * Return the count of listeners not called.
   *
   * @return int
   *   The count.
   */
  public function getNotCalledListenersCount() {
    return $this->data['not_called_listeners_count'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPanel(): PanelInterface {
    return new EventsPanel();
  }

  /**
   * {@inheritdoc}
   */
  public function lateCollect() {
    if ($this->eventDispatcher instanceof EventDispatcherTraceableInterface) {
      $countCalled = 0;
      $calledListeners = $this->eventDispatcher->getCalledListeners();
      foreach ($calledListeners as &$events) {
        foreach ($events as &$priority) {
          foreach ($priority as &$listener) {
            $countCalled++;
            $listener['clazz'] = $this->getMethodData($listener['class'], $listener['method']);
          }
        }
      }

      $countNotCalled = 0;
      $notCalledListeners = $this->eventDispatcher->getNotCalledListeners();
      foreach ($notCalledListeners as $events) {
        foreach ($events as $priority) {
          foreach ($priority as $listener) {
            $countNotCalled++;
          }
        }
      }

      $this->data = [
        'called_listeners' => $calledListeners,
        'called_listeners_count' => $countCalled,
        'not_called_listeners' => $notCalledListeners,
        'not_called_listeners_count' => $countNotCalled,
      ];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function reset() {
    $this->data = [];
  }

}
