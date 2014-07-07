<?php

/**
 * @file
 * Contains \Drupal\simplenews\SubscriberListBuilder.
 */

namespace Drupal\simplenews;

use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of simplenews subscriber entities.
 *
 * @see \Drupal\simplenews\Entity\Subscriber
 */
class SubscriberListBuilder extends EntityListBuilder {

  /**
   * The entity query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Constructs a new SubscriberListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, QueryFactory $query_factory) {
    parent::__construct($entity_type, $storage);
    $this->queryFactory = $query_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entity_query = $this->queryFactory->get('simplenews_subscriber');
    $entity_query->condition('subscriptions.status', 1);
    $entity_query->pager(50);
    $sids = $entity_query->execute();
    return $this->storage->loadMultiple($sids);
  }

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
