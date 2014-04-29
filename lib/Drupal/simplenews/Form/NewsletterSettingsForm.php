<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\NewsletterSettingsForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Configure simplenews newsletter settings.
 */
class NewsletterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    $site_config = $this->configFactory->get('system.site');
    $address_default = $site_config->get('mail');

    $config = $this->configFactory->get('simplenews.settings');
    $form = array();
    $form['simplenews_default_options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Default newsletter options'),
      '#collapsible' => FALSE,
      '#description' => t('These options will be the defaults for new newsletters, but can be overridden in the newsletter editing form.'),
    );
    $links = array('!mime_mail_url' => 'http://drupal.org/project/mimemail', '!html_url' => 'http://drupal.org/project/htmlmail');
    $description = t('Default newsletter format. Install <a href="!mime_mail_url">Mime Mail</a> module or <a href="!html_url">HTML Mail</a> module to send newsletters in HTML format.', $links);
    $form['simplenews_default_options']['simplenews_format'] = array(
      '#type' => 'select',
      '#title' => t('Format'),
      '#options' => simplenews_format_options(),
      '#description' => $description,
      '#default_value' => $config->get('format'),
    );
    // @todo Do we need these master defaults for 'priority' and 'receipt'?
    $form['simplenews_default_options']['simplenews_priority'] = array(
      '#type' => 'select',
      '#title' => t('Priority'),
      '#options' => simplenews_get_priority(),
      '#description' => t('Note that email priority is ignored by a lot of email programs.'),
      '#default_value' => $config->get('priority'),
    );
    $form['simplenews_default_options']['simplenews_receipt'] = array(
      '#type' => 'checkbox',
      '#title' => t('Request receipt'),
      '#default_value' => $config->get('receipt', 0),
      '#description' => t('Request a Read Receipt from your newsletters. A lot of email programs ignore these so it is not a definitive indication of how many people have read your newsletter.'),
    );
    $form['simplenews_default_options']['simplenews_send'] = array(
      '#type' => 'radios',
      '#title' => t('Default send action'),
      '#options' => array(
        SIMPLENEWS_COMMAND_SEND_TEST => t('Send one test newsletter to the test address'),
        SIMPLENEWS_COMMAND_SEND_NOW => t('Send newsletter'),
      ),
      '#default_value' => $config->get('send'),
    );
    $form['simplenews_test_address'] = array(
      '#type' => 'fieldset',
      '#title' => t('Test addresses'),
      '#collapsible' => FALSE,
      '#description' => t('Supply a comma-separated list of email addresses to be used as test addresses. The override function allows to override these addresses in the newsletter editing form.'),
    );
    $form['simplenews_test_address']['simplenews_test_address'] = array(
      '#type' => 'textfield',
      '#title' => t('Email address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $config->get('test_address', $address_default),
    );
    $form['simplenews_test_address']['simplenews_test_address_override'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow test address override'),
      '#default_value' => $config->get('test_address_override', 0),
    );
    $form['simplenews_sender_info'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender information'),
      '#collapsible' => FALSE,
      '#description' => t('Default sender address that will only be used for confirmation emails. You can specify sender information for each newsletter separately on the newsletter\'s settings page.'),
    );
    $form['simplenews_sender_info']['simplenews_from_name'] = array(
      '#type' => 'textfield',
      '#title' => t('From name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $config->get('from_name', $site_config->get('name', 'Drupal')),
    );
    $form['simplenews_sender_info']['simplenews_from_address'] = array(
      '#type' => 'textfield',
      '#title' => t('From email address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $config->get('from_address', $address_default),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $this->configFactory->get('simplenews.settings')
      ->set('text', $form_state['values']['simplenews_text'])
      ->save();

    parent::submitForm($form, $form_state);
  }

}