<?php

/**
 * @file
 * Token related hook implementations.
 */

use Drupal\Component\Utility\String;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

/**
 * Implements hook_token_info().
 */
function simplenews_token_info() {
  $types['simplenews-subscriber'] = array(
    'name' => t('Simplenews subscriber'),
    'description' => t('Tokens related to the newsletter recipient'),
    'needs-data' => 'simplenews_subscriber',
  );
  $types['simplenews-newsletter'] = array(
    'name' => t('Simplenews newsletter'),
    'description' => t('Tokens related to the newsletter'),
    'needs-data' => 'newsletter',
  );

  // Tokens for simplenews subscriber.
  $subscriber['subscribe-url'] = array(
    'name' => t('Subscription URL'),
    'description' => t('The URL of the page where the subscription is confirmed.'),
  );
  $subscriber['unsubscribe-url'] = array(
    'name' => t('Unsubscribe URL'),
    'description' => t('The URL of the page where the cancellation of the subscription is confirmed.'),
  );
  $subscriber['manage-url'] = array(
    'name' => t('Manage URL'),
    'description' => t('The URL of the page where the subscribers can manage their newsletter subscriptions.'),
  );
  $subscriber['combined-url'] = array(
    'name' => t('Combined confirmation URL'),
    'description' => t('The URL of the page where subscribers can confirm their subscription changes.'),
  );
  $subscriber['mail'] = array(
    'name' => t('Subscriber email'),
    'description' => t('The email address of the newsletter receiver.'),
  );

  $subscriber['user'] = array(
    'name' => t('Corresponding user'),
    'description' => t('The user object that corresponds to this subscriber. This is not set for anonymous subscribers.'),
    'type' => 'user',
  );

  // Tokens for simplenews newsletter.
  $newsletter['name'] = array(
    'name' => t('Newsletter'),
    'description' => t('The name of the newsletter.'),
  );
  $newsletter['url'] = array(
    'name' => t('Newsletter URL'),
    'description' => t('The URL of the page listing the issues of this newsletter.'),
  );

  return array(
    'types' => $types,
    'tokens' => array(
      'simplenews-subscriber' => $subscriber,
      'simplenews-newsletter' => $newsletter,
    ),
  );
}

/**
 * Implements hook_tokens().
 */
function simplenews_tokens($type, $tokens, $data = array(), $options = array()) {
  $replacements = array();
  $sanitize = !empty($options['sanitize']);
  if (isset($options['language'])) {
    $url_options['language'] = $options['language'];
    $language_code = $options['language']->language;
  }
  else {
    $language_code = NULL;
  }

  switch ($type) {
    case 'simplenews-subscriber':
      if (!isset($data['simplenews_subscriber'])) {
        return;
      }
      /** @var \Drupal\simplenews\Entity\Subscriber $subscriber */
      $subscriber = $data['simplenews_subscriber'];
      $newsletter = isset($data['newsletter']) ? $data['newsletter'] : NULL;
      $language = $subscriber->getLangcode();

      $url_arguments = array('absolute' => TRUE, 'langcode' => $language);

      foreach ($tokens as $name => $original) {
        $route_parameters = array('snid' => $subscriber->id(), 'timestamp' => REQUEST_TIME);
        switch ($name) {
          case 'subscribe-url':
            $hash = simplenews_generate_hash($subscriber->getMail(), 'add');
            $route_parameters += array(
              'action' => 'add',
              'newsletter_id' => $newsletter->id(),
              'hash' => $hash,
            );
            $replacements[$original] = \Drupal::url('simplenews.newsletter_confirm_subscription', $route_parameters, $url_arguments);
            break;

          case 'unsubscribe-url':
            if (!$subscriber->id()) {
              // Temporary test subscriber.
              $replacements[$original] = t('No unsubscribe link for test subscribers.');
              continue;
            }
            $hash = simplenews_generate_hash($subscriber->getMail(), 'remove');
            $route_parameters += array(
              'action' => 'remove',
              'newsletter_id' => $newsletter->id(),
              'hash' => $hash,
            );
            $replacements[$original] = \Drupal::url('simplenews.newsletter_confirm_subscription', $route_parameters, $url_arguments);
            break;

          case 'combined-url':
            $hash = simplenews_generate_hash($subscriber->getMail(), 'combined' . serialize($subscriber->getChanges()));
            $route_parameters += array('hash' => $hash);
            $replacements[$original] = \Drupal::url('simplenews.newsletter_confirm_combined', $route_parameters, $url_arguments);
            break;

          case 'manage-url':
            $hash = simplenews_generate_hash($subscriber->getMail(), 'manage');
            $route_parameters += array('hash' => $hash);
            $replacements[$original] = \Drupal::url('simplenews.newsletter_subscriptions_arguments', $route_parameters, $url_arguments);
            break;

          case 'mail':
            $replacements[$original] = $sanitize ? String::checkPlain($subscriber->getMail()) : $subscriber->getMail();
            break;
        }
      }

      if (($user_tokens = \Drupal::token()->findWithPrefix($tokens, 'user')) && !empty($subscriber->uid)) {
        $replacements += \Drupal::token()->generate('user', $user_tokens, array('user' => User::load($subscriber->uid)), $options);
      }

      break;

    case 'simplenews-newsletter':
      if (!isset($data['newsletter'])) {
        return;
      }
      $newsletter = $data['newsletter'];
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'name':
            if (isset($newsletter->name)) {
              $replacements[$original] = $sanitize ? String::checkPlain($newsletter->name) : $newsletter->name;
            }
            else {
              $replacements[$original] = t('Unassigned newsletter');
            }
            break;

          case 'url':
            $uri = entity_uri('simplenews_newsletter', $newsletter);
            $replacements[$original] = Url::fromUri($uri['path'], $uri['options'])->toString();
            break;
        }
      }
      break;
  }

  return $replacements;
}
