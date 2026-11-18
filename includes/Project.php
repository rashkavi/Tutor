<?php
class Project
{
    private const BASE_NAME = "ENLIXO Tutor";
    private const BASE_URL = "https://enlixotutor.com";
    private const INSTITUTE_LOGO = "common/enlixo-dark.png";
    private const SUB_FOLDER_ASSETS_PATH = "../assets";
    private const SUB_FOLDER_INCLUDES_PATH = "../includes";
    private const SUB_FOLDER_API_ASSETS_PATH = "../../assets";
    private const VERSION = "1.0.10";
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

    /**
     * The ?v= of every CSS / JS link: the release number + when a style or script file last changed. A file uploaded
     * again (or fixed after a cut-off upload) gets a new address, so browsers and host caches never keep the old copy.
     * The newest change time is looked up at most once a minute (cached in the temp folder).
     */
    public static function getVersion()
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }
        $root = dirname(__DIR__);
        $cache = rtrim(sys_get_temp_dir(), '/\\') . '/enlixo-asset-stamp-' . md5($root);
        $stamp = 0;
        if (is_file($cache) && filemtime($cache) > time() - 60) {
            $stamp = (int) @file_get_contents($cache);
        }
        if ($stamp === 0) {
            foreach (['assets/css', 'assets/js', 'assets/landing', 'tutor/js', 'portal/js', 'parent/js', 'admin/js'] as $dir) {
                if (!is_dir($root . '/' . $dir)) {
                    continue;
                }
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if (in_array(strtolower($f->getExtension()), ['css', 'js'], true)) {
                        $stamp = max($stamp, $f->getMTime());
                    }
                }
            }
            @file_put_contents($cache, (string) $stamp);
        }
        return $version = self::VERSION . ($stamp > 0 ? '.' . base_convert((string) $stamp, 10, 36) : '');
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
