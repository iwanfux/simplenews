#
# Table structure for table `sn_subscriptions`
#

CREATE TABLE sn_subscriptions (
  snid SERIAL,
  a_status smallint NOT NULL default '0',
  s_status smallint NOT NULL default '0',
  mail varchar(64) NOT NULL default '',
  uid integer NOT NULL default '0',
  PRIMARY KEY  (snid)
) TABLESPACE drupal_data;

#
# Table structure for table `sn_newsletters`
#

CREATE TABLE sn_newsletters (
  nid integer NOT NULL default '0',
  tid integer NOT NULL default '0',
  s_status smallint NOT NULL default '0',
  s_format varchar(8) NOT NULL default '',
  priority smallint NOT NULL default '0',
  receipt smallint NOT NULL default '0',
  PRIMARY KEY  (nid)
) TABLESPACE drupal_data;

#
# Table structure for table `sn_snid_tid`
#

CREATE TABLE sn_snid_tid (
  snid integer NOT NULL default '0',
  tid integer NOT NULL default '0',
  PRIMARY KEY  (snid,tid)
) TABLESPACE drupal_data;

