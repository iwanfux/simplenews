<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberSettingsForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure simplenews newsletter settings.
 */
class SubscriberSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_admin_settings_subscriber';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('simplenews.settings');
    $form['simplenews_default_options'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Default subscriber options'),
      '#collapsible' => FALSE,
      '#description' => $this->t('These options will be the defaults for new subscribers, but can be overridden in the subscriber editing form.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /*$this->configFactory()->get('simplenews.settings')
      ->set('subscriber.xy', $form_state->getValue('xy'))
      ->save();*/

    parent::submitForm($form, $form_state);
  }

}