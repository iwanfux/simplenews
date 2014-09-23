<?php

/**
 * @file
 * Contains Drupal\simplenews\Entity\Subscriber.
 */

namespace Drupal\simplenews\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\simplenews\SubscriberInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the simplenews subscriber entity.
 *
 * @ContentEntityType(
 *   id = "simplenews_subscriber",
 *   label = @Translation("Simplenews subscriber"),
 *   handlers = {
 *     "list_builder" = "Drupal\simplenews\SubscriberListBuilder",
 *     "form" = {
 *       "default" = "Drupal\simplenews\Form\SubscriberForm",
 *       "account" = "Drupal\simplenews\Form\SubscriptionsAccountForm",
 *       "delete" = "Drupal\simplenews\Form\SubscriberDeleteForm",
 *     }
 *   },
 *   base_table = "simplenews_subscriber",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "mail"
 *   },
 *   fieldable = TRUE,
 *   field_ui_base_route = "simplenews.settings_subscriber",
 *   admin_permission = "administer simplenews subscriptions",
 *   links = {
 *     "edit-form" = "simplenews.subscriber_edit",
 *     "delete-form" = "simplenews.subscriber_delete",
 *   }
 * )
 */
class Subscriber extends ContentEntityBase implements SubscriberInterface {

  /**
   * Whether currently copying field values to corresponding User.
   *
   * @var bool
   */
  protected static $syncing;

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
    $value = $this->get('uid')->getValue();
    if (isset($value[0]['target_id'])) {
      return $value[0]['target_id'];
    }
    return '0';
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
    return unserialize($this->get('changes')->value);
  }

  /**
   * {@inheritdoc}
   */
  public function setChanges($changes) {
    $this->set('changes', serialize($changes));
  }

  /**
   * {@inheritdoc}
   */
  public function isSyncing() {
    return static::$syncing;
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
    if ($status == SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED) {
      \Drupal::moduleHandler()->invokeAll('simplenews_subscribe', array($this, $newsletter_id));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe($newsletter_id, $source = 'unknown', $timestamp = REQUEST_TIME) {
    if($subscription = $this->getSubscription($newsletter_id)) {
      $subscription->status = SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED;
    } else {
      $next_delta = count($this->subscriptions);
      $this->subscriptions[$next_delta]->target_id = $newsletter_id;
      $this->subscriptions[$next_delta]->status = SIMPLENEWS_SUBSCRIPTION_STATUS_UNSUBSCRIBED;
      $this->subscriptions[$next_delta]->source = $source;
      $this->subscriptions[$next_delta]->timestamp = $timestamp;
    }
    // Clear eventually existing mail spool rows for this subscriber.
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    simplenews_delete_spool(array('snid' => $this->id(), 'newsletter_id' => $newsletter_id));

    \Drupal::moduleHandler()->invokeAll('simplenews_unsubscribe', array($this, $newsletter_id));
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Copy values for shared fields to existing user.
    $user = User::load($this->getUserId());
    if (isset($user)) {
      static::$syncing = TRUE;
      foreach ($this->getUserSharedFields($user) as $field_name) {
        $user->set($field_name, $this->get($field_name)->getValue());
      }
      $user->save();
      static::$syncing = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(EntityStorageInterface $storage) {
    parent::postCreate($storage);

    // Set the uid field if there is a user with the same email.
    $user_ids = \Drupal::entityQuery('user')
      ->condition('mail', $this->getMail())
      ->execute();
    if (!empty($user_ids)) {
      $this->setUserId(array_pop($user_ids));
    }

    // Copy values for shared fields from existing user.
    $user = User::load($this->getUserId());
    if (isset($user)) {
      foreach ($this->getUserSharedFields($user) as $field_name) {
        $this->set($field_name, $user->get($field_name)->getValue());
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserSharedFields(UserInterface $user) {
    $field_names = array();
    // Find any fields sharing name and type.
    foreach ($this->getFieldDefinitions() as $field_definition) {
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_definition */
      $field_name = $field_definition->getName();
      $user_field = $user->getFieldDefinition($field_name);
      if ($field_definition->getBundle() && isset($user_field) && $user_field->getType() == $field_definition->getType()) {
        $field_names[] = $field_name;
      }
    }
    return $field_names;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Subscriber ID'))
      ->setDescription(t('Primary key: Unique subscriber ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The subscriber UUID.'))
      ->setReadOnly(TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('Boolean indicating the status of the subscriber.'))
      ->setSetting('default_value', FALSE);

    $fields['mail'] = BaseFieldDefinition::create('email')
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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The corresponding user.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayConfigurable('form', TRUE);

    $fields['langcode'] = BaseFieldDefinition::create('language')
      ->setLabel(t('Language code'))
      ->setDescription(t('The subscribers preffered language.'));

    $fields['changes'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Changes'))
      ->setDescription(t('Contains the requested subscription changes.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the subscriber was created.'));

    return $fields;
  }
}
