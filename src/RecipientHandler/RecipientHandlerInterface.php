<?php
/**
 * @file
 * Contains \Drupal\simplenews\RecipientHandler\RecipientHandlerInterface.
 */

namespace Drupal\simplenews\RecipientHandler;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Interface for Simplenews Recipient Handler Classes.
 */
interface RecipientHandlerInterface extends \Countable, PluginInspectionInterface {

  /**
   * Build the query that gets the list of recipients.
   *
   * @return A SelectQuery object with the columns 'snid', 'mail' and
   * 'newsletter_id' for each recipient.
   */
  function buildRecipientQuery();

  /**
   * Build a query to count the number of recipients.
   *
   * @return A SelectQuery object to count the number of recipients.
   */
  function buildRecipientCountQuery();
}