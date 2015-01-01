<?php

/**
 * @file
 * Simplenews adminstration test functions.
 *
 * @ingroup simplenews
 *
 * @todo:
 * Newsletter node create, send draft, send final
 */

namespace Drupal\simplenews\Tests;

use Drupal\Component\Utility\String;
use Drupal\Component\Utility\Unicode;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;

/**
 * Managing of newsletter categories and content types.
 *
 * @group simplenews
 */
class SimplenewsAdministrationTest extends SimplenewsTestBase {

  /**
   * Implement getNewsletterFieldId($newsletter_id)
   */
  function getNewsletterFieldId($newsletter_id) {
    return 'edit-subscriptions-' . str_replace('_', '-', $newsletter_id);
  }

  /**
   * Test various combinations of newsletter settings.
   */
  function testNewsletterSettings() {

    // Allow registration of new accounts without approval.
    $site_config = \Drupal::config('user.settings');
    $site_config->set('verify_mail', false);
    $site_config->save();

    // Allow authenticated users to subscribe.
    $this->setAuthenticatedUserSubscription(TRUE);

    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
      'administer simplenews subscriptions',
      'create simplenews content',
      'send newsletter',
    ));
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/config/services/simplenews');

    // Create a newsletter for all possible setting combinations.
    $new_account = array('none', 'off', 'on', 'silent');
    $opt_inout = array('hidden', 'single', 'double');

    foreach ($new_account as $new_account_setting) {
      foreach ($opt_inout as $opt_inout_setting) {
        $this->clickLink(t('Add newsletter'));
        $edit = array(
          'name' => implode('-', array($new_account_setting, $opt_inout_setting)),
          'id' => implode('_', array($new_account_setting, $opt_inout_setting)),
          'description' => $this->randomString(20),
          'new_account' => $new_account_setting,
          'opt_inout' => $opt_inout_setting,
          'priority' => rand(0, 5),
          'receipt' => rand(0, 1) ? TRUE : FALSE,
          'from_name' => $this->randomMachineName(),
          'from_address' => $this->randomEmail(),
        );
        $this->drupalPostForm(NULL, $edit, t('Save'));
      }
    }

    drupal_static_reset('simplenews_newsletter_load_multiple');
    $newsletters = simplenews_newsletter_get_all();

    // Check registration form.
    $this->drupalLogout();
    $this->drupalGet('user/register');
    foreach ($newsletters as $newsletter) {
      if (strpos($newsletter->name, '-') === FALSE) {
        continue;
      }

      // Explicitly subscribe to the off-double newsletter.
      if ($newsletter->name == 'off-double') {
        $off_double_newsletter_id = $newsletter->id();
      }

      list($new_account_setting, $opt_inout_setting) = explode('-', $newsletter->name);
      if ($newsletter->new_account == 'on' && $newsletter->opt_inout != 'hidden') {
        $this->assertFieldChecked($this->getNewsletterFieldId($newsletter->id()));
      }
      elseif ($newsletter->new_account == 'off' && $newsletter->opt_inout != 'hidden') {
        $this->assertNoFieldChecked($this->getNewsletterFieldId($newsletter->id()));
      }
      else {
        $this->assertNoField('subscriptions[' . $newsletter->id() . ']', t('Hidden or silent newsletter is not shown.'));
      }
    }

    // Register a new user through the form.
    $edit = array(
      'name' => $this->randomMachineName(),
      'mail' => $this->randomEmail(),
      'pass[pass1]' => $pass = $this->randomMachineName(),
      'pass[pass2]' => $pass,
      'subscriptions[' . $off_double_newsletter_id . ']' => $off_double_newsletter_id,
    );
    $this->drupalPostForm(NULL, $edit, t('Create new account'));

    // Verify confirmation messages.
    $this->assertText(t('Registration successful. You are now logged in.'));
    foreach ($newsletters as $newsletter) {
      // Check confirmation message for all on and non-hidden newsletters and
      // the one that was explicitly selected.
      if (($newsletter->new_account == 'on' && $newsletter->opt_inout != 'hidden') || $newsletter->name == 'off-double') {
        $this->assertText(t('You have been subscribed to @name.', array('@name' => $newsletter->name)));
      }
      else {
        // All other newsletters must not show a message, e.g. those which were
        // subscribed silently.
        $this->assertNoText(t('You have been subscribed to @name.', array('@name' => $newsletter->name)));
      }
    }

    // Log out again.
    $this->drupalLogout();

    $user = user_load_by_name($edit['name']);
    // Set the password so that the login works.
    $user->pass_raw = $edit['pass[pass1]'];

    // Verify newsletter subscription pages.
    $this->drupalLogin($user);
    foreach (array('newsletter/subscriptions', 'user/' . $user->id() . '/simplenews') as $path) {
      $this->drupalGet($path);
      foreach ($newsletters as $newsletter) {
        if (strpos($newsletter->name, '-') === FALSE) {
          continue;
        }
        list($new_account_setting, $opt_inout_setting) = explode('-', $newsletter->name);
        if ($newsletter->opt_inout == 'hidden') {
          $this->assertNoField('subscriptions[' . $newsletter->id() . ']', t('Hidden newsletter is not shown.'));
        }
        elseif ($newsletter->new_account == 'on' || $newsletter->name == 'off-double' || $newsletter->new_account == 'silent') {
          // All on, silent and the explicitly selected newsletter should be checked.
          $this->assertFieldChecked($this->getNewsletterFieldId($newsletter->id()));
        }
        else {
          $this->assertNoFieldChecked($this->getNewsletterFieldId($newsletter->id()));
        }
      }
    }

    // Unsubscribe from a newsletter.
    $edit = array(
      'subscriptions[' . $off_double_newsletter_id . ']' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    $this->assertNoFieldChecked($this->getNewsletterFieldId($off_double_newsletter_id));

    // Get a newsletter which has the block enabled.
    /*foreach ($newsletters as $newsletter) {
      // The default newsletter is missing the from mail address. Use another one.
      if ($newsletter->block == TRUE && $newsletter->newsletter_id != 1 && $newsletter->opt_inout != 'hidden') {
        $edit_newsletter = $newsletter;
        break;
      }
    }*/

    $this->drupalLogin($admin_user);

    /*$this->setupSubscriptionBlock($edit_newsletter->newsletter_id, $settings = array(
      'issue count' => 2,
      'previous issues' => 1,
    ));

    // Create a bunch of newsletters.
    $generated_names = array();
    $date = strtotime('monday this week');
    for ($index = 0; $index < 3; $index++) {
      $name = $this->randomMachineName();
      $generated_names[] = $name;
      $this->drupalGet('node/add/simplenews');
      $edit = array(
        'title' => $name,
        'simplenews_newsletter[und]' => $edit_newsletter->newsletter_id,
        'date' => date('c', strtotime('+' . $index . ' day', $date)),
      );
      $this->drupalPostForm(NULL, $edit, ('Save'));
      $this->clickLink(t('Newsletter'));
      $this->drupalPostForm(NULL, array('simplenews[send]' => SIMPLENEWS_COMMAND_SEND_NOW), t('Submit'));
    }

    // Display the two recent issues.
    $this->drupalGet('');
    $this->assertText(t('Previous issues'), 'Should display recent issues.');

    $displayed_issues = $this->xpath("//div[@class='issues-list']/div/ul/li/a");

    $this->assertEqual(count($displayed_issues), 2, 'Displys two recent issues.');

    $this->assertFalse(in_array($generated_names[0], $displayed_issues));
    $this->assertTrue(in_array($generated_names[1], $displayed_issues));
    $this->assertTrue(in_array($generated_names[2], $displayed_issues));

    $this->drupalGet('admin/config/services/simplenews/manage/' . $edit_newsletter->id());
    $this->assertFieldByName('name', $edit_newsletter->name, t('Newsletter name is displayed when editing'));
    $this->assertFieldByName('description', $edit_newsletter->description, t('Newsletter description is displayed when editing'));

    $edit = array('block' => FALSE);
    $this->drupalPostForm(NULL, $edit, t('Save'));

    \Drupal::entityManager()->getStorage('simplenews_newsletter')->resetCache();
    $updated_newsletter = simplenews_newsletter_load($edit_newsletter->newsletter_id);
    $this->assertEqual(0, $updated_newsletter->block, t('Block for newsletter disabled'));

    $this->drupalGet('admin/structure/block');
    $this->assertNoText($edit_newsletter->name, t('Newsletter block was removed'));

    // Delete a newsletter.
    $this->drupalGet('admin/config/services/simplenews/manage/' . $edit_newsletter->id());
    $this->clickLink(t('Delete'));
    $this->drupalPostForm(NULL, array(), t('Delete'));

    // Verify that the newsletter has been deleted.
    \Drupal::entityManager()->getStorage('simplenews_newsletter')->resetCache();
    $this->assertFalse(simplenews_newsletter_load($edit_newsletter->newsletter_id));
    $this->assertFalse(db_query('SELECT newsletter_id FROM {simplenews_newsletter} WHERE newsletter_id = :newsletter_id', array(':newsletter_id' => $edit_newsletter->newsletter_id))->fetchField());*/
  }

  /**
   * Test newsletter subscription management.
   *
   * Steps performed:
   *
   */
  function testSubscriptionManagement() {
    $admin_user = $this->drupalCreateUser(array('administer newsletters', 'administer simplenews settings', 'administer simplenews subscriptions'));
    $this->drupalLogin($admin_user);

    // Create a second newsletter.
    $edit = array(
      'name' => $name = $this->randomMachineName(),
      'id'  => Unicode::strtolower($name),
    );
    $this->drupalPostForm('admin/config/services/simplenews/add', $edit, t('Save'));

    // Add a number of users to each newsletter separately and then add another
    // bunch to both.
    $subscribers = array();
    drupal_static_reset('simplenews_newsletter_load_multiple');

    $groups = array();
    $newsletters = simplenews_newsletter_get_all();
    foreach ($newsletters as $newsletter) {
      $groups[$newsletter->id()] = array($newsletter->id());
    }
    $groups['all'] = array_keys($groups);

    $subscribers_flat = array();
    foreach ($groups as $key => $group) {
      for ($i = 0; $i < 5; $i++) {
        $mail = $this->randomEmail();
        $subscribers[$key][$mail] = $mail;
        $subscribers_flat[$mail] = $mail;
      }
    }

    // Create a user and assign him one of the mail addresses of the all group.
    $user = $this->drupalCreateUser(array('subscribe to newsletters'));
    // Make sure that user_save() does not update the user object, as it will
    // override the pass_raw property which we'll need to log this user in
    // later on.
    $user_mail = current($subscribers['all']);
    $user->setEmail($user_mail);
    $user->save();

    $delimiters = array(',', ' ', "\n");

    $this->drupalGet('admin/people/simplenews');
    $i = 0;
    foreach ($groups as $key => $group) {
      $this->clickLink(t('Mass subscribe'));
      $edit = array(
        // Implode with a different, supported delimiter for each group.
        'emails' => implode($delimiters[$i++], $subscribers[$key]),
      );
      foreach ($group as $newsletter_id) {
        $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
      }
      $this->drupalPostForm(NULL, $edit, t('Subscribe'));
    }

    // The user to which the mail was assigned should be listed too.
    $this->assertText($user->label());

    // Verify that all addresses are displayed in the table.
    $mail_addresses = $this->xpath('//tr/td[1]');
    $this->assertEqual(15, count($mail_addresses));
    foreach ($mail_addresses as $mail_address) {
      $mail_address = (string) $mail_address;
      $this->assertTrue(isset($subscribers_flat[$mail_address]));
      unset($subscribers_flat[$mail_address]);
    }
    // All entries of the array should be removed by now.
    $this->assertTrue(empty($subscribers_flat));

    // Limit list to subscribers of the first newsletter only.
    reset($groups);
    $first = key($groups);
    // Build a flat list of the subscribers of this list.
    $subscribers_flat = array_merge($subscribers[$first], $subscribers['all']);

    $edit = array(
      'list' => 'newsletter_id-' . $first,
    );
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    // Verify that all addresses are displayed in the table.
    $mail_addresses = $this->xpath('//tr/td[1]');
    $this->assertEqual(10, count($mail_addresses));
    foreach ($mail_addresses as $mail_address) {
      $mail_address = (string) $mail_address;
      $this->assertTrue(isset($subscribers_flat[$mail_address]));
      unset($subscribers_flat[$mail_address]);
    }
    // All entries of the array should be removed by now.
    $this->assertTrue(empty($subscribers_flat));

    // Filter a single mail address, the one assigned to a user.
    $edit = array(
      'email' => Unicode::substr(current($subscribers['all']), 0, 4)
    );
    $this->drupalPostForm(NULL, $edit, t('Filter'));

    $rows = $this->xpath('//tbody/tr');
    $this->assertEqual(1, count($rows));
    $this->assertEqual(current($subscribers['all']), (string) $rows[0]->td[1]);
    $this->assertEqual($user->label(), (string) $rows[0]->td[2]->a);

    // Reset the filter.
    $this->drupalPostForm(NULL, array(), t('Reset'));

    // Test mass-unsubscribe, unsubscribe one from the first group and one from
    // the all group, but only from the first newsletter.
    $first_mail = array_rand($subscribers[$first]);
    $all_mail = array_rand($subscribers['all']);
    unset($subscribers[$first][$first_mail]);
    $edit = array(
      'emails' => $first_mail . ', ' . $all_mail,
      'newsletters[' . $first . ']' => TRUE,
    );
    $this->clickLink(t('Mass unsubscribe'));
    $this->drupalPostForm(NULL, $edit, t('Unsubscribe'));

    // The all mail is still displayed because it's still subscribed to the
    // second newsletter. Reload the page to get rid of the confirmation
    // message.
    $this->drupalGet('admin/people/simplenews');
    $this->assertNoText($first_mail);
    $this->assertText($all_mail);

    // Limit to first newsletter, the all mail shouldn't be shown anymore.
    $edit = array(
      'list' => 'newsletter_id-' . $first,
    );
    $this->drupalPostForm(NULL, $edit, t('Filter'));
    $this->assertNoText($first_mail);
    $this->assertNoText($all_mail);

    // Check exporting.
    $this->clickLink(t('Export'));
    $this->drupalPostForm(NULL, array('newsletters[' . $first . ']' => TRUE), t('Export'));
    $export_field = $this->xpath($this->constructFieldXpath('name', 'emails'));
    $exported_mails = (string) $export_field[0];
    foreach ($subscribers[$first] as $mail) {
      $this->assertTrue(strpos($exported_mails, $mail) !== FALSE, t('Mail address exported correctly.'));
    }
    foreach ($subscribers['all'] as $mail) {
      if ($mail != $all_mail) {
        $this->assertTrue(strpos($exported_mails, $mail) !== FALSE, t('Mail address exported correctly.'));
      }
      else {
        $this->assertFALSE(strpos($exported_mails, $mail) !== FALSE, t('Unsubscribed mail address not exported.'));
      }
    }

    // Only export unsubscribed mail addresses.
    $edit = array(
      'subscribed[subscribed]' => FALSE,
      'subscribed[unsubscribed]' => TRUE,
      'newsletters[' . $first . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Export'));

    $export_field = $this->xpath($this->constructFieldXpath('name', 'emails'));
    $exported_mails = (string) $export_field[0];
    $exported_mails = explode(', ', $exported_mails);
    $this->assertEqual(2, count($exported_mails));
    $this->assertTrue(in_array($all_mail, $exported_mails));
    $this->assertTrue(in_array($first_mail, $exported_mails));


    // Make sure there are unconfirmed subscriptions.
    $unconfirmed = array();
    $unconfirmed[] = $this->randomEmail();
    $unconfirmed[] = $this->randomEmail();
    foreach ($unconfirmed as $mail) {
      simplenews_subscribe($mail, $first, TRUE);
    }

    // Only export unconfirmed mail addresses.
    $edit = array(
      'subscribed[subscribed]' => FALSE,
      'subscribed[unconfirmed]' => TRUE,
      'subscribed[unsubscribed]' => FALSE,
      'newsletters[' . $first . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Export'));

    $export_field = $this->xpath($this->constructFieldXpath('name', 'emails'));
    $exported_mails = (string) $export_field[0];
    $exported_mails = explode(', ', $exported_mails);
    $this->assertEqual(2, count($exported_mails));
    $this->assertTrue(in_array($unconfirmed[0], $exported_mails));
    $this->assertTrue(in_array($unconfirmed[1], $exported_mails));

    // Make sure the user is subscribed to the first newsletter_id.
    simplenews_subscribe($user_mail, $first, FALSE);
    $before_count = simplenews_count_subscriptions($first);

    // Block the user.
    $user->block();
    $user->save();

    $this->drupalGet('admin/people/simplenews');

    // Verify updated subscriptions count.
    drupal_static_reset('simplenews_count_subscriptions');
    $after_count = simplenews_count_subscriptions($first);
    $this->assertEqual($before_count - 1, $after_count, t('Blocked users are not counted in subscription count.'));

    // Test mass subscribe with previously unsubscribed users.
    for ($i = 0; $i < 3; $i++) {
      $tested_subscribers[] = $this->randomEmail();
    }
    simplenews_subscribe($tested_subscribers[0], $first, FALSE);
    simplenews_subscribe($tested_subscribers[1], $first, FALSE);
    simplenews_unsubscribe($tested_subscribers[0], $first, FALSE);
    simplenews_unsubscribe($tested_subscribers[1], $first, FALSE);
    $unsubscribed = implode(', ', array_slice($tested_subscribers, 0, 2));
    $edit = array(
      'emails' => implode(', ', $tested_subscribers),
      'newsletters[' . $first . ']' => TRUE,
    );

    $this->drupalPostForm('admin/people/simplenews/import', $edit, t('Subscribe'));
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    $this->assertFalse(simplenews_user_is_subscribed($tested_subscribers[0], $first, t('Subscriber not resubscribed through mass subscription.')));
    $this->assertFalse(simplenews_user_is_subscribed($tested_subscribers[1], $first, t('Subscriber not resubscribed through mass subscription.')));
    $this->assertTrue(simplenews_user_is_subscribed($tested_subscribers[2], $first, t('Subscriber subscribed through mass subscription.')));
    $substitutes = array('@name' => String::checkPlain(simplenews_newsletter_load($first)->label()), '@mail' => $unsubscribed);
    $this->assertText(t('The following addresses were skipped because they have previously unsubscribed from @name: @mail.', $substitutes));
    $this->assertText(t("If you would like to resubscribe them, use the 'Force resubscription' option."));


    // Test mass subscribe with previously unsubscribed users and force
    // resubscription.
    $tested_subscribers[2] = $this->randomEmail();
    $edit = array(
      'emails' => implode(', ', $tested_subscribers),
      'newsletters[' . $first . ']' => TRUE,
      'resubscribe' => TRUE,
    );

    $this->drupalPostForm('admin/people/simplenews/import', $edit, t('Subscribe'));
    drupal_static_reset('simplenews_user_is_subscribed');
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    $this->assertTrue(simplenews_user_is_subscribed($tested_subscribers[0], $first, t('Subscriber resubscribed trough mass subscription.')));
    $this->assertTrue(simplenews_user_is_subscribed($tested_subscribers[1], $first, t('Subscriber resubscribed trough mass subscription.')));
    $this->assertTrue(simplenews_user_is_subscribed($tested_subscribers[2], $first, t('Subscriber subscribed trough mass subscription.')));

    // Delete newsletter.
    \Drupal::entityManager()->getStorage('simplenews_newsletter')->resetCache();
    $this->drupalGet('admin/config/services/simplenews/manage/' . $first);
    $this->clickLink(t('Delete'));
    $this->drupalPostForm(NULL, array(), t('Delete'));

    $this->assertText(t('All subscriptions to newsletter @newsletter have been deleted.', array('@newsletter' => $newsletters[$first]->name)));

    // Verify that all related data has been deleted.
    $this->assertFalse(Newsletter::load($first));
    // @todo: create 2 blocks  that ref on newsletter and than test if deletion works.
    //$this->assertFalse(db_query('SELECT * FROM {block} WHERE module = :module AND delta = :newsletter_id', array(':module' => 'simplenews', ':newsletter_id' => $first))->fetchField());


    // Verify that all subscriptions of that newsletter have been removed.
    $this->drupalGet('admin/people/simplenews');
    foreach ($subscribers[$first] as $mail) {
      $this->assertNoText($mail);
    }

    // Reset list and click on the first subscriber.
    $this->drupalPostForm(NULL, array(), t('Reset'));
    $this->clickLink(t('Edit'));

    // Get the subscriber id from the path.
    $this->assertTrue(preg_match('|admin/people/simplenews/edit/(\d+)$|', $this->getUrl(), $matches), 'Subscriber found');
    $subscriber =  Subscriber::load($matches[1]);

    $this->assertTitle(t('Edit subscriber @mail', array('@mail' => $subscriber->getMail())) . ' | Drupal');
    $this->assertFieldChecked('edit-status');

    // Disable account.
    $edit = array(
      'status' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    $this->assertFalse(simplenews_user_is_subscribed($subscriber->getMail(), $this->getRandomNewsletter(), t('Subscriber is not active')));

    // Re-enable account.
    $this->drupalGet('admin/people/simplenews/edit/' . $subscriber->id());
    $this->assertTitle(t('Edit subscriber @mail', array('@mail' => $subscriber->getMail())) . ' | Drupal');
    $this->assertNoFieldChecked('edit-status');
    $edit = array(
      'status' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    $this->assertTrue(simplenews_user_is_subscribed($subscriber->getMail(), $this->getRandomNewsletter(), t('Subscriber is active again.')));

    // Remove the newsletter.
    $this->drupalGet('admin/people/simplenews/edit/' . $subscriber->id());
    $this->assertTitle(t('Edit subscriber @mail', array('@mail' => $subscriber->getMail())) . ' | Drupal');
    $nlids = $subscriber->getSubscribedNewsletterIds();
    $newsletter_id = reset($nlids);
    $edit['subscriptions[' . $newsletter_id . ']'] = FALSE;
    $this->drupalPostForm(NULL, $edit, t('Save'));
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    $nlids = $subscriber->getSubscribedNewsletterIds();
    $this->assertFalse(simplenews_user_is_subscribed($subscriber->getMail(), reset($nlids), t('Subscriber not subscribed anymore.')));

    // @todo Test Admin subscriber edit preferred language $subscription->language

    // Register a subscriber with an insecure e-mail address through the API
    // and make sure the address is correctly encoded.
    $xss_mail = "<script>alert('XSS');</script>";
    simplenews_subscribe($xss_mail, $this->getRandomNewsletter(), FALSE);
    $this->drupalGet('admin/people/simplenews');
    $this->assertNoRaw($xss_mail);
    $this->assertRaw(String::checkPlain($xss_mail));

    $xss_subscriber = simplenews_subscriber_load_by_mail($xss_mail);
    $this->drupalGet('admin/people/simplenews/edit/' . $xss_subscriber->id());
    $this->assertNoRaw($xss_mail);
    $this->assertRaw(String::checkPlain($xss_mail));
  }

  /**
   * Test content type configuration.
   */
  function dtestContentTypes() {
    $admin_user = $this->drupalCreateUser(array('administer blocks', 'administer content types', 'administer nodes', 'access administration pages', 'administer permissions', 'administer newsletters', 'administer simplenews subscriptions', 'bypass node access', 'send newsletter'));
    $this->drupalLogin($admin_user);

    $this->drupalGet('admin/structure/types');
    $this->clickLink(t('Add content type'));
    $edit = array(
      'name' => $name = $this->randomMachineName(),
      'type' => $type = strtolower($name),
      'simplenews_content_type' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Save content type'));

    // Verify that the newsletter settings are shown.
    $this->drupalGet('node/add/' . $type);
    $this->assertText(t('Newsletter'));

    // Create an issue.
    $edit = array(
      'title' => $this->randomMachineName(),
    );
    $this->drupalPostForm(NULL, $edit, ('Save'));

    // Send newsletter.
    $this->clickLink(t('Newsletter'));
    $this->assertText(t('Send newsletter'));
    $this->drupalPostForm(NULL, array(), t('Submit'));

    $mails = $this->drupalGetMails();
    $this->assertEqual('simplenews_test', $mails[0]['id']);
    // @todo: Test with a custom test mail address.
    $this->assertEqual('simpletest@example.com', $mails[0]['to']);
    $this->assertEqual(t('[Drupal newsletter] @title', array('@title' => $edit['title'])), $mails[0]['subject']);

    // Update the content type, remove the simpletest checkbox.
    $edit = array(
      'simplenews_content_type' => FALSE,
    );
    $this->drupalPostForm('admin/structure/types/manage/' . $type, $edit, t('Save content type'));

    // Verify that the newsletter settings are still shown.
    // Note: Previously the field got autoremoved. We leave it remaining due to potential data loss.
    $this->drupalGet('node/add/' . $type);
    $this->assertNoText(t('Replacement patterns'));
    $this->assertText(t('Newsletter'));

    // @todo: Test node update/delete.
    // Delete content type.
    // @todo: Add assertions.
    $this->drupalPostForm('admin/structure/types/manage/' . $type . '/delete', array(), t('Delete'));
  }

}
