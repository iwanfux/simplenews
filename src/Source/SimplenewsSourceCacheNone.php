<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceCacheNone.
 */

namespace Drupal\simplenews\Source;

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