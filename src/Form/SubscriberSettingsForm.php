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
    $form['account'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('User account'),
      '#collapsible' => FALSE,
    );
    $form['account']['simplenews_sync_account'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Synchronize with account'),
      '#default_value' => $config->get('subscriber.sync_account'),
      '#description' => $this->t('<p>When checked subscriptions will be synchronized with site accounts. Fields that exist with identical name and type on subscriber and accounts will be synchronized. When accounts are deleted, subscriptions with the same email address will be removed. When site accounts are blocked/unblocked, subscriptions will be deactivated/activated. When not checked subscriptions will be unchanged when associated accounts are deleted or blocked.</p><p><strong>Note:</strong> This option is intended to be set once only. If disabled and later enabled again, beware of data inconsistency.</p>'),
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
    $this->configFactory()->get('simplenews.settings')
      ->set('subscriber.sync_account', $form_state->getValue('simplenews_sync_account'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
