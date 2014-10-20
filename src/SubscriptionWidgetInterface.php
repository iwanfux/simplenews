<?php
/**
 * @file
 * Contains \Drupal\simplenews\SubscriptionWidgetInterface.
 */

namespace Drupal\simplenews;

use Drupal\Core\Field\WidgetInterface;

/**
 * Defines a widget used for the subscriptions field of a Subscriber.
 */
interface SubscriptionWidgetInterface extends WidgetInterface {

  /**
   * Set the newsletters available for selection.
   *
   * @param string[] $newsletter_ids
   *   Indexed array of newsletter IDs.
   */
  public function setAvailableNewsletterIds(array $newsletter_ids);

  /**
   * Hide the widget.
   *
   * Must be called before ::formElement().
   *
   * @param bool $set
   *   If FALSE, widget will not be hidden.
   */
  public function setHidden($set = TRUE);

  /**
   * Whether the widget is set to be hidden.
   *
   * @return bool
   *   Whether the widget is hidden.
   */
  public function isHidden();

  /**
   * Returns the IDs of the selected or deselected newsletters.
   *
   * @param array $form_state_value
   *   The value of the widget as returned by FormStateInterface::getValue().
   * @param bool $selected
   *   Whether to extract selected (TRUE) or deselected (FALSE) newsletter IDs.
   *
   * @return string[]
   *   IDs of selected/deselected newsletters.
   */
  public function extractNewsletterIds($form_state_value, $selected);

}
