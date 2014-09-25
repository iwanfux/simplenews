<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriptionsBlockForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Configure simplenews subscriptions of the logged user.
 */
class SubscriptionsBlockForm extends SubscriberFormBase {

  protected $uniqueId;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if (empty($this->uniqueId)) {
      throw new \Exception('Unique ID must be set with setUniqueId.');
    }
    return 'simplenews_subscriptions_block_' . $this->uniqueId;
  }

  /**
   * {@inheritdoc}
   */
  public function setUniqueId($id) {
    $this->uniqueId = $id;
  }

  /**
   * Convenience method for the case of only one available newsletter.
   *
   * @see ::setNewsletter()
   *
   * @return string|null
   *   If there is exactly one newsletter available in this form, this method
   *   returns its ID. Otherwise it returns NULL.
   */
  protected function getOnlyNewsletter() {
    $newsletters = $this->getNewsletters();
    if (count($newsletters) == 1) {
      return array_shift($newsletters);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['subscriptions']['widget']['#options']
      = array_intersect_key($form['subscriptions']['widget']['#options'], array_flip($this->getNewsletters()));
    $mail = $this->entity->getMail();

    // Tweak the appearance of the subscriptions widget.
    if ($this->getOnlyNewsletter() != NULL) {
      $form['subscriptions']['#access'] = FALSE;
    }
    else {
      if ($mail) {
        $form['subscriptions']['widget']['#title'] = t('Subscriptions for %mail', array('%mail' => $mail));
        $form['subscriptions']['widget']['#description'] = t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.');
      }
      else {
        $form['subscriptions']['widget']['#title'] = t('Manage your newsletter subscriptions');
        $form['subscriptions']['widget']['#description'] = t('Select the newsletter(s) to which you want to subscribe or unsubscribe.');
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Set up some flags from which submit button visibility can be determined.
    $multiple = count($this->getNewsletters()) > 1;
    $mail = $this->entity->getMail();
    $subscribed = !$multiple && $mail && $this->entity->isSubscribed($this->getOnlyNewsletter());

    // Add all buttons, but conditionally set #access.
    $action_defaults = array(
      '#type' => 'submit',
      '#submit' => array('::submitForm', '::save'),
    );
    $actions = array(
      'subscribe' => array(
        // Show 'Subscribe' if not subscribed, or user is unknown.
        '#access' => (!$multiple && !$subscribed) || !$mail,
        '#value' => t('Subscribe'),
        // @todo: add clean submit handler
      ) + $action_defaults,
      'unsubscribe' => array(
        // Show 'Unsubscribe' if subscribed, or unknown and can select.
        '#access' => (!$multiple && $subscribed) || (!$mail && $multiple),
        '#value' => t('Unsubscribe'),
        // @todo: add clean submit handler
      ) + $action_defaults,
      'update' => array(
        // Show 'Update' if user is known and can select newsletters.
        '#access' => $multiple && $mail,
        '#value' => t('Update'),
        // @todo: add clean submit handler
      ) + $action_defaults,
    );
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $valid_email = valid_email_address($form_state->getValue('mail')[0]['value']);
    if (!$valid_email) {
      $form_state->setErrorByName('mail', t('The e-mail address you supplied is not valid.'));
    }

    // Unless we're in update mode, or only one newsletter is available, at
    // least one checkbox must be checked.
    if (!count($form_state->getValue('subscriptions')) && $this->getOnlyNewsletter() == NULL && $form_state->getValue('op') != t('Update')) {
      $form_state->setErrorByName('subscriptions', t('You must select at least one newsletter.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Pretend that the '#type' => 'value' field is a widget.
    if (count($this->getNewsletters()) == 1) {
      if ($this->entity->isSubscribed($this->getOnlyNewsletter())) {
        $form_state->unsetValue('subscriptions');
      }
      else {
        $form_state->setValue('subscriptions', array(array('target_id' => $this->getOnlyNewsletter())));
      }
    }

    // Group confirmation mails as necessary and configured.
    simplenews_confirmation_combine(TRUE);
    switch ($form_state->getValue('op')) {
      case t('Update'):
        drupal_set_message(t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state->getValue('mail')[0]['value'])));
        break;
      case t('Subscribe'):
        if (simplenews_confirmation_send_combined()) {
          drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.'));
        }
        else {
          drupal_set_message(t('You have been subscribed.'));
        }
        break;
      case t('Unsubscribe'):
        if (simplenews_confirmation_send_combined()) {
          drupal_set_message(t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.'));
        }
        else {
          drupal_set_message(t('You have been unsubscribed.'));
        }
        break;
    }
    parent::submitForm($form, $form_state);
  }
}
