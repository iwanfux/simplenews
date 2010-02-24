<?php
// $Id$

/**
 * @file
 * Default theme implementation to format the simplenews newsletter body.
 *
 * Copy this file in your theme directory to create a custom themed body.
 * Rename it to override it. Available templates:
 *   simplenews-newsletter-body--[category machine name].tpl.php
 *   simplenews-newsletter-body--[view mode].tpl.php
 *   simplenews-newsletter-body--[category machine name]--[view mode].tpl.php
 *
 * [category machine name]: Machine readable name of the newsletter category
 * [view mode]: 'email_plain', 'email_html', 'email_textalt'
 * Example:
 *   simplenews-newsletter-body--drupal--email_plain.tpl.php
 *
 * Available variables:
 * - $build: Array as expected by render().
 * - $title: Node title
 * - $language: Language object
 * - $view_mode: Active view mode.
 *
 * @see template_preprocess_simplenews_newsletter_body()
 */
?>
<h2><?php print $title; ?></h2>
<?php print render($build); ?>
