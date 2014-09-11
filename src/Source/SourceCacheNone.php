<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceCacheNone.
 */

namespace Drupal\simplenews\Source;

/**
 * Cache implementation that does not cache anything at all.
 *
 * @ingroup source
 */
class SourceCacheNone extends SourceCacheStatic {

  /**
   * Implements SourceCacheStatic::set().
   */
  public function isCacheable($group, $key) {
    return FALSE;
  }

}