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
    if ($enabled) {
      $role = Role::load(DRUPAL_ANONYMOUS_RID);
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
    $mail = drupal_strtolower($this->randomName($number, $prefix) . '@' . $domain);
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
   * @param int $newsletter_id
   *   newsletter id
   * @param array $settings
   *  ['message'] = Block message
   *  ['form'] = '1': Subscription form; '0': Link to form
   *  ['link to previous'] = {1, 0} Display link to previous issues
   *  ['previous issues'] = {1, 0} Display previous issues
   *  ['issue count'] = {1, 2, 3, ...}Number of issues to display
   *  ['rss feed'] = {1, 0} Display RSS-feed icon
   */
  function setupSubscriptionBlock($newsletter_id, $settings = array()) {
    $bid = db_select('block')
      ->fields('block', array('bid'))
      ->condition('module', 'simplenews')
      ->condition('delta', $newsletter_id)
      ->execute();

    // Check to see if the box was created by checking that it's in the database..
    $this->assertNotNull($bid, t('Block found in database'));

    // Set block parameters
    $edit = array();
    $edit['regions[bartik]'] = 'sidebar_first';
    if (isset($settings['message'])) {
      $edit['simplenews_block_m_' . $newsletter_id] = $settings['message'];
    }
    if (isset($settings['form'])) {
      $edit['simplenews_block_f_' . $newsletter_id] = $settings['form'];
    }
    if (isset($settings['link to previous'])) {
      $edit['simplenews_block_l_' . $newsletter_id] = $settings['link to previous'];
    }
    if (isset($settings['previous issues'])) {
      $edit['simplenews_block_i_status_' . $newsletter_id] = $settings['previous issues'];
    }
    if (isset($settings['issue count'])) {
      $edit['simplenews_block_i_' . $newsletter_id] = $settings['issue count'];
      // @todo check the count
    }
    if (isset($settings['rss feed'])) {
      $edit['simplenews_block_r_' . $newsletter_id] = $settings['rss feed'];
    }

    // Simplify confirmation form submission by hiding the subscribe block on
    // that page. Same for the newsletter/subscriptions page.
    $edit['pages'] = "newsletter/confirm/*\nnewsletter/subscriptions";

    $this->drupalPostForm('admin/structure/block/manage/simplenews/' . $newsletter_id . '/configure', $edit, t('Save block'));
    $this->assertText('The block configuration has been saved.', 'The newsletter block configuration has been saved.');
  }

  function setUpSubscribers($count = 100, $newsletter_id = 1) {
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