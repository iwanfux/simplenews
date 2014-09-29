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
use Drupal\user\Entity\User;

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
    $this->user->set('field_shared', $this->randomMachineName());
    $this->user->save();
  }

  /**
   * Tests that fields are synchronized using the Subscriber form.
   */
  public function testSubscriberFormFieldSync() {
    // Create a subscriber for the user.
    $subscriber = Subscriber::create(array(
      // Subscribers are linked to users by the uid field.
      'uid' => $this->user->id(),
      'mail' => 'anything@example.com',
    ));
    $subscriber->save();

    // Edit subscriber field and assert user field is changed accordingly.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/people/simplenews/edit/' . $subscriber->id());
    $this->assertField('field_shared[0][value]');
    $this->assertRaw($this->user->field_shared->value);

    $new_value = $this->randomMachineName();
    $this->drupalPostForm(NULL, array('field_shared[0][value]' => $new_value), t('Save'));
    $this->drupalGet('admin/people/simplenews/edit/' . $subscriber->id());
    $this->assertRaw($new_value);

    $this->user = User::load($this->user->id());
    $this->assertEqual($this->user->field_shared->value, $new_value);
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
    entity_get_form_display($entity_type, $bundle, 'default')
      ->setComponent($field_name, array(
        'type' => 'string_textfield',
      ))->save();
  }

}
