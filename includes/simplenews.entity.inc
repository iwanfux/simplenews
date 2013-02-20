<?php

/**
 * @file
 * Simplenews entities definitions.
 */

/**
 * Class for simplenews_newsletter entity.
 *
 * @incroup newsletter
 */
class SimplenewsNewsletter extends Entity {

  /**
   * The primary key.
   *
   * @var integer
   */
  public $newsletter_id;

  /**
   * Name of the newsletter.
   *
   * @var string
   */
  public $name;

  /**
   * Description of the newsletter.
   *
   * @var string
   */
  public $description;

  /**
   * HTML or plaintext newsletter indicator.
   *
   * @var integer
   */
  public $format;

  /**
   * Priority indicator.
   *
   * @var integer
   */
  public $priority;

  /**
   * TRUE if a read receipt should be requested.
   *
   * @var boolean
   */
  public $receipt;

  /**
   * Name of the e-mail author.
   *
   * @var string
   */
  public $from_name;

  /**
   * Newsletter subject.
   *
   * @var string
   */
  public $email_subject;

  /**
   * E-mail author address.
   *
   * @var string
   */
  public $from_address;

  /**
   * Indicates if hyperlinks should be kept inline or extracted.
   *
   * @var boolean
   */
  public $hyperlinks;

  /**
   * Indicates how to integrate with the register form.
   *
   * @var integer
   */
  public $new_account;

  /**
   * Defines the Opt-In/out options.
   *
   * @var integer.
   */
  public $opt_inout;

  /**
   * TRUE if a block should be provided for this newsletter.
   *
   * @var boolean
   */
  public $block;

  /**
   * Weight of the newsletter when displayed in listings.
   *
   * @var integer
   */
  public $weight;

  /**
   * Overrides Entity::setUp().
   *
   * Prevent failure if the entity system ist not fully loaded.
   */
  protected function setUp() {
    // Hack to get arount the not loaded entity system.
    // @todo: fix this and remove hack.
    if (function_exists('entity_get_info')) {
      parent::setUp();
    }
  }

  /**
   * Overrides Entity::__sleep().
   *
   * Prevent failure if the entity system ist not fully loaded.
   */
  public function __sleep() {
    // @todo: fix this and remove hack.
    $vars = get_object_vars($this);
    unset($vars['entityInfo'], $vars['idKey'], $vars['nameKey'], $vars['statusKey']);
    return array_combine(array_keys($vars), (array_keys($vars)));
  }

  /**
   * Overrides Entity::defaultUri().
   */
  protected function defaultUri() {
    return array('path' => 'newsletter/' . $this->identifier());
  }

  /**
   * Overrides Entity::defaultLabel().
   */
  protected function defaultLabel() {
    return $this->name;
  }

}

/**
 * Class for simplenews_subscriber entity.
 *
 * @incroup newsletter
 */
class SimplenewsSubscriber extends Entity {

  /**
   * An array of newsletter ids this subscriber is subscribed to.
   */
  public $newsletter_ids = array();

  /**
   * Overrides Entity::setUp().
   *
   * Prevent failure if the entity system ist not fully loaded.
   */
  protected function setUp() {
    // Hack to get arount the not loaded entity system.
    // @todo: fix this and remove hack.
    if (function_exists('entity_get_info')) {
      parent::setUp();
    }
  }

  /**
   * Overrides Entity::__sleep().
   *
   * Prevent failure if the entity system ist not fully loaded.
   */
  public function __sleep() {
    // @todo: fix this and remove hack.
    $vars = get_object_vars($this);
    unset($vars['entityInfo'], $vars['idKey'], $vars['nameKey'], $vars['statusKey']);
    return array_combine(array_keys($vars), (array_keys($vars)));
  }

  /**
   * Overrides Entity::defaultLabel().
   */
  protected function defaultLabel() {
    return $this->mail;
  }
}
