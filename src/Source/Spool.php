<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\Spool.
 */

namespace Drupal\simplenews\Source;

/**
 * Simplenews Spool implementation.
 *
 * @ingroup spool
 */
class Spool implements SpoolInterface {

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
   * Implements SpoolInterface::_construct($spool_list);
   */
  public function __construct($spool_list) {
    $this->spool_list = $spool_list;
  }

  /**
   * Implements SpoolInterface::nextSource();
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
    $entity = entity_load_single($spool_data->entity_type, $spool_data->entity_id);
    if (!$entity) {
      // If the entity load failed, set the processed status done and proceed with
      // the next mail.
      $this->processed[$spool_data->msid]->result = array(
        'status' => SIMPLENEWS_SPOOL_DONE,
        'error' => TRUE
      );
      return $this->nextSource();
    }

    if ($spool_data->data) {
      $subscriber = $spool_data->data;
    }
    else {
      $subscriber = simplenews_subscriber_load_by_mail($spool_data->mail);
    }

    if (!$subscriber) {
      // If loading the subscriber failed, set the processed status done and
      // proceed with the next mail.
      $this->processed[$spool_data->msid]->result = array(
        'status' => SIMPLENEWS_SPOOL_DONE,
        'error' => TRUE
      );
      return $this->nextSource();
    }

    $source_class = $this->getSourceImplementation($spool_data);
    $source = new $source_class($entity, $subscriber, $spool_data->entity_type);

    // Set which entity is actually used. In case of a translation set, this might
    // not be the same entity.
    $this->processed[$spool_data->msid]->actual_entity_type = $source->getEntityType();
    $this->processed[$spool_data->msid]->actual_entity_id
      = entity_id($source->getEntityType(), $source->getEntity());
    return $source;
  }

  /**
   * Implements SpoolInterface::getProcessed();
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
    $default = ($spool_data->entity_type == 'node') ? 'SourceNode' : NULL;

    // First check if there is a class set for this entity type (default
    // 'simplenews_source_node' to SourceNode.
    $class = variable_get('simplenews_source_' . $spool_data->entity_type, $default);

    // If no class was found, fall back to the generic 'simplenews_source'
    // variable.
    if (empty($class)) {
      $class = variable_get('simplenews_source', 'SourceEntity');
    }

    return $class;
  }
}