<?php
/**
 * @file
 * Simplenews monitoring test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Tests for simplenews sensor.
 *
 * @group simplenews
 * @requires module monitoring
 */
class SimplenewsMonitoringTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'user', 'field', 'text', 'simplenews', 'monitoring', 'monitoring_test', 'entity_reference');

  /**
   * Tests individual sensors.
   */
  function testSensors() {

    $this->installConfig(array('node'));
    $this->installConfig(array('simplenews'));
    $this->installEntitySchema('monitoring_sensor_result');
    $this->installSchema('simplenews', 'simplenews_mail_spool');

    // No spool items - status OK.
    $result = $this->runSensor('simplenews_pending');
    $this->assertEqual($result->getValue(), 0);

    // Crate a spool item in state pending.
    simplenews_save_spool(array(
      'mail' => 'mail@example.com',
      'entity_type' => 'node',
      'entity_id' => 1,
      'newsletter_id' => 'default',
      'snid' => 1,
      'data' => array('data' => 'data'),
    ));

    $result = $this->runSensor('simplenews_pending');
    $this->assertEqual($result->getValue(), 1);
  }

  /**
   * Executes a sensor and returns the result.
   *
   * @param string $sensor_name
   *   Name of the sensor to execute.
   *
   * @return \Drupal\monitoring\Result\SensorResultInterface
   *   The sensor result.
   */
  protected function runSensor($sensor_name) {
    // Make sure the sensor is enabled.
    monitoring_sensor_manager()->enableSensor($sensor_name);
    return monitoring_sensor_run($sensor_name, TRUE, TRUE);
  }
}
