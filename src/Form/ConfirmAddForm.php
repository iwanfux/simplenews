<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\ConfirmAddForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\simplenews\NewsletterInterface;

/**
 * Implements a add confirmation form for simplenews subscriptions.
 */
class ConfirmAddForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Confirm subscription');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Subscribe');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('You can always unsubscribe later.');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_confirm_add';
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {
    return new Url('simplenews.newsletter_subscriptions');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $mail = '', NewsletterInterface $newsletter = NULL) {
    $form = parent::buildForm($form, $form_state);
    $form['question'] = array(
      '#markup' => '<p>' . t('Are you sure you want to add %user to the %newsletter mailing list?', array('%user' => simplenews_mask_mail($mail), '%newsletter' => $newsletter->name)) . "<p>\n",
    );
    $form['mail'] = array(
      '#type' => 'value',
      '#value' => $mail,
    );
    $form['newsletter'] = array(
      '#type' => 'value',
      '#value' => $newsletter,
    );
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
    simplenews_subscribe($form_state['values']['mail'], $form_state['values']['newsletter']->id(), FALSE, 'website');

    $config = \Drupal::config('simplenews.settings');
    if (!$path = $config->get('subscription.confirm_subscribe_page')) {
      $site_config = \Drupal::config('system.site');
      $path = $site_config->get('page.front');
      drupal_set_message(t('%user was added to the %newsletter mailing list.', array('%user' => $form_state['values']['mail'], '%newsletter' => $form_state['values']['newsletter']->name)));
    }

    $form_state['redirect_route'] = Url::createFromPath($path)->getRouteName();
  }
}