<?php

/**
 * @file
 * Definition of Drupal\contact\Entity\Category.
 */

namespace Drupal\simplenews\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\simplenews\NewsletterInterface;

/**
 * Defines the simplenews newsletter entity.
 *
 * @ConfigEntityType(
 *   id = "simplenews_newsletter",
 *   label = @Translation("Simplenews newsletter"),
 *   controllers = {
 *     "list_builder" = "Drupal\simplenews\NewsletterListBuilder",
 *     "form" = {
 *       "add" = "Drupal\simplenews\NewsletterForm",
 *       "edit" = "Drupal\simplenews\NewsletterForm",
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
   * The newsletter ID.
   *
   * @var string
   */
  public $id;

  /**
   * The newsletter name.
   *
   * @var string
   */
  public $name;

  /**
   * A description of the newsletter.
   *
   * @var string
   */
  public $description;

  /**
   * Format of the newsletter email.
   * plain
   * html
   *
   * @var string
   */
  public $format;

  /**
   * Email priority according to RFC 2156 and RFC 5231.
   * 0 = none
   * 1 = highest
   * 2 = high
   * 3 = normal
   * 4 = low
   * 5 = lowest
   *
   * @var int
   */
  public $priority = 0;

  /**
   * Boolean indicating request for email receipt confirmation according to RFC
   * 2822.
   *
   * @var int
   */
  public $receipt = 0;

  /**
   * Sender name for newsletter emails.
   *
   * @var string
   */
  public $from_name = '';

  /**
   * Subject of newsletter email. May contain tokens.
   *
   * @var string
   */
  public $subject = '';

  /**
   * Sender address for newsletter emails.
   *
   * @var string
   */
  public $from_address = '';

    /**
   * Flag indicating type of hyperlink conversion.
   * 0 = hyperlinks are placed at email bottom
   * 1 = hyperlinks are in-line
   *
   * @var int
   */
  public $hyperlinks = 0;

  /**
   * How to treat subscription at account creation.
   * none = None
   * on = Default on
   * off = Default off
   * silent = Invisible subscription
   *
   * @var string
   */
  public $new_account = '';

  /**
   * How to treat subscription confirmation.
   * hidden = Newsletter is hidden from the user
   * single = Single opt-in
   * double = Double opt-in
   *
   * @var string
   */
  public $opt_inout = '';

  /**
   * For this newsletter a subscription block is available.
   *
   * @var int
   */
  public $block = 0;

  /**
   * Weight of this newsletter (used for sorting).
   *
   * @var int
   */
  public $weight = 0;
}
