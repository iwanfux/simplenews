<?php
/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberFormBase.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Entity\ContentEntityForm;
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

    $options = array();
    $default_value = $this->entity->getSubscribedNewsletterIds();

    // Get newsletters for subscription form checkboxes.
    // Newsletters with opt-in/out method 'hidden' will not be listed.
    foreach (simplenews_newsletter_get_visible() as $newsletter) {
      $options[$newsletter->id()] = String::checkPlain($newsletter->name);
    }

    $form['subscriptions'] = array(
      '#type' => 'fieldset',
      '#title' => t('Current newsletter subscriptions.'),
      '#description' => t('Select your newsletter subscriptions.'),
    );
    $form['subscriptions']['newsletters'] = array(
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $default_value,
    );

    if ($this->entity->getUserId() > 0) {
      $form['mail']['#disabled'] = 'disabled';
    }

    return $form;
  }

}
