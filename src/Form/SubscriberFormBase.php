<?php
/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberFormBase.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Newsletter;

/**
 * Entity form for Subscriber with common routines.
 */
class SubscriberFormBase extends ContentEntityForm {

  /**
   * The newsletters available to select from.
   *
   * @var string[]
   */
  protected $newsletters = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->setNewsletters(array_keys(Newsletter::loadMultiple()));
    parent::__construct($entity_manager);
  }

  /**
   * Set the newsletters available to select from.
   *
   * Unless called otherwise, all newsletters will be available.
   *
   * @param string[] $newsletters
   *   An array of Newsletter IDs.
   */
  public function setNewsletters(array $newsletters) {
    $this->newsletters = $newsletters;
  }

  /**
   * Returns the newsletters available to select from.
   *
   * @return string[]
   *   The newsletter IDs available to select from, as an associative array with values equNewsletter IDs for keys and values.
   */
  public function getNewsletters() {
    return $this->newsletters;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if ($this->entity->getUserId() > 0) {
      $form['mail']['#disabled'] = 'disabled';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\simplenews\Entity\Subscriber $entity */
    // Subscribe to new selected newsletters.
    foreach ($this->getSubscriptionsValueIds($form_state) as $id) {
      if (!$entity->isSubscribed($id)) {
        $entity->subscribe($id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, 'website');
      }
    }

    // Unsubscribe from deselected newsletters.
    $selected = $this->getSubscriptionsValueIds($form_state);
    foreach ($this->getNewsletters() as $id) {
      if ($entity->isSubscribed($id) && array_search($id, $selected) === FALSE) {
        $entity->unsubscribe($id, 'website');
      }
    }

    // Copy rest.
    $form_state->unsetValue('subscriptions');
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * Extracts the IDs of the newsletters selected in the subscriptions widget.
   *
   * @param FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Selected newsletter IDs.
   */
  protected function getSubscriptionsValueIds(FormStateInterface $form_state) {
    return array_map(function($item) {
      return $item['target_id'];
    }, $form_state->getValue('subscriptions') ?: array());
  }
}
