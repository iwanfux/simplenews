<?php

if (!ini_get('safe_mode')) {
  set_time_limit(300);
}
require_once 'includes/bootstrap.inc';
drupal_bootstrap('full');

if (module_exist('simplenews')) {
  $sql = "CREATE TABLE {sn_snid_tid} (
  `snid` int(10) NOT NULL default '0',
  `tid` int(10) NOT NULL default '0',
  PRIMARY KEY  (`snid`,`tid`)
) TYPE=MyISAM";
  if ($result = db_query($sql)) {
    $error = 'Created db table: sn_snid_tid<br>';
    $tree = module_invoke('taxonomy', 'get_tree', simplenews_get_vid());
    if ($tree) {
      $result = db_query("SELECT snid FROM {sn_subscriptions}");
      while ($snid = db_fetch_object($result)) {
        foreach ($tree as $newsletter) {
          db_query("INSERT INTO {sn_snid_tid} (snid, tid) VALUES (%d, %d)", $snid->snid, $newsletter->tid);
        }
      }
    }
    $error .= 'Updated db table: sn_snid_tid<br>';
    $result = db_query('SELECT * FROM {permission} ORDER BY rid');
    while ($role = db_fetch_object($result)) {
      if (strstr($role->perm,'administer simplenews')) {
        $role->perm = str_replace('administer simplenews', 'administer newsletters', $role->perm);
        db_query('UPDATE {permission} SET perm = "%s" WHERE rid = %d', $role->perm, $role->rid);
      }
    }
    $error .= 'Updated db table: permission<br>';
    menu_rebuild();
    $error .= 'Update completed';
  }
  else {
    $error = 'Database error: could not create table sn_snid_tid. Update not performed';
  }
}
else {
  $error = 'Simplenews module not installed or not enabled. Update not performed';
}

print $error;

?>