<?php

/**
 * @file
 * Definition of Drupal\simplenews\SubscriberForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Component\Utility\String;

/**
 * Form controller for the subscriber edit forms.
 */
class SubscriberForm extends ContentEntityForm {
  /**
   * Overrides Drupal\Core\Entity\EntityForm::form().
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);
    /* @var \Drupal\simplenews\SubscriberInterface */
    $subscriber = $this->entity;
    $options = array();
    $default_value = array();

    $form['activated'] = array(
      '#title' => t('Status'),
      '#type' => 'fieldset',
      '#description' => t('Active or inactive account.'),
      '#weight' => 15,
    );
    $form['activated']['status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Active'),
      '#default_value' => $subscriber->getStatus(),
    );

    $language_manager = \Drupal::languageManager();
    if ($language_manager->isMultilingual()) {
      $languages = $language_manager->getLanguages();
      foreach ($languages as $langcode => $language) {
        $language_options[$langcode] = $language->getName();
      }
      $form['language'] = array(
        '#type' => 'fieldset',
        '#title' => t('Preferred language'),
        '#description' => t('The e-mails will be localized in language chosen. Real users have their preference in account settings.'),
        '#disabled' => FALSE,
      );
      if ($subscriber->getUserId()) {
        // Fallback if user has not defined a language.
        $form['language']['langcode'] = array(
          '#type' => 'item',
          '#title' => t('User language'),
          '#markup' => $subscriber->language()->getName(),
        );
      }
      else {
        $form['language']['langcode'] = array(
          '#type' => 'select',
          '#default_value' => $subscriber->language()->id,
          '#options' => $language_options,
          '#required' => TRUE,
        );
      }
    }

    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function save(array $form, array &$form_state) {
    $subscriber = $this->entity;
    $status = $subscriber->save();

    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Subscriber %label has been updated.', array('%label' => $subscriber->label())));
    }
    else {
      drupal_set_message(t('Subscriber %label has been added.', array('%label' => $subscriber->label())));
    }

    $form_state['redirect_route']['route_name'] = 'simplenews.subscriber_edit';
    $form_state['redirect_route']['route_parameters'] = array('simplenews_subscriber' =>$subscriber->id());
  }
}