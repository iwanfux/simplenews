<?php

/**
 * @file
 * Simplenews source test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\Component\Utility\String;
use Drupal\node\Entity\Node;
use Drupal\simplenews\Entity\Newsletter;
use \Drupal\simplenews\Source\SourceTest;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Test cases for creating and sending newsletters.
 *
 * @group simplnews
 */
class SimplenewsSourceTest extends SimplenewsTestBase {

  function setUp() {
    parent::setUp();

    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');

    // Create the filtered_html text format.
    $filtered_html_format = entity_create('filter_format', array(
      'format' => 'filtered_html',
      'name' => 'Filtered HTML',
      'weight' => 0,
      'filters' => array(
        // URL filter.
        'filter_url' => array(
          'weight' => 0,
          'status' => 1,
        ),
        // HTML filter.
        'filter_html' => array(
          'weight' => 1,
          'status' => 1,
          'allowed-values'
        ),
        // Line break filter.
        'filter_autop' => array(
          'weight' => 2,
          'status' => 1,
        ),
        // HTML corrector filter.
        'filter_htmlcorrector' => array(
          'weight' => 10,
          'status' => 1,
        ),
      ),
    ));
    $filtered_html_format->save();

    // Refresh permissions.
    $this->checkPermissions(array(), TRUE);

    $admin_user = $this->drupalCreateUser(array(
      'administer newsletters',
      'send newsletter',
      'administer nodes',
      'administer simplenews subscriptions',
      'create simplenews content',
      'edit any simplenews content',
      'view own unpublished content',
      'delete any simplenews content',
      'administer simplenews settings',
      $filtered_html_format->getPermissionName()));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that sending a minimal implementation of the source interface works.
   */
  function testSendMinimalSourceImplementation() {

    // Create a basic plaintext test source and send it.
    $plain_source = new SourceTest('plain');
    simplenews_send_source($plain_source);
    $mails = $this->drupalGetMails();
    $mail = $mails[0];

    // Assert resulting mail.
    $this->assertEqual('simplenews_node', $mail['id']);
    $this->assertEqual('simplenews', $mail['module']);
    $this->assertEqual('node', $mail['key']);
    $this->assertEqual($plain_source->getRecipient(), $mail['to']);
    $this->assertEqual($plain_source->getFromAddress(), $mail['from']);
    $this->assertEqual($plain_source->getFromFormatted(), $mail['reply-to']);
    $this->assertEqual($plain_source->getLanguage(), $mail['langcode']);
    $this->assertTrue($mail['params']['plain']);

    $this->assertFalse(isset($mail['params']['plaintext']));
    $this->assertFalse(isset($mail['params']['attachments']));

    $this->assertEqual($plain_source->getSubject(), $mail['subject']);
    $this->assertTrue(strpos($mail['body'], 'the plain body') !== FALSE);
    $this->assertTrue(strpos($mail['body'], 'the plain footer') !== FALSE);

    $html_source = new SourceTest('html');
    simplenews_send_source($html_source);
    $mails = $this->drupalGetMails();
    $mail = $mails[1];

    // Assert resulting mail.
    $this->assertEqual('simplenews_node', $mail['id']);
    $this->assertEqual('simplenews', $mail['module']);
    $this->assertEqual('node', $mail['key']);
    $this->assertEqual($plain_source->getRecipient(), $mail['to']);
    $this->assertEqual($plain_source->getFromAddress(), $mail['from']);
    $this->assertEqual($plain_source->getFromFormatted(), $mail['reply-to']);
    $this->assertEqual($plain_source->getLanguage(), $mail['langcode']);
    $this->assertEqual(NULL, $mail['params']['plain']);

    $this->assertTrue(isset($mail['params']['plaintext']));
    $this->assertTrue(strpos($mail['params']['plaintext'], 'the plain body') !== FALSE);
    $this->assertTrue(strpos($mail['params']['plaintext'], 'the plain footer') !== FALSE);
    $this->assertTrue(isset($mail['params']['attachments']));
    $this->assertEqual('example://test.png', $mail['params']['attachments'][0]['uri']);

    $this->assertEqual($plain_source->getSubject(), $mail['subject']);
    $this->assertTrue(strpos($mail['body'], 'the body') !== FALSE);
    $this->assertTrue(strpos($mail['body'], 'the footer') !== FALSE);
  }

  /**
   * Test sending a newsletter to 100 recipients with caching enabled.
   */
  function testSendCaching() {

    $this->setUpSubscribers(100);
    // Enable build caching.
    $edit = array(
      'simplenews_source_cache' => '\Drupal\simplenews\Source\SourceCacheBuild',
    );
    $this->drupalPostForm('admin/config/services/simplenews/settings/mail', $edit, t('Save configuration'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);
    // Unsubscribe one of the recipients to make sure that he doesn't receive
    // the mail.
    simplenews_unsubscribe(array_shift($this->subscribers), $this->getRandomNewsletter(), FALSE, 'test');

    $before = microtime(TRUE);
    simplenews_mail_spool();
    $after = microtime(TRUE);

    // Make sure that 99 mails have been sent.
    $this->assertEqual(99, count($this->drupalGetMails()));

    // Test that tokens are correctly replaced.
    $newsletter_id = $this->getRandomNewsletter();
    foreach (array_slice($this->drupalGetMails(), 0, 3) as $mail) {
      // Make sure that the same mail was used in the body token as it has been
      // sent to. Also verify that the mail is plaintext.
      $this->assertTrue(strpos($mail['body'], '*' . $mail['to'] . '*') !== FALSE);
      $this->assertFalse(strpos($mail['body'], '<strong>'));
      // Make sure the body is only attached once.
      $this->assertEqual(1, preg_match_all('/Mail token/', $mail['body'], $matches));

      $this->assertTrue(strpos($mail['body'], t('Unsubscribe from this newsletter')));
      // Make sure the mail has the correct unsubscribe hash.
      $hash = simplenews_generate_hash($mail['to'], 'remove');
      $this->assertTrue(strpos($mail['body'], $hash), 'Correct hash is used in footer');
      $this->assertTrue(strpos($mail['headers']['List-Unsubscribe'], $hash), 'Correct hash is used in header');
    }

    // Report time. @todo: Find a way to actually do some assertions here.
    $this->pass(t('Mails have been sent in @sec seconds with build caching enabled.', array('@sec' => round($after - $before, 3))));
  }

  /**
   * Send a newsletter with the HTML format.
   */
  function testSendHTML() {
    $this->setUpSubscribers(5);

    // Use custom testing mail system to support HTML mails.
    $mail_config = \Drupal::config('system.mail');
    $mail_config->set('interface.default', 'test_simplenews_html_mail');
    $mail_config->save();

    // Set the format to HTML.
    $this->drupalGet('admin/config/services/simplenews');
    $this->clickLink(t('Edit'));
    $edit_newsletter = array(
      'format' => 'html',
      // Use umlaut to provoke mime encoding.
      'from_name' => 'Drupäl',
      // @todo: This shouldn't be necessary, default value is missing. Probably
      // should not be required.
      'from_address' => $this->randomEmail(),
      // Request a confirmation receipt.
      'receipt' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit_newsletter, t('Save'));
    $this->clickLink(t('Edit'));

    $edit = array(
      'title[0][value]' => $this->randomString(),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);
    // Send mails.
    simplenews_mail_spool();

    // Make sure that 5 mails have been sent.
    $this->assertEqual(5, count($this->drupalGetMails()));

    // Test that tokens are correctly replaced.
    foreach (array_slice($this->drupalGetMails(), 0, 3) as $mail) {
      debug($mail['body']);
      // Verify title.
      $this->assertTrue(strpos($mail['body'], '<h2>' . $node->getTitle() . '</h2>') !== FALSE);

      // Make sure that the same mail was used in the body token as it has been
      // sent to.
      $this->assertTrue(strpos($mail['body'], '<strong>' . $mail['to'] . '</strong>') !== FALSE);

      // Make sure the body is only attached once.
      $this->assertEqual(1, preg_match_all('/Mail token/', $mail['body'], $matches));

      // Check the plaintext version.
      $this->assertTrue(strpos($mail['params']['plaintext'], $mail['to']) !== FALSE);
      $this->assertFalse(strpos($mail['params']['plaintext'], '<strong>'));
      // Make sure the body is only attached once.
      $this->assertEqual(1, preg_match_all('/Mail token/', $mail['params']['plaintext'], $matches));

      // Make sure formatted address is properly encoded.
      $from = '"' . addslashes(mime_header_encode($edit_newsletter['from_name'])) . '" <' . $edit_newsletter['from_address'] . '>';
      $this->assertEqual($from, $mail['reply-to']);
      // And make sure it won't get encoded twice.
      $this->assertEqual($from, mime_header_encode($mail['reply-to']));

      // @todo: Improve this check, there are currently two spaces, not sure
      // where they are coming from.
      $this->assertTrue(strpos($mail['body'], 'class="newsletter-footer"'));

      // Verify receipt headers.
      $this->assertEqual($mail['headers']['Disposition-Notification-To'], $edit_newsletter['from_address']);
      $this->assertEqual($mail['headers']['X-Confirm-Reading-To'], $edit_newsletter['from_address']);
    }
  }

  /**
   * Send a issue with the newsletter set to hidden.
   */
  function testSendHidden() {
    $this->setUpSubscribers(5);

    // Set the format to HTML.
    $this->drupalGet('admin/config/services/simplenews');
    $this->clickLink(t('Edit'));
    $edit = array(
      'opt_inout' => 'hidden',
      // @todo: This shouldn't be necessary.
      'from_address' => $this->randomEmail(),
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);
    // Send mails.
    simplenews_mail_spool();

    // Make sure that 5 mails have been sent.
    $this->assertEqual(5, count($this->drupalGetMails()));

    // Test that tokens are correctly replaced.
    foreach (array_slice($this->drupalGetMails(), 0, 3) as $mail) {
      // Verify the footer is not displayed for hidden newsletters.
      $this->assertFalse(strpos($mail['body'], t('Unsubscribe from this newsletter')));
    }
  }

  /**
   * Test with disabled caching.
   */
  function testSendNoCaching() {
    $this->setUpSubscribers(100);
    // Disable caching.
    $edit = array(
      'simplenews_source_cache' => '\Drupal\simplenews\Source\SourceCacheNone',
    );
    $this->drupalPostForm('admin/config/services/simplenews/settings/mail', $edit, t('Save configuration'));

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);

    $before = microtime(TRUE);
    simplenews_mail_spool();
    $after = microtime(TRUE);

    // Make sure that 100 mails have been sent.
    $this->assertEqual(100, count($this->drupalGetMails()));

    // Test that tokens are correctly replaced.
    foreach (array_slice($this->drupalGetMails(), 0, 3) as $mail) {
      // Make sure that the same mail was used in the body token as it has been
      // sent to. Also verify that the mail is plaintext.
      $this->assertTrue(strpos($mail['body'], '*' . $mail['to'] . '*') !== FALSE);
      $this->assertFalse(strpos($mail['body'], '<strong>'));
      // Make sure the body is only attached once.
      $this->assertEqual(1, preg_match_all('/Mail token/', $mail['body'], $matches));
    }

    // Report time. @todo: Find a way to actually do some assertions here.
    $this->pass(t('Mails have been sent in @sec seconds with caching disabled.', array('@sec' => round($after - $before, 3))));
  }

  /**
   * Test with disabled caching.
   */
  function testSendMissingNode() {
    $this->setUpSubscribers(1);

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);

    // Delete the node manually in the database.
    db_delete('node')
      ->condition('nid', $node->id())
      ->execute();
    db_delete('node_revision')
      ->condition('nid', $node->id())
      ->execute();
    \Drupal::entityManager()->getStorage('node')->resetCache();

    simplenews_mail_spool();

    // Make sure that no mails have been sent.
    $this->assertEqual(0, count($this->drupalGetMails()));

    $spool_row = db_query('SELECT * FROM {simplenews_mail_spool}')->fetchObject();
    $this->assertEqual(SIMPLENEWS_SPOOL_DONE, $spool_row->status);
  }

  /**
   * Test with disabled caching.
   */
  function testSendMissingSubscriber() {
    $this->setUpSubscribers(1);

    $edit = array(
      'title[0][value]' => $this->randomString(10),
      'body[0][value]' => "Mail token: <strong>[simplenews-subscriber:mail]</strong>",
      'simplenews_issue' => 'default',
    );
    $this->drupalPostForm('node/add/simplenews', $edit, ('Save and publish'));
    $this->assertTrue(preg_match('|node/(\d+)$|', $this->getUrl(), $matches), 'Node created');
    $node = Node::load($matches[1]);

    // Add node to spool.
    simplenews_add_node_to_spool($node);

    // Delete the subscriber.
    $subscriber = simplenews_subscriber_load_by_mail(reset($this->subscribers));
    $subscriber->delete();

    simplenews_mail_spool();

    // Make sure that no mails have been sent.
    $this->assertEqual(0, count($this->drupalGetMails()));

    $spool_row = db_query('SELECT * FROM {simplenews_mail_spool}')->fetchObject();
    $this->assertEqual(SIMPLENEWS_SPOOL_DONE, $spool_row->status);
  }
}