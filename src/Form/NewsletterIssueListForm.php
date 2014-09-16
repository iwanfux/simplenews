<?php

/**
 * @file
 * Definition of Drupal\simplenews\Form\NewsletterIssueListForm.
 */

namespace Drupal\simplenews\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;

/**
 * Base form for category edit forms.
 */
class NewsletterIssueListForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simplenews_newsletter_issue_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /*$form['filter'] = $this->getIssueFilterForm();
    $form['filter']['#theme'] = 'simplenews_filter_form';
    $form['admin'] = $this->getAdminIssues();*/
    $form = $this->getAdminIssues();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * Overrides Drupal\Core\Entity\EntityForm::save().
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /*switch ($form_state->getVallue('op')) {
      case t('Filter'):
        $_SESSION['simplenews_issue_filter'] = array(
          'newsletter' => $form_state->getValue('newsletter'),
        );
        break;
      case t('Reset'):
        $_SESSION['simplenews_issue_filter'] = _simplenews_issue_filter_default();
        break;
    }*/
  }

  /**
   * Return form for issue filters.
   *
   * @see save()
   */
  public function getIssueFilterForm() {
    // Current filter selections in $session var; stored at form submission
    // Example: array('newsletter' => 'all')
    $session = isset($_SESSION['simplenews_issue_filter']) ? $_SESSION['simplenews_issue_filter'] : $this->getDefaultIssueFilter();
    $filters = $this->getIssueFilters();

    $form['filters'] = array(
      '#type' => 'fieldset',
      '#title' => t('Show only newsletters which'),
    );

    // Filter values are default
    $form['filters']['newsletter'] = array(
      '#type' => 'select',
      '#title' => $filters['newsletter']['title'],
      '#options' => $filters['newsletter']['options'],
      '#default_value' => $session['newsletter'],
    );
    $form['filters']['buttons']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Filter'),
      '#prefix' => '<span class="spacer" />',
    );
    // Add Reset button if filter is in use
    if ($session != _simplenews_issue_filter_default()) {
      $form['filters']['buttons']['reset'] = array(
        '#type' => 'submit',
        '#value' => t('Reset'),
      );
    }

    return $form;
  }

  public function getIssueFilters() {
    // Newsletter filter
    $filters['newsletter'] = array(
      'title' => t('Subscribed to'),
      'options' => array(
        'all' => t('All newsletters'),
        'newsletter_id-0' => t('Unassigned newsletters'),
      ),
    );
    foreach (simplenews_newsletter_list() as $newsletter_id => $name) {
      $filters['newsletter']['options']['newsletter_id-' . $newsletter_id] = $name;
    }

    return $filters;
  }

  /**
   * Helper function: returns issue filter default settings
   */
  function getDefaultIssueFilter() {
    return array(
      'newsletter' => 'all',
    );
  }

  /* Form builder: Builds a list of newsletters with operations.
  *
  * @see simplenews_admin_issues_validate()
  * @see simplenews_admin_issues_submit()
  */
  public function getAdminIssues() {
    $config = \Drupal::config('simplenews.settings');
    // Build an 'Update options' form.
    /*$form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Update options'),
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
    );
    $options = array();
    foreach (\Drupal::moduleHandler()->invokeAll('simplenews_issue_operations') as $operation => $array) {
      $options[$operation] = $array['label'];
    }
    $form['options']['operation'] = array(
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => 'activate',
    );
    $form['options']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Update'),
      '#submit' => array('simplenews_admin_issues_submit'),
      '#validate' => array('simplenews_admin_issues_validate'),
    );*/

    if ($config->get('mail.last_cron')) {
      $form['last_sent'] = array(
        '#markup' => '<p>' . \Drupal::translation()->formatPlural($config->get('mail.last_sent'), 'Last batch: 1 mail sent at !time.', 'Last batch: !count mails sent at !time.', array('!time' => format_date($config->get('mail.last_cron'), 'small'), '!count' => $config->get('mail.last_sent'))) . "</p>\n",
      );
    }
    // Table header. Used as tablesort default
    $header = array(
      'title' => array(
        'data' => t('Title'),
        'specifier' => 'title',
        'type' => 'property',
      ),
      'newsletter' => array(
        'data' => t('Newsletter'),
        'specified' => array(
          'field' => 'simplenews_issue',
          'column' => 'target_id',
        ),
        'type' => 'field',
      ),
      'created' => array(
        'data' => t('Created'),
        'specifier' => 'created',
        'sort' => 'desc',
        'type' => 'property',
      ),
      'published' => array('data' => t('Published')),
      'sent' => array('data' => t('Sent')),
      'subscribers' => array('data' => t('Subscribers')),
      'operations' => array('data' => t('Operations')),
    );

    $query = \Drupal::entityQuery('node');
    //simplenews_build_issue_filter_query($query);
    $nids = $query
      ->tableSort($header)
      ->condition('type', simplenews_get_content_types())
      ->pager(30)
      ->execute();
    $options = array();

    module_load_include('inc', 'simplenews', 'includes/simplenews.mail');
    $categories = simplenews_newsletter_list();
    foreach (Node::loadMultiple($nids) as $node) {
      $subscriber_count = $this->countSubscriptions($node->simplenews_issue->target_id);
      $pending_count = simplenews_count_spool(array('entity_id' => $node->id(), 'entity_type' => 'node'));
      $status_render_array = array('#theme' => 'simplenews_status','#source' => 'sent', '#status' => $node->simplenews_issue->status);
      $send_status = $node->simplenews_issue->status == SIMPLENEWS_STATUS_SEND_PENDING ? $subscriber_count - $pending_count : drupal_render($status_render_array);

      $published_render_array = array('#theme' => 'simplenews_status','#source' => 'published', '#status' => $node->isPublished());
      $node_url = $node->urlInfo();
      $node_edit_url = $node->urlInfo('edit-form');
      $options[$node->id()] = array(
        'title' => \Drupal::l($node->getTitle(), $node_url->getRouteName(), $node_url->getRouteParameters()),
        'newsletter' => $node->simplenews_issue->target_id && isset($categories[$node->simplenews_issue->target_id]) ? $categories[$node->simplenews_issue->target_id] : t('- Unassigned -'),
        'created' => format_date($node->getCreatedTime(), 'small'),
        'published' => drupal_render($published_render_array),
        'sent' => $send_status,
        'subscribers' => $subscriber_count,
        'operations' => \Drupal::l(t('edit'), $node_edit_url->getRouteName(), $node_edit_url->getRouteParameters(), array('query' => drupal_get_destination())),
      );
    }

    $form['issues'] = array(
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => t('No newsletters available.'),
    );

    $form['pager'] = array('#theme' => 'pager');

    return $form;
  }

  /**
   * Count number of subscribers per newsletter list.
   *
   * @param $newsletter_id
   *   The newsletter id.
   *
   * @return
   *   Number of subscribers.
   */
  public function countSubscriptions($newsletter_id) {
    $subscription_count = &drupal_static(__FUNCTION__);

    if (isset($subscription_count[$newsletter_id])) {
      return $subscription_count[$newsletter_id];
    }

    // @todo: entity query + aggregate
    $query = db_select('simplenews_subscriber__subscriptions', 'ss');
    $query->leftJoin('simplenews_subscriber', 'sn', 'sn.id = ss.entity_id');
    $query->condition('subscriptions_target_id', $newsletter_id)
      ->condition('sn.status', 1)
      ->condition('ss.subscriptions_status', SIMPLENEWS_SUBSCRIPTION_STATUS_SUBSCRIBED);
    $subscription_count[$newsletter_id] = $query->countQuery()->execute()->fetchField();
    return $subscription_count[$newsletter_id];
  }

}
