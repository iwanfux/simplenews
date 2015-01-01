<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceEntity.
 */

namespace Drupal\simplenews\Source;

use Drupal\Component\Utility\Unicode;
use Drupal\file\Entity\File;
use Drupal\user\Entity\User;

/**
 * Default source class for entities.
 */
class SourceEntity implements SourceEntityInterface {

  /**
   * The entity object.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  protected $entity;

  /**
   * The entity type.
   */
  protected $entity_type;

  /**
   * The cached build render array.
   */
  protected $build;

  /**
   * The newsletter.
   */
  protected $newsletter;

  /**
   * The subscriber and therefore recipient of this mail.
   */
  protected $subscriber;

  /**
   * The mail key used for mails.
   */
  protected $key = 'test';

  /**
   * Cache implementation used for this source.
   *
   * @var SourceCacheInterface
   */
  protected $cache;

  /**
   * Implements SourceEntityInterface::_construct();
   */
  public function __construct($entity, $subscriber, $entity_type) {
    $this->setSubscriber($subscriber);
    $this->setEntity($entity, $entity_type);
    $this->initCache();
    $this->newsletter = $entity->simplenews_issue->entity;
  }

  /**
   * Set the entity of this source.
   */
  public function setEntity($entity, $entity_type) {
    $this->entity_type = $entity_type;
    $this->entity = $entity;
    if ($this->entity->hasTranslation($this->getLanguage())) {
      $this->entity = $this->entity->getTranslation($this->getLanguage());
    }
  }

  /**
   * Initialize the cache implementation.
   */
  protected function initCache() {
    $config = \Drupal::configFactory()->get('simplenews.settings');
    $class = $config->get('mail.source_cache');
    $this->cache = new $class($this);
  }

  /**
   * Returns the corresponding newsletter.
   */
  public function getNewsletter() {
    return $this->newsletter;
  }

  /**
   * Set the active subscriber.
   */
  public function setSubscriber($subscriber) {
    $this->subscriber = $subscriber;
  }

  /**
   * Return the subscriber object.
   */
  public function getSubscriber() {
    return $this->subscriber;
  }

  /**
   * Implements SourceInterface::getHeaders().
   */
  public function getHeaders(array $headers) {

    // If receipt is requested, add headers.
    if ($this->newsletter->receipt) {
      $headers['Disposition-Notification-To'] = $this->getFromAddress();
      $headers['X-Confirm-Reading-To'] = $this->getFromAddress();
    }

    // Add priority if set.
    switch ($this->newsletter->priority) {
      case SIMPLENEWS_PRIORITY_HIGHEST:
        $headers['Priority'] = 'High';
        $headers['X-Priority'] = '1';
        $headers['X-MSMail-Priority'] = 'Highest';
        break;
      case SIMPLENEWS_PRIORITY_HIGH:
        $headers['Priority'] = 'urgent';
        $headers['X-Priority'] = '2';
        $headers['X-MSMail-Priority'] = 'High';
        break;
      case SIMPLENEWS_PRIORITY_NORMAL:
        $headers['Priority'] = 'normal';
        $headers['X-Priority'] = '3';
        $headers['X-MSMail-Priority'] = 'Normal';
        break;
      case SIMPLENEWS_PRIORITY_LOW:
        $headers['Priority'] = 'non-urgent';
        $headers['X-Priority'] = '4';
        $headers['X-MSMail-Priority'] = 'Low';
        break;
      case SIMPLENEWS_PRIORITY_LOWEST:
        $headers['Priority'] = 'non-urgent';
        $headers['X-Priority'] = '5';
        $headers['X-MSMail-Priority'] = 'Lowest';
        break;
    }

    // Add user specific header data.
    $headers['From'] = $this->getFromFormatted();
    $headers['List-Unsubscribe'] = '<' . \Drupal::token()->replace('[simplenews-subscriber:unsubscribe-url]', $this->getTokenContext(), array('sanitize' => FALSE)) . '>';

    // Add general headers
    $headers['Precedence'] = 'bulk';
    return $headers;
  }

  /**
   * Implements SourceInterface::getTokenContext().
   */
  function getTokenContext() {
    return array(
      'newsletter' => $this->getNewsletter(),
      'simplenews_subscriber' => $this->getSubscriber(),
      $this->getEntityType() => $this->getEntity(),
    );
  }

  /**
   * Set the mail key.
   */
  function setKey($key) {
    $this->key = $key;
  }

  /**
   * Implements SourceInterface::getKey().
   */
  function getKey() {
    return $this->key;
  }

  /**
   * Implements SourceInterface::getFromFormatted().
   */
  function getFromFormatted() {
    // Windows based PHP systems don't accept formatted email addresses.
    if (Unicode::substr(PHP_OS, 0, 3) == 'WIN') {
      return $this->getFromAddress();
    }
    return '"' . addslashes(Unicode::mimeHeaderEncode($this->getNewsletter()->from_name)) . '" <' . $this->getFromAddress() . '>';
  }

  /**
   * Implements SourceInterface::getFromAddress().
   */
  function getFromAddress() {
    return $this->getNewsletter()->from_address;
  }

  /**
   * Implements SourceInterface::getRecipient().
   */
  function getRecipient() {
    return $this->getSubscriber()->getMail();
  }

  /**
   * Implements SourceInterface::getFormat().
   */
  function getFormat() {
    return $this->getNewsletter()->format;
  }

  /**
   * Implements SourceInterface::getLanguage().
   */
  function getLanguage() {
    return $this->getSubscriber()->getLangcode();
  }

  /**
   * Implements SourceEntityInterface::getEntity().
   */
  function getEntity() {
    return $this->entity;
  }

  /**
   * Implements SourceEntityInterface::getEntityType().
   */
  function getEntityType() {
    return $this->entity_type;
  }

  /**
   * Implements SourceInterface::getSubject().
   */
  function getSubject() {
    // Build email subject and perform some sanitizing.
    // Use the requested language if enabled.
    $langcode = $this->getLanguage();
    $subject = \Drupal::token()->replace($this->getNewsletter()->subject, $this->getTokenContext(), array('sanitize' => FALSE, 'langcode' => $langcode));

    // Line breaks are removed from the email subject to prevent injection of
    // malicious data into the email header.
    $subject = str_replace(array("\r", "\n"), '', $subject);
    return $subject;
  }

  /**
   * Set up the necessary language and user context.
   */
  protected function setContext() {

    // Switch to the user
    if ($this->uid = $this->getSubscriber()->getUserId()) {
      \Drupal::service('account_switcher')->switchTo(User::load($this->uid));
    }

    // Change language if the requested language is enabled.
    /*$language = $this->getLanguage();
    $languages = LanguageManagerInterface::getLanguages();
    if (isset($languages[$language])) {
      $this->original_language = \Drupal::languageManager()->getCurrentLanguage();
      $GLOBALS['language'] = $languages[$language];
      $GLOBALS['language_url'] = $languages[$language];
      // Overwrites the current content language for i18n_select.
      if (\Drupal::moduleHandler()->moduleExists('i18n_select')) {
        $GLOBALS['language_content'] = $languages[$language];
      }
    }*/
  }

  /**
   * Reset the context.
   */
  protected function resetContext() {

    // Switch back to the previous user.
    if ($this->uid) {
      \Drupal::service('account_switcher')->switchBack();
    }

    // Switch language back.
    if (!empty($this->original_language)) {
      $GLOBALS['language'] = $this->original_language;
      $GLOBALS['language_url'] = $this->original_language;
      if (\Drupal::moduleHandler()->moduleExists('i18n_select')) {
        $GLOBALS['language_content'] = $this->original_language;
      }
    }
  }

  /**
   * Build the entity object.
   *
   * The resulting build array is cached as it is used in multiple places.
   * @param $format
   *   (Optional) Override the default format. Defaults to getFormat().
   */
  protected function build($format = NULL) {
    if (empty($format)) {
      $format = $this->getFormat();
    }
    if (!empty($this->build[$format])) {
      return $this->build[$format];
    }

    // Build message body
    // Supported view modes: 'email_plain', 'email_html', 'email_textalt'
    $build = \Drupal::entityManager()->getViewBuilder($this->getEntityType())->view($this->getEntity(), 'email_' . $format, $this->getLanguage());
    $build['#entity_type'] = $this->getEntityType();

    // We need to prevent the standard theming hooks, but we do want to allow
    // modules such as panelizer that override it, so only clear the standard
    // entity hook and entity type hooks.
    if ($build['#theme'] == 'entity' || $build['#theme'] == $this->getEntityType()) {
      unset($build['#theme']);
    }

    foreach (\Drupal::entityManager()->getFieldDefinitions($this->getEntityType(), $this->getEntity()->bundle()) as $field_name => $field) {
      if (isset($build[$field_name])) {
        $build[$field_name]['#theme'] = 'simplenews_field';
      }
    }

    $this->build[$format] = $build;
    return $this->build[$format];
  }

  /**
   * Build the themed newsletter body.
   *
   * @param $format
   *   (Optional) Override the default format. Defaults to getFormat().
   */
  protected function buildBody($format = NULL) {
    if (empty($format)) {
      $format = $this->getFormat();
    }
    if ($cache = $this->cache->get('build', 'body:' . $format)) {
      return $cache;
    }
    $body = array(
      '#theme' => 'simplenews_newsletter_body',
      '#build' => $this->build($format),
      '#newsletter' => $this->getNewsletter(),
      '#language' => $this->getLanguage(),
      '#simplenews_subscriber' => $this->getSubscriber(),
    );
    $markup = \Drupal::service('renderer')->renderPlain($body);
    $this->cache->set('build', 'body:' . $format, $markup);
    return $markup;
  }

  /**
   * Implements SourceInterface::getBody().
   */
  public function getBody() {
    return $this->getBodyWithFormat($this->getFormat());
  }

  /**
   * Implements SourceInterface::getBody().
   */
  public function getPlainBody() {
    return $this->getBodyWithFormat('plain');
  }

  /**
   * Get the body with the requested format.
   *
   * @param $format
   *   Either html or plain.
   *
   * @return
   *   The rendered mail body as a string.
   */
  protected function getBodyWithFormat($format) {
    // Switch to correct user and language context.
    $this->setContext();

    if ($cache = $this->cache->get('final', 'body:' . $format)) {
      return $cache;
    }

    $body = $this->buildBody($format);

    // Build message body, replace tokens.
    $body = \Drupal::token()->replace($body, $this->getTokenContext(), array('sanitize' => FALSE, 'langcode' => $this->getLanguage()));
    if ($format == 'plain') {
      // Convert HTML to text if requested to do so.
      $body = simplenews_html_to_text($body, $this->getNewsletter()->hyperlinks);
    }
    $this->cache->set('final', 'body:' . $format, $body);
    $this->resetContext();
    return $body;
  }

  /**
   * Builds the themed footer.
   *
   * @param $format
   *   (Optional) Set the format of this footer build, overrides the default
   *   format.
   */
  protected function buildFooter($format = NULL) {
    if ($format == 'plain') {
      //debug(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
    }
    if (empty($format)) {
      $format = $this->getFormat();
    }

    if ($cache = $this->cache->get('build', 'footer:' . $format)) {
      return $cache;
    }

    // Build and buffer message footer
    $footer = array(
      '#theme' => 'simplenews_newsletter_footer',
      '#build' => $this->build($format),
      '#newsletter' => $this->getNewsletter(),
      '#context' => $this->getTokenContext(),
      '#key' => $this->getKey(),
      '#language' => $this->getLanguage(),
      '#format' => $format,
    );
    $markup = \Drupal::service('renderer')->renderPlain($footer);

    $this->cache->set('build', 'footer:' . $format, $markup);
    return $markup;
  }

  /**
   * Implements SourceInterface::getFooter().
   */
  public function getFooter() {
    return $this->getFooterWithFormat($this->getFormat());
  }

  /**
   * Implements SourceInterface::getPlainFooter().
   */
  public function getPlainFooter() {
    return $this->getFooterWithFormat('plain');
  }

  /**
   * Get the footer in the specified format.
   *
   * @param $format
   *   Either html or plain.
   *
   * @return
   *   The footer for the requested format.
   */
  protected function getFooterWithFormat($format) {
    // Switch to correct user and language context.
    $this->setContext();
    if ($cache = $this->cache->get('final', 'footer:' . $format)) {
      return $cache;
    }
    $final_footer = \Drupal::token()->replace($this->buildFooter($format), $this->getTokenContext(), array('sanitize' => FALSE, 'langcode' => $this->getLanguage()));
    $this->cache->set('final', 'footer:' . $format, $final_footer);
    $this->resetContext();
    return $final_footer;
  }

  /**
   * Implements SourceInterface::getAttachments().
   */
  function getAttachments() {
    if ($cache = $this->cache->get('data', 'attachments')) {
      return $cache;
    }

    $attachments = array();
    $build = $this->build();
    $fids = array();
    $bundle = $this->getEntity()->bundle();
    foreach ($this->getEntity()->getFieldDefinitions($this->getEntityType(), $bundle) as $field_name => $field_definition) {
      // @todo: Find a better way to support more field types.
      // Only add fields of type file which are enabled for the current view
      // mode as attachments.
      if ($field_definition->getType() == 'file' && isset($build[$field_name])) {

        if ($items = $this->node->get($field_name)) {
          foreach ($items as $item) {
            $fids[] = $item->target_id;
          }
        }
      }
    }
    if (!empty($fids)) {
      $attachments = File::loadMultiple($fids);
    }

    $this->cache->set('data', 'attachments', $attachments);
    return $attachments;
  }

}
