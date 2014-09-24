<?php

/**
 * @file
 * Contains \Drupal\simplenews\Plugin\Field\FieldWidget\SubscriptionWidget.
 */

namespace Drupal\simplenews\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
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
  protected function getSelectedOptions(FieldItemListInterface $items, $delta = 0) {
    // Copy parent behavior but also check the status property.
    $flat_options = $this->flattenOptions($this->getOptions($items[$delta]));
    $selected_options = array();
    foreach ($items as $item) {
      $value = $item->{$this->column};
      // Keep the value if it actually is in the list of options (needs to be
      // checked against the flat list).
      if ($item->status && isset($flat_options[$value])) {
        $selected_options[] = $value;
      }
    }
    return $selected_options;
  }
}
