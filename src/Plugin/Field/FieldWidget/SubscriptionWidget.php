<?php

/**
 * @file
 * Contains \Drupal\options\Plugin\Field\FieldWidget\SelectWidget.
 */

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\options\Plugin\Field\FieldWidget\ButtonsWidget;

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
class SubscriptionWidget extends ButtonsWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, array &$form_state) {
    //debug($values);
    //$existing_values = $form_state['controller']->getEntity()->subscriptions->getValue();
    return $values;
  }
}