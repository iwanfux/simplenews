<?php

/**
 * @file
 * Simplenews send test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\node\Entity\Node;

/**
 * Test cases for creating and sending newsletters.
 *
 * @group simplenews
 */
class SimplenewsSendTest extends SimplenewsTestBase {

  function setUp() {
    parent::setUp();

    $admin_user = $this->drupalCreateUser(array(
      'administer newsletters',
      'send newsletter',
      'administer nodes',
      'administer simplenews subscriptions',
      'create simplenews content',
      'edit any simplenews content',
      'view own unpublished content',
      'delete any simplenews content',
    ));
    $this->drupalLogin($admin_user);

    // Subscribe a few users.
    $this->setUpSubscribers(5);
  }

  /**
   * Creates and sends a node using the API.
   */
  function testProgrammaticNewsletter() {
    // Create a very basic node.
    $node = Node::create(array(
      'type' => 'simplenews',
      'title' => $this->randomString(10),
      'uid' => 0,
      'status' => 1
    ));
    $node->simplenews_issue->target_id = $this->getRandomNewsletter();
    $node->simplenews_issue->handler = 'simplenews_all';
    $node->save();

    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');

    // Send the node.
    simplenews_add_node_to_spool($node);
    $node->save();

    // Send mails.
    simplenews_mail_spool();
    simplenews_clear_spool();
    // Update sent status for newsletter admin panel.
    simplenews_send_status_update();

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $node->getTitle(), t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have been received a mail'));

    // Create another node.
    $node = Node::create(array(
      'type' => 'simplenews',
      'title' => $this->randomString(10),
      'uid' => 0,
      'status' => 1
    ));
    $node->simplenews_issue->target_id = $this->getRandomNewsletter();
    $node->simplenews_issue->handler = 'simplenews_all';
    $node->save();

    // Send the node.
    simplenews_add_node_to_spool($node);
    $node->save();

    // Make sure that they have been added.
    $this->assertEqual(simplenews_count_spool(), 5);

    // Mark them as pending, fake a currently running send process.
    $this->assertEqual(count(simplenews_get_spool(2)), 2);

    // Those two should be excluded from the count now.
    $this->assertEqual(simplenews_count_spool(), 3);

    // Get two additional spool entries.
    $this->assertEqual(count(simplenews_get_spool(2)), 2);

    // Now only one should be returned by the count.
    $this->assertEqual(simplenews_count_spool(), 1);
  }

  /**
   * Send a newsletter using cron.
   */
  function testSendNowNoCron() {
    // Disable cron.
    $config = \Drupal::config('simplenews.settings');
    $config->set('mail.use_cron', FALSE);
    $config->save();

    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm(NULL, $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send one test newsletter to the test address'));
    $this->assertText(t('Send newsletter'));
    $this->assertNoText(t('Send newsletter when published'), t('Send on publish is not shown for published nodes.'));

    // Verify state.
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));

    // Send now.
    $this->drupalPostForm(NULL, array('send' => SIMPLENEWS_COMMAND_SEND_NOW), t('Submit'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $node->simplenews_issue->status, t('Newsletter sending finished'));

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $edit['title[0][value]'], t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have been received a mail'));

    $this->assertEqual(5, $node->simplenews_issue->sent_count, 'subscriber count is correct');
  }

  /**
   * Send a newsletter using cron.
   */
  function testSendMultipleNoCron() {
    // Disable cron.
    $config = \Drupal::config('simplenews.settings');
    $config->set('mail.use_cron', FALSE);
    $config->save();

    // Verify that the newsletter settings are shown.
    $nodes = array();
    for ($i = 0; $i < 3; $i++) {
      $this->drupalGet('node/add/simplenews');
      $this->assertText(t('Create Newsletter Issue'));

      $edit = array(
        'title[0][value]' => $this->randomString(10),
        'simplenews_issue' => 'default',
      );
      // The last newsletter shouldn't be published.
      if ($i != 2) {
        $this->drupalPostForm(NULL, $edit, ('Save and publish'));
      } else {
        $this->drupalPostForm(NULL, $edit, ('Save as unpublished'));
      }
      $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
      $nodes[] = Node::load($matches[1]);

      // Verify state.
      $node = current($nodes);
      $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));
    }

    // Send the first and last newsletter on the newsletter overview.
    list ($first, $second, $unpublished) = $nodes;

    $edit = array(
      'issues[' . $first->id() . ']' => $first->id(),
      'issues[' . $unpublished->id() . ']' => $unpublished->id(),
      'operation' => 'activate',
    );
    $this->drupalPostForm('admin/content/simplenews', $edit, t('Update'));

    $this->assertText(t('Sent the following newsletters: @title.', array('@title' => $first->getTitle())));
    $this->assertText(t('Newsletter @title is unpublished and will be sent on publish.', array('@title' => $unpublished->getTitle())));

    // Verify states.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $first = Node::load($first->id());
    $second = Node::load($first->id());
    $unpublished = Node::load($unpublished->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $first->simplenews_issue->status, t('First Newsletter sending finished'));
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $second->simplenews_issue->status, t('Second Newsletter not sent'));
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PUBLISH, $unpublished->simplenews_issue->status, t('Newsletter set to send on publish'));

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $first->getTitle(), t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have received a mail'));
  }

  /**
   * Send a newsletter using cron and a low throttle.
   */
  function testSendNowCronThrottle() {
    $config = \Drupal::config('simplenews.settings');
    $config->set('mail.throttle', 3);
    $config->save();

    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm(NULL, $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send one test newsletter to the test address'));
    $this->assertText(t('Send newsletter'));
    $this->assertNoText(t('Send newsletter when published'), t('Send on publish is not shown for published nodes.'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));

    // Send now.
    $this->drupalPostForm(NULL, array('send' => SIMPLENEWS_COMMAND_SEND_NOW), t('Submit'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PENDING, $node->simplenews_issue->status, t('Newsletter sending pending.'));

    // Verify that no mails have been sent yet.
    $mails = $this->drupalGetMails();
    $this->assertEqual(0, count($mails), t('No mails were sent yet.'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(5, $spooled, t('5 mails have been added to the mail spool'));

    // Run cron for the first time.
    simplenews_cron();

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PENDING, $node->simplenews_issue->status, t('Newsletter sending pending.'));
    $this->assertEqual(3, $node->simplenews_issue->sent_count, 'subscriber count is correct');

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(2, $spooled, t('2 mails remaining in spool.'));

    // Run cron for the second time.
    simplenews_cron();

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $node->simplenews_issue->status, t('Newsletter sending finished.'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(0, $spooled, t('No mails remaining in spool.'));

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $edit['title[0][value]'], t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have been received a mail'));
    $this->assertEqual(5, $node->simplenews_issue->sent_count);
  }

  /**
   * Send a newsletter without using cron.
   */
  function testSendNowCron() {

    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => 'default',
    );
    // Try preview first.
    $this->drupalPostForm(NULL, $edit, t('Preview'));

    $this->clickLink(t('Back to content editing'));

    // Then save.
    $this->drupalPostForm(NULL, array(), t('Save and publish'));

    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send one test newsletter to the test address'));
    $this->assertText(t('Send newsletter'));
    $this->assertNoText(t('Send newsletter when published'), t('Send on publish is not shown for published nodes.'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));

    // Send now.
    $this->drupalPostForm(NULL, array('send' => SIMPLENEWS_COMMAND_SEND_NOW), t('Submit'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PENDING, $node->simplenews_issue->status, t('Newsletter sending pending.'));

    // Verify that no mails have been sent yet.
    $mails = $this->drupalGetMails();
    $this->assertEqual(0, count($mails), t('No mails were sent yet.'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(5, $spooled, t('5 mails have been added to the mail spool'));

    // Check warning message on node edit form.
    $this->clickLink(t('Edit'));
    $this->assertText(t('This newsletter issue is currently being sent. Any changes will be reflected in the e-mails which have not been sent yet.'));

    // Run cron.
    simplenews_cron();

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $node->simplenews_issue->status, t('Newsletter sending finished.'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(0, $spooled, t('No mails remaining in spool.'));

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $edit['title[0][value]'], t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have been received a mail'));
  }

  /**
   * Send a newsletter on publish without using cron.
   */
  function testSendPublishNoCron() {
    // Disable cron.
    $config = \Drupal::config('simplenews.settings');
    $config->set('mail.use_cron', FALSE);
    $config->save();

    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm(NULL, $edit, ('Save as unpublished'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send one test newsletter to the test address'));
    $this->assertText(t('Send newsletter when published'), t('Send on publish is shown'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));

    // Send now.
    $this->drupalPostForm(NULL, array('send' => SIMPLENEWS_COMMAND_SEND_PUBLISH), t('Submit'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache(array($node->id()));
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PUBLISH, $node->simplenews_issue->status, t('Newsletter set up for sending on publish.'));

    $this->clickLink(t('Edit'));
    $this->drupalPostForm(NULL, array(), t('Save and publish'));

    // Send on publish does not send immediately.
    \Drupal::entityManager()->getStorage('node')->resetCache(array($node->id()));
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    simplenews_mail_attempt_immediate_send(array(), FALSE);

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache(array($node->id()));
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $node->simplenews_issue->status, t('Newsletter sending finished'));
    // @todo test sent subscriber count.
    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $edit['title[0][value]'], t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have been received a mail'));
  }

  function testUpdateNewsletter() {
    // Create a second newsletter.
    $this->drupalGet('admin/config/services/simplenews');
    $this->clickLink(t('Add newsletter'));
    $edit = array(
      'name' => $this->randomString(10),
      'id' => strtolower($this->randomMachineName(10)),
      'description' => $this->randomString(20),
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertText(t('Newsletter @name has been added', array('@name' => $edit['name'])));

    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    $first_newsletter_id = $this->getRandomNewsletter();

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => $first_newsletter_id,
    );
    $this->drupalPostForm(NULL, $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created.');

    // Verify newsletter.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($matches[1]);
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter sending not started.'));
    $this->assertEqual($first_newsletter_id, $node->simplenews_issue->target_id);

    do {
      $second_newsletter_id = $this->getRandomNewsletter();
    } while ($first_newsletter_id == $second_newsletter_id);


    $this->clickLink(t('Edit'));
    $update = array(
      'simplenews_issue' => $second_newsletter_id,
    );
    $this->drupalPostForm(NULL, $update, t('Save and keep published'));

    // Verify newsletter.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter sending not started.'));
    $this->assertEqual($second_newsletter_id, $node->simplenews_issue->target_id, t('Newsletter has newsletter_id ' . $second_newsletter_id . '.'));
  }

  /**
   * Create a newsletter, send mails and then delete.
   */
  function testDelete() {
    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/simplenews');
    $this->assertText(t('Create Newsletter Issue'));

    // Prevent deleting the mail spool entries automatically.
    $config = \Drupal::config('simplenews.settings');
    $config->set('mail.spool_expire', 1);
    $config->save();

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm(NULL, $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send one test newsletter to the test address'));
    $this->assertText(t('Send newsletter'));
    $this->assertNoText(t('Send newsletter when published'), t('Send on publish is not shown for published nodes.'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_NOT, $node->simplenews_issue->status, t('Newsletter not sent yet.'));

    // Send now.
    $this->drupalPostForm(NULL, array('send' => SIMPLENEWS_COMMAND_SEND_NOW), t('Submit'));

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_PENDING, $node->simplenews_issue->status, t('Newsletter sending pending.'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(5, $spooled, t('5 mails remaining in spool.'));

    // Verify that deleting isn't possible right now.
    $this->clickLink(t('Edit'));
    $this->assertText(t("You can't delete this newsletter because it has not been sent to all its subscribers."));
    $this->assertNoText(t('Delete'));

    // Send mails.
    simplenews_cron();

    // Verify state.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $node = Node::load($node->id());
    $this->assertEqual(SIMPLENEWS_STATUS_SEND_READY, $node->simplenews_issue->status, t('Newsletter sending finished'));

    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(5, $spooled, t('Mails are kept in simplenews_mail_spool after being sent.'));

    // Verify mails.
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('All mails were sent.'));
    foreach ($mails as $mail) {
      $this->assertEqual($mail['subject'], '[Default newsletter] ' . $edit['title[0][value]'], t('Mail has correct subject'));
      $this->assertTrue(isset($this->subscribers[$mail['to']]), t('Found valid recipient'));
      unset($this->subscribers[$mail['to']]);
    }
    $this->assertEqual(0, count($this->subscribers), t('all subscribers have received a mail'));

    // Update timestamp to simulate pending lock expiration.
    db_update('simplenews_mail_spool')
      ->fields(array(
        'timestamp' => simplenews_get_expiration_time() - 1,
      ))
      ->execute();

    // Verify that kept mail spool rows are not re-sent.
    simplenews_cron();
    simplenews_get_spool();
    $mails = $this->drupalGetMails();
    $this->assertEqual(5, count($mails), t('No additional mails have been sent.'));

    // Now delete.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $this->drupalGet($node->getSystemPath('edit-form'));
    $this->clickLink(t('Delete'));
    $this->drupalPostForm(NULL, array(), t('Delete'));

    // Verify.
    \Drupal::entityManager()->getStorage('node')->resetCache();
    $this->assertFalse(Node::load($node->id()));
    $spooled = db_query('SELECT COUNT(*) FROM {simplenews_mail_spool} WHERE entity_id = :nid AND entity_type = :type', array(':nid' => $node->id(), ':type' => 'node'))->fetchField();
    $this->assertEqual(0, $spooled, t('No mails remaining in spool.'));
  }

}