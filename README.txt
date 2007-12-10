$Id$

------------
Description
------------

This module sends html or plain text newsletters to the subscription list. In the newsletter 
footer an unsubscribe link is provided. Subscription and unsubscription are managed through 
a block, a form or by the newsletter administrator on the module's admin pages.

Individual newsletters are grouped in newsletters by a newsletter taxonomy term. Newsletters 
can have a block with the ability of (un)subscription, listing of recent newsletters and an 
associated rss-feed. 

Send newsletters and not-sent newsletters are listed separately. The subscription list can be managed. 

Sending of large mailings can be managed by cron.

------------
Requirements
------------

- Drupal 5

- Taxonomy module should be enabled.

- For large mailing lists, cron is required.

- HTML-format newsletters and/or newsletters with file attachments require the mimemail module.

------------
Upgrading
------------

If you are upgrading from Drupal 4.6, please download the Drupal 4.7 version
and upgrade to that first. Then, upgrade to this Drupal 5 version from 
Drupal 4.7.

------------
Installation
------------

- Create a new directory "simplenews" in the sites/all/modules directory and place the
  entire contents of this simplenews folder in it.

- Enable the module on the Modules admin page:
    Administer > Site building > Modules

- Grant the proper access to user accounts at the Access control page:
    Administer > User management > Access control. 
  To enable users to (un)subscribe to a newsletter use the "subscribe to newsletters" 
  permission. This will enable the Simplenews block where the user can (un)subscribe 
  to a newsletter. 
  Use the "view links in block" permission to enable the display of previous newsletters 
  in the Simplenews block.

- Enable the Simplenews block on the Administer blocks page:
    Administer > Site building > Blocks.
  One block is available for each newsletter you have on the website. Note that multiple 
  newsletter blocks with subscription form does not work. This is a known bug in Drupal 5
  version of Simplenews. See http://drupal.org/node/121479

- Configure Simplenews on the Simplenews admin pages:
    Administer > Content Management > Newsletters > Settings.

- Configure the Simplenews block on the block configuration page. On the Block admin page
  (Administer > Site building > Blocks) click the Configure link of the appropriate
  simplenews block.
 
- Permission "subscribe to newsletters" is required to view the subscription form 
  in the simplenews block or to view the link to the subscription form.
  Links in the simplenews block (to previous issues, previous issues and RSS-feed) are only
  displayed to users who have "view links in block" privileges.

------------
Collaboration with other modules
------------

- Taxonomy
  The taxonomy module is required by Simplenews. Simplenews creates a 'Newsletter' vocabulary
  which contains terms for each series of newsletters. Each newsletter node is tagged with one
  of the terms to group it into one of the newsletter series.

- Mimemail
  By using Mimemail module simplenews can send HTML emails. Mime Mail takes care of the 
  MIME-encoding of the email. 
  Mime Mail is also required to be able to send emails with attachments, both plain text and 
  HTML emails.

- Simplenews_template
  Simplenews Template provides a themable template with configurable header, footer and style. 
  Header, footer and style are configurable for each newsletter independently.

- Simplenews_roles
  A helper module for the Simplenews module which automatically populates a newsletter 
  subscription list with users from specified roles.

- Category
  Simplenews and Category module are currently NOT COMPATIBLE. See http://drupal.org/node/115693

- Poormanscron
  Cron jobs are required to send large mailing lists. Cron jobs can be triggered by Poormanscron 
  or any other cron mechanisme such as crontab. Make shure that the number of newsletters send
  per cron run will not exceed the interval time between two cron jobs. The number of newsletter 
  per cron is set at:
    Administer > Content Management > Newsletters > Settings > General

------------
Credits
------------

Originally written by Dries Knapen.
Currently maintained by RobRoy and Sutharsan.
