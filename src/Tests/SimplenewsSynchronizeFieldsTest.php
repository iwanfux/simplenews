<?php
/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsSynchronizeFieldsTest.
 */

namespace Drupal\simplenews\Tests;

use Drupal\field\Entity\FieldInstanceConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simpletest\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Tests that fields shared by user account and subscribers are synchronized.
 *
 * @group simplenews
 */
class SimplenewsSynchronizeFieldsTest extends KernelTestBase {
  /**
   * Modules to enable.
   *
   * @var array()
   */
  public static $modules = array('simplenews', 'user', 'field', 'system');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installSchema('system', array('sequences', 'sessions'));
  }

  /**
   * Tests that shared fields are synchronized.
   */
  public function testSynchronizeFields() {
    // Create and attach a field only to subscriber.
    $this->addField('string', 'field_on_subscriber', 'simplenews_subscriber');

    // Create and attach a field only to user.
    $this->addField('string', 'field_on_user', 'user');

    // Create and attach a field to both.
    $this->addField('string', 'field_on_both', 'simplenews_subscriber');
    $this->addField('string', 'field_on_both', 'user');

    // Create a user and a subscriber.
    /** @var \Drupal\user\Entity\User $user */
    $user = User::create(array(
      'field_on_user' => 'foo',
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
      'created' => 1000,
    ));
    $user->save();
    /** @var \Drupal\simplenews\Entity\Subscriber $subscriber */
    $subscriber = Subscriber::create(array(
      'field_on_subscriber' => 'foo',
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
      'created' => 2000,
    ));
    $subscriber->save();

    // Update the fields on the subscriber.
    $subscriber->set('field_on_both', 'bar');
    $subscriber->set('created', 3000);
    $subscriber->save();

    // Assert that (only) the shared field is also updated on the user.
    $user = User::load($user->id());
    $this->assertEqual($user->get('field_on_both')->value, 'bar');
    $this->assertEqual($user->get('created')->value, 1000);

    // Update the fields on the user.
    $user->set('field_on_both', 'baz');
    $user->set('created', 4000);
    $user->save();

    // Assert that (only) the shared field is also updated on the subscriber.
    $subscriber = Subscriber::load($subscriber->id());
    $this->assertEqual($subscriber->get('field_on_both')->value, 'baz');
    $this->assertEqual($subscriber->get('created')->value, 3000);
  }

  /**
   * Tests that new entities copy values from corresponding user/subscriber.
   */
  public function testSetSharedFieldAutomatically() {
    $this->fail('To be implemented.');
    // Create and attach a field only to subscriber.

    // Create and attach a field only to user.

    // Create and attach a field to both.

    // Create a user with values for the fields.

    // Create a subscriber.

    // Assert that the shared field already has a value.

    // Create a subscriber with values for the fields.

    // Create a user.

    // Assert that the shared field already has a value.
  }

  /**
   * Creates and saves a field storage and instance.
   *
   * @param string $type
   *   The field type.
   * @param string $field_name
   *   The name of the new field.
   * @param string $entity_type
   *   The ID of the entity type to attach the field instance to.
   * @param string $bundle
   *   (optional) The entity bundle. Defaults to same as $entity_type.
   */
  protected function addField($type, $field_name, $entity_type, $bundle = NULL) {
    if (!isset($bundle)) {
      $bundle = $entity_type;
    }
    FieldStorageConfig::create(array(
      'name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
    ))->save();
    FieldInstanceConfig::create(array(
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ))->save();
  }
}
