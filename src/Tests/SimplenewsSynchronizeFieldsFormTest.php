<?php
/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsSynchronizeFieldsFormTest.
 */

namespace Drupal\simplenews\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simpletest\WebTestBase;

/**
 * Tests that shared fields are synchronized when using forms.
 *
 * @group simplenews
 */
class SimplenewsSynchronizeFieldsFormTest extends WebTestBase {

  /**
   * Modules to enable.
   * 
   * @var array
   */
  public static $modules = array('field', 'simplenews');

  /**
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Add a field to both entities.
    $this->addField('string', 'field_shared', 'user');
    $this->addField('string', 'field_shared', 'simplenews_subscriber');

    // Create a user.
    $this->user = $this->drupalCreateUser(array(
      'administer simplenews subscriptions',
    ));
    $this->user->setEmail('user@example.com');
    $this->user->set('field_shared', 'foo');
    $this->user->save();
  }

  /**
   * Tests that fields are synchronized using the Subscriber form.
   */
  public function testSubscriberFormFieldSync() {
    // Create a subscriber for the user.
    $subscriber = Subscriber::create(array(
      'field_shared' => 'foo',
      'uid' => $this->user->id(),
    ));

    // Evaluate the edit form.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/people/simplenews/add');
    $this->assertField('field_shared[0]');
    $this->assertText('foo');
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
    FieldConfig::create(array(
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ))->save();
  }

}
