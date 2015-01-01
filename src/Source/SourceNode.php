<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceNode.
 */

namespace Drupal\simplenews\Source;

/**
 * Simplenews source implementation based on nodes for a single subscriber.
 *
 * @ingroup source
 */
class SourceNode extends SourceEntity {

  /**
   * Overrides SourceEntity::__construct();
   */
  public function __construct($node, $subscriber, $entity_type = 'node') {
    parent::__construct($node, $subscriber, $entity_type);
  }

  /**
   * Set the node.
   */
  function setNode($node) {
    $this->setEntity($node, 'node');
  }

  /**
   * Implements SourceSpoolInterface::getNode().
   */
  function getNode() {
    return $this->entity;
  }
}
