<?php

/**
 * @file
 * Contains \Drupal\simplenews\Plugin\Block\SimplenewsSubscriptionBlock.
 */

namespace Drupal\simplenews\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Simplenews subscription' block with all available newsletters and an email field.
 *
 * @Block(
 *   id = "simplenews_subscription_block",
 *   admin_label = @Translation("Simplenews subscription"),
 *   category = @Translation("Simplenews")
 * )
 */
class SimplenewsSubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity query object for newsletters.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $newsletterQuery;

  /**
   * Constructs an SimplenewsSubscriptionBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface; $newsletterStorage
   *   The storage object for newsletters.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder object.
   * @param \Drupal\Core\Entity\Query\QueryInterface $newsletterQuery
   *   The entity query object for newsletters.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityStorageInterface $newsletterStorage, FormBuilderInterface $formBuilder, QueryInterface $newsletterQuery) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->newsletterStorage = $newsletterStorage;
    $this->formBuilder = $formBuilder;
    $this->newsletterQuery = $newsletterQuery;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')->getStorage('simplenews_newsletter'),
      $container->get('form_builder'),
      $container->get('entity.query')->get('simplenews_newsletter')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // By default, the block will contain 1 newsletter.
    return array(
      'newsletters' => array(),
      'message' => t('Select the newsletter(s) to which you want to subscribe or unsubscribe.'),
      'form' => 1,
      'issue_status' => 0,
      'issues' => 5,
      'unique_id' => '',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Only grant access to users with the 'subscribe to newsletters' permission.
    return $account->hasPermission('subscribe to newsletters');
  }

  /**
   * Overrides \Drupal\block\BlockBase::blockForm().
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $newsletters = simplenews_newsletter_get_visible();
    foreach ($newsletters as $newsletter) {
      $options[$newsletter->id()] = $newsletter->name;
    }

    $form['newsletters'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Newsletters'),
      '#options' => $options,
      '#required' => TRUE,
      '#default_value' => $this->configuration['newsletters'],
    );
    $form['message'] = array(
      '#type' => 'textfield',
      '#title' => t('Block message'),
      '#size' => 60,
      '#maxlength' => 255,
      '#default_value' => $this->configuration['message'],
    );
    $form['form'] = array(
      '#type' => 'radios',
      '#title' => t('Subscription interface'),
      '#options' => array('1' => t('Subscription form'), '0' => t('Link to form')),
      '#description' => t("Note: this requires permission 'subscribe to newsletters'."),
      '#default_value' => $this->configuration['form'],
    );
    /*if (\Drupal::moduleHandler()->moduleExists('views')) {
        $form['link_previous'] = array(
          '#type' => 'checkbox',
          '#title' => t('Display link to previous issues'),
          '#return_value' => 1,
          '#default_value' => variable_get('simplenews_block_l_' . $delta, 1),
          '#description' => t('Link points to newsletter/newsletter_id, which is provided by the newsletter issue list default view.'),
        );
      }*/
    $form['issue_status'] = array(
      '#type' => 'checkbox',
      '#title' => t('Display previous issues'),
      '#return_value' => 1,
      '#default_value' => $this->configuration['issue_status'],
    );
    $form['issue_count'] = array(
      '#type' => 'select',
      '#title' => t('Number of issues to display'),
      '#options' => array_combine(array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10), array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)),
      '#default_value' => $this->configuration['issues'],
      '#states' => array(
        'visible' => array(
          ":input[name='status']" => array('checked' => TRUE),
        ),
      ),
    );
    /*if (\Drupal::moduleHandler()->moduleExists('views')) {
      $form['rss_feed'] = array(
        '#type' => 'checkbox',
        '#title' => t('Display RSS-feed icon'),
        '#return_value' => 1,
        '#default_value' => variable_get('simplenews_block_r_' . $delta, 1),
        '#description' => t('Link points to newsletter/feed/newsletter_id, which is provided by the newsletter issue list default view.'),
      );
    }*/
    return $form;
  }

    /**
   * Overrides \Drupal\block\BlockBase::blockSubmit().
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['newsletters'] = array_filter($form_state['values']['newsletters']);
    $this->configuration['message'] = $form_state['values']['message'];
    $this->configuration['form'] = $form_state['values']['form'];
    //$this->configuration['link_previous'] = $form_state['values']['link_previous'];
    $this->configuration['issue_status'] = $form_state['values']['issue_status'];
    $this->configuration['issue_count'] = $form_state['values']['issue_count'];
    //$this->configuration['rss_feed'] = $form_state['values']['rss_feed'];
    $this->configuration['unique_id'] = \Drupal::service('uuid')->generate();
}

  /**
   * {@inheritdoc}
   */
  public function build() {
    $newsletters = $this->newsletterStorage->loadMultiple($this->configuration['newsletters']);
    $message = $this->configuration['message'];
    $form_object = \Drupal::service('class_resolver')->getInstanceFromDefinition('\Drupal\simplenews\Form\SubscriptionsBlockForm');
    $form_object->setUniqueId($this->configuration['unique_id']);
    return $this->formBuilder->getForm($form_object, $newsletters);
  }

}
