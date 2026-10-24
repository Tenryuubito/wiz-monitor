CREATE TABLE tx_wizmonitor_project (
	title varchar(255) DEFAULT '' NOT NULL,
	base_url varchar(1024) DEFAULT '' NOT NULL,
	check_urls text,
	check_pages varchar(255) DEFAULT '' NOT NULL,
	expected_keyword varchar(255) DEFAULT '' NOT NULL,
	secret_encrypted text
);

CREATE TABLE tx_wizmonitor_check (
	uid int(11) unsigned NOT NULL auto_increment,
	project int(11) unsigned DEFAULT '0' NOT NULL,
	checked_at int(11) unsigned DEFAULT '0' NOT NULL,
	url varchar(1024) DEFAULT '' NOT NULL,
	reachable smallint(5) unsigned DEFAULT '0' NOT NULL,
	http_status smallint(5) unsigned DEFAULT '0' NOT NULL,
	response_time int(11) unsigned DEFAULT '0' NOT NULL,
	keyword_found smallint(5) DEFAULT '-1' NOT NULL,
	tls_expires_at int(11) unsigned DEFAULT '0' NOT NULL,
	failure_reason varchar(1024) DEFAULT '' NOT NULL,

	PRIMARY KEY (uid),
	KEY project_checked (project, checked_at),
	KEY checked_at (checked_at)
);

CREATE TABLE tx_wizmonitor_snapshot (
	uid int(11) unsigned NOT NULL auto_increment,
	project int(11) unsigned DEFAULT '0' NOT NULL,
	last_attempt_at int(11) unsigned DEFAULT '0' NOT NULL,
	last_success_at int(11) unsigned DEFAULT '0' NOT NULL,
	failure_reason varchar(1024) DEFAULT '' NOT NULL,
	payload mediumtext,
	error_cursor int(11) unsigned DEFAULT '0' NOT NULL,
	evaluation mediumtext,
	evaluated_at int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY project (project)
);

CREATE TABLE tx_wizmonitor_error (
	uid int(11) unsigned NOT NULL auto_increment,
	project int(11) unsigned DEFAULT '0' NOT NULL,
	fingerprint varchar(64) DEFAULT '' NOT NULL,
	level varchar(16) DEFAULT '' NOT NULL,
	channel varchar(255) DEFAULT '' NOT NULL,
	message text,
	exception_class varchar(255) DEFAULT '' NOT NULL,
	exception_code varchar(64) DEFAULT '' NOT NULL,
	file varchar(1024) DEFAULT '' NOT NULL,
	line int(11) unsigned DEFAULT '0' NOT NULL,
	request_url varchar(1024) DEFAULT '' NOT NULL,
	occurrences int(11) unsigned DEFAULT '0' NOT NULL,
	first_seen int(11) unsigned DEFAULT '0' NOT NULL,
	last_seen int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY project_fingerprint (project, fingerprint),
	KEY project_last_seen (project, last_seen)
);

CREATE TABLE tx_wizmonitor_incident (
	uid int(11) unsigned NOT NULL auto_increment,
	project int(11) unsigned DEFAULT '0' NOT NULL,
	category varchar(32) DEFAULT '' NOT NULL,
	status varchar(16) DEFAULT '' NOT NULL,
	findings mediumtext,
	opened_at int(11) unsigned DEFAULT '0' NOT NULL,
	updated_at int(11) unsigned DEFAULT '0' NOT NULL,
	resolved_at int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY project_category_open (project, category, resolved_at)
);
