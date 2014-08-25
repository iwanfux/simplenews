<?php

/**
 * @file
 * Simplenews subscribe test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\Component\Utility\String;
use Drupal\simplenews\Entity\Subscriber;

/**
 * (un)subscription of anonymous and authenticated users.
 *
 * Subscription via block, subscription page and account page
 *
 * @group simplenews
 */
class SimplenewsSubscribeTest extends SimplenewsTestBase {

  /**
   * Subscribe to multiple newsletters at the same time.
   */
  function testSubscribeMultiple() {
    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters',
      'administer simplenews subscriptions',
    ));
    $this->drupalLogin($admin_user);
    $this->setAnonymousUserSubscription(TRUE);

    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = array(
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'opt_inout' => 'double',
      );
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }

    $newsletters = simplenews_newsletter_get_all();

    $this->drupalLogout();

    $enable = array_rand($newsletters, 3);

    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    foreach ($enable as $newsletter_id) {
      $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
    }
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'), t('Subscription confirmation e-mail sent.'));

    $mails = $this->drupalGetMails();
    $body = $mails[0]['body'];

    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $pos = strpos($body, t('Subscribe to @name', array('@name' => $newsletter->name)));
      if (in_array($newsletter_id, $enable)) {
        $this->assertTrue($pos);
      }
      else {
        $this->assertFalse($pos);
      }
    }

    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $this->assertRaw(t('Are you sure you want to confirm the following subscription changes for %user?', array('%user' => simplenews_mask_mail($mail))), t('Subscription confirmation found.'));

    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      if (in_array($newsletter_id, $enable)) {
        $this->assertText(t('Subscribe to @name', array('@name' => $newsletter->name)));
      }
      else {
        $this->assertNoText(t('Subscribe to @name', array('@name' => $newsletter->name)));
      }
    }

    $this->drupalPostForm($confirm_url, NULL, t('Confirm'));
    $this->assertRaw(t('Subscription changes confirmed for %user.', array('%user' => $mail)), t('Anonymous subscriber added to newsletter'));

    drupal_static_reset('simplenews_user_is_subscribed');
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    // Verify subscription changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $is_subscribed = simplenews_user_is_subscribed($mail, $newsletter_id);
      if (in_array($newsletter_id, $enable)) {
        $this->assertTrue($is_subscribed);
      }
      else {
        $this->assertFalse($is_subscribed);
      }
    }

    // Go to the manage page and submit without changes.
    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $hash = simplenews_generate_hash($subscriber->getMail(), 'manage');
    $this->drupalPostForm('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash, array(), t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $mail)));
    $mails = $this->drupalGetMails();
    $this->assertEqual(1, count($mails), t('No confirmation mails have been sent.'));

    // Unsubscribe from two of the three enabled newsletters.
    $disable = array_rand(array_flip($enable), 2);

    $edit = array(
      'mail' => $mail,
    );
    foreach ($disable as $newsletter_id) {
      $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
    }
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Unsubscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'), t('Subscription confirmation e-mail sent.'));

    $mails = $this->drupalGetMails();
    $body = $mails[1]['body'];
    /* @todo: get rid of new-line in text
    vn9whrkr@example.com at http://drupal8.local/:\\n\\n - Unsubscribe from
rWcewRqx
     */
    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $pos = strpos($body, t('Unsubscribe from @name', array('@name' => $newsletter->name)));
      if (in_array($newsletter_id, $disable)) {
        $this->assertTrue($pos);
      }
      else {
        $this->assertFalse($pos);
      }
    }

    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $this->assertRaw(t('Are you sure you want to confirm the following subscription changes for %user?', array('%user' => simplenews_mask_mail($mail))), t('Subscription confirmation found.'));

    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      if (in_array($newsletter_id, $disable)) {
        $this->assertText(t('Unsubscribe from @name', array('@name' => $newsletter->name)));
      }
      else {
        $this->assertNoText(t('Unsubscribe from @name', array('@name' => $newsletter->name)));
      }
    }

    $this->drupalPostForm($confirm_url, NULL, t('Confirm'));
    $this->assertRaw(t('Subscription changes confirmed for %user.', array('%user' => $mail)), t('Anonymous subscriber added to newsletter'));

    // Verify subscription changes.
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    $still_enabled = array_diff($enable, $disable);
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $is_subscribed = simplenews_user_is_subscribed($mail, $newsletter_id);
      if (in_array($newsletter_id, $still_enabled)) {
        $this->assertTrue($is_subscribed);
      }
      else {
        $this->assertFalse($is_subscribed);
      }
    }

    // Make sure that a single change results in a non-multi confirmation mail.
    $newsletter_id = reset($disable);
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $mails = $this->drupalGetMails();
    $body = $mails[2]['body'];

    // Load simplenews settings config object.
    $config = \Drupal::config('simplenews.settings');

    // Change behavior to always use combined mails.
    $config->set('subscription.use_combined', 'always');
    $config->save();
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $mails = $this->drupalGetMails();
    $body = $mails[3]['body'];

    // Change behavior to never, should send two separate mails.
    $config->set('subscription.use_combined', 'never');
    $config->save();
    $edit = array(
      'mail' => $mail,
    );
    foreach ($disable as $newsletter_id) {
      $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
    }
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'), t('Subscription confirmation e-mail sent.'));
    $mails = $this->drupalGetMails();
    foreach (array(4, 5) as $id) {
      $body = $mails[$id]['body'];
      $this->extractConfirmationLink($body);
    }

    // Make sure that the /ok suffix works, unsubscribe from everything.
    $config->set('subscription.use_combined', 'multiple');
    $config->save();
    $edit = array(
      'mail' => $mail,
    );
    foreach (array_keys($newsletters) as $newsletter_id) {
      $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
    }
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Unsubscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));

    $mails = $this->drupalGetMails();
    $body = $mails[6]['body'];

    $confirm_url = $this->extractConfirmationLink($body);
    $this->drupalGet($confirm_url);
    $this->drupalGet($confirm_url . '/ok');
    $this->assertRaw(t('Subscription changes confirmed for %user.', array('%user' => $mail)), t('Confirmation message displayed.'));

    // Verify subscription changes.
    $controller = \Drupal::entityManager()->getStorage('simplenews_subscriber');

    $controller->resetCache();
    drupal_static_reset('simplenews_user_is_subscribed');
    foreach (array_keys($newsletters) as $newsletter_id) {
      $this->assertFalse(simplenews_user_is_subscribed($mail, $newsletter_id));
    }

    // Call confirmation url after it is allready used.
    // Using direct url.
    $this->drupalGet($confirm_url . '/ok');
    $this->assertNoResponse(404, 'Redirected after calling confirmation url more than once.');
    $this->assertRaw(t('All changes to your subscriptions where already applied. No changes made.'));

    // Using confirmation page.
    $this->drupalGet($confirm_url);
    $this->assertNoResponse(404, 'Redirected after calling confirmation url more than once.');
    $this->assertRaw(t('All changes to your subscriptions where already applied. No changes made.'));

    // Test expired confirmation links.
    $enable = array_rand($newsletters, 3);

    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    foreach ($enable as $newsletter_id) {
      $edit['newsletters[' . $newsletter_id . ']'] = TRUE;
    }
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));

    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $expired_timestamp = REQUEST_TIME - 86401;
    $changes = $subscriber->getChanges();
    $hash = simplenews_generate_hash($subscriber->getMail(), 'combined' . serialize($subscriber->getChanges()), $expired_timestamp);
    $url = 'newsletter/confirm/combined/' . $subscriber->id() . '/' . $expired_timestamp . '/' . $hash;
    $this->drupalGet($url);
    $this->assertText(t('This link has expired.'));
    $this->drupalPostForm(NULL, array(), t('Request new confirmation mail'));

    $mails = $this->drupalGetMails();
    $body = $mails[8]['body'];
    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      $pos = strpos($body, t('Subscribe to @name', array('@name' => $newsletter->name)));
      if (in_array($newsletter_id, $enable)) {
        $this->assertTrue($pos);
      }
      else {
        $this->assertFalse($pos);
      }
    }

    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $this->assertRaw(t('Are you sure you want to confirm the following subscription changes for %user?', array('%user' => simplenews_mask_mail($mail))), t('Subscription confirmation found.'));

    // Verify listed changes.
    foreach ($newsletters as $newsletter_id => $newsletter) {
      if (in_array($newsletter_id, $enable)) {
        $this->assertText(t('Subscribe to @name', array('@name' => $newsletter->name)));
      }
      else {
        $this->assertNoText(t('Subscribe to @name', array('@name' => $newsletter->name)));
      }
    }

    $this->drupalPostForm($confirm_url, NULL, t('Confirm'));
    $this->assertRaw(t('Subscription changes confirmed for %user.', array('%user' => $mail)), t('Anonymous subscriber added to newsletter'));
  }

  /**
   * Extract a confirmation link from a mail body.
   */
  function extractConfirmationLink($body) {
    $pattern = '@newsletter/confirm/.+@';
    preg_match($pattern, $body, $match);
    $found = preg_match($pattern, $body, $match);
    if (!$found) {
      return FALSE;
    }
    $confirm_url = $match[0];
    $this->assertTrue($found, t('Confirmation URL found: @url', array('@url' => $confirm_url)));
    return $confirm_url;
  }

  /**
   * testSubscribeAnonymous
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe anonymous via block
   *   2. Subscribe anonymous via subscription page
   *   3. Subscribe anonymous via multi block
   */
  function testSubscribeAnonymous() {
    // 0. Preparation
    // Login admin
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin
    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer newsletters',
      'administer permissions')
    );
    $this->drupalLogin($admin_user);
    $this->setAnonymousUserSubscription(TRUE);

    // Create some newsletters for multi-sign up block.
    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = array(
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'opt_inout' => 'double',
      );
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }

    $newsletter_id = $this->getRandomNewsletter();

    $this->drupalLogout();

    //file_put_contents('output.html', $this->drupalGetContent());
    // 1. Subscribe anonymous via block
    // Subscribe + submit
    // Assert confirmation message
    // Assert outgoing email
    //
    // Confirm using mail link
    // Confirm using mail link
    // Assert confirmation message

    // Setup subscription block with subscription form.
    $block_settings = array(
      'newsletters' => array($newsletter_id),
      'message' => $this->randomMachineName(4),
      'form' => 1,
      'issue_status' => 0,
    );
    $single_block = $this->setupSubscriptionBlock($block_settings);

    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    $this->drupalPostForm('', $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'), t('Subscription confirmation e-mail sent.'));

    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_UNCONFIRMED, $subscription->status, t('Subscription is unconfirmed'));

    $mails = $this->drupalGetMails();
    $confirm_url = $this->extractConfirmationLink($mails[0]['body']);
    $body = $mails[0]['body'];

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm(NULL, array(), t('Subscribe'));
    $this->assertRaw(t('%user was added to the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber added to newsletter'));

    // Test that it is possible to register with a mail address that is already
    // a subscriber.
    $site_config = \Drupal::config('user.settings');
    $site_config->set('register', 'visitors');
    $site_config->set('verify_mail', false);
    $site_config->save();

    $edit = array(
      'name' => $this->randomMachineName(),
      'mail' => $mail,
      'pass[pass1]' => $pass = $this->randomMachineName(),
      'pass[pass2]' => $pass,
    );
    $this->drupalPostForm('user/register', $edit, t('Create new account'));

    // Verify confirmation messages.
    $this->assertText(t('Registration successful. You are now logged in.'));

    // Verify that the subscriber has been updated and references to the correct
    // user.
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $account = user_load_by_mail($mail);
    $this->assertEqual($subscriber->getUserId(), $account->id());
    $this->assertEqual($account->getUsername(), $edit['name']);

    $this->drupalLogout();

    // Disable the newsletter block.
    $single_block->delete();

    // 2. Subscribe anonymous via subscription page
    // Subscribe + submit
    // Assert confirmation message
    // Assert outgoing email
    //
    // Confirm using mail link
    // Confirm using mail link
    // Assert confirmation message

    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      "newsletters[$newsletter_id]" => '1',
      'mail' => $mail,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'), t('Subscription confirmation e-mail sent.'));

    $mails = $this->drupalGetMails();
    $body = $mails[2]['body'];
    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm($confirm_url, NULL, t('Subscribe'));
    $this->assertRaw(t('%user was added to the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber added to newsletter'));

    // 3. Subscribe anonymous via multi block

    // Setup subscription block with subscription form.
    $block_settings = array(
      'newsletters' => array_keys(simplenews_newsletter_get_all()),
      'message' => $this->randomMachineName(4),
      'form' => 1,
      'issue_status' => 0,
    );

    $multi_block = $this->setupSubscriptionBlock($block_settings);

    // Try to submit multi-signup form without selecting a newsletter.
    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    $this->drupalPostForm('', $edit, t('Subscribe'));
    $this->assertText(t('You must select at least one newsletter.'));

    // Now fill out the form and try again. The e-mail should still be listed.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $mails = $this->drupalGetMails();
    $body = $mails[3]['body'];
    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm($confirm_url, NULL, t('Subscribe'));
    $this->assertRaw(t('%user was added to the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber added to newsletter'));

    // Try to subscribe again, this should not re-set the status to unconfirmed.
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

    // Now the same with the newsletter/subscriptions page.
    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));
    $this->assertText(t('You must select at least one newsletter.'));

    // Now fill out the form and try again.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Subscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));

    $mails = $this->drupalGetMails();
    $body = $mails[5]['body'];
    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm($confirm_url, NULL, t('Subscribe'));
    $this->assertRaw(t('%user was added to the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber added to newsletter'));

    // Test unsubscribe on newsletter/subscriptions page.
    $edit = array(
      'mail' => $mail,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Unsubscribe'));
    $this->assertText(t('You must select at least one newsletter.'));

    // Now fill out the form and try again.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Unsubscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));
    $mails = $this->drupalGetMails();
    $body = $mails[6]['body'];
    $this->assertTrue(strpos($body, t('We have received a request to remove the @mail', array('@mail' => $mail))) === 0);

    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to remove %user from the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm($confirm_url, NULL, t('Unsubscribe'));
    $this->assertRaw(t('%user was unsubscribed from the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber removed from newsletter'));

    // Visit the newsletter/subscriptions page with the hash.
    $subscriber = simplenews_subscriber_load_by_mail($mail);

    $hash = simplenews_generate_hash($subscriber->getMail(), 'manage');
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->assertText(t('Subscriptions for @mail', array('@mail' => $mail)));

    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Update'));

    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $mail)));

    // Make sure the subscription is confirmed.
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = simplenews_subscriber_load_by_mail($mail);

    $this->assertTrue($subscriber->isSubscribed($newsletter_id));
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);


    // Attempt to fetch the page using a wrong hash but correct format.
    $hash = simplenews_generate_hash($subscriber->getMail() . 'a', 'manage');
    $this->drupalGet('newsletter/subscriptions/' . $subscriber->id() . '/' . REQUEST_TIME . '/' . $hash);
    $this->assertResponse(404);

    // Attempt to unsubscribe a non-existing subscriber.
    $mail = $this->randomEmail();
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Unsubscribe'));
    $this->assertText(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));
    $mails = $this->drupalGetMails();
    $body = $mails[7]['body'];
    // Remove line breaks from body in case the string is split.
    $body = str_replace("\n", ' ', $body);
    $this->assertTrue(strpos($body, 'is not subscribed to this mailing list') !== FALSE);

    // Test expired confirmation links.
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Subscribe'));

    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $expired_timestamp = REQUEST_TIME - 86401;
    $hash = simplenews_generate_hash($subscriber->getMail(), 'add', $expired_timestamp);
    $url = 'newsletter/confirm/add/' . $subscriber->id() . '/' . $newsletter_id . '/' . $expired_timestamp . '/' . $hash;
    $this->drupalGet($url);
    $this->assertText(t('This link has expired.'));
    $this->drupalPostForm(NULL, array(), t('Request new confirmation mail'));

    $mails = $this->drupalGetMails();
    $body = $mails[9]['body'];
    $confirm_url = $this->extractConfirmationLink($body);

    $this->drupalGet($confirm_url);
    $newsletter = simplenews_newsletter_load($newsletter_id);
    $this->assertRaw(t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)), t('Subscription confirmation found.'));

    $this->drupalPostForm($confirm_url, NULL, t('Subscribe'));
    $this->assertRaw(t('%user was added to the %newsletter mailing list.', array('%user' => $mail, '%newsletter' => $newsletter->name)), t('Anonymous subscriber added to newsletter'));

    // Make sure the subscription is confirmed now.
    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = simplenews_subscriber_load_by_mail($mail);

    $this->assertTrue($subscriber->isSubscribed($newsletter_id));
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);
  }

  /**
   * Test anonymous subscription with single opt in.
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe anonymous via block
   */
  function testSubscribeAnonymousSingle() {
    // 0. Preparation
    // Login admin
    // Create single opt in newsletter.
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin
    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters')
    );
    $this->drupalLogin($admin_user);
    $this->setAnonymousUserSubscription(TRUE);

    $this->drupalGet('admin/config/services/simplenews');
    $this->clickLink(t('Add newsletter'));
    $name = $this->randomMachineName();
    $edit = array(
      'name' => $name,
      'id' => strtolower($name),
      'description' => $this->randomString(20),
      'opt_inout' => 'single',
    );
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $this->drupalLogout();

    $newsletter_id = $edit['id'];

    // Setup subscription block with subscription form.
    $block_settings = array(
      'newsletters' => array($newsletter_id),
      'message' => $this->randomMachineName(4),
      'form' => 1,
      'issue_status' => 0,
    );
    $block = $this->setupSubscriptionBlock($block_settings);

    // 1. Subscribe anonymous via block
    // Subscribe + submit
    // Assert confirmation message
    // Verify subscription state.

    $mail = $this->randomEmail(8, 'testmail');
    $edit = array(
      'mail' => $mail,
    );
    $this->drupalPostForm('', $edit, t('Subscribe'));
    $this->assertText(t('You have been subscribed.'), t('Anonymous subscriber added to newsletter'));

    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $subscription->status);

    // Unsubscribe again.
    $edit = array(
      'mail' => $mail,
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Unsubscribe'));

    \Drupal::entityManager()->getStorage('simplenews_subscriber')->resetCache();
    $subscriber = simplenews_subscriber_load_by_mail($mail);
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED, $subscription->status);
  }

  /**
   * testSubscribeAuthenticated
   *
   * Steps performed:
   *   0. Preparation
   *   1. Subscribe authenticated via block
   *   2. Unsubscribe authenticated via subscription page
   *   3. Subscribe authenticated via subscription page
   *   4. Unsubscribe authenticated via account page
   *   5. Subscribe authenticated via account page
   *   6. Subscribe authenticated via multi block
   */
  function testSubscribeAuthenticated() {
    // 0. Preparation
    // Login admin
    // Set permission for anonymous to subscribe
    // Enable newsletter block
    // Logout admin
    // Login Subscriber

    $admin_user = $this->drupalCreateUser(array(
      'administer blocks',
      'administer content types',
      'administer nodes',
      'access administration pages',
      'administer permissions',
      'administer newsletters')
    );
    $this->drupalLogin($admin_user);
    $this->setAuthenticatedUserSubscription(TRUE);

    // Create some newsletters for multi-sign up block.
    $this->drupalGet('admin/config/services/simplenews');
    for ($i = 0; $i < 5; $i++) {
      $this->clickLink(t('Add newsletter'));
      $name = $this->randomMachineName();
      $edit = array(
        'name' => $name,
        'id' => strtolower($name),
        'description' => $this->randomString(20),
        'opt_inout' => 'double',
      );
      $this->drupalPostForm(NULL, $edit, t('Save'));
    }

    $newsletter_id = $this->getRandomNewsletter();
    $this->drupalLogout();

    // Setup subscription block with subscription form.
    $block_settings = array(
      'newsletters' => array($newsletter_id),
      'message' => $this->randomMachineName(4),
      'form' => 1,
      'issue_status' => 0,
    );
    $single_block = $this->setupSubscriptionBlock($block_settings);

    $subscriber_user = $this->drupalCreateUser(array('subscribe to newsletters'));
    $this->drupalLogin($subscriber_user);

    // 1. Subscribe authenticated via block
    // Subscribe + submit
    // Assert confirmation message

    $this->drupalPostForm('', NULL, t('Subscribe'));
    $this->assertText(t('You have been subscribed.'), t('Authenticated user subscribed using the subscription block.'));

    // 2. Unsubscribe authenticated via subscription page
    // Unsubscribe + submit
    // Assert confirmation message

    $edit = array(
      "newsletters[$newsletter_id]" => 0,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Update'));
    $this->assertRaw(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $subscriber_user->getEmail())), t('Authenticated user unsubscribed on the subscriptions page.'));

    // 3. Subscribe authenticated via subscription page
    // Subscribe + submit
    // Assert confirmation message

    $edit = array(
      "newsletters[$newsletter_id]" => '1',
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Update'));
    $this->assertRaw(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $subscriber_user->getEmail())), t('Authenticated user subscribed on the subscriptions page.'));

    // 4. Unsubscribe authenticated via account page
    // Unsubscribe + submit
    // Assert confirmation message

    $edit = array(
      "newsletters[$newsletter_id]" => FALSE,
    );
    $url = 'user/' . $subscriber_user->id() . '/simplenews';
    $this->drupalPostForm($url, $edit, t('Save'));
    $this->assertRaw(t('Your newsletter subscriptions have been updated.', array('%mail' => $subscriber_user->getEmail())), t('Authenticated user unsubscribed on the account page.'));

    $subscriber = simplenews_subscriber_load_by_mail($subscriber_user->getEmail());
    $subscription = $subscriber->getSubscription($newsletter_id);
    $this->assertEqual(SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED, $subscription->status, t('Subscription is unsubscribed'));

    // 5. Subscribe authenticated via account page
    // Subscribe + submit
    // Assert confirmation message

    $edit = array(
      "newsletters[$newsletter_id]" => '1',
    );
    $url = 'user/' . $subscriber_user->id() . '/simplenews';
    $this->drupalPostForm($url, $edit, t('Save'));
    $this->assertRaw(t('Your newsletter subscriptions have been updated.', array('%mail' => $subscriber_user->getEmail())), t('Authenticated user unsubscribed on the account page.'));

    // Disable the newsletter block.
    $single_block->delete();

    // Setup subscription block with subscription form.
    $block_settings = array(
      'newsletters' => array_keys(simplenews_newsletter_get_all()),
      'message' => $this->randomMachineName(4),
      'form' => 1,
      'issue_status' => 0,
    );

    $multi_block = $this->setupSubscriptionBlock($block_settings);

    // Try to submit multi-signup form without selecting a newsletter.
    $subscriber_user2 = $this->drupalCreateUser(array('subscribe to newsletters'));
    $this->drupalLogin($subscriber_user2);

    // Check that the user has only access to his own subscriptions page.
    $this->drupalGet('user/' . $subscriber_user->id() . '/simplenews');
    $this->assertResponse(403);

    $this->drupalGet('user/' . $subscriber_user2->id() . '/simplenews');
    $this->assertResponse(200);

    $this->assertNoField('mail');
    $this->drupalPostForm(NULL, array(), t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user2->getEmail())));

    // Nothing should have happened.
    $this->assertNoFieldChecked('edit-newsletters-' . $newsletter_id);

    // Now fill out the form and try again.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user2->getEmail())));

    $this->assertFieldChecked('edit-newsletters-' . $newsletter_id);

    // Unsubscribe.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => FALSE,
    );
    $this->drupalPostForm(NULL, $edit, t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user2->getEmail())));

    $this->assertNoFieldChecked('edit-newsletters-' . $newsletter_id);

    // And now the same for the newsletter/subscriptions page.
    $subscriber_user3 = $this->drupalCreateUser(array('subscribe to newsletters'));
    $this->drupalLogin($subscriber_user3);

    $this->assertNoField('mail');
    $this->drupalPostForm('newsletter/subscriptions', array(), t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user3->getEmail())));

    // Nothing should have happened.
    $this->assertNoFieldChecked('edit-newsletters-' . $newsletter_id);

    // Now fill out the form and try again.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user3->getEmail())));

    $this->assertFieldChecked('edit-newsletters-' . $newsletter_id);

    // Unsubscribe.
    $edit = array(
      'newsletters[' . $newsletter_id . ']' => FALSE,
    );
    $this->drupalPostForm('newsletter/subscriptions', $edit, t('Update'));
    $this->assertText(t('The newsletter subscriptions for @mail have been updated.', array('@mail' => $subscriber_user3->getEmail())));

    $this->assertNoFieldChecked('edit-newsletters-' . $newsletter_id);
  }

}