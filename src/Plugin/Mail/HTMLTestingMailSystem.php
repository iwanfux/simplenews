<?php
/**
 * @file
 * Contains DDrupal\simplenews\Plugin\Mail\HTMLTestingMailSystem.
 */

namespace Drupal\simplenews\Plugin\Mail;

use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;

/**
 * A mail sending implementation that captures sent messages to a variable.
 *
 * This class is for running tests or for development and does not convert HTML
 * to plaintext.
 *
 * @Mail(
 *   id = "test_simplenews_html_mail",
 *   label = @Translation("HTML test mailer"),
 *   description = @Translation("Sends the message as plain text, using PHP's native mail() function.")
 * )
 */
class HTMLTestingMailSystem implements MailInterface {

  /**
   * Implements MailSystemInterface::format().
   */
  public function format(array $message) {
    // Join the body array into one string.
    $message['body'] = implode("\n\n", $message['body']);
    // Wrap the mail body for sending.
    $message['body'] = MailFormatHelper::wrapMail($message['body']);
    return $message;
  }

  /**
   * Implements MailSystemInterface::mail().
   */
  public function mail(array $message) {
    $captured_emails = \Drupal::state()->get('system.test_mail_collector') ?: array();
    $captured_emails[] = $message;
    \Drupal::state()->set('system.test_mail_collector', $captured_emails);
    return TRUE;
  }
}