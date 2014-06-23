<?php

/**
 * @file
 * Contains \Drupal\options\Plugin\Field\FieldWidget\SelectWidget.
 */

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\options\Plugin\Field\FieldWidget\SelectWidget;

/**
 * Plugin implementation of the 'simplenews_subscription_select' widget.
 *
 * @FieldWidget(
 *   id = "simplenews_subscription_select",
 *   label = @Translation("Select list"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_text"
 *   },
 *   multiple_values = TRUE
 * )
 */
class SubscriptionWidget extends SelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, array &$form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    return $element;
  }
}
