<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SourceInterface.
 */

namespace Drupal\simplenews\Source;

/**
 * The source used to build a newsletter mail.
 *
 * @ingroup source
 */
interface SourceInterface {

  /**
   * Returns the mail headers.
   *
   * @param $headers
   *   The default mail headers.
   *
   * @return
   *   Mail headers as an array.
   */
  function getHeaders(array $headers);

  /**
   * Returns the mail subject.
   */
  function getSubject();

  /**
   * Returns the mail body.
   *
   * The body should either be plaintext or html, depending on the format.
   */
  function getBody();

  /**
   * Returns the plaintext body.
   */
  function getPlainBody();

  /**
   * Returns the mail footer.
   *
   * The footer should either be plaintext or html, depending on the format.
   */
  function getFooter();

  /**
   * Returns the plain footer.
   */
  function getPlainFooter();

  /**
   * Returns the mail format.
   *
   * @return
   *   The mail format as string, either 'plain' or 'html'.
   */
  function getFormat();

  /**
   * Returns the recipent of this newsletter mail.
   *
   * @return
   *   The recipient mail address(es) of this newsletter as a string.
   */
  function getRecipient();

  /**
   * The language that should be used for this newsletter mail.
   */
  function getLanguage();

  /**
   * Returns an array of attachments for this newsletter mail.
   *
   * @return
   *   An array of managed file objects with properties uri, filemime and so on.
   */
  function getAttachments();

  /**
   * Returns the token context to be used with token replacements.
   *
   * @return
   *   An array of objects as required by token_replace().
   */
  function getTokenContext();

  /**
   * Returns the mail key to be used for mails.
   *
   * @return
   *   The mail key, either test or node.
   */
  function getKey();

  /**
   * Returns the formatted from mail address.
   */
  function getFromFormatted();

  /**
   * Returns the plain mail address.
   */
  function getFromAddress();
}
