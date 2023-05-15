<?php

namespace ProklUng\Module\Boilerplate\MPBuilder;

/**
 * Fork from bitrix.mpbuilder module.
 */
class FileHelper
{
    /**
     * @param $path
     * @param $arFilter
     * @param $bAllFiles
     * @param $recursive
     *
     * @return array
     */
    public static function BuilderGetFiles($path, $arFilter = array(), $bAllFiles = false, $recursive = false)
    {
        static $len;
        if (!$recursive || !$len) {
            $len = strlen($path);
        }

        $retVal = array();
        if ($dir = opendir($path)) {
            while (false !== $item = readdir($dir)) {
                if (in_array($item, array_merge(array('.', '..', '.svn', '.hg', '.git', 'vendor', 'dist'), $arFilter))) {
                    continue;
                }
                if (is_dir($f = $path.'/'.$item)) {
                    $retVal = array_merge($retVal, static::BuilderGetFiles($f, $arFilter, $bAllFiles, true));
                } else {
                    if ($bAllFiles || substr($f, -4) == '.php') {
                        $retVal[] = str_replace('\\', '/', substr($f, $len));
                    }
                }
            }
            closedir($dir);
        }

        return $retVal;
    }

    /**
     * @param $path
     * @return void
     */
    public static function BuilderRmDir($path)
    {
        if (!is_dir($path)) {
            return;
        }
        $dir = opendir($path);
        while (false !== $item = readdir($dir)) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $f = $path.'/'.$item;
            if (is_dir($path.'/'.$item)) {
                static::BuilderRmDir($f);
            } else {
                unlink($f);
            }
        }
        closedir($dir);
        rmdir($path);
    }
}