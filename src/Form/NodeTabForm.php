<?php

/**
 * @file
 * Contains \Drupal\simplenews\Form\NodeTabForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Component\Utility\String;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Configure simplenews subscriptions of a user.
 */
class NodeTabForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_node_tab';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {
    $config = \Drupal::config('simplenews.settings');

    $status = $node->simplenews_issue->status;

    $form['#title'] = t('<em>Newsletter</em> @title', array('@title' => $node->getTitle()));

    // We will need the node
    $form_state['node'] = $node;

    // @todo delete this fieldset?
    $form['simplenews'] = array(
      '#type' => 'fieldset',
      '#title' => t('Send newsletter'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
      '#tree' => TRUE,
    );

    // Show newsletter sending options if newsletter has not been send yet.
    // If send a notification is shown.
    if ($status == SIMPLENEWS_STATUS_SEND_NOT || $status == SIMPLENEWS_STATUS_SEND_PUBLISH) {

      $options = array(
        SIMPLENEWS_COMMAND_SEND_TEST => t('Send one test newsletter to the test address'),
      );

      // Add option to send on publish when the node is unpublished.
      if ($node->isPublished()) {
        $options[SIMPLENEWS_COMMAND_SEND_PUBLISH] = t('Send newsletter when published');
      }
      else {
        $options[SIMPLENEWS_COMMAND_SEND_NOW] = t('Send newsletter');
      }

      if ($status == SIMPLENEWS_STATUS_SEND_PUBLISH) {
        $send_default = SIMPLENEWS_STATUS_SEND_PUBLISH;
      }
      else {
        $send_default = $config->get('newsletter.send');
      }
      $form['simplenews']['send'] = array(
        '#type' => 'radios',
        '#title' => t('Send newsletter'),
        '#default_value' => $send_default,
        '#options' => $options,
        '#attributes' => array(
          'class' => array('simplenews-command-send'),
        ),
      );

      if ($config->get('newsletter.test_address_override')) {
        $form['simplenews']['test_address'] = array(
          '#type' => 'textfield',
          '#title' => t('Test email addresses'),
          '#description' => t('A comma-separated list of email addresses to be used as test addresses.'),
          '#default_value' => $config->get('newsletter.test_address'),
          '#size' => 60,
          '#maxlength' => 128,
        );
      }
      else {
        $form['simplenews']['test_address'] = array(
          '#type' => 'value',
          '#value' => $config->get('newsletter.test_address'),
        );
      }

      $default_handler = isset($form_state['values']['simplenews']['recipient_handler']) ? $form_state['values']['simplenews']['recipient_handler'] : $node->simplenews_issue->handler;

      $recipient_handler_manager = \Drupal::service('plugin.manager.simplenews_recipient_handler');
      $options = $recipient_handler_manager->getOptions();
      $form['simplenews']['recipient_handler'] = array(
        '#type' => 'select',
        '#title' => t('Recipients'),
        '#description' => t('Please select to configure who to send the email to.'),
        '#options' => $options,
        '#default_value' => $default_handler,
        '#access' => count($options) > 1,
        '#ajax' => array(
          'callback' => 'simplenews_node_tab_send_form_handler_update',
          'wrapper' => 'recipient-handler-settings',
          'method' => 'replace',
          'effect' => 'fade',
        ),
      );

      // Get the handler class
      $handler_definitions = $recipient_handler_manager->getDefinitions();
      $handler = $handler_definitions[$default_handler];
      $class = $handler['class'];

      $settings = $node->simplenews_issue->handler_settings;

      if (method_exists($class, 'settingsForm')) {
        $element = array(
          '#parents' => array('simplenews', 'recipient_handler_settings'),
          '#prefix' => '<div id="recipient-handler-settings">',
          '#suffix' => '</div>',
        );

        $form['simplenews']['recipient_handler_settings'] = $class::settingsForm($element, $settings);
      }
      else {
        $form['simplenews']['recipient_handler']['#suffix'] = '<div id="recipient-handler-settings"></div>';
      }
    }
    else {
      $form['simplenews']['none'] = array(
        '#type' => 'checkbox',
        '#return_value' => 0,
        '#attributes' => array(
          'checked' => 'checked',
          'disabled' => 'disabled',
        ),
      );
      $form['simplenews']['none']['#title'] = ($status == SIMPLENEWS_STATUS_SEND_READY) ? t('This newsletter has been sent') : t('This newsletter is pending');
      return $form;
    }
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Submit'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state['values'];
    $node = $form_state['node'];

    // Validate recipient handler settings.
    if (!empty($form['simplenews']['recipient_handler_settings'])) {
      ctools_include('plugins');
      $handler = $values['simplenews']['recipient_handler'];
      $handler = ctools_get_plugins('simplenews', 'recipient_handlers', $handler);
      $class = $handler['class'];

      if (method_exists($class, 'settingsFormValidate')) {
        $class::settingsFormValidate($form['simplenews']['recipient_handler_settings'], $form_state);
      }
    }

    $default_address = variable_get('simplenews_test_address', variable_get('site_mail', ini_get('sendmail_from')));
    $mails = array($default_address);
    if (isset($values['simplenews']['send']) && $values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_TEST && variable_get('simplenews_test_address_override', 0)) {
      // @todo Can we simplify and use only two kind of messages?
      if (!empty($values['simplenews']['test_address'])) {
        $mails = explode(',', $values['simplenews']['test_address']);
        foreach ($mails as $mail) {
          $mail = trim($mail);
          if ($mail == '') {
            form_set_error('simplenews][test_address', t('Test email address is empty.'));
          }
          elseif (!valid_email_address($mail)) {
            form_set_error('simplenews][test_address', t('Invalid email address "%mail".', array('%mail' => $mail)));
          }
        }
      }
      else {
        form_set_error('simplenews][test_address', t('Missing test email address.'));
      }
    }
    $form_state['test_addresses'] = $mails;

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state['values'];
    $node = $form_state['node'];

    // Save the recipient handler and it's settings.
    simplenews_issue_handler($node, $values['simplenews']['recipient_handler']);

    if (!empty($form['simplenews']['recipient_handler_settings'])) {
      ctools_include('plugins');
      $handler = $values['simplenews']['recipient_handler'];
      $handler = ctools_get_plugins('simplenews', 'recipient_handlers', $handler);
      $class = $handler['class'];

      if (method_exists($class, 'settingsFormSubmit')) {
        $settings = $class::settingsFormSubmit($form['simplenews']['recipient_handler_settings'], $form_state);
        simplenews_issue_handler_settings($node, $settings);
      }
    }

    // Send newsletter to all subscribers or send test newsletter
    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    if ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_NOW) {
      simplenews_add_node_to_spool($node);
      // Attempt to send immediatly, if configured to do so.
      if (simplenews_mail_attempt_immediate_send(array('entity_id' => $node->nid, 'entity_type' => 'node'))) {
        drupal_set_message(t('Newsletter %title sent.', array('%title' => $node->title)));
      }
      else {
        drupal_set_message(t('Newsletter %title pending.', array('%title' => $node->title)));
      }
    }
    elseif ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_TEST) {
      simplenews_send_test($node, $form_state['test_addresses']);
    }

    // If the selected command is send on publish, just set the newsletter status.
    if ($values['simplenews']['send'] == SIMPLENEWS_COMMAND_SEND_PUBLISH) {
      simplenews_issue_status($node, SIMPLENEWS_STATUS_SEND_PUBLISH);
      drupal_set_message(t('The newsletter will be sent when the content is published.'));
    }

    node_save($node);
  }

  /**
   * Checks access for the simplenews node tab.
   *
   * @param \Drupal\user\UserInterface $user
   *   (optional) The owner of the shortcut set.
   *
   * @return mixed
   *   AccessInterface::ALLOW, AccessInterface::DENY, or AccessInterface::KILL.
   */
  public function checkAccess(UserInterface $user = NULL) {
    return AccessInterface::DENY;
    $account = $this->currentUser();

    if ($account->hasPermission('administer simplenews subscriptions')) {
      // Administrators can administer anyone's subscriptions.
      return AccessInterface::ALLOW;
    }

    if (!$account->hasPermission('subscribe to newsletters')) {
      // The user has no permission to subscribe to newsletters.
      return AccessInterface::DENY;
    }

    if ($user->id() == $account->id()) {
      // Users with the 'subscribe to newsletters' permission can administer their own
      // subscriptions.
      return AccessInterface::ALLOW;
    }
    return AccessInterface::DENY;
  }

}
