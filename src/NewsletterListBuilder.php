<?php

/**
 * @file
 * Contains \Drupal\simplenews\NewsletterListBuilder.
 */

namespace Drupal\simplenews;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of simplenews newsletter entities.
 *
 * @see \Drupal\simplenews\Entity\Newsletter
 */
class NewsletterListBuilder extends ConfigEntityListBuilder {
  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = t('Newsletter name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['name'] = $this->getLabel($entity);
    return $row + parent::buildRow($entity);
  }
}
