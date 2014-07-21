<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\RequestHashForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;

/**
 * Provides an generic base class for a confirmation form.
 */
class RequestHashForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('This link has expired.');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Request new confirmation mail');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_request_hash';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return new Url('simplenews.newsletter_subscriptions');
  }

  /**
   * Request new hash form.
   *
   * @param $key
   *   The mail key to be sent.
   * @param $context
   *   Necessary context to send the mail. Must at least include the simplenews
   *   subscriber.
   */
  public function buildForm(array $form, array &$form_state, $key = '', $context = array()) {
    $form = parent::buildForm($form, $form_state);
    $form_state['key'] = $key;
    $form_state['context'] = $context;

    return $form;
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
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    $params['from'] = _simplenews_set_from();
    $params['context'] = $form_state['context'];
    $subscriber = $params['context']['simplenews_subscriber'];
    drupal_mail('simplenews', $form_state['key'], $subscriber->getMail(), $subscriber->getLangcode(), $params, $params['from']['address']);
    drupal_set_message(t('The confirmation mail has been sent.'));
    $form_state['redirect_route'] = new Url('<front>');
  }

}