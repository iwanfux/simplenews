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
class SubscriberFormBase extends ContentEntityForm {

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
   *   The newsletter IDs available to select from, as an associative array with values equNewsletter IDs for keys and values.
   */
  public function getNewsletters() {
    return $this->newsletters;
  }

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
      $form['subscriptions']['widget']['#title'] = t('Subscriptions for %mail', array('%mail' => $mail));
      $form['subscriptions']['widget']['#description'] = t('Check the newsletters you want to subscribe to. Uncheck the ones you want to unsubscribe from.');
    }
    else {
      $form['subscriptions']['widget']['#title'] = t('Manage your newsletter subscriptions');
      $form['subscriptions']['widget']['#description'] = t('Select the newsletter(s) to which you want to subscribe or unsubscribe.');
    }

    // E-mail field is not editable for authenticated users.
    if ($this->entity->getUserId() > 0) {
      $form['mail']['#disabled'] = 'disabled';
      // No need for an attentive red asterisk.
      $form['mail']['#required'] = FALSE;
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
  protected function copyFormValuesToEntity(EntityInterface $entity, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\simplenews\Entity\Subscriber $entity */
    // Subscribe to new selected newsletters.
    foreach ($this->getSubscriptionsValueIds($form_state) as $id) {
      if (!$entity->isSubscribed($id)) {
        $entity->subscribe($id, SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, 'website');
        simplenews_confirmation_send('subscribe', $this->entity, simplenews_newsletter_load($id));
      }
    }

    // Unsubscribe from deselected newsletters.
    $selected = $this->getSubscriptionsValueIds($form_state);
    foreach ($this->getNewsletters() as $id) {
      if ($entity->isSubscribed($id) && array_search($id, $selected) === FALSE) {
        $entity->unsubscribe($id, 'website');
        simplenews_confirmation_send('unsubscribe', $this->entity, simplenews_newsletter_load($id));
      }
    }

    // Copy rest.
    $form_state->unsetValue('subscriptions');
    parent::copyFormValuesToEntity($entity, $form, $form_state);
  }

  /**
   * Extracts the IDs of the newsletters selected in the subscriptions widget.
   *
   * @param FormStateInterface $form_state
   *   Form state object.
   *
   * @return array
   *   Selected newsletter IDs.
   */
  protected function getSubscriptionsValueIds(FormStateInterface $form_state) {
    return array_map(function($item) {
      return $item['target_id'];
    }, $form_state->getValue('subscriptions') ?: array());
  }
}
