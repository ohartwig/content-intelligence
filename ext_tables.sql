CREATE TABLE tx_contentintelligence_analysis_result (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    page_uid int(11) unsigned DEFAULT '0' NOT NULL,
    language_uid int(11) DEFAULT '0' NOT NULL,
    analyzer_identifier varchar(100) DEFAULT '' NOT NULL,
    score float DEFAULT '0' NOT NULL,
    details mediumtext,
    suggestions mediumtext,
    analyzed_at int(11) unsigned DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY page_uid (page_uid, language_uid),
    KEY analyzer (analyzer_identifier)
);

CREATE TABLE tx_contentintelligence_audit_entry (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    page_uid int(11) unsigned DEFAULT '0' NOT NULL,
    content_uid int(11) unsigned DEFAULT '0' NOT NULL,
    table_name varchar(100) DEFAULT '' NOT NULL,
    field_name varchar(100) DEFAULT '' NOT NULL,
    origin varchar(20) DEFAULT 'human' NOT NULL,
    ai_provider varchar(50) DEFAULT '' NOT NULL,
    ai_model varchar(100) DEFAULT '' NOT NULL,
    prompt_hash varchar(64) DEFAULT '' NOT NULL,
    reviewed_by int(11) unsigned DEFAULT '0' NOT NULL,
    reviewed_at int(11) unsigned DEFAULT '0' NOT NULL,
    created_at int(11) unsigned DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY page_uid (page_uid),
    KEY origin (origin)
);

CREATE TABLE pages (
    tx_contentintelligence_readiness_score float DEFAULT '0' NOT NULL,
    tx_contentintelligence_last_analysis int(11) unsigned DEFAULT '0' NOT NULL,
    tx_contentintelligence_last_review int(11) unsigned DEFAULT '0' NOT NULL
);
