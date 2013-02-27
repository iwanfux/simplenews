<?php
/**
 * @file
 * Provide helpful functions and Interfaces for recipient handler plugins.
 */

/**
 * Interface for Simplenews Recipient Handler Classes.
 */
interface SimplenewsRecipientHandlerInterface extends Countable {

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

/**
 * Base class for all Simplenews Recipient Handler classes.
 *
 * This handler sends a newsletter issue to all subscribers of a given
 * newsletter.
 */
class SimplenewsRecipientHandlerBase implements SimplenewsRecipientHandlerInterface  {

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
    $select->condition('t.newsletter_id', $this->newsletter->newsletter_id);
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
