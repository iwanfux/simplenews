<?php

/**
 * @file
 * Definition of Drupal\simplenews\Entity\Category.
 */

namespace Drupal\simplenews\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\simplenews\NewsletterInterface;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the simplenews newsletter entity.
 *
 * @ConfigEntityType(
 *   id = "simplenews_newsletter",
 *   label = @Translation("Simplenews newsletter"),
 *   controllers = {
 *     "list_builder" = "Drupal\simplenews\NewsletterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\simplenews\Form\NewsletterForm",
 *       "edit" = "Drupal\simplenews\Form\NewsletterForm",
 *       "delete" = "Drupal\simplenews\Form\NewsletterDeleteForm"
 *     }
 *   },
 *   config_prefix = "newsletter",
 *   admin_permission = "administer simplenews forms",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name"
 *   },
 *   links = {
 *     "delete-form" = "simplenews.newsletter_delete",
 *     "edit-form" = "simplenews.newsletter_edit"
 *   }
 * )
 */
class Newsletter extends ConfigEntityBase implements NewsletterInterface {

  /**
   * The primary key.
   *
   * @var string
   */
  public $id;

  /**
   * Name of the newsletter.
   *
   * @var string
   */
  public $name = '';

  /**
   * Description of the newsletter.
   *
   * @var string
   */
  public $description = '';

  /**
   * HTML or plaintext newsletter indicator.
   *
   * @var string
   */
  public $format;

  /**
   * Priority indicator
   *
   * @var int
   */
  public $priority;

  /**
   * TRUE if a read receipt should be requested.
   *
   * @var boolean
   */
  public $receipt;

  /**
   * Name of the email author.
   *
   * @var string
   */
  public $from_name;

  /**
   * Subject of newsletter email. May contain tokens.
   *
   * @var string
   */
  public $subject = '[[simplenews-newsletter:name]] [node:title]';

  /**
   * Email author address.
   *
   * @var string
   */
  public $from_address;

    /**
   * Indicates if hyperlinks should be kept inline or extracted.
   *
   * @var boolean
   */
  public $hyperlinks = TRUE;

  /**
   * Indicates how to integrate with the register form.
   *
   * @var string
   */
  public $new_account = 'none';

  /**
   * Defines the Opt-In/out options.
   *
   * @var string
   */
  public $opt_inout = 'double';

  /**
   * Weight of this newsletter (used for sorting).
   *
   * @var int
   */
  public $weight = 0;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    $config = \Drupal::config('simplenews.settings');
    $values += array(
      'format' => $config->get('newsletter_format'),
      'priority' => $config->get('newsletter_priority'),
      'receipt' => $config->get('newsletter_receipt'),
      'from_name' => $config->get('newsletter_from_name'),
      'from_address' => $config->get('newsletter_from_address'),
    );
    parent::preCreate($storage, $values);
  }
}
