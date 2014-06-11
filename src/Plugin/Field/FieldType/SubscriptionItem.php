<?php

/**
 * @file
 * Contains \Drupal\simplenews\Plugin\Field\FieldType\SubscriptionItem.
 */

namespace Drupal\simplenews\Plugin\Field\FieldType;

use \Drupal\entity_reference\ConfigurableEntityReferenceItem;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'subscription' entity field type (extended entity_reference).
 *
 * Supported settings (below the definition's 'settings' key) are:
 * - target_type: The entity type to reference. Required.
 * - target_bundle: (optional): If set, restricts the entity bundles which may
 *   may be referenced. May be set to an single bundle, or to an array of
 *   allowed bundles.
 * - status: A flag indicating whether the user is subscribed (1) or
 *   unsubscribed (0)
 * - timestamp: Time of when the user has (un)subscribed.
 * - source: The source via which the user has (un)subscribed.
 *
 * @FieldType(
 *   id = "simplenews_subscription",
 *   label = @Translation("Simplenews subscription"),
 *   description = @Translation("An entity field containing an extended entityreference."),
 *   no_ui = TRUE,
 *   constraints = {"ValidReference" = {}}
 * )
 */
class SubscriptionItem extends ConfigurableEntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Call the parent to define the target_id and entity properties.
    $properties = parent::propertyDefinitions($field_definition);

    $properties['status'] = DataDefinition::create('integer')
      ->setLabel(t('Status'))
      ->setSetting('unsigned', TRUE);
    
    $properties['timestamp'] = DataDefinition::create('timestamp')
      ->setLabel(t('Timestamp'));

    $properties['source'] = DataDefinition::create('string')
      ->setLabel(t('Source'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns']['status'] = array(
      'description' => 'A flag indicating whether the user is subscribed (1) or unsubscribed (0).',
      'type' => 'int',
      'size' => 'tiny',
      'not null' => TRUE,
      'default' => 1,
    );
    $schema['columns']['timestamp'] = array(
      'description' => 'UNIX timestamp of when the user is (un)subscribed.',
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 0,
    );
    $schema['columns']['source'] = array(
      'description' => 'The source via which the user is (un)subscription.',
      'type' => 'varchar',
      'length' => 24,
      'not null' => TRUE,
      'default' => '',
    );
    return $schema;
  }
}