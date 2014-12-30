<?php
/**
 * @file
 * Contains \Drupal\simplenews\Plugin\monitoring\SensorPlugin\PendingSensorPlugin.
 */

namespace Drupal\simplenews\Plugin\monitoring\SensorPlugin;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;

/**
 * Monitors pending items in the simplenews mail spool.
 *
 * @SensorPlugin(
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
class PendingSensorPlugin extends SensorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    $result->setValue(simplenews_count_spool(array('status' => SIMPLENEWS_SPOOL_PENDING)));
  }
}
