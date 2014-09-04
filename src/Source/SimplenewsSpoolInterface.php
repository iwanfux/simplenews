<?php

/**
 * @file
 * Contains \Drupal\simplenews\Source\SimplenewsSpoolInterface.
 */

namespace Drupal\simplenews\Source;


/**
 * A Simplenews spool implementation is a factory for Simplenews sources.
 *
 * Their main functionility is to return a number of sources based on the passed
 * in array of mail spool rows. Additionally, it needs to return the processed
 * mail rows after a source was sent.
 *
 * @todo: Move spool functions into this interface.
 *
 * @ingroup spool
 */
interface SimplenewsSpoolInterface {

  /**
   * Initalizes the spool implementation.
   *
   * @param $spool_list
   *   An array of rows from the {simplenews_mail_spool} table.
   */
  function __construct($pool_list);

  /**
   * Returns a Simplenews source to be sent.
   *
   * A single source may represent any number of mail spool rows, e.g. by
   * addressing them as BCC.
   */
  function nextSource();

  /**
   * Returns the processed mail spool rows, keyed by the msid.
   *
   * Only rows that were processed while preparing the previously returned
   * source must be returned.
   *
   * @return
   *   An array of mail spool rows, keyed by the msid. Can optionally have set
   *   the following additional properties.
   *     - actual_nid: In case of content translation, the source node that was
   *       used for this mail.
   *     - error: FALSE if the prepration for this row failed. For example set
   *       when the corresponding node failed to load.
   *     - status: A simplenews spool status to indicate the status.
   */
  function getProcessed();
}