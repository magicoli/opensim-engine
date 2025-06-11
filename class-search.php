<?php
/**
 * W4OS Search Engine
 * 
 * Core search functionality for avatars, regions, events, etc.
 */

class OpenSim_Search
{
    private static $instance = null;
    private static $db;
    private static $db_creds = null;
    
    private static $events_table;
    private static $regions_table;
    private static $hypevents_url;
    private function __construct()
    {
        // Initialize database connection
        self::db();

        // Set custom table names from settings
        self::$events_table = Engine_Settings::get('engine.Search.SearchEventsTable', 'events');
        self::$regions_table = Engine_Settings::get('engine.Search.SearchRegionsTable', 'regions');

        if ( ! $SearchDB->tables_exist( array( SEARCH_REGION_TABLE, 'parcels', 'parcelsales', 'allparcels', 'objects', 'popularplaces', SEARCH_TABLE_EVENTS, 'classifieds', 'hostsregister' ) ) ) {
            error_log( 'Creating missing OpenSimSearch tables in ' . SEARCH_DB_NAME );
            ossearch_db_tables( $SearchDB );
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }    
        return self::$instance;
    }    
    
    public static function db() {
        if( self::$db ) {
            return self::$db;
        }
        if( self::$db === false ) {
            // Don't check again if already failed
            return false;
        }

        self::$db = false; // Reset to false to avoid multiple checks

        // Get SearchDB credentials from settings, fallback to main robust db
        self::$db_creds = Engine_Settings::get('engine.Search.SearchDB');

        if (self::$db_creds) {
            self::$db = new OpenSim_Database(self::$db_creds);
        } else {
            self::$db = OpenSim_Robust::db(); // Fallback to main database if SearchDB not configured
        }

        if (self::$db->is_connected()) {
            return self::$db;
        } else {
            error_log('[ERROR] ' . __METHOD__ . ' Database connection failed: ' . self::$db->get_error());
            self::$db = false; // Set to false if connection fails
        }

        return self::$db;
    }

    private function check_tables() {
        $db = self::db();
        if ( ! $db->connected ) {
            return false;
        }

        $table_events = self::$events_table;
        $tables_regions = self::$regions_table;

        $query = $db->prepare(
            "CREATE TABLE IF NOT EXISTS `allparcels` (
                `regionUUID` char(36) NOT NULL,
                `parcelname` varchar(255) NOT NULL,
                `ownerUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
                `groupUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
                `landingpoint` varchar(255) NOT NULL,
                `parcelUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
                `infoUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
                `parcelarea` int(11) NOT NULL,
                `gatekeeperURL` varchar(255),
                PRIMARY KEY  (`parcelUUID`),
                KEY `regionUUID` (`regionUUID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `classifieds` (
                `classifieduuid` char(36) NOT NULL,
                `creatoruuid` char(36) NOT NULL,
                `creationdate` int(20) NOT NULL,
                `expirationdate` int(20) NOT NULL,
                `category` varchar(20) NOT NULL,
                `name` varchar(255) NOT NULL,
                `description` text NOT NULL,
                `parceluuid` char(36) NOT NULL,
                `parentestate` int(11) NOT NULL,
                `snapshotuuid` char(36) NOT NULL,
                `simname` varchar(255) NOT NULL,
                `posglobal` varchar(255) NOT NULL,
                `parcelname` varchar(255) NOT NULL,
                `classifiedflags` int(8) NOT NULL,
                `priceforlisting` int(5) NOT NULL,
                PRIMARY KEY  (`classifieduuid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `$table_events` (
                `owneruuid` char(36) NOT NULL,
                `name` varchar(255) NOT NULL,
                `eventid` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `creatoruuid` char(36) NOT NULL,
                `category` int(2) NOT NULL,
                `description` text NOT NULL,
                `dateUTC` int(10) NOT NULL,
                `duration` int(10) NOT NULL,
                `covercharge` tinyint(1) NOT NULL,
                `coveramount` int(10) NOT NULL,
                `simname` varchar(255) NOT NULL,
                `parcelUUID` char(36) NOT NULL,
                `globalPos` varchar(255) NOT NULL,
                `eventflags` int(1) NOT NULL,
                `gatekeeperURL` varchar(255),
                `landingpoint` varchar(35) DEFAULT NULL, -- JOpenSim compatibility
                `parcelName` varchar(255) DEFAULT NULL, -- JOpenSim compatibility
                `mature` enum('true','false') NOT NULL, -- JOpenSim compatibility
                PRIMARY KEY (`eventid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

            CREATE TABLE IF NOT EXISTS `hostsregister` (
                `host` varchar(255) NOT NULL,
                `port` int(5) NOT NULL,
                `register` int(10) NOT NULL,
                `nextcheck` int(10) NOT NULL,
                `checked` tinyint(1) NOT NULL,
                `failcounter` int(10) NOT NULL,
                `gatekeeperURL` varchar(255),
                PRIMARY KEY (`host`,`port`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `objects` (
                `objectuuid` char(36) NOT NULL,
                `parceluuid` char(36) NOT NULL,
                `location` varchar(255) NOT NULL,
                `name` varchar(255) NOT NULL,
                `description` varchar(255) NOT NULL,
                `regionuuid` char(36) NOT NULL default '',
                `gatekeeperURL` varchar(255),
                PRIMARY KEY  (`objectuuid`,`parceluuid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `parcels` (
                `parcelUUID` char(36) NOT NULL,
                `regionUUID` char(36) NOT NULL,
                `parcelname` varchar(255) NOT NULL,
                `landingpoint` varchar(255) NOT NULL,
                `description` varchar(255) NOT NULL,
                `searchcategory` varchar(50) NOT NULL,
                `build` enum('true','false') NOT NULL,
                `script` enum('true','false') NOT NULL,
                `public` enum('true','false') NOT NULL,
                `dwell` float NOT NULL default '0',
                `infouuid` varchar(36) NOT NULL default '',
                `mature` varchar(10) NOT NULL default 'PG',
                `gatekeeperURL` varchar(255),
                `imageUUID` char(36),
                PRIMARY KEY  (`regionUUID`,`parcelUUID`),
                KEY `name` (`parcelname`),
                KEY `description` (`description`),
                KEY `searchcategory` (`searchcategory`),
                KEY `dwell` (`dwell`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `parcelsales` (
                `regionUUID` char(36) NOT NULL,
                `parcelname` varchar(255) NOT NULL,
                `parcelUUID` char(36) NOT NULL,
                `area` int(6) NOT NULL,
                `saleprice` int(11) NOT NULL,
                `landingpoint` varchar(255) NOT NULL,
                `infoUUID` char(36) NOT NULL default '00000000-0000-0000-0000-000000000000',
                `dwell` int(11) NOT NULL,
                `parentestate` int(11) NOT NULL default '1',
                `mature` varchar(10) NOT NULL default 'PG',
                `gatekeeperURL` varchar(255),
                PRIMARY KEY  (`regionUUID`,`parcelUUID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `popularplaces` (
                `parcelUUID` char(36) NOT NULL,
                `name` varchar(255) NOT NULL,
                `dwell` float NOT NULL,
                `infoUUID` char(36) NOT NULL,
                `has_picture` tinyint(1) NOT NULL,
                `mature` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
                `gatekeeperURL` varchar(255),
                PRIMARY KEY  (`parcelUUID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

            CREATE TABLE IF NOT EXISTS `$table_regions` (
                `regionname` varchar(255) NOT NULL,
                `regionUUID` char(36) NOT NULL,
                `regionhandle` varchar(255) NOT NULL,
                `url` varchar(255) NOT NULL,
                `owner` varchar(255) NOT NULL,
                `owneruuid` char(36) NOT NULL,
                `gatekeeperURL` varchar(255),
                PRIMARY KEY  (`regionUUID`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
            "
        );

        $result = $query->execute();        
    }
}
