<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberDeleteForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;

/**
 * Builds the form to delete a contact category.
 */
class SubscriberDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return array(
      'route_name' => 'simplenews.subscriber_add',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, array &$form_state) {
    $this->entity->delete();
    drupal_set_message(t('Subscriber %label has been deleted.', array('%label' => $this->entity->label())));
    watchdog('simplenews', 'Subscriber %label has been deleted.', array('%label' => $this->entity->label()), WATCHDOG_NOTICE);
    $form_state['redirect_route']['route_name'] = 'simplenews.subscriber_add';
  }

}
