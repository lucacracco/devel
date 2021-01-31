<?php

namespace Drupal\webprofiler\Panel;

/**
 * Panel to render collected data about events.
 *
 * @package Drupal\webprofiler\Panel
 */
class EventsPanel extends PanelBase implements PanelInterface {

  /**
   * @inheritDoc
   */
  public function render($token, $name): array {
    /** @var \Symfony\Component\HttpKernel\Profiler\Profiler $profiler */
    $profiler = \Drupal::service('webprofiler.profiler');
    /** @var \Drupal\webprofiler\DataCollector\EventsDataCollector $collector */
    $collector = $profiler->loadProfile($token)->getCollector($name);

    $data = array_merge(
      $this->renderListeners($collector->getCalledListeners(), 'Called'),
      $this->renderListeners($collector->getNotCalledListeners(), 'NotCalled'),
    );

    return [
      '#theme' => 'webprofiler_dashboard_panel',
      '#title' => $this->t('Events'),
      '#data' => $data,
    ];
  }

  /**
   * Render Listeners table.
   *
   * @param array $listeners
   *   The listeners data.
   * @param $label
   *   The label.
   *
   * @return array[]
   *   A render array.
   */
  protected function renderListeners($listeners, $label) {
    if (count($listeners) == 0) {
      return [
        $label => [
          '#markup' => '<p>' . $this->t('No found @label listeners',
              ['@label' => $label]) . '</p>',
        ],
      ];
    }

    $rows = [];
    foreach ($listeners as $event_name => $events) {
      foreach ($events as $priority_value => $priority) {
        foreach ($priority as $listener) {
          $row = [];
          $row[] = $event_name;
          if ($listener['clazz']) {
            if ($listener['class'] == "Closure") {
              $row[] = "Clouser";
            }
            else {
              // TODO: implement link to directly class!.
              $row[] = $listener['class'];
            }
          }
          else {
            $row[] = $listener['service'][0] . ' ' . $listener['service'][1];
          }
          $row[] = $priority_value;
          $rows[] = $row;
        }
      }
    }

    return [
      $label => [
        '#theme' => 'webprofiler_dashboard_table',
        '#title' => $this->t($label),
        '#data' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Listeners'),
            $this->t('Class'),
            $this->t('Priority'),
          ],
          '#rows' => $rows,
          '#attributes' => [
            'class' => [
              'webprofiler__table',
            ],
          ],
        ],
      ],
    ];


  }

}
