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
   * A message to use as description for the block.
   *
   * @var string
   */
  public $message;

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
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Hide subscription widget if only one newsletter available.
    if (count($this->getNewsletters()) == 1) {
      $this->getSubscriptionWidget($form_state)->setHidden();
    }

    $form = parent::form($form, $form_state);

    $form['message'] = array(
      '#type' => 'item',
      '#markup' => $this->message,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // If only one newsletter, show Subscribe/Unsubscribe instead of Update.
    $actions = parent::actions($form, $form_state);
    if ($this->getOnlyNewsletterId() != NULL) {
      $actions[static::SUBMIT_UPDATE]['#access'] = FALSE;
      $actions[static::SUBMIT_SUBSCRIBE]['#access'] = !$this->entity->isSubscribed($this->getOnlyNewsletterId());
      $actions[static::SUBMIT_UNSUBSCRIBE]['#access'] = $this->entity->isSubscribed($this->getOnlyNewsletterId());
    }
    return parent::actions($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSubmitMessage(FormStateInterface $form_state, $op, $confirm) {
    switch ($op) {
      case static::SUBMIT_UPDATE:
        return $this->t('The newsletter subscriptions for %mail have been updated.', array('%mail' => $form_state->getValue('mail')[0]['value']));

      case static::SUBMIT_SUBSCRIBE:
        if ($confirm) {
          return $this->t('You will receive a confirmation e-mail shortly containing further instructions on how to complete your subscription.');
        }
        return $this->t('You have been subscribed.');

      case static::SUBMIT_UNSUBSCRIBE:
        if ($confirm) {
          return $this->t('You will receive a confirmation e-mail shortly containing further instructions on how to cancel your subscription.');
        }
        return $this->t('You have been unsubscribed.');
    }
  }

}
