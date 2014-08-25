<?php
/**
 * @file
 * Contains \Drupal\simplenews\Plugin\simplenews\RecipientHandler\RecipientHandlerBase.
 */

namespace Drupal\simplenews\Plugin\simplenews\RecipientHandler;

use Drupal\simplenews\RecipientHandler\RecipientHandlerInterface;


/**
 * Base class for all Recipient Handler classes.
 *
 * This handler sends a newsletter issue to all subscribers of a given
 * newsletter.
 *
 * @RecipientHandler(
 *   id = "simplenews_all",
 *   title = @Translation("All newsletter subscribers")
 * )
 */
class RecipientHandlerBase implements RecipientHandlerInterface  {

  /**
   * The newsletter entity.
   *
   * @var SimplenewsNewsletter
   */
  public $newsletter;

  /**
   * Name of the handler plugin to be used.
   *
   * @var String
   */
  public $handler = '';

  /**
   * Settings array.
   *
   * @var array
   */
  public $settings = array();

  /**
   * @param SimplenewsNewsletter $newsletter
   *   The simplenews newsletter.
   * @param String $handler
   *   The name of the handler plugin to use.
   * @param array $settings
   *   An array of settings used by the handler to build the list of recipients.
   */
  public function __construct($newsletter, $handler, $settings = array()) {
    $this->newsletter = $newsletter;
    $this->handler = $handler;
    $this->settings = $settings;
  }

  /**
   * Implements SimplenewsRecipientHandlerInterface::buildRecipientQuery()
   */
  public function buildRecipientQuery() {
    $select = db_select('simplenews_subscriber', 's');
    $select->innerJoin('simplenews_subscription', 't', 's.snid = t.snid');
    $select->addField('s', 'snid');
    $select->addField('s', 'mail');
    $select->addField('t', 'newsletter_id');
    $select->condition('t.newsletter_id', $this->newsletter->id());
    $select->condition('t.status', SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED);
    $select->condition('s.activated', SIMPLENEWS_SUBSCRIPTION_ACTIVE);

    return $select;
  }

  /**
   * Implements SimplenewsRecipientHandlerInterface::buildRecipientCountQuery()
   */
  public function buildRecipientCountQuery() {
    return $this->buildRecipientQuery()->countQuery();
  }

  /**
   * Implements Countable::count().
   */
  public function count() {
    return $this->buildRecipientCountQuery()->execute()->fetchField();
  }
}
