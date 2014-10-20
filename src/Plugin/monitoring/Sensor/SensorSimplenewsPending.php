<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\Sensor\SensorSimplenewsPending.
 */

namespace Drupal\simplenews\Plugin\monitoring\Sensor;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\SensorThresholds;

/**
 * Monitors pending items in the simplenews mail spool.
 *
 * @Sensor(
 *   id = "simplenews_pending",
 *   label = @Translation("Simplenews Pending"),
 *   description = @Translation("Monitors pending items in the simplenews mail spool."),
 *   provider = "simplenews",
 *   addable = FALSE
 * )
 *
 * Once all is processed, the value should be 0.
 *
 * @see simplenews_count_spool()
 */
class SensorSimplenewsPending extends SensorThresholds {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    $result->setValue(simplenews_count_spool(array('status' => SIMPLENEWS_SPOOL_PENDING)));
  }
}
