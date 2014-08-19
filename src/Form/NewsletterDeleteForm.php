<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\NewsletterDeleteForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Builds the form to delete a contact category.
 */
class NewsletterDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('simplenews.newsletter_list');
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
  public function submit(array $form, FormStateInterface $form_state) {
    $this->entity->delete();
    drupal_set_message(t('Newsletter %label has been deleted.', array('%label' => $this->entity->label())));
    watchdog('simplenews', 'Newsletter %label has been deleted.', array('%label' => $this->entity->label()), WATCHDOG_NOTICE);
    $form_state->setRedirect('simplenews.newsletter_list');
  }

}
