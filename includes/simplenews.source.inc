<?php

/**
 * @file
 * Contains SimplenewsSource interface and implementations.
 */

/**
 * The source used to build a newsletter mail.
 */
interface SimplenewsSourceInterface {

  /**
   * Returns the mail headers.
   *
   * @param $headers
   *   The default mail headers.
   *
   * @return
   *   Mail headers as an array.
   */
  function getHeaders(array $headers);

  /**
   * Returns the mail subject.
   */
  function getSubject();

  /**
   * Returns the mail body.
   */
  function getBody();

  /**
   * Returns the plaintext body.
   */
  function getPlainBody();

  /**
   * Returns the mail footer.
   */
  function getFooter();

  /**
   * Returns the mail format.
   *
   * @return
   *   The mail format as string, either 'plain' or 'html'.
   */
  function getFormat();

  /**
   * Returns the recipent of this newsletter mail.
   *
   * @return
   *   The recipient mail address(es) of this newsletter as a string.
   */
  function getRecipient();

  /**
   * The language that should be used for this newsletter mail.
   */
  function getLanguage();

  /**
   * Returns an array of attachments for this newsletter mail.
   *
   * @return
   *   An array of managed file objects with properties uri, filemime and so on.
   */
  function getAttachments();

  /**
   * Returns the token context to be used with token replacements.
   *
   * @return
   *   An array of objects as required by token_replace().
   */
  function getTokenContext();

  /**
   * Returns the mail key to be used for drupal_mail().
   *
   * @return
   *   The mail key, either test or node.
   */
  function getKey();

  /**
   * Returns the formatted from mail address.
   */
  function getFromFormatted();

  /**
   * Returns the plain mail address.
   */
  function getFromAddress();
}

/**
 * Source interface based on a node.
 *
 * This is the interface that needs to be implemented to be compatible with
 * the default simplenews spool implementation and therefore exposed in
 * hook_simplenews_source_cache_info().
 */
interface SimplenewsSourceNodeInterface extends SimplenewsSourceInterface {

  /**
   * Create a source based on a node and subscriber.
   */
  function __construct($node, $subscriber);

  /**
   * Returns the actually used node of this source.
   */
  function getNode();
}

/**
 * Interface for a simplenews source cache implementation.
 *
 * This is only compatible with the SimplenewsSpoolSourceInterface interface.
 */
interface SimplenewsSourceCacheInterface {

  /**
   * Create a new instance, allows to initialize based on the used
   * source.
   */
  function __construct(SimplenewsSourceNodeInterface $source);

  /**
   * Return a cached element, if existing.
   *
   * Although group and key can be used to identify the requested cache, the
   * implementations are responsible to create a unique cache key themself using
   * the $source. For example based on the node id and the language.
   *
   * @param $group
   *   Group of the cache key, which allows cache implementations to decide what
   *   they want to cache. Currently used groups:
   *     - data: Raw data, e.g. attachments.
   *     - build: Built and themed content, before personalizations like tokens.
   *     - final: The final returned data. Caching this means that newsletter
   *       can not be personalized anymore.
   * @param $key
   *   Identifies the requested element, e.g. body, footer or attachments.
   */
  function get($group, $key);

  /**
   * Write an element to the cache.
   *
   * Although group and key can be used to identify the requested cache, the
   * implementations are responsible to create a unique cache key themself using
   * the $source. For example based on the node id and the language.
   *
   * @param $group
   *   Group of the cache key, which allows cache implementations to decide what
   *   they want to cache. Currently used groups:
   *     - data: Raw data, e.g. attachments.
   *     - build: Built and themed content, before personalizations like tokens.
   *     - final: The final returned data. Caching this means that newsletter
   *       can not be personalized anymore.
   * @param $key
   *   Identifies the requested element, e.g. body, footer or attachments.
   * @param $data
   *   The data to be saved in the cache.
   */
  function set($group, $key, $data);
}

/**
 * A Simplenews spool implementation is a factory for Simplenews sources.
 *
 * Their main functionility is to return a number of sources based on the passed
 * in array of mail spool rows. Additionally, it needs to return the processed
 * mail rows after a source was sent.
 *
 * @todo: Move spool functions into this interface.
 */
interface SimplenewsSpoolInterface {

  /**
   * Initalizes the spool implementation.
   *
   * @param $spool_list
   *   An array of rows from the {simplenews_mail_spool} table.
   */
  function __construct($pool_list);

  /**
   * Returns a Simplenews source to be sent.
   *
   * A single source may represent any number of mail spool rows, e.g. by
   * addressing them as BCC.
   */
  function nextSource();

  /**
   * Returns the processed mail spool rows, keyed by the msid.
   *
   * Only rows that were processed while preparing the previously returned
   * source must be returned.
   *
   * @return
   *   An array of mail spool rows, keyed by the msid. Can optionally have set
   *   the following additional properties.
   *     - actual_nid: In case of content translation, the source node that was
   *       used for this mail.
   *     - error: FALSE if the prepration for this row failed. For example set
   *       when the corresponding node failed to load.
   *     - status: A simplenews spool status to indicate the status.
   */
  function getProcessed();
}

/**
 * Abstract base class for simplenews sources.
 */
class SimplenewsSpool implements SimplenewsSpoolInterface {

  /**
   * Array with mail spool rows being processed.
   *
   * @var array
   */
  protected $spool_list;

  /**
   * Array of the processed mail spool rows.
   */
  protected $processed = array();

  /**
   * Implements SimplenewsSpoolInterface::_construct($spool_list);
   */
  public function __construct($spool_list) {
    $this->spool_list = $spool_list;
  }

  /**
   * Implements SimplenewsSpoolInterface::nextSource();
   */
  public function nextSource() {
    // Get the current mail spool row and update the internal pointer to the
    // next row.
    $return = each($this->spool_list);
    // If we're done, return false.
    if (!$return) {
      return FALSE;
    }
    $spool_data = $return['value'];

    // Store this spool row as processed.
    $this->processed[$spool_data->msid] = $spool_data;

    $node = node_load($spool_data->nid);
    if (!$node) {
      // If node the load failed, set the processed status done and proceed with
      // the next mail.
      $this->processed[$spool_data->msid]->result = array(
        'status' => SIMPLENEWS_SPOOL_DONE,
        'error' => TRUE
      );
      return $this->prepareMail();
    }

    if ($spool_data->data) {
      $subscriber = $spool_data->data;
    }
    else {
      $subscriber = simplenews_get_subscription(_simplenews_user_load($spool_data->mail));
    }

    $source_class = $this->getSourceImplementation($spool_data);
    $source = new $source_class($node, $subscriber);

    // Set which node is actually used. In case of a translation set, this might
    // not be the same node.
    $this->processed[$spool_data->msid]->actual_nid = $source->getNode()->nid;
    return $source;
  }

  /**
   * Implements SimplenewsSpoolInterface::getProcessed();
   */
  function getProcessed() {
    $processed = $this->processed;
    $this->processed = array();
    return $processed;
  }

  /**
   * Return the Simplenews source implementation for the given mail spool row.
   */
  protected function getSourceImplementation($spool_data) {
    return variable_get('simplenews_source', 'SimplenewsSourceNode');
  }
}

/**
 * Simplenews source implementation based on nodes for a single subscriber.
 */
class SimplenewsSourceNode implements SimplenewsSourceNodeInterface {

  /**
   * The node object.
   */
  protected $node;

  /**
   * The cached build render array.
   */
  protected $build;

  /**
   * The newsletter category.
   */
  protected $category;

  /**
   * The subscriber and therefore recipient of this mail.
   */
  protected $subscriber;

  /**
   * The mail key used for drupal_mail().
   */
  protected $key = 'test';

  /**
   * The simplenews newsletter.
   */
  protected $newsletter;

  /**
   * Cache implementation used for this source.
   *
   * @var SimplenewsSourceCacheInterface
   */
  protected $cache;

  /**
   * Implements SimplenewsSourceInterface::_construct();
   */
  public function __construct($node, $subscriber) {
    $this->setSubscriber($subscriber);
    $this->setNode($node);
    $this->newsletter = simplenews_newsletter_load($node->nid);
    $this->category = simplenews_category_load($this->newsletter->tid);
    $this->initCache();
  }

  /**
   * Set the node of this source.
   *
   * If the node is part of a translation set, switch to the node for the
   * requested language, if existent.
   */
  public function setNode($node) {
    $langcode = $this->getLanguage();
    $nid = $node->nid;
    if (module_exists('translation')) {
      // If the node has translations and a translation is required
      // the equivalent of the node in the required language is used
      // or the base node (nid == tnid) is used.
      if ($tnid = $node->tnid) {
        if ($langcode != $node->language) {
          $translations = translation_node_get_translations($tnid);
          // A translation is available in the preferred language.
          if ($translation = $translations[$langcode]) {
            $nid = $translation->nid;
            $langcode = $translation->language;
          }
          else {
            // No translation found which matches the preferred language.
            foreach ($translations as $translation) {
              if ($translation->nid == $tnid) {
                $nid = $tnid;
                $langcode = $translation->language;
                break;
              }
            }
          }
        }
      }
    }
    // If a translation of the node is used, load this node.
    if ($nid != $node->nid) {
      $this->node = node_load($nid);
    }
    else {
      $this->node = $node;
    }
  }

  /**
   * Initialize the cache implementation.
   */
  protected function initCache() {
    $class = variable_get('simplenews_source_cache', 'SimplenewsSourceCacheBuild');
    $this->cache = new $class($this);
  }

  /**
   * Returns the corresponding category.
   */
  public function getCategory() {
    return $this->category;
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
    if ($this->category->receipt) {
      $headers['Disposition-Notification-To'] = $from;
      $headers['X-Confirm-Reading-To'] = $from;
    }

    // Add priority if set.
    switch ($this->category->priority) {
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
    $message['headers']['From'] = $this->getFromFormatted();
    $message['headers']['List-Unsubscribe'] = '<' . token_replace('[simplenews-subscriber:unsubscribe-url]', $this->getTokenContext(), array('sanitize' => FALSE)) . '>';

    // Add general headers
    $headers['Precedence'] = 'bulk';
    return $headers;
  }

  /**
   * Implements SimplenewsSourceInterface::getTokenContext().
   */
  function getTokenContext() {
    return array(
      'category' => $this->getCategory(),
      'account' => $this->getSubscriber(),
      'node' => $this->getNode(),
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
    $name = $this->getCategory()->from_name;

    // Windows based PHP systems don't accept formatted emails.
    if (drupal_substr(PHP_OS, 0, 3) == 'WIN') {
      return $this->getFromAddress();
    }
    else {
      return '"' . mime_header_encode($name) . '" <' . $this->getFromAddress() . '>';
    }

    return $formatted_address;
  }

  /**
   * Implements SimplenewsSourceInterface::getFromAddress().
   */
  function getFromAddress() {
    return $this->getCategory()->from_address;
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
    return $this->getCategory()->format;
  }

  /**
   * Implements SimplenewsSourceInterface::getLanguage().
   */
  function getLanguage() {
    return $this->getSubscriber()->language;
  }

  /**
   * Implements SimplenewsSourceSpoolInterface::getNode().
   */
  function getNode() {
    return $this->node;
  }

  /**
   * Implements SimplenewsSourceInterface::getSubject().
   */
  function getSubject() {
    // Build email subject and perform some sanitizing.
    $subject = token_replace($this->getCategory()->email_subject, $this->getTokenContext(), array('sanitize' => FALSE));

    // Line breaks are removed from the email subject to prevent injection of
    // malicious data into the email header.
    $subject = str_replace(array("\r", "\n"), '', $subject);
    return $subject;
  }

  /**
   * Overwrites the current content language for i18n_select.
   */
  protected function setContentLanguage($language) {
    if (module_exists('i18n_select')) {
      $this->original_language = $GLOBALS['language_content'];
      $languages = language_list();
      $GLOBALS['language_content'] = $languages[$language];
    }
  }

  /**
   * Reset the content language back to the previous value.
   */
  protected function resetContentLanguage() {
    if (module_exists('i18n_select')) {
      $GLOBALS['language_content'] = $this->original_language;
    }
  }

  /**
   * Build the node object.
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

    // Set the active language to the node's language.
    // This needs to be done as otherwise the language used to send the mail
    // is the language of the user logged in.
    $this->setContentLanguage($this->node->language);

    // Build message body
    // Supported view modes: 'email_plain', 'email_html', 'email_textalt'
    $build = node_view($this->node, 'email_' . $format);
    unset($build['#theme']);

    foreach (field_info_instances('node', $this->node->type) as $field_name => $field) {
      if (isset($build[$field_name])) {
        $build[$field_name]['#theme'] = 'simplenews_field';
      }
    }

    $this->resetContentLanguage();

    $this->build = $build;
    return $build;
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
    $body = theme('simplenews_newsletter_body', array('build' => $this->build($format), 'category' => $this->getCategory(), 'language' => $this->getLanguage()));
    $this->cache->set('build', 'body', $body);
    return $body;
  }

  /**
   * Implements SimplenewsSourceInterface::getBody().
   */
  public function getBody() {

    // Impersonate the subscriber if he is a user.
    if ($uid = $this->getSubscriber()->uid) {
      simplenews_impersonate_user($uid);
    }

    if ($cache = $this->cache->get('final', 'body')) {
      return $cache;
    }

    $body = $this->buildBody();

    // Build message body, replace tokens.
    $body = token_replace($body, $this->getTokenContext(), array('sanitize' => FALSE));
    $this->cache->set('final', 'body', $body);

    // Switch back to the previous user.
    if ($uid) {
      simplenews_revert_user();
    }
    return $body;
  }

  /**
   * Implements SimplenewsSourceInterface::getBody().
   */
  public function getPlainBody() {

    // Impersonate the subscriber if he is a user.
    if ($uid = $this->getSubscriber()->uid) {
      simplenews_impersonate_user($uid);
    }

    if ($cache = $this->cache->get('final', 'body:plain')) {
      return $cache;
    }
    $body = $this->buildBody('plain');

    // Build message body, replace tokens.
    $body = token_replace($body, $this->getTokenContext(), array('sanitize' => FALSE));
    $body = simplenews_html_to_text($body, $this->getCategory()->hyperlinks);
    $this->cache->set('final', 'body:plain', $body);

    // Switch back to the previous user.
    if ($uid) {
      simplenews_revert_user();
    }
    return $body;
  }

  /**
   * Builds the themed footer.
   */
  protected function buildFooter() {
    if ($cache = $this->cache->get('build', 'footer')) {
      return $cache;
    }

    // Build and buffer message footer
    $footer = theme('simplenews_newsletter_footer', array(
      'build' => $this->build(),
      'category' => $this->getCategory(),
      'context' => $this->getTokenContext(),
      'key' => $this->getKey(),
      'language' => $this->getLanguage(),
      )
    );
    $this->cache->set('build', 'footer', $footer);
    return $footer;
  }

  /**
   * Implements SimplenewsSourceInterface::getFooter().
   */
  function getFooter() {
    if ($cache = $this->cache->get('final', 'footer')) {
      return $cache;
    }

    if ($this->category->opt_inout != SIMPLENEWS_OPT_INOUT_HIDDEN) {
      $final_footer = token_replace($this->buildFooter(), $this->getTokenContext(), array('sanitize' => FALSE));
    }

    $this->cache->set('build', 'footer', $final_footer);
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
    foreach (field_info_instances('node', $this->node->type) as $field_name => $field_instance) {
      // @todo: Find a better way to support more field types.
      // Only add fields of type file which are enabled for the current view
      // mode as attachments.
      $field = field_info_field($field_name);
      if ($field['type'] == 'file' && isset($build[$field_name])) {

        if ($items = field_get_items('node', $node, $field_name)) {
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

/**
 * Abstract implementation of the source caching that does static caching.
 *
 * Subclasses need to implement the abstract function isCacheable() to decide
 * what should be cached.
 */
abstract class SimplenewsSourceCacheStatic implements SimplenewsSourceCacheInterface {

  /**
   * The simplenews source for which this cache is used.
   *
   * @var SimplenewsSourceNodeInterface
   */
  protected $source;

  /**
   * The cache identifier for the given source.
   */
  protected $cid;

  /**
   * The static cache.
   */
  protected static $cache = array();

  /**
   * Implements SimplenewsSourceNodeInterface::__construct().
   */
  public function __construct(SimplenewsSourceNodeInterface $source) {
    $this->source = $source;
  }

  /**
   * Returns the cache identifier for the current source.
   */
  protected function getCid() {
    if (empty($this->cid)) {
      $this->cid = $this->source->getNode()->nid . ':' . $this->source->getLanguage();
    }
    return $this->cid;
  }

  /**
   * Implements SimplenewsSourceNodeInterface::get().
   */
  public function get($group, $key) {
    if (!$this->isCacheable($group, $key)) {
      return;
    }

    if (isset(self::$cache[$this->getCid()][$group][$key])) {
      return self::$cache[$this->getCid()][$group][$key];
    }
  }

  /**
   * Implements SimplenewsSourceNodeInterface::set().
   */
  public function set($group, $key, $data) {
    if (!$this->isCacheable($group, $key)) {
      return;
    }

    self::$cache[$this->getCid()][$group][$key] = $data;
  }

  /**
   * Return if the requested element should be cached.
   *
   * @return
   *   TRUE if it should be cached, FALSE otherwise.
   */
  abstract function isCacheable($group, $key);
}

/**
 * Cache implementation that does not cache anything at all.
 */
class SimplenewsSourceCacheNone extends SimplenewsSourceCacheStatic {

  /**
   * Implements SimplenewsSourceCacheStatic::set().
   */
  public function isCacheable($group, $key) {
    return FALSE;
  }

}

/**
 * Source cache implementation that caches build and data element.
 */
class SimplenewsSourceCacheBuild extends SimplenewsSourceCacheStatic {

  /**
   * Implements SimplenewsSourceCacheStatic::set().
   */
  function isCacheable($group, $key) {

    // Only cache for anon users.
    if (user_is_logged_in()) {
      return FALSE;
    }

     // Only cache data and build information.
    return in_array($group, array('data', 'build'));
  }

}