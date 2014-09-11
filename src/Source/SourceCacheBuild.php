<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceCacheBuild.
 */

namespace Drupal\simplenews\Source;

/**
 * Source cache implementation that caches build and data element.
 *
 * @ingroup source
 */
class SourceCacheBuild extends SourceCacheStatic {

  /**
   * Implements SourceCacheStatic::set().
   */
  function isCacheable($group, $key) {

    // Only cache for anon users.
    if (\Drupal::currentUser()->isAuthenticated()) {
      return FALSE;
    }

    // Only cache data and build information.
    return in_array($group, array('data', 'build'));
  }

}