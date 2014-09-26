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
use Drupal\user\Entity\User;

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
    $multiple = count($this->getNewsletters()) > 1;
    $mail = $this->entity->getMail();
    $subscribed = !$multiple && $mail && $this->entity->isSubscribed($this->getOnlyNewsletter());

    // Add all buttons, but conditionally set #access.
    $actions = array(
      static::SUBMIT_SUBSCRIBE => array(
        // Show 'Subscribe' if not subscribed, or user is unknown.
        '#access' => (!$multiple && !$subscribed) || !$mail,
        '#type' => 'submit',
        '#value' => t('Subscribe'),
        '#submit' => array('::submitForm', '::save', '::submitSubscribe'),
      ),
      static::SUBMIT_UNSUBSCRIBE => array(
        // Show 'Unsubscribe' if subscribed, or unknown and can select.
        '#access' => (!$multiple && $subscribed) || (!$mail && $multiple),
        '#type' => 'submit',
        '#value' => t('Unsubscribe'),
        '#submit' => array('::submitForm', '::save', '::submitUnsubscribe'),
      ),
      static::SUBMIT_UPDATE => array(
        // Show 'Update' if user is known and can select newsletters.
        '#access' => $multiple && $mail,
        '#type' => 'submit',
        '#value' => t('Update'),
        '#submit' => array('::submitForm', '::save', '::submitUpdate'),
      ),
    );
    return $actions;
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
    $user = User::load($this->entity->getUserId());
    $to_subscribe = array();
    simplenews_confirmation_combine(TRUE);
    foreach ($this->getSelectedNewsletters($form_state) as $id) {
      if (!$this->entity->isSubscribed($id)) {
        $to_subscribe[] = $id;
        if (simplenews_require_double_opt_in($id, $user)) {
          simplenews_confirmation_send('subscribe', $this->entity, simplenews_newsletter_load($id));
        }
      }
    }
    $confirm = simplenews_confirmation_send_combined($this->entity);
    foreach ($to_subscribe as $id) {
      $this->entity->subscribe($id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, 'website');
    }
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_SUBSCRIBE, $confirm));
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
    $user = User::load($this->entity->getUserId());
    $to_unsubscribe = array();
    simplenews_confirmation_combine(TRUE);
    foreach ($this->getSelectedNewsletters($form_state) as $id) {
      if ($this->entity->isSubscribed($id)) {
        $to_unsubscribe[] = $id;
        if (simplenews_require_double_opt_in($id, $user)) {
          simplenews_confirmation_send('unsubscribe', $this->entity, simplenews_newsletter_load($id));
        }
      }
    }
    $confirm = simplenews_confirmation_send_combined($this->entity);
    foreach ($to_unsubscribe as $id) {
      $this->entity->unsubscribe($id, 'website');
    }
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_UNSUBSCRIBE, $confirm));
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
    $selected = $this->getSelectedNewsletters($form_state);
    $user = User::load($this->entity->getUserId());
    $to_subscribe = array();
    $to_unsubscribe = array();
    simplenews_confirmation_combine(TRUE);
    foreach ($this->getNewsletters() as $id) {
      // Subscribe if selected and not already subscribed.
      if (!$this->entity->isSubscribed($id) && array_search($id, $selected) !== FALSE) {
        $to_subscribe[] = $id;
        $this->entity->subscribe($id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, 'website');
        if (simplenews_require_double_opt_in($id, $user)) {
          simplenews_confirmation_send('subscribe', $this->entity, simplenews_newsletter_load($id));
        }
      }
      // Unsubscribe if subscribed and deselected.
      if ($this->entity->isSubscribed($id) && array_search($id, $selected) === FALSE) {
        $to_unsubscribe[] = $id;
        if (simplenews_require_double_opt_in($id, $user)) {
          simplenews_confirmation_send('unsubscribe', $this->entity, simplenews_newsletter_load($id));
        }
      }
    }
    $confirm = simplenews_confirmation_send_combined($this->entity);
    foreach ($to_subscribe as $id) {
      $this->entity->subscribe($id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, 'website');
    }
    foreach ($to_unsubscribe as $id) {
      $this->entity->unsubscribe($id, 'website');
    }
    drupal_set_message($this->getSubmitMessage($form_state, static::SUBMIT_UPDATE, $confirm));
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
