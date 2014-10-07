<?php
/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsSynchronizeFieldsTest.
 */

namespace Drupal\simplenews\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
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
  public static $modules = array('simplenews', 'user', 'field', 'system', 'language');

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('simplenews_subscriber');
    $this->installSchema('system', array('sequences', 'sessions'));
    \Drupal::config('system.mail')->set('interface.default', 'test_mail_collector')->save();
    \Drupal::config('simplenews.settings')
      ->set('subscriber.sync_account', TRUE)
      ->save();
    ConfigurableLanguage::create(array('id' => 'fr'))->save();
  }

  /**
   * Tests that creating/updating User updates existing Subscriber base fields.
   */
  public function testSynchronizeBaseFields() {
    // Create subscriber.
    /** @var \Drupal\simplenews\Entity\Subscriber $subscriber */
    $subscriber = Subscriber::create(array(
      'mail' => 'user@example.com',
    ));
    $subscriber->save();

    // Create user with same email.
    /** @var \Drupal\user\Entity\User $user */
    $user = User::create(array(
      'mail' => 'user@example.com',
      'preferred_langcode' => 'fr',
    ));
    $user->save();

    // Assert that subscriber's fields are updated.
    $subscriber = Subscriber::load($subscriber->id());
    $this->assertEqual($subscriber->getUserId(), $user->id());
    $this->assertEqual($subscriber->getLangcode(), 'fr');
    $this->assertFalse($subscriber->getStatus());

    // Update user fields.
    $user->setEmail('user2@example.com');
    $user->set('preferred_langcode', 'en');
    $user->activate();
    $user->save();

    // Assert that subscriber's fields are updated again.
    $subscriber = Subscriber::load($subscriber->id());
    $this->assertEqual($subscriber->getMail(), 'user2@example.com');
    $this->assertEqual($subscriber->getLangcode(), 'en');
    $this->assertTrue($subscriber->getStatus());

    // Status is not synced if sync_account is not set.
    \Drupal::config('simplenews.settings')->set('subscriber.sync_account', FALSE)->save();
    $user->block();
    $user->save();
    $subscriber = Subscriber::load($subscriber->id());
    $this->assertTrue($subscriber->getStatus());
  }

  /**
   * Tests that shared fields are synchronized.
   */
  public function testSynchronizeConfigurableFields() {
    // Create and attach a field to both.
    $this->addField('string', 'field_on_both', 'simplenews_subscriber');
    $this->addField('string', 'field_on_both', 'user');

    // Create a user and a subscriber.
    /** @var \Drupal\simplenews\Entity\Subscriber $subscriber */
    $subscriber = Subscriber::create(array(
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
      'created' => 2000,
    ));
    $subscriber->save();
    /** @var \Drupal\user\Entity\User $user */
    $user = User::create(array(
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
      'created' => 1000,
    ));
    $user->save();

    // Update the fields on the subscriber.
    $subscriber = Subscriber::load($subscriber->id());
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
    // Create and attach a field to both.
    $this->addField('string', 'field_on_both', 'simplenews_subscriber');
    $this->addField('string', 'field_on_both', 'user');

    // Create a user with values for the fields.
    /** @var \Drupal\user\Entity\User $user */
    $user = User::create(array(
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
    ));
    $user->save();

    // Create a subscriber.
    /** @var \Drupal\simplenews\Entity\Subscriber $subscriber */
    $subscriber = Subscriber::create(array(
        'mail' => 'user@example.com',
    ));

    // Assert that the shared field already has a value.
    $this->assertEqual($subscriber->get('field_on_both')->value, $user->get('field_on_both')->value);

    // Create a subscriber with values for the fields.
    $subscriber = Subscriber::create(array(
      'field_on_both' => 'bar',
      'mail' => 'user@example.com',
    ));
    $subscriber->save();

    // Create a user.
    $user = User::create(array(
      'mail' => 'user@example.com',
    ));

    // Assert that the shared field already has a value.
    $this->assertEqual($user->get('field_on_both')->value, $subscriber->get('field_on_both')->value);
  }

  /**
   * Unsets the sync setting and asserts that fields are not synced.
   */
  public function testDisableSync() {
    // Disable sync.
    \Drupal::config('simplenews.settings')->set('subscriber.sync_account', FALSE)->save();

    // Create and attach a field to both.
    $this->addField('string', 'field_on_both', 'simplenews_subscriber');
    $this->addField('string', 'field_on_both', 'user');

    // Create a user with a value for the field.
    $user = User::create(array(
      'name' => 'user',
      'field_on_both' => 'foo',
      'mail' => 'user@example.com',
    ));
    $user->save();

    // Create a subscriber.
    $subscriber = Subscriber::create(array(
      'mail' => 'user@example.com',
    ));

    // Assert that the shared field does not get the value from the user.
    $this->assertNull($subscriber->get('field_on_both')->value);

    // Update the subscriber and assert that it is not synced to the user.
    $subscriber->set('field_on_both', 'bar');
    $subscriber->save();
    $user = User::load($user->id());
    $this->assertEqual($user->get('field_on_both')->value, 'foo');

    // Create a subscriber with a value for the field.
    $subscriber = Subscriber::create(array(
      'field_on_both' => 'foo',
      'mail' => 'user2@example.com',
    ));
    $subscriber->save();

    // Create a user.
    $user = User::create(array(
      'name' => 'user2',
      'mail' => 'user2@example.com',
    ));

    // Assert that the shared field does not get the value from the subscriber.
    $this->assertNull($user->get('field_on_both')->value);

    // Update the user and assert that it is not synced to the subscriber.
    $user->set('field_on_both', 'bar');
    $user->save();
    $subscriber = Subscriber::load($subscriber->id());
    $this->assertEqual($subscriber->get('field_on_both')->value, 'foo');
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
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => $type,
    ))->save();
    FieldConfig::create(array(
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ))->save();
  }
}
