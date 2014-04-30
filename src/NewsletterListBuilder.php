<?php

/**
 * @file
 * Contains \Drupal\simplenews\NewsletterListBuilder.
 */

namespace Drupal\simplenews;

use Drupal\Component\Utility\String;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of contact category entities.
 *
 * @see \Drupal\contact\Entity\Category
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
