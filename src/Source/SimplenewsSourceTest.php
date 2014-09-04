<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSourceTest.
 */

namespace Drupal\simplenews\Source;

/**
 * Example source implementation used for tests.
 *
 * @ingroup source
 */
class SimplenewsSourceTest implements SimplenewsSourceInterface {

  protected $format;

  public function __construct($format) {
    $this->format = $format;
  }

  public function getAttachments() {
    return array(
      array(
        'uri' => 'example://test.png',
        'filemime' => 'x-example',
        'filename' => 'test.png',
      ),
    );
  }

  public function getBody() {
    return $this->getFormat() == 'plain' ? $this->getPlainBody() : 'the body';
  }

  public function getFooter() {
    return $this->getFormat() == 'plain' ? $this->getPlainFooter() : 'the footer';
  }

  public function getPlainFooter() {
    return 'the plain footer';
  }

  public function getFormat() {
    return $this->format;
  }

  public function getFromAddress() {
    return 'test@example.org';
  }

  public function getFromFormatted() {
    return 'Test <test@example.org>';
  }

  public function getHeaders(array $headers) {
    $headers['X-Simplenews-Test'] = 'OK';
    return $headers;
  }

  public function getKey() {
    return 'node';
  }

  public function getLanguage() {
    return 'en';
  }

  public function getPlainBody() {
    return 'the plain body';
  }

  public function getRecipient() {
    return 'recipient@example.org';
  }

  public function getSubject() {
    return 'the subject';
  }

  public function getTokenContext() {
    return array();
  }
}