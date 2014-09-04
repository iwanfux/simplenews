<?php


/**
 * Simplenews source implementation based on nodes for a single subscriber.
 *
 * @ingroup source
 */
class SimplenewsSourceNode extends SimplenewsSourceEntity {

  /**
   * Overrides SimplenewsSourceEntity::__construct();
   */
  public function __construct($node, $subscriber, $entity_type = 'node') {
    parent::__construct($node, $subscriber, $entity_type);
  }

  /**
   * Overrides SimplenewsSourceEntity::setEntity().
   *
   * Handles node translation.
   */
  public function setEntity($node, $entity_type = 'node') {
    $this->entity_type = $entity_type;
    $langcode = $this->getLanguage();
    $nid = $node->nid;
    if (\Drupal::moduleHandler()->moduleExists('translation')) {
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
      $this->entity = node_load($nid);
    }
    else {
      $this->entity = $node;
    }
  }

  /**
   * Set the node.
   */
  function setNode($node) {
    $this->setEntity($node, 'node');
  }

  /**
   * Implements SimplenewsSourceSpoolInterface::getNode().
   */
  function getNode() {
    return $this->entity;
  }
}

/**
 * Abstract implementation of the source caching that does static caching.
 *
 * Subclasses need to implement the abstract function isCacheable() to decide
 * what should be cached.
 *
 * @ingroup source
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
   * Implements SimplenewsSourceCacheInterface::__construct().
   */
  public function __construct(SimplenewsSourceEntityInterface $source) {
    $this->source = $source;

    self::$cache = &drupal_static(__CLASS__, array());
  }

  /**
   * Returns the cache identifier for the current source.
   */
  protected function getCid() {
    if (empty($this->cid)) {
      $entity_id = entity_id($this->source->getEntityType(), $this->source->getEntity());
      $this->cid = $this->source->getEntityType() . ':' . $entity_id . ':' . $this->source->getLanguage();
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
 *
 * @ingroup source
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
 *
 * @ingroup source
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

/**
 * Example source implementation used for tests.
 *
 * @ingroup source
 */
class SimplenewsSourceTest implements SimplenewsSourceInterface {

  protected $format;

  public function __construct($format) {
    $this->format = $format;
  }

  public function getAttachments() {
    return array(
      array(
        'uri' => 'example://test.png',
        'filemime' => 'x-example',
        'filename' => 'test.png',
      ),
    );
  }

  public function getBody() {
    return $this->getFormat() == 'plain' ? $this->getPlainBody() : 'the body';
  }

  public function getFooter() {
    return $this->getFormat() == 'plain' ? $this->getPlainFooter() : 'the footer';
  }

  public function getPlainFooter() {
    return 'the plain footer';
  }

  public function getFormat() {
    return $this->format;
  }

  public function getFromAddress() {
    return 'test@example.org';
  }

  public function getFromFormatted() {
    return 'Test <test@example.org>';
  }

  public function getHeaders(array $headers) {
    $headers['X-Simplenews-Test'] = 'OK';
    return $headers;
  }

  public function getKey() {
    return 'node';
  }

  public function getLanguage() {
    return 'en';
  }

  public function getPlainBody() {
    return 'the plain body';
  }

  public function getRecipient() {
    return 'recipient@example.org';
  }

  public function getSubject() {
    return 'the subject';
  }

  public function getTokenContext() {
    return array();
  }
}
