$Id$

DESCRIPTION
-----------

This module sends html or plain text newsletters to the subscription list. In
the newsletter footer an unsubscribe link is provided. Subscription and
unsubscription are managed through a block, a form or by the newsletter
administrator on the module's admin pages.

Individual newsletters are grouped in newsletters by a newsletter taxonomy term.
Newsletters can have a block with the ability of (un)subscription, listing of
recent newsletters and an associated rss-feed. 

Send newsletters and not-sent newsletters are listed separately. The
subscription list can be managed. 

Sending of large mailings can be managed by cron.

REQUIREMENTS
------------

 * Drupal 6
 * Taxonomy module
 * For large mailing lists, cron is required.
 * HTML-format newsletters and/or newsletters with file attachments require the
   mimemail module. 


INSTALLATION
------------

 1. CREATE DIRECTORY

    Create a new directory "simplenews" in the sites/all/modules directory and
    place the entire contents of this simplenews folder in it.

 2. ENABLE THE MODULE

    Enable the module on the Modules admin page:
      Administer > Site building > Modules

 3. ACCESS PERMISSION

    Grant the proper access to user accounts at the Access control page:
      Administer > User management > Access control. 
    To enable users to (un)subscribe to a newsletter use the "subscribe to
    newsletters" permission. This will enable the Simplenews block where the
    user can (un)subscribe to a newsletter. 
    Use the "view links in block" permission to enable the display of previous
    newsletters in the Simplenews block.

 4. ENABLE SIMPLENEWS BLOCK

    Enable the Simplenews block on the Administer blocks page:
      Administer > Site building > Blocks.
    One block is available for each newsletter you have on the website. 

 5. CONFIGURE SIMPLENEWS

    Configure Simplenews on the Simplenews admin pages:
      Administer > Content Management > Newsletters > Settings.

    You can select the content type used by Simplenews for it's newsletters.
    Select the taxonomy term Simplenews uses to determine different newsletter
    series.

 6. CONFIGURE SIMPLENEWS BLOCK

    Configure the Simplenews block on the Block configuration page. You reach
    this page from Block admin page (Administer > Site building > Blocks). Click
    the 'Configure' link of the appropriate simplenews block.
 
    Permission "subscribe to newsletters" is required to view the subscription
    form in the simplenews block or to view the link to the subscription form.
    Links in the simplenews block (to previous issues, previous issues and
    RSS-feed) are only displayed to users who have "view links in block"
    privileges.

 7. SIMPLENEWS BLOCK THEMING

    More control over the content of simplenews blocks is possible using the  
    block theming. Additional variables are available for custom features:
      $block['subscribed'] is TRUE if user is subscribed to the newsletter
      $block['user'] is TRUE if the user is logged in (authenticated)
      $block['tid'] is the term id number of the newsletter

 8. Multilingual support
 
    Simplenews supports multilingual newsletters for node translation,
    multilingual taxonomy and url path prefixes.

    When translated newsletter issues are avialable subscribers revieve the
    newsletter in their preferred language (according to account setting).
    Translation module is required for newsletter translation.

    Multilingual taxonomy of 'Localized terms' and 'per language terms' is
    supported. 'per language vocabulary' is not supported.
    I18ntaxonomy module is required.
    Use 'Localized terms' for a multilingual newsletter. Taxonomy terms are
    translated and translated newsletters are each taged with the same
    (translated) term. Subscribers reveive the newsletter in the preferred
    language set in their account settings or in the site default language.
    Use 'per language terms' for mailinglists each with a different language.
    Newsletters of different language each have their own tag and own list of
    subscribers.
    
    Path prefixes are added to footer message according to the subscribers
    preferred language.

    The preferred language of anonymous users is set based on the interface
    language of the page they visit for subscription. Anonymous users can NOT
    change their preferred language. Users with an account on the site will be
    subscribed with the preferred language as set in their account settings.

 9. TIPS

    A subscription page is available at: /newsletter/subscriptions

SEND NEWSLETTERS WITH CRON
--------------------------

Cron jobs are required to send large mailing lists. Cron jobs can be triggered
by Poormanscron or any other cron mechanisme such as crontab.
If you have a medium or large size mailinglist (i.e. more than 500
subscribers) always use cron to send the newsletters.
  
When you use cron:
 * Check the 'Use cron to send newsletters' checkbox.
 * Set the 'Cron throttle' to the number of newsletters send per cron run.
   Too high values will lead to the warning message 'Attempting to re-run cron
   while it is already running'
    
When you do not use cron:
 * Uncheck the 'Use cron to send newsletters' checkbox.
   All newsletters will be send after saving a newsletter node with
   'Send newletter' selected.
    
These settings are found on the Newsletter Settings page under
  Mail backend options at:
  Administer > Content Management > Newsletters > Settings

COLLABORATION WITH OTHER MODULES
--------------------------------

 * Taxonomy
   The taxonomy module is required by Simplenews. Simplenews creates a
   'Newsletter' vocabulary which contains terms for each series of newsletters.
   Each newsletter node is tagged with one of the terms to group it into one of
   the newsletter series.

 * Mimemail (Currently under development for 6.x)
   By using Mimemail module simplenews can send HTML emails. Mime Mail takes
   care of the MIME-encoding of the email. 
   Mime Mail is also required to be able to send emails with attachments, both
   plain text and HTML emails.

 * Simplenews_template (Currently not available for 6.x)
   Simplenews Template provides a themable template with configurable header,
   footer and style. Header, footer and style are configurable for each
   newsletter independently.

 * Simplenews_roles (Currently not available for 6.x)
   A helper module for the Simplenews module which automatically populates a
   newsletter subscription list with users from specified roles.

 * Poormanscron
   Use Poormanscron if you don't have access to cron systems such as crontab.
   Read the 'Send newsletters with Cron' remarks above.

CREDITS
-------

Originally written by Dries Knapen.
Currently maintained by Sutharsan and RobRoy.
