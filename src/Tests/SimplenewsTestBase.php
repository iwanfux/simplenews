<?php

/**
 * @file
 * Simplenews test functions.
 *
 * @ingroup simplenews
 */

namespace Drupal\simplenews\Tests;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\simplenews\Entity\Newsletter;
use Drupal\simplenews\Entity\Subscriber;
use Drupal\simpletest\WebTestBase;
use Drupal\user\Entity\Role;

/**
 * Class SimplenewsTestBase
 */
abstract class SimplenewsTestBase extends WebTestBase {

  public static $modules = array('simplenews', 'block');

  /**
   * The Simplenews settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  public function setUp() {
    parent::setUp();
    $this->config = \Drupal::config('simplenews.settings');

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
  function randomEmail($number = 4, $domain = 'example.com') {
    $mail = drupal_strtolower($this->randomMachineName($number) . '@' . $domain);
    return $mail;
  }

  /**
   * Select randomly one of the available newsletters.
   *
   * @return string
   *   The ID of a newsletter.
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
    entity_get_form_display($entity_type, $bundle, 'default')
      ->setComponent($field_name, array(
        'type' => 'string_textfield',
      ))->save();
    entity_get_display($entity_type, $bundle, 'default')
      ->setComponent($field_name, array(
        'type' => 'string',
      ))->save();
  }

  /**
   * Visits and submits a newsletter management form.
   *
   * @param string|string[] $newsletter_ids
   *   An ID or an array of IDs of the newsletters to subscribe to.
   * @param string $email
   *   The email to subscribe.
   * @param array $edit
   *   (optional) Additional form field values, keyed by form field names.
   * @param string $submit
   *   (optional) The value of the form submit button. Defaults to
   *   t('Subscribe').
   * @param string $path
   *   (optional) The path where to expect the form, defaults to
   *   'newsletter/subscriptions'.
   * @param int $response
   *   (optional) Expected response, defaults to 200.
   */
  protected function subscribe($newsletter_ids, $email = NULL, array $edit = array(), $submit = NULL, $path = 'newsletter/subscriptions', $response = 200) {
    if (isset($email)) {
      $edit += array(
        'mail[0][value]' => $email,
      );
    }
    if (!is_array($newsletter_ids)) {
      $newsletter_ids = [$newsletter_ids];
    }
    foreach ($newsletter_ids as $newsletter_id) {
      $edit["subscriptions[$newsletter_id]"] = $newsletter_id;
    }
    $this->drupalPostForm($path, $edit, $submit ?: t('Subscribe'));
    $this->assertResponse($response);
  }

  /**
   * Visits and submits the user registration form.
   *
   * @param string $email
   *   (optional) The email of the new user. Defaults to a random email.
   * @param array $edit
   *   (optional) Additional form field values, keyed by form field names.
   *
   * @return int
   *   Uid of the new user.
   */
  protected function registerUser($email = NULL, array $edit = array()) {
    $edit += array(
      'mail' => $email ?: $this->randomEmail(),
      'name' => $this->randomMachineName(),
    );
    $this->drupalPostForm('user/register', $edit, t('Create new account'));
    // Return uid of new user.
    $uids = \Drupal::entityQuery('user')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    return array_shift($uids);
  }

  /**
   * Returns the last created Subscriber.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The Subscriber entity, or NULL if there is none.
   */
  protected function getLatestSubscriber() {
    $snids = \Drupal::entityQuery('simplenews_subscriber')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();
    return Subscriber::load(array_shift($snids));
  }
}
