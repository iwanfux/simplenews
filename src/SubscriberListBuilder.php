<?php

/**
 * @file
 * Contains \Drupal\simplenews\SubscriberListBuilder.
 */

namespace Drupal\simplenews;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of simplenews subscriber entities.
 *
 * @see \Drupal\simplenews\Entity\Subscriber
 */
class SubscriberListBuilder extends EntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = t('Subscriber name');
    $header['user'] = t('Username');
    $header['newsletters'] = t('Subscribed newsletters');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $this->getLabel($entity);
    $row['user'] = $entity->get('uid')->target_id ? $entity->get('uid')->entity->label() : '';

    // Get all subscribed newsletters.
    $newsletters = array();
    $subscribed_newsletters = simplenews_newsletter_load_multiple($entity->getSubscribedNewsletterIds());
    foreach ($subscribed_newsletters as $newsletter) {
      $newsletters[] = $newsletter->label();
    }
    $row['newsletters'] = implode(', ', $newsletters);
    return $row + parent::buildRow($entity);
  }
}
