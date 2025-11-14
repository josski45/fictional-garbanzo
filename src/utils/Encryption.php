<?php

namespace JosskiTools\Utils;

/**
 * Encryption Class - XXTEA-like algorithm for HAR data decryption
 */
class Encryption {
    
    /**
     * Convert string to byte array
     */
    private static function staasttlB($str) {
        $bytes = [];
        for ($i = 0; $i < strlen($str); $i++) {
            $bytes[] = ord($str[$i]);
        }
        return $bytes;
    }
    
    /**
     * Convert data to byte object
     */
    private static function DtoBo($data) {
        if (is_string($data)) {
            return self::staasttlB($data);
        }
        return $data;
    }
    
    /**
     * Convert list to byte data
     */
    private static function llstotaBoD($list) {
        $result = [];
        for ($i = 0; $i < count($list); $i += 4) {
            $n = ($list[$i] & 0xFF) |
                 (($list[$i + 1] & 0xFF) << 8) |
                 (($list[$i + 2] & 0xFF) << 16) |
                 (($list[$i + 3] & 0xFF) << 24);
            // Convert to signed 32-bit integer
            if ($n > 0x7FFFFFFF) {
                $n = $n - 0x100000000;
            }
            $result[] = $n;
        }
        return $result;
    }
    
    /**
     * Convert data to byte list
     */
    private static function DsttDats($data, $includeLength = true) {
        $length = count($data);
        $result = [];
        
        for ($i = 0; $i < $length; $i++) {
            $val = $data[$i] & 0xFFFFFFFF;
            $result[] = $val & 0xFF;
            $result[] = ($val >> 8) & 0xFF;
            $result[] = ($val >> 16) & 0xFF;
            $result[] = ($val >> 24) & 0xFF;
        }
        
        if ($includeLength) {
            $dataLength = count($result);
            return array_slice($result, 0, $dataLength);
        }
        
        return $result;
    }
    
    /**
     * Convert byte list to string
     */
    private static function stlBatl($bytes) {
        $str = '';
        foreach ($bytes as $byte) {
            $str .= chr($byte & 0xFF);
        }
        return $str;
    }
    
    /**
     * Encrypt data using XXTEA-like algorithm
     */
    public static function encryptData($data, $key) {
        if (strlen($data) === 0) {
            return '';
        }
        
        $v = self::llstotaBoD(self::DtoBo($data));
        $k = self::llstotaBoD(self::DtoBo(str_pad($key, 16, $key)));
        $n = count($v);
        
        if ($n < 1) {
            return '';
        }
        
        $delta = 0x9E3779B9;
        $q = floor(6 + 52 / $n);
        $sum = 0;
        $z = $v[$n - 1];
        
        for ($i = 0; $i < $q; $i++) {
            $sum = ($sum + $delta) & 0xFFFFFFFF;
            $e = ($sum >> 2) & 3;
            
            for ($p = 0; $p < $n; $p++) {
                $y = $v[($p + 1) % $n];
                $mx = ((($z >> 5) ^ ($y << 2)) + (($y >> 3) ^ ($z << 4))) ^ 
                      (($sum ^ $y) + ($k[($p & 3) ^ $e] ^ $z));
                $mx = $mx & 0xFFFFFFFF;
                
                $v[$p] = ($v[$p] + $mx) & 0xFFFFFFFF;
                $z = $v[$p];
            }
        }
        
        return base64_encode(self::stlBatl(self::DsttDats($v, false)));
    }
    
    /**
     * Decrypt data using XXTEA-like algorithm
     */
    public static function decryptData($data, $key) {
        if (strlen($data) === 0) {
            return '';
        }
        
        try {
            $decodedData = base64_decode($data);
            if ($decodedData === false) {
                return '';
            }
            
            $v = self::llstotaBoD(self::DtoBo($decodedData));
            $k = self::llstotaBoD(self::DtoBo(str_pad($key, 16, $key)));
            $n = count($v);
            
            if ($n < 1) {
                return '';
            }
            
            $delta = 0x9E3779B9;
            $q = floor(6 + 52 / $n);
            $sum = (int)(($q * $delta) & 0xFFFFFFFF);
            $y = $v[0];
            
            for ($i = 0; $i < $q; $i++) {
                $e = ($sum >> 2) & 3;
                
                for ($p = $n - 1; $p >= 0; $p--) {
                    $z = $v[$p > 0 ? $p - 1 : $n - 1];
                    $mx = ((($z >> 5) ^ ($y << 2)) + (($y >> 3) ^ ($z << 4))) ^ 
                          (($sum ^ $y) + ($k[($p & 3) ^ $e] ^ $z));
                    $mx = $mx & 0xFFFFFFFF;
                    
                    $v[$p] = ($v[$p] - $mx) & 0xFFFFFFFF;
                    $y = $v[$p];
                }
                
                $sum = ($sum - $delta) & 0xFFFFFFFF;
            }
            
            return self::stlBatl(self::DsttDats($v, false));
        } catch (\Exception $e) {
            return '';
        }
    }
}
