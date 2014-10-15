<?php
/**
 * @file
 * Contains \Drupal\simplenews\Tests\SimplenewsSynchronizeFieldsFormTest.
 */

namespace Drupal\simplenews\Tests;

use Drupal\simplenews\Entity\Subscriber;
use Drupal\user\Entity\User;

/**
 * Tests that shared fields are synchronized when using forms.
 *
 * @group simplenews
 */
class SimplenewsSynchronizeFieldsFormTest extends SimplenewsTestBase {

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
      'administer simplenews settings',
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

    // Unset the sync setting and assert field is not synced.
    $this->drupalPostForm('admin/config/people/simplenews/settings/subscriber', array('simplenews_sync_account' => FALSE), t('Save configuration'));

    $unsynced_value = $this->randomMachineName();
    $this->drupalPostForm('admin/people/simplenews/edit/' . $subscriber->id(), array('field_shared[0][value]' => $unsynced_value), t('Save'));
    $this->drupalGet('admin/people/simplenews/edit/' . $subscriber->id());
    $this->assertRaw($unsynced_value);

    $this->user = User::load($this->user->id());
    $this->assertEqual($this->user->field_shared->value, $new_value);
    $this->assertNotEqual($this->user->field_shared->value, $unsynced_value);
  }

}
