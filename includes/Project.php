<?php
class Project
{
    private const BASE_NAME = "ENLIXO Tutor";
    private const BASE_URL = "https://enlixotutor.com";
    private const INSTITUTE_LOGO = "common/enlixo-dark.png";
    private const SUB_FOLDER_ASSETS_PATH = "../assets";
    private const SUB_FOLDER_INCLUDES_PATH = "../includes";
    private const SUB_FOLDER_API_ASSETS_PATH = "../../assets";
    private const VERSION = "1.0.8";

    public static function getBaseName()
    {
        return self::BASE_NAME;
    }
    public static function getBaseUrl()
    {
        return self::BASE_URL;
    }

    public static function getInstituteLogo()
    {
        return self::INSTITUTE_LOGO;
    }

    public static function getVersion()
    {
        return self::VERSION;
    }

    public static function getAssetsPath()
    {
        return self::SUB_FOLDER_ASSETS_PATH;
    }

    public static function getApiAssetsPath()
    {
        return self::SUB_FOLDER_API_ASSETS_PATH;
    }

    public static function getIncludesPath()
    {
        return self::SUB_FOLDER_INCLUDES_PATH;
    }

    public static function getPortalBasePath()
    {
        return 'Student Portal |' . self::BASE_NAME;
    }

    public static function getParentBasePath()
    {
        return 'Parent Dashboard |' . self::BASE_NAME;
    }

    // Bunny Stream settings come from config/config.php (env vars BUNNY_LIBRARY_ID / BUNNY_API_KEY,
    // or config/config.local.php). Never put the key in this file: it is committed to git.
    public static function getBunnyLibraryId()
    {
        return (string) (self::bunnyConfig()['library_id'] ?? '');
    }

    public static function getBunnyApiKey()
    {
        return (string) (self::bunnyConfig()['api_key'] ?? '');
    }

    private static function bunnyConfig()
    {
        static $bunny = null;
        if ($bunny === null) {
            $config = require dirname(__DIR__) . '/config/config.php';
            $bunny = is_array($config['bunny'] ?? null) ? $config['bunny'] : [];
        }
        return $bunny;
    }
}
