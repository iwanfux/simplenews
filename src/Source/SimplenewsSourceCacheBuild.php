<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceCacheBuild.
 */

namespace Drupal\simplenews\Source;

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