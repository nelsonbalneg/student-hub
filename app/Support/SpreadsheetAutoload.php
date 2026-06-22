<?php

namespace App\Support;

use Composer\Autoload\ClassLoader;

class SpreadsheetAutoload
{
    public static function register(): void
    {
        $basePath = dirname(__DIR__, 2);
        $vendorPath = $basePath.DIRECTORY_SEPARATOR.'vendor';
        $loader = ClassLoader::getRegisteredLoaders()[$vendorPath] ?? null;

        if (! $loader instanceof ClassLoader) {
            return;
        }

        $loader->setClassMapAuthoritative(false);
        $loader->addPsr4(
            'Maatwebsite\\Excel\\',
            $vendorPath.'/maatwebsite/excel/src',
        );
        $loader->addPsr4(
            'PhpOffice\\PhpSpreadsheet\\',
            $vendorPath.'/phpoffice/phpspreadsheet/src/PhpSpreadsheet',
        );
    }
}
