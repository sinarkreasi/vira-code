<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer;

use Composer\Autoload\ClassLoader;

class InstalledVersions
{
    private static $installed;
    private static $canGetVendors;
    private static $installedByVendor = [];

    public static function getInstalledPackages()
    {
        return [];
    }

    public static function isInstalled(
        $packageName,
        $includeDevRequirements = true,
    ) {
        return false;
    }

    public static function getVersion($packageName)
    {
        return null;
    }

    public static function getPrettyVersion($packageName)
    {
        return null;
    }

    public static function getReference($packageName)
    {
        return null;
    }

    public static function getRootPackage()
    {
        return [
            "pretty_version" => "1.0.0",
            "version" => "1.0.0.0",
            "type" => "library",
            "install_path" => __DIR__ . "/../../",
            "aliases" => [],
            "reference" => null,
            "name" => "vira/vira-code",
            "dev" => false,
        ];
    }

    public static function getRawData()
    {
        return self::$installed;
    }

    public static function reload($data)
    {
        self::$installed = $data;
        self::$installedByVendor = [];
    }

    public static function getInstallPath($packageName)
    {
        return null;
    }

    public static function getAllRawData()
    {
        return [self::getRawData()];
    }
}
