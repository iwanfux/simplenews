<?php
// $Id$

/**
 * @file simplenews-status.tpl.php
 * Default theme implementation to display the simplenews newsletter status.
 *
 * Available variables:
 * - $img: status image
 * - $alt: 'alt' message
 * - $title: 'title' message
 *
 * @see template_preprocess_simplenews_status()
 */
?>
  <img src="<?php print $image; ?>" width="15" height="15" alt="<?php print $alt; ?>" border="0" title="<?php print $title; ?>" />
