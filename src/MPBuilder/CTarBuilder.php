<?php

namespace ProklUng\Module\Boilerplate\MPBuilder;

/**
 * Fork from bitrix.mpbuilder module.
 */
class CTarBuilder
{
    var $gzip;
    var $file;
    var $err = [];
    var $res;
    var $Block = 0;
    var $path;
    var $ReadBlockMax = 2000;
    var $ReadBlockCurrent = 0;
    var $header = null;
    var $ArchiveSizeMax;
    var $lang = '';
    /**
     * @var false|int|mixed
     */
    private $ArchiveSizeCurrent;

    const BX_EXTRA = 'BX0000';

    ##############
    # WRITE
    # {

    function openWrite($file)
    {
        if (!isset($this->gzip) && (substr($file, -3)=='.gz' || substr($file, -4)=='.tgz')) {
            $this->gzip = true;
        }

        if ($this->ArchiveSizeMax > 0) {
            while (file_exists($file1 = $this->getNextName($file))) {
                $file = $file1;
            }

            $size = 0;
            if (($size = $this->getArchiveSize($file)) >= $this->ArchiveSizeMax) {
                $file = $file1;
                $size = 0;
            }
            $this->ArchiveSizeCurrent = $size;
        }
        return $this->open($file, 'a');
    }

    function createEmptyGzipExtra($file)
    {
        if (file_exists($file)) {
            return false;
        }

        if (!($f = gzopen($file, 'wb'))) {
            return false;
        }
        gzwrite($f, '');
        gzclose($f);

        $data = file_get_contents($file);

        if (!($f = fopen($file, 'w'))) {
            return false;
        }

        $ar = unpack('A3bin0/A1FLG/A6bin1', substr($data, 0, 10));
        if ($ar['FLG'] != 0) {
            return $this->Error('Error writing extra field: already exists');
        }

        $EXTRA = chr(0).chr(0).chr(strlen(self::BX_EXTRA)).chr(0).self::BX_EXTRA;
        fwrite($f, $ar['bin0'].chr(4).$ar['bin1'].chr(strlen($EXTRA)).chr(0).$EXTRA.substr($data, 10));
        fclose($f);
        return true;
    }

    function writeBlock($str)
    {
        $l = strlen($str);
        if ($l!=512) {
            return $this->Error('TAR_WRONG_BLOCK_SIZE'.$l);
        }

        if ($this->ArchiveSizeMax && $this->ArchiveSizeCurrent >= $this->ArchiveSizeMax) {
            $file = $this->getNextName();
            $this->close();

            if (!$this->open($file, $this->mode)) {
                return false;
            }

            $this->ArchiveSizeCurrent = 0;
        }

        if ($res = $this->gzip ? gzwrite($this->res, $str) : fwrite($this->res, $str)) {
            $this->Block++;
            $this->ArchiveSizeCurrent+=512;
        }

        return $res;
    }

    function writeHeader($ar)
    {
        $header0 = pack('a100a8a8a8a12a12', $ar['filename'], decoct($ar['mode']), decoct($ar['uid']), decoct($ar['gid']), decoct($ar['size']), decoct($ar['mtime']));
        $header1 = pack('a1a100a6a2a32a32a8a8a155', $ar['type'], '', '', '', '', '', '', '', $ar['prefix']);

        $checksum = pack('a8', decoct($this->checksum($header0.'        '.$header1)));
        $header = pack('a512', $header0.$checksum.$header1);
        return $this->writeBlock($header) || $this->Error('TAR_ERR_WRITE_HEADER');
    }

    function addFile($f)
    {
        $f = str_replace('\\', '/', $f);
        $path = substr($f, strlen($this->path) + 1);
        if ($path == '') {
            return true;
        }
        if (strlen($path)>512) {
            return $this->Error('TAR_PATH_TOO_LONG', htmlspecialcharsbx($path));
        }

        $ar = array();

        if (is_dir($f)) {
            $ar['type'] = 5;
            $path .= '/';
        } else {
            $ar['type'] = 0;
        }

        $info = stat($f);
        if ($info) {
            if ($this->ReadBlockCurrent == 0) { // read from start
                $ar['mode'] = 0777 & $info['mode'];
                $ar['uid'] = $info['uid'];
                $ar['gid'] = $info['gid'];
                $ar['size'] = $ar['type']==5 ? 0 : $info['size'];
                $ar['mtime'] = $info['mtime'];


                if (strlen($path)>100) { // Long header
                    $ar0 = $ar;
                    $ar0['type'] = 'L';
                    $ar0['filename'] = '././@LongLink';
                    $ar0['size'] = strlen($path);
                    if (!$this->writeHeader($ar0)) {
                        return false;
                    }
                    $path .= str_repeat(chr(0), 512 - strlen($path));

                    if (!$this->writeBlock($path)) {
                        return false;
                    }
                    $ar['filename'] = substr($path, 0, 100);
                } else {
                    $ar['filename'] = $path;
                }

                if (!$this->writeHeader($ar)) {
                    return false;
                }
            }

            if ($ar['type']==0 && $info['size']>0) { // File
                if (!($rs = fopen($f, 'rb'))) {
                    return $this->Error('TAR_ERR_FILE_READ', htmlspecialcharsbx($f));
                }

                if ($this->ReadBlockCurrent) {
                    fseek($rs, $this->ReadBlockCurrent * 512);
                }

                $i = 0;
                while (!feof($rs) && ('' !== $str = fread($rs, 512))) {
                    $this->ReadBlockCurrent++;
                    if (feof($rs) && ($l = strlen($str)) && $l < 512) {
                        $str .= str_repeat(chr(0), 512 - $l);
                    }

                    if (!$this->writeBlock($str)) {
                        fclose($rs);
                        return $this->Error('TAR_ERR_FILE_WRITE', htmlspecialcharsbx($f));
                    }

                    if ($this->ReadBlockMax && ++$i >= $this->ReadBlockMax) {
                        fclose($rs);
                        return true;
                    }
                }
                fclose($rs);
                $this->ReadBlockCurrent = 0;
            }
            return true;
        } else {
            return $this->Error('TAR_ERR_FILE_NO_ACCESS', htmlspecialcharsbx($f));
        }
    }

    # }
    ##############

    ##############
    # BASE
    # {
    function open($file, $mode = 'r')
    {
        $this->file = $file;
        $this->mode = $mode;

        if ($this->gzip) {
            if (!function_exists('gzopen')) {
                return $this->Error('TAR_NO_GZIP');
            } else {
                if ($mode == 'a' && !file_exists($file) && !$this->createEmptyGzipExtra($file)) {
                    return false;
                }
                $this->res = gzopen($file, $mode.'b');
            }
        } else {
            $this->res = fopen($file, $mode.'b');
        }

        return $this->res;
    }

    function close()
    {
        if ($this->gzip) {
            gzclose($this->res);

            if ($this->mode == 'a') {
                $f = fopen($this->file, 'rb+');
#               fseek($f, -4, SEEK_END);
                fseek($f, 18);
                fwrite($f, pack('V', $this->ArchiveSizeCurrent));
                fclose($f);
            }
        } else {
            fclose($this->res);
        }
    }

    function getNextName($file = '')
    {
        if (!$file) {
            $file = $this->file;
        }
        static $CACHE;
        $c = &$CACHE[$file];

        if (!$c) {
            $l = strrpos($file, '.');
            $num = substr($file, $l+1);
            if (is_numeric($num)) {
                $file = substr($file, 0, $l+1).++$num;
            } else {
                $file .= '.1';
            }
            $c = $file;
        }
        return $c;
    }

    function checksum($str)
    {
        static $CACHE;
        $checksum = &$CACHE[md5($str)];
        if (!$checksum) {
            for ($i = 0; $i < 512; $i++) {
                if ($i>=148 && $i<156) {
                    $checksum += 32; // ord(' ')
                } else {
                    $checksum += ord($str[$i]);
                }
            }
        }
        return $checksum;
    }

    function getArchiveSize($file = '')
    {
        if (!$file) {
            $file = $this->file;
        }
        static $CACHE;
        $size = &$CACHE[$file];

        if (!$size) {
            if (!file_exists($file)) {
                $size = 0;
            } else {
                if ($this->gzip) {
                    $f = fopen($file, 'rb');
                    fseek($f, 18);
                    $readed = unpack('V', fread($f, 4));

                    $size = end($readed);
                    fclose($f);
                } else {
                    $size = filesize($file);
                }
            }
        }
        return $size;
    }

    function Error($err_code, $str = '')
    {
        $this->err[] = self::GetMessage($err_code).' '.$str;
        return false;
    }

    function xmkdir($dir)
    {
        if (!file_exists($dir)) {
            $upper_dir = dirname($dir);
            if (!file_exists($upper_dir) && !self::xmkdir($upper_dir)) {
                return false;
            }

            return mkdir($dir);
        }

        return is_dir($dir);
    }

    function GetMessage($code)
    {
        static $arLang;

        if (!$arLang) {
            $arLang = array(
                'TAR_WRONG_BLOCK_SIZE' => 'Wrong block size: ',
                'TAR_ERR_FORMAT' => 'Archive is corrupted, wrong block: ',
                'TAR_EMPTY_FILE' => 'Filename is empty, wrong block: ',
                'TAR_ERR_CRC' => 'Checksum error on file: ',
                'TAR_ERR_FOLDER_CREATE' => 'Can\'t create folder: ',
                'TAR_ERR_FILE_CREATE' => 'Can\'t create file: ',
                'TAR_ERR_FILE_OPEN' => 'Can\'t open file: ',
                'TAR_ERR_FILE_SIZE' => 'File size is wrong: ',
                'TAR_ERR_WRITE_HEADER' => 'Error writing header',
                'TAR_PATH_TOO_LONG' => 'Path is too long: ',
                'TAR_ERR_FILE_READ' => 'Error reading file: ',
                'TAR_ERR_FILE_WRITE' => 'Error writing file: ',
                'TAR_ERR_FILE_NO_ACCESS' => 'No access to file: ',
                'TAR_NO_GZIP' => 'Function &quot;gzopen&quot; is not available',
            );
        }
        return $arLang[$code];
    }
}
