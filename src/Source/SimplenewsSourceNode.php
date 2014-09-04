<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceNode.
 */

namespace Drupal\simplenews\Source;

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