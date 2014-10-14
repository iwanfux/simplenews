<?php

/**
 * @file
 * Contains \Drupal\simplenews\Plugin\Field\FieldWidget\SubscriptionWidget.
 */

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'simplenews_subscription_select' widget.
 *
 * @FieldWidget(
 *   id = "simplenews_subscription_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "simplenews_subscription"
 *   },
 *   multiple_values = TRUE
 * )
 */
class SubscriptionWidget extends OptionsButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    //debug($values);
    //$existing_values = $form_state->get('controller')->getEntity()->subscriptions->getValue();
    return $values;
  }
}
