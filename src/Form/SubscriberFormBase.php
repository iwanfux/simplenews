<?php
/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberFormBase.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Entity form for Subscriber with common routines.
 */
class SubscriberFormBase extends ContentEntityForm {

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
    $subscriptions_to_remove = array_diff($entity->getSubscribedNewsletterIds(), $this->getSubscriptionsValueIds($form_state));
    foreach ($subscriptions_to_remove as $id) {
      $entity->unsubscribe($id, 'website');
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
    }, $form_state->getValue('subscriptions'));
  }
}
