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
    
    private function __construct()
    {
        // Search functionality will be moved here from existing files
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
}
