<?php
/**
 * Helpers Currency Class
 * 
 * @package     magicoli/opensim-helpers
 * @author      Gudule Lapointe <gudule@speculoos.world>
 * @link            https://github.com/magicoli/opensim-helpers
 * @license     AGPLv3
 */

class OpenSim_Currency {
    private static $enabled;
    private static $db;
    private static $db_creds;
    private static $provider;
    private static $module;
    private static $url;

    public function __construct() {
        $this->init();
    }

    private static function disable() {
        self::$enabled = false;
        self::$db = false; // Reset database connection
        self::$url = false; // Reset URL
    }

    private static function init() {
        self::$provider = Engine_Settings::get('engine.Economy.Provider');
        if( empty(self::$provider) ) {
            error_log('[DEBUG] ' . __METHOD__ . ' No Economy provider');
            // No provider set, disable the currency helper
            self::disable();
            return;
        }
        
        $base_url = Engine_Settings::get('robust.GridInfoService.economy', Helpers::url());
        if( empty($base_url) ) {
            error_log('[ERROR] ' . __METHOD__ . ' Could not determine currency or helpers URL');
            self::disable();
            return;
        }
        self::$url = rtrim($base_url, '/') . '/currency.php';

        error_log('[DEBUG] ' . __METHOD__ . ' Economy provider: ' . self::$provider);

        switch (self::$provider) {
            case '':
                // No provider set, disable the currency helper
                self::disable();
                return false;
            case 'free':
                self::$module = 'BetaGridLikeMoneyModule';
                // TODO: Not sure we need db for free transactions, double check
                self::$db_creds = Engine_Settings::get(
                    'robust.DatabaseService.ConnectionString', 
                    false
                );
                break;
            case 'gloebit':
                self::$module = 'Gloebit';
                // Use Gloebit DB if set, fallback to Robust DB
                self::$db_creds = Engine_Settings::get(
                    'opensim.Gloebit.GLBSpecificConnectionString',
                    Engine_Settings::get(
                        'robust.DatabaseService.ConnectionString', 
                        false
                    )
                );
                break;
            case 'podex':
            case 'moneyserver':
                self::$module = 'DTLNSLMoneyModule';
                self::$db_creds = Engine_Settings::get(
                    'moneyserver.MySql',
                    Engine_Settings::get(
                        'robust.DatabaseService.ConnectionString', 
                        false
                    )
                );
            default:
                // Not implemented, disabled by default
                self::disable();
                return false;
        }


        self::db(); // Initialize the search database connection
        if ( ! self::$db ) {
            error_log( '[ERROR] Failed to connect to the search database.' );
            self::disable();
            return;
        }

        // If nothing above has set Enabled to false, we are good to set it to true.
        if( self::$enabled !== false ) {
            self::$enabled = true;
        }
    }

    public static function url() {
        if( self::$url === null ) {
            self::init();
        }
        return self::$url;
    }

    public static function enabled() {
        if( self::$enabled === null ) {
            self::init();
        }
        return self::$enabled;
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

        if(! self::$db_creds ) {
            self::disable();
            return false;
        }
        
        if (self::$db_creds) {
            self::$db = new OpenSim_Database(self::$db_creds);
        } else {
            self::$db = OpenSim_Robust::db(); // Fallback to Robust database if SearchDB not configured
        }

        if (self::$db) {
            return self::$db;
        }

        error_log('[ERROR] ' . __METHOD__ . ' Database connection failed');
        self::$db = false; // Set to false if connection fails

        return self::$db;
    }

    public static function process() {
        if( ! self::enabled() ) {
            return array(
                'success' => false,
                'errorMessage' => 'Currency helper is not enabled',
                'errorURI' => self::$url,
                'error_code' => 503
            );
        }
            
        $summary = [];
        return osSuccess('Currency request processed', $summary);
    }

}
