<?php

/**
 * @file
 * Contains Drupal\simplenews\Entity\Subscriber.
 */

namespace Drupal\simplenews\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\simplenews\SubscriberInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinition;

/**
 * Defines the simplenews subscriber entity.
 *
 * @ContentEntityType(
 *   id = "simplenews_subscriber",
 *   label = @Translation("Simplenews subscriber"),
 *   controllers = {
 *     "list_builder" = "Drupal\simplenews\SubscriberListBuilder",
 *     "form" = {
 *       "default" = "Drupal\simplenews\Form\SubscriberForm",
 *       "delete" = "Drupal\simplenews\Form\SubscriberDeleteForm",
 *     }
 *   },
 *   base_table = "simplenews_subscriber",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "mail"
 *   },
 *   fieldable = TRUE,
 *   admin_permission = "administer simplenews subscriptions",
 *   links = {
 *     "admin-form" = "simplenews.subscriber_add",
 *     "edit-form" = "simplenews.subscriber_edit",
 *     "delete-form" = "simplenews.subscriber_delete",
 *   }
 * )
 */
class Subscriber extends ContentEntityBase implements SubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    return $this->get('message')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage($message) {
    $this->set('message', $message);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
  }

  /**
   * {@inheritdoc}
   */
  public function getMail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setMail($mail) {
    $this->set('mail', $mail);
  }

  /**
   * {@inheritdoc}
   */
  public function getUserId() {
    return $this->get('uid')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setUserId($uid) {
    $this->set('uid', $uid);
  }
  
  /**
   * {@inheritdoc}
   */
  public function getLangcode() {
    return $this->get('langcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setLangcode($langcode) {
    $this->set('langcode', $langcode);
  }
  
  /**
   * {@inheritdoc}
   */
  public function getChanges() {
    return $this->get('changes')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChanges($changes) {
    $this->set('changes', $changes);
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed($newsletter_id) {
    foreach ($this->subscriptions as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isUnsubscribed($newsletter_id) {
    foreach ($this->subscriptions as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscription($newsletter_id) {
    foreach ($this->subscriptions as $item) {
      if ($item->target_id == $newsletter_id) {
        return $item;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribedNewsletterIds() {
    $ids = array();
    foreach ($this->subscriptions as $item) {
      if ($item->status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED) {
        $ids[] = $item->target_id;
      }
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function subscribe($newsletter_id, $status = SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED, $source = 'unknown', $timestamp = REQUEST_TIME) {
    if($subscription = $this->getSubscription($newsletter_id)) {
      $subscription->status = $status;
    } else {
      $next_delta = count($this->subscriptions);
      $this->subscriptions[$next_delta]->target_id = $newsletter_id;
      $this->subscriptions[$next_delta]->status = $status;
      $this->subscriptions[$next_delta]->source = $source;
      $this->subscriptions[$next_delta]->timestamp = $timestamp;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe($newsletter_id, $status = SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED, $source = 'unknown', $timestamp = REQUEST_TIME) {
    if($subscription = $this->getSubscription($newsletter_id)) {
      $subscription->status = $status;
    } else {
      $next_delta = count($this->subscriptions);
      $this->subscriptions[$next_delta]->target_id = $newsletter_id;
      $this->subscriptions[$next_delta]->status = $status;
      $this->subscriptions[$next_delta]->source = $source;
      $this->subscriptions[$next_delta]->timestamp = $timestamp;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = FieldDefinition::create('integer')
      ->setLabel(t('Subscriber ID'))
      ->setDescription(t('Primary key: Unique subscriber ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = FieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The subscriber UUID.'))
      ->setReadOnly(TRUE);

    $fields['status'] = FieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Boolean indicating the status of the subscriber.'))
      ->setSetting('default_value', FALSE);

    $fields['mail'] = FieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The subscribers email address.'))
      ->setSetting('default_value', '')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', array( 
        'type' => 'email',
        'settings' => array(),
        'weight' => 5,
      )) 
      ->setDisplayConfigurable('form', TRUE);

    $fields['uid'] = FieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The corresponding user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('form', array(
        'type' => 'entity_reference_autocomplete',
        'settings' => array(
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ),
        'weight' => 10,
      ))
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = FieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The subscribers preffered language.'));

    $fields['changes'] = FieldDefinition::create('string_long')
      ->setLabel(t('Changes'))
      ->setDescription(t('Contains the requested subscription changes.'));

    $fields['created'] = FieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the subscriber was created.'));

    return $fields;
  }
}
