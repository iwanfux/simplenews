<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\MailSettingsForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

#    'page arguments' => array('simplenews_admin_settings_mail'),

/**
 * Configure simplenews newsletter settings.
 */
class MailSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_admin_settings_mail';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory()->get('simplenews.settings');
    $form['simplenews_mail_backend']['simplenews_use_cron'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Use cron to send newsletters'),
      '#default_value' => $config->get('mail.use_cron'),
      '#description' => $this->t('When checked cron will be used to send newsletters (recommended). Test newsletters and confirmation emails will be sent immediately. Leave unchecked for testing purposes.'),
    );

    $sources = simplenews_get_source_caches();
    $sources_labels = array();
    $sources_descriptions = '';
    foreach ($sources as $name => $source) {
      $sources_labels[$name] = $source['label'];
      $sources_descriptions .= $this->t('<strong>@label</strong>: @description <br />', array('@label' => $source['label'], '@description' => $source['description']));
    }

    $form['simplenews_mail_backend']['simplenews_source_cache'] = array(
      '#type' => 'select',
      '#title' => $this->t('Cache'),
      '#description' => $this->t('Chosing a different cache implementation allows for a different behavior during sending mails.') . '<br /><br />' . $sources_descriptions,
      '#options' => $sources_labels,
      '#default_value' => $config->get('mail.source_cache'),
    );

    $throttle_val = array(1, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000);
    $throttle = array_combine($throttle_val, $throttle_val);
    $throttle[SIMPLENEWS_UNLIMITED] = $this->t('Unlimited');
    if (function_exists('getrusage')) {
      $description_extra = '<br />' . $this->t('Cron execution must not exceed the PHP maximum execution time of %max seconds. You find the time spend to send emails in the <a href="/admin/reports/dblog">Recent log entries</a>.', array('%max' => ini_get('max_execution_time')));
    }
    else {
      $description_extra = '<br />' . $this->t('Cron execution must not exceed the PHP maximum execution time of %max seconds.', array('%max' => ini_get('max_execution_time')));
    }
    $form['simplenews_mail_backend']['simplenews_throttle'] = array(
      '#type' => 'select',
      '#title' => $this->t('Cron throttle'),
      '#options' => $throttle,
      '#default_value' => $config->get('mail.throttle'),
      '#description' => $this->t('Sets the numbers of newsletters sent per cron run. Failure to send will also be counted.') . $description_extra,
    );
    $form['simplenews_mail_backend']['simplenews_spool_expire'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mail spool expiration'),
      '#options' => array(
        0 => $this->t('Immediate'),
        1 => \Drupal::translation()->formatPlural(1, '1 day', '@count days'),
        7 => \Drupal::translation()->formatPlural(1, '1 week', '@count weeks'),
        14 => \Drupal::translation()->formatPlural(2, '1 week', '@count weeks'),
      ),
      '#default_value' => $config->get('mail.spool_expire'),
      '#description' => $this->t('Newsletter mails are spooled. How long must messages be retained in the spool after successful sending. Keeping the message in the spool allows mail statistics (which is not yet implemented). If cron is not used, immediate expiration is advised.'),
    );
    $form['simplenews_mail_backend']['simplenews_debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Log emails'),
      '#default_value' => $config->get('mail.debug'),
      '#description' => $this->t('When checked all outgoing simplenews emails are logged in the system log. A logged email does not guarantee that it is send or will be delivered. It only indicates that a message is sent to the PHP mail() function. No status information is available of delivery by the PHP mail() function.'),
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
      ->set('mail.use_cron', $form_state['values']['simplenews_use_cron'])
      ->set('mail.source_cache', $form_state['values']['simplenews_source_cache'])
      ->set('mail.throttle', $form_state['values']['simplenews_throttle'])
      ->set('mail.spool_expire', $form_state['values']['simplenews_spool_expire'])
      ->set('mail.debug', $form_state['values']['simplenews_debug'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}