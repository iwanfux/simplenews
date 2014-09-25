<?php

/**
 * @file
 * Simplenews test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\user\Entity\Role;

/**
 * Class SimplenewsTestBase
 */
abstract class SimplenewsTestBase extends WebTestBase {

  public static $modules = array('simplenews', 'block');

  public function setUp() {
    parent::setUp();
    $site_config = \Drupal::config('system.site');
    $site_config->set('site_mail', 'simpletest@example.com');

    // The default newsletter has already been created, so we need to make sure
    // that the defaut newsletter has a valid from address.
    $newsletter = Newsletter::load('default');
    $newsletter->from_address = $site_config->get('site_mail');
    $newsletter->save();

  }

  /**
   * Set anonymous user permission to subscribe.
   *
   * @param boolean $enabled
   *   Allow anonymous subscribing.
   */
  function setAnonymousUserSubscription($enabled) {
    $role = Role::load(DRUPAL_ANONYMOUS_RID);
    if ($enabled) {
      $role->grantPermission('subscribe to newsletters');
    } else {
      $role->revokePermission('subscribe to newsletters');
    }
    $role->save();
  }

  /**
   * Set authenticated user permission to subscribe.
   *
   * @param boolean $enabled
   *   Allow authenticated subscribing.
   */
  function setAuthenticatedUserSubscription($enabled) {
    $role = Role::load(DRUPAL_AUTHENTICATED_RID);
    if ($enabled) {
      $role->grantPermission('subscribe to newsletters');
    } else {
      $role->revokePermission('subscribe to newsletters');
    }
    $role->save();
  }

  /**
   * Generates a random email address.
   *
   * The generated addresses are stored in a class variable. Each generated
   * adress is checked against this store to prevent duplicates.
   *
   * @todo: Make this function redundant by modification of Simplenews.
   * Email addresses are case sensitive, simplenews system should handle with
   * this correctly.
   */
  function randomEmail($number = 4, $prefix = 'simpletest_', $domain = 'example.com') {
    $mail = drupal_strtolower($this->randomMachineName($number, $prefix) . '@' . $domain);
    return $mail;
  }

  /**
   * Select randomly one of the available newsletters.
   *
   * @return newsletter newsletter_id.
   */
  function getRandomNewsletter() {
    if ($newsletters = array_keys(simplenews_newsletter_get_all())) {
      return $newsletters[array_rand($newsletters)];
    }
    return 0;
  }

  /**
   * Enable newsletter subscription block.
   *
   * @param array $settings
   *  ['newsletters'] = Array of newsletters (id => 1)
   *  ['message'] = Block message
   *  ['form'] = '1': Subscription form; '0': Link to form
   *  ['link_previous'] = {1, 0} Display link to previous issues
   *  ['issue_status'] = {1, 0} Display previous issues
   *  ['issue_count'] = {1, 2, 3, ...} Number of issues to display
   *  ['rss_feed'] = {1, 0} Display RSS-feed icon
   */
  function setupSubscriptionBlock($settings = array()) {

    $settings += [
      'newsletters' => array(),
      'message' => t('Select the newsletter(s) to which you want to subscribe or unsubscribe.'),
      'form' => 1,
      'issue_status' => 0,
      'issues' => 5,
      'unique_id' => \Drupal::service('uuid')->generate()
    ];

    // Simplify confirmation form submission by hiding the subscribe block on
    // that page. Same for the newsletter/subscriptions page.
    $settings['visibility']['request_path']['pages'] = "newsletter/confirm/*\nnewsletter/subscriptions";
    $settings['visibility']['request_path']['negate'] = TRUE;
    $settings['region'] = 'sidebar_first';

    //$this->drupalPostForm('admin/structure/block/manage/simplenews/' . $newsletter_id . '/configure', $edit, t('Save block'));
    $block = $this->drupalPlaceBlock('simplenews_subscription_block', $settings);
    $this->assertTrue($block->id());

    return $block;
  }

  function setUpSubscribers($count = 100, $newsletter_id = 'default') {
    // Subscribe users.
    $this->subscribers = array();
    for ($i = 0; $i < $count; $i++) {
      $mail = $this->randomEmail();
      $this->subscribers[$mail] = $mail;
    }

    $this->drupalGet('admin/people/simplenews');
    $this->clickLink(t('Mass subscribe'));
    $edit = array(
      'emails' => implode(',', $this->subscribers),
      // @todo: Don't hardcode the default newsletter_id.
      'newsletters[' . $newsletter_id . ']' => TRUE,
    );
    $this->drupalPostForm(NULL, $edit, t('Subscribe'));
  }
}
