<?php
// $Id$

/**
 * @file simplenews-block.tpl.php
 * Default theme implementation to display the simplenews block.
 * The form applies to one newsletter (series) identified by $tid
 *
 * Available variables:
 * - $subscribed: the current user is subscribed to the $tid newsletter
 * - $user: the current user is authenticated
 * - $tid: tid of the newsletter
 * - $message: announcement message (Default: 'Stay informed on our latest news!')
 * - $form: newsletter subscription form
 * - $subscription_link: link to subscription form at 'newsletter/subscriptions'
 * - $newsletter_link: link to taxonomy list of the newsletter issue
 * - $issuelist: list of newsletters (of the $tid newsletter series)
 * - $rssfeed: rss feed of newsletter (series)
 *
 * Simplenews module controls the display of the block content. The following
 * variables are available for this purpose:
 *  - $use_message:
 *  - $use_form
 *  - $use_issue_link
 *  - $use_issue_list
 *  - $use_rss
 *
 * @see template_preprocess_simplenews_block()
 */
?>
  <?php if ($use_message && $message): ?>
    <p><?php print $message; ?></p>
  <?php endif; ?>

  <?php if ($use_form): ?>
    <?php print $form; ?>
  <?php elseif ($subscription_link): ?>
    <p><?php print $subscription_link; ?></p>
  <?php endif; ?>

  <?php if ($use_issue_link && $newsletter_link): ?>
    <div class="issues-link"><?php print $newsletter_link; ?></div>
  <?php endif; ?>

  <?php if ($use_issue_list && $issue_list): ?>
    <div class="issues-list"><?php print $issuelist; ?></div>
  <?php endif; ?>

  <?php if ($use_rss): ?>
    <?php print $rssfeed; ?>
  <?php endif; ?>
