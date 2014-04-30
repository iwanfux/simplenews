<?php

/**
 * @file
 * Definition of Drupal\simplenews\NewsletterForm.
 */

namespace Drupal\simplenews;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Base form for category edit forms.
 */
class NewsletterForm extends EntityForm {

  /**
   * Overrides Drupal\Core\Entity\EntityForm::form().
   */
  public function form(array $form, array &$form_state) {
    $form = parent::form($form, $form_state);

    $newsletter = $this->entity;

    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#maxlength' => 255,
      '#default_value' => $newsletter->label(),
      '#description' => t("The newsletter name."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $newsletter->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => array(
        'exists' => 'simplenews_newsletter_load',
        'source' => array('name'),
      ),
      '#disabled' => !$newsletter->isNew(),
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $newsletter->description,
      '#description' => t("A description of the newsletter."),
    );
    $links = array('!mime_mail_url' => 'http://drupal.org/project/mimemail', '!html_url' => 'http://drupal.org/project/htmlmail');
    $description = t('Newsletter format. Install <a href="!mime_mail_url">Mime Mail</a> module or <a href="!html_url">HTML Mail</a> module to send newsletters in HTML format.', $links);
    $form['weight'] = array(
      '#type' => 'hidden',
      '#value' => $newsletter->weight,
     );
    $form['subscription'] = array(
      '#type' => 'fieldset',
      '#title' => t('Subscription settings'),
      '#collapsible' => FALSE,
    );
  // Subscribe at account registration time.
  $options = simplenews_new_account_options();
  $form['subscription']['new_account'] = array(
    '#type' => 'select',
    '#title' => t('Subscribe new account'),
    '#options' => $options,
    '#default_value' => $newsletter->new_account,
    '#description' => t('None: This newsletter is not listed on the user registration page.<br />Default on: This newsletter is listed on the user registion page and is selected by default.<br />Default off: This newsletter is listed on the user registion page and is not selected by default.<br />Silent: A new user is automatically subscribed to this newsletter. The newsletter is not listed on the user registration page.'),
    );

    // Type of (un)subsribe confirmation
    $options = simplenews_opt_inout_options();
    $form['subscription']['opt_inout'] = array(
      '#type' => 'select',
      '#title' => t('Opt-in/out method'),
      '#options' => $options,
      '#default_value' => $newsletter->opt_inout,
      '#description' => t('Hidden: This newsletter does not appear on subscription forms. No unsubscription footer in newsletter.<br /> Single: Users are (un)subscribed immediately, no confirmation email is sent.<br />Double: When (un)subscribing at a subscription form, anonymous users receive an (un)subscription confirmation email. Authenticated users are (un)subscribed immediately.'),
    );
    // Provide subscription block for this newsletter.
    $form['subscription']['block'] = array(
      '#type' => 'checkbox',
      '#title' => t('Subscription block'),
      '#default_value' => $newsletter->block,
      '#description' => t('A subscription block will be provided for this newsletter. Anonymous and authenticated users can subscribe and unsubscribe using this block.'),
    );

    $form['email'] = array(
      '#type' => 'fieldset',
      '#title' => t('Email settings'),
      '#collapsible' => FALSE,
    );
    // Hide format selection if there is nothing to choose.
    // The default format is plain text.
    $format_options = simplenews_format_options();
    if (count($format_options) > 1) {
      $form['email']['format'] = array(
        '#type' => 'radios',
        '#title' => t('Email format'),
        '#default_value' => $newsletter->format,
        '#options' => $format_options,
      );
    }
    else {
      $form['email']['format'] = array(
        '#type' => 'hidden',
        '#value' => key($format_options),
      );
      $form['email']['format_text'] = array(
        '#markup' => t('Newsletter emails will be sent in %format format.', array('%format' => $newsletter->format)),
      );
    }
    // Type of hyperlinks.
    $form['email']['hyperlinks'] = array(
      '#type' => 'radios',
      '#title' => t('Hyperlink conversion'),
      '#description' => t('Determine how the conversion to text is performed.'),
      '#options' => array(t('Append hyperlinks as a numbered reference list'), t('Display hyperlinks inline with the text')),
      '#default_value' => $newsletter->hyperlinks,
      '#states' => array(
        'visible' => array(
          ':input[name="format"]' => array(
            'value' => 'plain',
          ),
        ),
      ),
    );

    $form['email']['priority'] = array(
      '#type' => 'select',
      '#title' => t('Email priority'),
      '#default_value' => $newsletter->priority,
      '#options' => simplenews_get_priority(),
    );
    $form['email']['receipt'] = array(
      '#type' => 'checkbox',
      '#title' => t('Request receipt'),
      '#return_value' => 1,
      '#default_value' => $newsletter->receipt,
    );

    // Email sender name
    $form['simplenews_sender_information'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender information'),
      '#collapsible' => FALSE,
    );
    $form['simplenews_sender_information']['from_name'] = array(
      '#type' => 'textfield',
      '#title' => t('From name'),
      '#size' => 60,
      '#maxlength' => 128,
      '#default_value' => $newsletter->from_name,
    );

    // Email subject
    $form['simplenews_subject'] = array(
      '#type' => 'fieldset',
      '#title' => t('Newsletter subject'),
      '#collapsible' => FALSE,
    );
    if (module_exists('token')) {
      $form['simplenews_subject']['token_help'] = array(
        '#title' => t('Replacement patterns'),
        '#type' => 'fieldset',
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      );
      $form['simplenews_subject']['token_help']['browser'] = array(
        '#theme' => 'token_tree',
        '#token_types' => array('simplenews-newsletter', 'node', 'simplenews-subscriber'),
      );
    }

    $form['simplenews_subject']['email_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Email subject'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $newsletter->subject,
    );

    // Email from address
    $form['simplenews_sender_information']['from_address'] = array(
      '#type' => 'email',
      '#title' => t('From email address'),
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
      '#default_value' => $newsletter->from_address,
    );

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
      '#weight' => 50,
    );

    if ($newsletter->id) {
      $form['actions']['delete'] = array(
        '#type' => 'submit',
        '#value' => t('Delete'),
        '#weight' => 55,
      );
    }
    return $form;
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::validate().
   */
  public function validate(array $form, array &$form_state) {
    parent::validate($form, $form_state);
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function save(array $form, array &$form_state) {
    $newsletter = $this->entity;
    $status = $newsletter->save();

    $edit_link = \Drupal::linkGenerator()->generateFromUrl($this->t('Edit'), $this->entity->urlInfo());

    if ($status == SAVED_UPDATED) {
      drupal_set_message(t('Newsletter %label has been updated.', array('%label' => $newsletter->label())));
      watchdog('simplenews', 'Newsletter %label has been updated.', array('%label' => $newsletter->label()), WATCHDOG_NOTICE, $edit_link);
    }
    else {
      drupal_set_message(t('Newsletter %label has been added.', array('%label' => $newsletter->label())));
      watchdog('simplenews', 'Newsletter %label has been added.', array('%label' => $newsletter->label()), WATCHDOG_NOTICE, $edit_link);
    }

    $form_state['redirect_route']['route_name'] = 'simplenews.newsletter_list';
  }

}
