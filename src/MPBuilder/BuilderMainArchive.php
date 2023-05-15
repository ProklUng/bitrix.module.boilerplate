<?php

namespace ProklUng\Module\Boilerplate\MPBuilder;

use Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentOutOfRangeException;
use Bitrix\Main\Text\Encoding;
use CBXArchive;
use IBXArchive;

/**
 * Fork from bitrix.mpbuilder module.
 */
class BuilderMainArchive
{
    /**
     * Полный архив.
     *
     * @param string $module_id ID модуля.
     * @param string $version   Версия.
     *
     * @return string
     */
    public static function build(string $module_id, string $version = ''): string
    {
        $strError = '';
        $m_dir = $_SERVER['DOCUMENT_ROOT'].'/local/modules/'.$module_id;

        include($m_dir.'/install/version.php');

        if ($version) {
            $f = $m_dir.'/install/version.php';
            if (!file_put_contents(
                $f,
                '<'.'?'."\n".
                '$arModuleVersion = array('."\n".
                '	"VERSION" => "'.EscapePHPString($version).'",'."\n".
                '	"VERSION_DATE" => "'.date('Y-m-d H:i:s').'"'."\n".
                ');'."\n".
                '?'.'>'
            )) {
                $strError .= ('NE UDALOSQ ZAPISATQ'.$f).'<br>';
            }
        }

        if (is_dir($tmp = $_SERVER['DOCUMENT_ROOT'].BX_ROOT.'/tmp/'.$module_id)) {
            FileHelper::BuilderRmDir($tmp);
        }

        mkdir($tmp.'/.last_version', BX_DIR_PERMISSIONS, true);

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('ISO-8859-1');
        }

        $tar = new CTarBuilder();
        $tar->path = $tmp;
        if (!$tar->openWrite($f = $tmp.'/.last_version.tar.gz')) {
            $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_OTKRYTQ_F').$f.'<br>';
        } else {
            $ar = FileHelper::BuilderGetFiles($m_dir, array('.svn', '.hg', '.git'), true);
            foreach ($ar as $file) {
                $from = $m_dir.$file;
                $to = $tmp.'/.last_version'.$file;

                if (false === $str = file_get_contents($from)) {
                    $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_PROCITATQ').$from.'<br>';
                } else {
                    if (substr($file, -4) == '.php' && static::GetStringCharset($str) == 'utf8') {
                        $str = Encoding::convertEncoding($str, 'utf8', 'cp1251');
                    }

                    if (!file_exists($dir = dirname($to))) {
                        mkdir($dir, BX_DIR_PERMISSIONS, true);
                    }

                    if (false === file_put_contents($to, $str)) {
                        $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_SOHRANITQ').$to.'<br>';
                    } else {
                        $tar->addFile($to);
                    }
                }
            }
            $tar->close();

            $distDir = $_SERVER['DOCUMENT_ROOT'].'/bitrix/dist';
            if (!file_exists($distDir)) {
                @mkdir($distDir);
            } else {
                static::recursiveDel($distDir);
            }

            $path = $distDir.'/.last_version.tar.gz';

            copy($tmp.'/.last_version.tar.gz', $path);
            static::recurse_copy($tmp.'/.last_version', $distDir.'/.last_version');

            static::createUpdateArchive($distDir.'/.last_version');
        }

        return $strError;
    }

    /**
     * @param string $strUpdaterDir
     * @param string $filename
     * @return void
     */
    public static function createUpdateArchive(string $strUpdaterDir, string $filename = '.last_version.zip')
    {
        $strDir = $_SERVER['DOCUMENT_ROOT'].'/bitrix/dist';
        if (!is_dir($strDir)) {
            mkdir($strDir, BX_DIR_PERMISSIONS, true);
        }

        $strArcFileName = $strDir.'/'. $filename;

        @unlink($strArcFileName);

        require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/classes/general/tar_gz.php');

        $obArc = CBXArchive::GetArchive($strArcFileName, 'ZIP');
        if ($obArc instanceof IBXArchive) {
            $obArc->SetOptions(array(
                'COMPRESS' => true,
                'ADD_PATH' => false,
                'REMOVE_PATH' => dirname($strUpdaterDir),
                'CHECK_PERMISSIONS' => false,
            ));
            $arPackFiles = array($strUpdaterDir);
            $obArc->pack($arPackFiles, '');
        }
        unset($obArc);
    }

    /**
     * @param string $dir
     * @return void
     */
    public static function recursiveDel(string $dir)
    {
        if (!@file_exists($dir)) {
            return;
        }

        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $ri = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($ri as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
    }

    /**
     * Авторасчет следующей версии модуля.
     *
     * @param string $moduleId
     *
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function nextVersionModule(string $moduleId): string
    {
        include($_SERVER['DOCUMENT_ROOT'].'/local/modules/'.$moduleId.'/install/version.php');

        /**
         * @var array $arModuleVersion
         */

        $latestVersion = Helper::getLatestVersionModule();
        if (!$latestVersion) {
            $latestVersion = $arModuleVersion['VERSION'];
        }

        return (string)static::VersionUp($latestVersion);
    }

    /**
     * Собрать версию модуля.
     *
     * @param string $module_id
     * @param string $version
     *
     * @return string
     * @throws ArgumentNullException
     * @throws ArgumentOutOfRangeException
     */
    public static function buildVersion(string $module_id, string $version = ''): string
    {
        global $APPLICATION;

        $strError = '';

        $m_dir = $_SERVER['DOCUMENT_ROOT'].'/local/modules/'.$module_id;

        if (!$version) {
            $version = static::nextVersionModule($module_id);
        }

        $timeUpdate = date('Y-m-d H:i:s');

        $strVersion =
            '<'.'?'."\n".
            '$arModuleVersion = array('."\n".
            '	"VERSION" => "'.EscapePHPString($version).'",'."\n".
            '	"VERSION_DATE" => "'.$timeUpdate.'"'."\n".
            ');'."\n".
            '?'.'>';

        $tmp_dir = $_SERVER['DOCUMENT_ROOT'].BX_ROOT.'/tmp/'.$module_id;

        if (is_dir($tmp_dir)) {
            FileHelper::BuilderRmDir($tmp_dir);
        }

        @mkdir($tmp_dir.'/'.$version, BX_DIR_PERMISSIONS, true);

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('ISO-8859-1');
        }

        $tar = new CTarBuilder;
        $tar->path = $tmp_dir;
        if (!$tar->openWrite($f = $tmp_dir.'/'.$version.'.tar.gz')) {
            $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_OTKRYTQ_F').$f.'<br>';
        } else {
            rename($m_dir.'/install/version.php', $m_dir.'/install/_version.php');
            if (!file_put_contents($f = $m_dir.'/install/version.php', $strVersion)) {
                $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_ZAPISATQ').$f.'<br>';
            }

            include($m_dir.'/install/version.php');

            $lastDateUpdateModule = Helper::getDateLatestUpdateModule();
            /**
             * @var array $arModuleVersion
             */
            $ar = FileHelper::BuilderGetFiles($m_dir, array(), true);
            $time_from = strtotime($lastDateUpdateModule ?: $arModuleVersion['VERSION_DATE']);
            foreach ($ar as $file) {
                $from = $m_dir.$file;
                $to = $tmp_dir.'/'.$version.$file;

                if (filemtime($from) < $time_from) {
                    continue;
                }

                if (false === $str = file_get_contents($from)) {
                    $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_PROCITATQ').$from.'<br>';
                } else {
                    if (substr($file, -4) == '.php' && static::GetStringCharset($str) == 'utf8') {
                        $str = $APPLICATION->ConvertCharset($str, 'utf8', 'cp1251');
                    }

                    if (!file_exists($dir = dirname($to))) {
                        mkdir($dir, BX_DIR_PERMISSIONS, true);
                    }

                    if (false === file_put_contents($to, $str)) {
                        $strError .= ('BITRIX_MPBUILDER_NE_UDALOSQ_SOHRANITQ').$to.'<br>';
                    } else {
                        $tar->addFile($to);
                    }
                }
            }

            $tar->close();

            $distDir = $_SERVER['DOCUMENT_ROOT'].'/bitrix/dist';
            if (!file_exists($distDir)) {
                @mkdir($distDir);
            }

            static::recursiveDel($distDir.'/'.$version);
            static::recurse_copy($tmp_dir.'/'.$version, $distDir.'/'.$version);

            static::createUpdateArchive($distDir.'/'.$version, $version . '.zip');

            Helper::setDateLatestUpdateModule($timeUpdate);
            Helper::setLatestVersionModule($version);
        }

        return $strError;
    }

    /**
     * @param $src
     * @param $dst
     * @return void
     */
    public static function recurse_copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src.'/'.$file)) {
                    static::recurse_copy($src.'/'.$file, $dst.'/'.$file);
                } else {
                    copy($src.'/'.$file, $dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * @param $str
     * @return string
     */
    public static function GetStringCharset($str)
    {
        global $APPLICATION;
        if (preg_match("/[\xe0\xe1\xe3-\xff]/", $str)) {
            return 'cp1251';
        }
        $str0 = $APPLICATION->ConvertCharset($str, 'utf8', 'cp1251');
        if (preg_match("/[\xe0\xe1\xe3-\xff]/", $str0, $regs)) {
            return 'utf8';
        }

        return 'ascii';
    }

    /**
     * @param $num
     * @return mixed|string
     */
    public static function VersionUp($num)
    {
        $ar = explode('.', $num);
        if (count($ar) == 3) {
            return $ar[0].'.'.$ar[1].'.'.(++$ar[2]);
        }

        return $num;
    }
}
