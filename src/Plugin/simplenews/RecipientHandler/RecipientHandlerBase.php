<?php
/**
 * @file
 * Contains \Drupal\simplenews\Plugin\simplenews\RecipientHandler\RecipientHandlerBase.
 */

namespace Drupal\simplenews\Plugin\simplenews\RecipientHandler;

use Drupal\Core\Plugin\PluginBase;
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
class RecipientHandlerBase extends PluginBase implements RecipientHandlerInterface  {

  /**
   * The newsletter entity.
   *
   * @var SimplenewsNewsletter
   */
  public $newsletter;

  /**
   * @param SimplenewsNewsletter $newsletter
   *   The simplenews newsletter.
   * @param String $handler
   *   The name of the handler plugin to use.
   * @param array $settings
   *   An array of settings used by the handler to build the list of recipients.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->newsletter = $configuration['newsletter'];
  }

  /**
   * Implements SimplenewsRecipientHandlerInterface::buildRecipientQuery()
   */
  public function buildRecipientQuery() {
    $select = db_select('simplenews_subscriber', 's');
    $select->innerJoin('simplenews_subscriber__subscriptions', 't', 's.id = t.entity_id');
    $select->addField('s', 'id', 'snid');
    $select->addField('s', 'mail');
    $select->addField('t', 'subscriptions_target_id', 'newsletter_id');
    $select->condition('t.subscriptions_target_id', $this->newsletter->id());
    $select->condition('t.subscriptions_status', SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED);
    $select->condition('s.status', SIMPLENEWS_SUBSCRIPTION_ACTIVE);

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
