<?php

/**
 * @file
 * Contains Drupal\contact\Entity\Message.
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
 *     "storage" = "Drupal\Core\Entity\ContentEntityNullStorage",
 *     "view_builder" = "Drupal\simplenews\SubscriberViewBuilder",
 *     "form" = {
 *       "default" = "Drupal\simplenews\SubscriberForm"
 *     }
 *   },
 *   entity_keys = {
 *     "bundle" = "subscriber"
 *   },
 *   bundle_entity_type = "simplenews_subscriber",
 *   fieldable = TRUE,
 *   links = {
 *     "admin-form" = "simplenews.subscriber_edit"
 *   }
 * )
 */
class Subscriber extends ContentEntityBase implements SubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function id() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isPersonal() {
    return $this->bundle() == 'personal';
  }

  /**
   * {@inheritdoc}
   */
  public function getCategory() {
    return $this->get('category')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderName($sender_name) {
    $this->set('name', $sender_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getSenderMail() {
    return $this->get('mail')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSenderMail($sender_mail) {
    $this->set('mail', $sender_mail);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubject() {
    return $this->get('subject')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubject($subject) {
    $this->set('subject', $subject);
  }

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
  public function copySender() {
    return (bool)$this->get('copy')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCopySender($inform) {
    $this->set('copy', (bool) $inform);
  }

  /**
   * {@inheritdoc}
   */
  public function getPersonalRecipient() {
    if ($this->isPersonal()) {
      return $this->get('recipient')->entity;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields['category'] = FieldDefinition::create('entity_reference')
      ->setLabel(t('Category ID'))
      ->setDescription(t('The ID of the associated category.'))
      ->setSettings(array('target_type' => 'contact_category'))
      ->setRequired(TRUE);

    $fields['name'] = FieldDefinition::create('string')
      ->setLabel(t("The sender's name"))
      ->setDescription(t('The name of the person that is sending the contact message.'));

    $fields['mail'] = FieldDefinition::create('email')
      ->setLabel(t("The sender's email"))
      ->setDescription(t('The email of the person that is sending the contact message.'));

    $fields['subject'] = FieldDefinition::create('string')
      ->setLabel(t('The message subject'))
      ->setDescription(t('The subject of the contact message.'));

    $fields['message'] = FieldDefinition::create('string')
      ->setLabel(t('The message text'))
      ->setDescription(t('The text of the contact message.'));

    $fields['copy'] = FieldDefinition::create('boolean')
      ->setLabel(t('Copy'))
      ->setDescription(t('Whether to send a copy of the message to the sender.'));

    $fields['recipient'] = FieldDefinition::create('entity_reference')
      ->setLabel(t('Recipient ID'))
      ->setDescription(t('The ID of the recipient user for personal contact messages.'))
      ->setSettings(array('target_type' => 'user'));

    return $fields;
  }

}
