<?php

/**
 * @file
 * Contains \Drupal\simplenews\RecipientHandler\Annotation\RecipientHandler.
 */

namespace Drupal\simplenews\RecipientHandler\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an recipient handler annotation object.
 *
 * Plugin Namespace: Plugin\RecipientHandler
 *
 * For a working example, see \Drupal\simplenews\Plugin\RecipientHandler\RecipientHandler
 *
 * @see \Drupal\simplenews\RecipientHandler\RecipientHandlerManager
 * @see \Drupal\simplenews\RecipientHandler\RecipientHandlerInterface
 * @see plugin_api
 *
 * @Annotation
 */
class RecipientHandler extends Plugin {

  /**
   * The archiver plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the recipient handler plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $title;

  /**
   * The description of the recipient handler plugin.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $description;

}
