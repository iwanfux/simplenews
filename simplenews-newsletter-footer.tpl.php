<?php
// $Id$

/**
 * @file simplenews-newsletter-footer.tpl.php
 * Default theme implementation to format the simplenews newsletter footer.
 *
 * Available variables:
 * - $node: newsletter node object
 * - $language: language object
 * - $key: email key [node|test]
 * - $format: newsletter format [plain|html]
 * - $unsubscribe_text: unsubscribe text
 * - $test_message: test message warning message
 *
 * Available tokens:
 * - !confirm_unsubscribe_url: unsubscribe url to be used as link
 * for more tokens: see simplenews_mail_tokens()
 *
 * @see template_preprocess_simplenews_newsletter_footer()
 */
?>
<?php if ($format == 'html'): ?>
  <p class="newsletter-footer"><a href="!confirm_unsubscribe_url"><?php print $unsubscribe_text ?></a></p>
<?php else: ?>
-- <?php print $unsubscribe_text ?>: !confirm_unsubscribe_url
<?php endif ?>

<?php if ($key == 'test'): ?>
- - - <?php print $test_message ?> - - -
<?php endif ?>