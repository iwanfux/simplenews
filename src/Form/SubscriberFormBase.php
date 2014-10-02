<?php
/**
 * @file
 * Contains \Drupal\simplenews\Form\SubscriberFormBase.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\simplenews\Entity\Newsletter;

/**
 * Entity form for Subscriber with common routines.
 */
abstract class SubscriberFormBase extends ContentEntityForm {

  /**
   * Submit button ID for creating new subscriptions.
   *
   * @var string
   */
  const SUBMIT_SUBSCRIBE = 'subscribe';

  /**
   * Submit button ID for removing existing subscriptions.
   *
   * @var string
   */
  const SUBMIT_UNSUBSCRIBE = 'unsubscribe';

  /**
   * Submit button ID for creating and removing subscriptions.
   *
   * @var string
   */
  const SUBMIT_UPDATE = 'update';

  /**
   * The newsletters available to select from.
   *
   * @var string[]
   */
  protected $newsletters = array();

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->setNewsletters(array_keys(Newsletter::loadMultiple()));
    parent::__construct($entity_manager);
  }

  /**
   * Set the newsletters available to select from.
   *
   * Unless called otherwise, all newsletters will be available.
   *
   * @param string[] $newsletters
   *   An array of Newsletter IDs.
   */
  public function setNewsletters(array $newsletters) {
    $this->newsletters = $newsletters;
  }

  /**
   * Returns the newsletters available to select from.
   *
   * @return string[]
   *   The newsletter IDs available to select from, as an associative array with
   *   values equNewsletter IDs for keys and values.
   */
  public function getNewsletters() {
    return $this->newsletters;
  }

  /**
   * Convenience method for the case of only one available newsletter.
   *
   * @see ::setNewsletters()
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
   * Returns a message to display to the user upon successful form submission.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   * @param string $op
   *   A string equal to either ::SUBMIT_UPDATE, ::SUBMIT_SUBSCRIBE or
   *   ::SUBMIT_UNSUBSCRIBE.
   * @param bool $confirm
   *   Whether a confirmation mail is sent or not.
   *
   * @return string
   *   A HTML message.
   */
  abstract protected function getSubmitMessage(FormStateInterface $form_state, $op, $confirm);

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    // Filter newsletter options as indicated with ::setNewsletters().
    $form['subscriptions']['widget']['#options']
      = array_intersect_key($form['subscriptions']['widget']['#options'], array_flip($this->getNewsletters()));

    // Modify UI texts.
    if ($mail = $this->entity->getMail()) {
      $form['mail']['#access'] = FALSE;
      $form['subscriptions']['widget']['#title'] = t('Subscriptions for %mail', array('%mail' => $mail));
      $form['subscriptions']['widget']['#description'] = t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.');
    }
    else {
      $form['subscriptions']['widget']['#title'] = t('Manage your newsletter subscriptions');
      $form['subscriptions']['widget']['#description'] = t('Select the newsletter(s) to which you want to subscribe or unsubscribe.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // Set up some flags from which submit button visibility can be determined.
    $options = $form['subscriptions']['#access'];
    $mail = (bool) $this->entity->getMail();
    $subscribed = !$options && $mail && $this->entity->isSubscribed($this->getOnlyNewsletter());

    // Add all buttons, but conditionally set #access.
    $actions = array(
      static::SUBMIT_SUBSCRIBE => array(
        // Show 'Subscribe' if not subscribed, or user is unknown.
        '#access' => (!$options && !$subscribed) || !$mail,
        '#type' => 'submit',
        '#value' => t('Subscribe'),
        '#validate' => array('::validate'),
        '#submit' => array('::submitForm', '::save', '::submitSubscribe'),
      ),
      static::SUBMIT_UNSUBSCRIBE => array(
        // Show 'Unsubscribe' if subscribed, or unknown and can select.
        '#access' => (!$options && $subscribed) || (!$mail && $options),
        '#type' => 'submit',
        '#value' => t('Unsubscribe'),
        '#validate' => array('::validate'),
        '#submit' => array('::submitForm', '::save', '::submitUnsubscribe'),
      ),
      static::SUBMIT_UPDATE => array(
        // Show 'Update' if user is known and can select newsletters.
        // @todo Incorrect conditions.
        '#access' => $options && $mail,
        '#type' => 'submit',
        '#value' => t('Update'),
        '#validate' => array('::validate'),
        '#submit' => array('::submitForm', '::save', '::submitUpdate'),
      ),
    );
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array $form, FormStateInterface $form_state) {
    $mail = $form_state->getValue(array('mail', 0, 'value'));
    // Users should login to manage their subscriptions.
    if (\Drupal::currentUser()->isAnonymous() && $user = user_load_by_mail($mail)) {
      $message = $user->isBlocked() ?
        $this->t('The email address %mail belongs to a blocked user.', array('%mail' => $mail)) :
        $this->t('There is an account registered for the e-mail address %mail. Please log in to manage your newsletter subscriptions.', array('%mail' => $mail));
      $form_state->setErrorByName('mail', $message);
    }

    $valid_email = valid_email_address($mail);
    if (!$valid_email && $form['mail']['#access']) {
      $form_state->setErrorByName('mail', t('The e-mail address you supplied is not valid.'));
    }

    // Unless the submit handler is 'update', if the newsletter checkboxes are
    // available, at least one must be checked.
    $update = in_array('::submitUpdate', $form_state->getSubmitHandlers());
    if (!$update && $form['subscriptions']['#access'] && !count($form_state->getValue('subscriptions'))) {
      $form_state->setErrorByName('subscriptions', t('You must select at least one newsletter.'));
    }

    parent::validate($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Merge with any existing subscriber.
    $mail = $form_state->getValue(array('mail', 0, 'value'));
    if ($this->entity->isNew() && $subscriber = simplenews_subscriber_load_by_mail($mail)) {
      $this->setEntity($subscriber);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit callback that subscribes to selected newsletters.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitSubscribe(array $form, FormStateInterface $form_state) {
    $mail = $form_state->getValue(array('mail', 0, 'value'));
    $account = user_load_by_mail($mail);
    simplenews_confirmation_combine(TRUE);
    foreach ($this->getSelectedNewsletters($form_state) as $newsletter_id) {
      $confirm = simplenews_require_double_opt_in($newsletter_id, $account);
      simplenews_subscribe($mail, $newsletter_id, $confirm, 'website');
    }
    $sent = simplenews_confirmation_send_combined();
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_SUBSCRIBE, $sent));
  }

  /**
   * Submit callback that unsubscribes from selected newsletters.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitUnsubscribe(array $form, FormStateInterface $form_state) {
    $mail = $form_state->getValue(array('mail', 0, 'value'));
    $account = user_load_by_mail($mail);
    simplenews_confirmation_combine(TRUE);
    foreach ($this->getSelectedNewsletters($form_state) as $newsletter_id) {
      $confirm = simplenews_require_double_opt_in($newsletter_id, $account);
      simplenews_unsubscribe($mail, $newsletter_id, $confirm, 'website');
    }
    $sent = simplenews_confirmation_send_combined();
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_UNSUBSCRIBE, $sent));
  }

  /**
   * Submit callback that (un)subscribes to newsletters based on selection.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitUpdate(array $form, FormStateInterface $form_state) {
    $mail = ($account = $form_state->get('user')) ? $account->getEmail() : $form_state->getValue(array('mail', 0, 'value'));
    // We first subscribe, then unsubscribe. This prevents deletion of
    // subscriptions when unsubscribed from the newsletter.
    $selected = $this->getSelectedNewsletters($form_state);
    foreach ($this->getNewsletters() as $newsletter_id) {
      if (in_array($newsletter_id, $selected)) {
        simplenews_subscribe($mail, $newsletter_id, FALSE, 'website');
      }
      else {
        simplenews_unsubscribe($mail, $newsletter_id, FALSE, 'website');
      }
    }
    $sent = simplenews_confirmation_send_combined();
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_UPDATE, $sent));
  }

  /**
   * {@inheritdoc}
   */
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    // Subscriptions are handled by submit callback instead.
    $subsciptions_value = $form_state->getValue('subscriptions');
    $form_state->unsetValue('subscriptions');
    parent::copyFormValuesToEntity($entity, $form, $form_state);
    $form_state->setValue('subscriptions', $subsciptions_value);
  }

  /**
   * Extracts the IDs of the newsletters selected in the subscriptions widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Selected newsletter IDs.
   */
  protected function getSelectedNewsletters(FormStateInterface $form_state) {
    return array_map(function($item) {
      return $item['target_id'];
    }, $form_state->getValue('subscriptions') ?: array());
  }
}
