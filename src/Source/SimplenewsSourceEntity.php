<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceEntity.
 */

namespace Drupal\simplenews\Source;

/**
 * Default source class for entities.
 */
class SimplenewsSourceEntity implements SimplenewsSourceEntityInterface {

  /**
   * The entity object.
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
   * The mail key used for drupal_mail().
   */
  protected $key = 'test';

  /**
   * Cache implementation used for this source.
   *
   * @var SimplenewsSourceCacheInterface
   */
  protected $cache;

  /**
   * Implements SimplenewsSourceEntityInterface::_construct();
   */
  public function __construct($entity, $subscriber, $entity_type) {
    $this->setSubscriber($subscriber);
    $this->setEntity($entity, $entity_type);
    $this->initCache();
    $this->newsletter = simplenews_newsletter_load(simplenews_issue_newsletter_id($entity));
  }

  /**
   * Set the entity of this source.
   */
  public function setEntity($entity, $entity_type) {
    $this->entity_type = $entity_type;
    $this->entity = $entity;
  }

  /**
   * Initialize the cache implementation.
   */
  protected function initCache() {
    $class = variable_get('simplenews_source_cache', 'SimplenewsSourceCacheBuild');
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
   * Implements SimplenewsSourceInterface::getHeaders().
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
    $headers['List-Unsubscribe'] = '<' . token_replace('[simplenews-subscriber:unsubscribe-url]', $this->getTokenContext(), array('sanitize' => FALSE)) . '>';

    // Add general headers
    $headers['Precedence'] = 'bulk';
    return $headers;
  }

  /**
   * Implements SimplenewsSourceInterface::getTokenContext().
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
   * Implements SimplenewsSourceInterface::getKey().
   */
  function getKey() {
    return $this->key;
  }

  /**
   * Implements SimplenewsSourceInterface::getFromFormatted().
   */
  function getFromFormatted() {
    // Windows based PHP systems don't accept formatted email addresses.
    if (drupal_substr(PHP_OS, 0, 3) == 'WIN') {
      return $this->getFromAddress();
    }

    return '"' . addslashes(mime_header_encode($this->getNewsletter()->from_name)) . '" <' . $this->getFromAddress() . '>';
  }

  /**
   * Implements SimplenewsSourceInterface::getFromAddress().
   */
  function getFromAddress() {
    return $this->getNewsletter()->from_address;
  }

  /**
   * Implements SimplenewsSourceInterface::getRecipient().
   */
  function getRecipient() {
    return $this->getSubscriber()->mail;
  }

  /**
   * Implements SimplenewsSourceInterface::getFormat().
   */
  function getFormat() {
    return $this->getNewsletter()->format;
  }

  /**
   * Implements SimplenewsSourceInterface::getLanguage().
   */
  function getLanguage() {
    return $this->getSubscriber()->language;
  }

  /**
   * Implements SimplenewsSourceEntityInterface::getEntity().
   */
  function getEntity() {
    return $this->entity;
  }

  /**
   * Implements SimplenewsSourceEntityInterface::getEntityType().
   */
  function getEntityType() {
    return $this->entity_type;
  }

  /**
   * Implements SimplenewsSourceInterface::getSubject().
   */
  function getSubject() {
    // Build email subject and perform some sanitizing.
    $langcode = $this->getLanguage();
    $language_list = language_list();
    // Use the requested language if enabled.
    $language = isset($language_list[$langcode]) ? $language_list[$langcode] : NULL;
    $subject = token_replace($this->getNewsletter()->email_subject, $this->getTokenContext(), array('sanitize' => FALSE, 'language' => $language));

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
    if ($this->uid = $this->getSubscriber()->uid) {
      simplenews_impersonate_user($this->uid);
    }

    // Change language if the requested language is enabled.
    $language = $this->getLanguage();
    $languages = language_list();
    if (isset($languages[$language])) {
      $this->original_language = $GLOBALS['language'];
      $GLOBALS['language'] = $languages[$language];
      $GLOBALS['language_url'] = $languages[$language];
      // Overwrites the current content language for i18n_select.
      if (\Drupal::moduleHandler()->moduleExists('i18n_select')) {
        $GLOBALS['language_content'] = $languages[$language];
      }
    }
  }

  /**
   * Reset the context.
   */
  protected function resetContext() {

    // Switch back to the previous user.
    if ($this->uid) {
      simplenews_revert_user();
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
    $build = entity_view($this->getEntityType(), array($this->getEntity()), 'email_' . $format);
    $build = reset($build[$this->getEntityType()]);

    // We need to prevent the standard theming hooks, but we do want to allow
    // modules such as panelizer that override it, so only clear the standard
    // entity hook and entity type hooks.
    if ($build['#theme'] == 'entity' || $build['#theme'] == $this->getEntityType()) {
      unset($build['#theme']);
    }

    list(, , $bundle) = entity_extract_ids($this->getEntityType(), $this->getEntity());
    foreach (field_info_instances($this->getEntityType(), $bundle) as $field_name => $field) {
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
    $body = theme('simplenews_newsletter_body', array('build' => $this->build($format), 'newsletter' => $this->getNewsletter(), 'language' => $this->getLanguage(), 'simplenews_subscriber' => $this->getSubscriber()));
    $this->cache->set('build', 'body:' . $format, $body);
    return $body;
  }

  /**
   * Implements SimplenewsSourceInterface::getBody().
   */
  public function getBody() {
    return $this->getBodyWithFormat($this->getFormat());
  }

  /**
   * Implements SimplenewsSourceInterface::getBody().
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
    $body = token_replace($body, $this->getTokenContext(), array('sanitize' => FALSE));
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
    if (empty($format)) {
      $format = $this->getFormat();
    }

    if ($cache = $this->cache->get('build', 'footer:' . $format)) {
      return $cache;
    }

    // Build and buffer message footer
    $footer = theme('simplenews_newsletter_footer', array(
      'build' => $this->build($format),
      'newsletter' => $this->getNewsletter(),
      'context' => $this->getTokenContext(),
      'key' => $this->getKey(),
      'language' => $this->getLanguage(),
      'format' => $format,
    ));
    $this->cache->set('build', 'footer:' . $format, $footer);
    return $footer;
  }

  /**
   * Implements SimplenewsSourceInterface::getFooter().
   */
  public function getFooter() {
    return $this->getFooterWithFormat($this->getFormat());
  }

  /**
   * Implements SimplenewsSourceInterface::getPlainFooter().
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
    $final_footer = token_replace($this->buildFooter($format), $this->getTokenContext(), array('sanitize' => FALSE));
    $this->cache->set('final', 'footer:' . $format, $final_footer);
    $this->resetContext();
    return $final_footer;
  }

  /**
   * Implements SimplenewsSourceInterface::getAttachments().
   */
  function getAttachments() {
    if ($cache = $this->cache->get('data', 'attachments')) {
      return $cache;
    }

    $attachments = array();
    $build = $this->build();
    $fids = array();
    list(, , $bundle) = entity_extract_ids($this->getEntityType(), $this->getEntity());
    foreach (field_info_instances($this->getEntityType(), $bundle) as $field_name => $field_instance) {
      // @todo: Find a better way to support more field types.
      // Only add fields of type file which are enabled for the current view
      // mode as attachments.
      $field = field_info_field($field_name);
      if ($field['type'] == 'file' && isset($build[$field_name])) {

        if ($items = field_get_items('node', $this->node, $field_name)) {
          foreach ($items as $item) {
            $fids[] = $item['fid'];
          }
        }
      }
    }
    if (!empty($fids)) {
      $attachments = file_load_multiple($fids);
    }

    $this->cache->set('data', 'attachments', $attachments);
    return $attachments;
  }
}