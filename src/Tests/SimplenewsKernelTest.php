<?php

/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsKernelTest.
 */

namespace Drupal\simplenews\Tests;

use Drupal\simpletest\KernelTestBase;

/**
 * Unit tests for certain functions.
 *
 * @group simplenews
 */
class SimplenewsKernelTest extends KernelTestBase {

  static function getInfo() {
    return array(
      'name' => 'Unit tests',
      'description' => 'Unit tests for certain functions.',
      'group' => 'Simplenews',
    );
  }

  public function testMasking() {
    module_load_include('inc', 'simplenews', 'includes/simplenews.subscription');

    $this->assertEqual('t*****@e*****.org', simplenews_mask_mail('test@example.org'));
    $this->assertEqual('t*****@e*****.org', simplenews_mask_mail('t@example.org'));
    $this->assertEqual('t*****@t*****.org', simplenews_mask_mail('t@test.example.org'));
    $this->assertEqual('t*****@e*****', simplenews_mask_mail('t@example'));

  }
}
