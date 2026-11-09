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
    private const BUNNY_LIBRARY_ID = "594208";
    private const BUNNY_API_KEY   = "ba6ab531-6fb3-4889-811fb7c1c0a4-62a3-4361";

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

    public static function getBunnyLibraryId()
    {
        return self::BUNNY_LIBRARY_ID;
    }

    public static function getBunnyApiKey()
    {
        return self::BUNNY_API_KEY;
    }
}
