<?php
// $Id$

/**
 * @file simplenews-newsletter-body.tpl.php
 * Default theme implementation to format the simplenews newsletter body.
 *
 * Available variables:
 * - node: Newsletter node object
 * - $body: Newsletter body (formatted as plain text or HTML)
 * - $title: Node title
 * - $language: Language object
 *
 * @see template_preprocess_simplenews_newsletter_body()
 */
?>
<h2><?php print $title; ?></h2>
<?php print $body; ?>
