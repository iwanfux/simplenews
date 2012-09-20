<?php

/**
 * @file
 * Token related hook implementations.
 */

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
 *
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
      $subscriber = $data['simplenews_subscriber'];
      $newsletter = isset($data['newsletter']) ? $data['newsletter'] : NULL;
      $language = isset($subscriber->language->language) ? $subscriber->language : user_preferred_language($subscriber);

      $url_arguments = array('absolute' => TRUE, 'language' => $language);

      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'subscribe-url':
            $hash = simplenews_generate_hash($subscriber->mail, 'add');
            $url = 'newsletter/confirm/add/' . $subscriber->snid . '/' . $newsletter->newsletter_id. '/' . REQUEST_TIME . '/' . $hash;
            $replacements[$original] = url($url, $url_arguments);
            break;

          case 'unsubscribe-url':
            if (!isset($subscriber->snid)) {
              // Temporary test subscriber.
              $replacements[$original] = t('No unsubscribe link for test subscribers.');
              continue;
            }
            $hash = simplenews_generate_hash($subscriber->mail, 'remove');
            $url = 'newsletter/confirm/remove/' . $subscriber->snid . '/' . $newsletter->newsletter_id . '/' . REQUEST_TIME . '/' . $hash;
            $replacements[$original] = url($url, $url_arguments);
            break;

          case 'combined-url':
            $hash = simplenews_generate_hash($subscriber->mail, 'combined' . serialize($subscriber->changes));
            $url = 'newsletter/confirm/combined/' . $subscriber->snid . '/' . REQUEST_TIME . '/' . $hash;
            $replacements[$original] = url($url, $url_arguments);
            break;

          case 'manage-url':
            $hash = simplenews_generate_hash($subscriber->mail, 'manage');
            $url = 'newsletter/subscriptions/' . $subscriber->snid . '/' . $newsletter->newsletter_id . '/' . REQUEST_TIME . '/' . $hash;
            $replacements[$original] = url($url, $url_arguments);
            break;

          case 'mail':
            $replacements[$original] = $sanitize ? check_plain($subscriber->mail) : $subscriber->mail;
            break;
        }
      }

      if (($user_tokens = token_find_with_prefix($tokens, 'user')) && !empty($subscriber->uid)) {
        $replacements += token_generate('user', $user_tokens, array('user' => user_load($subscriber->uid)), $options);
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
              $replacements[$original] = $sanitize ? check_plain($newsletter->name) : $newsletter->name;
            }
            else {
              $replacements[$original] = t('Unassigned newsletter');
            }
            break;

          case 'url':
            $uri = entity_uri('simplenews_newsletter', $newsletter);
            $replacements[$original] = url($uri['path'], $uri['options']);
            break;
        }
      }
      break;
  }

  return $replacements;
}
