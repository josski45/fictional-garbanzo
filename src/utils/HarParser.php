<?php

namespace JosskiTools\Utils;

require_once __DIR__ . '/Encryption.php';

/**
 * HAR Parser Class - Parse and decrypt HAR files
 */
class HarParser {
    
    /**
     * Parse HAR file and decrypt data
     */
    public static function parseHarAndDecrypt($harContent, $key) {
        try {
            $harData = json_decode($harContent, true);
            
            if (!$harData || !isset($harData['log']['entries'])) {
                return ['success' => false, 'error' => 'Invalid HAR format'];
            }
            
            $entries = $harData['log']['entries'];
            $results = [];
            
            foreach ($entries as $entry) {
                $url = $entry['request']['url'] ?? '';
                
                // Check request POST data
                $requestResult = self::checkRequestData($entry, $key);
                if ($requestResult) {
                    $results[] = [
                        'url' => $url,
                        'type' => 'Request POST Data',
                        'decrypted' => $requestResult
                    ];
                }
                
                // Check response content
                $responseResult = self::checkResponseData($entry, $key);
                if ($responseResult) {
                    $results[] = [
                        'url' => $url,
                        'type' => 'Response Content',
                        'decrypted' => $responseResult
                    ];
                }
                
                // Check query parameters
                $queryResult = self::checkQueryParams($entry, $key);
                if ($queryResult) {
                    $results[] = [
                        'url' => $url,
                        'type' => 'Query Parameters',
                        'decrypted' => $queryResult
                    ];
                }
            }
            
            return [
                'success' => true,
                'count' => count($results),
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check request POST data
     */
    private static function checkRequestData($entry, $key) {
        if (!isset($entry['request']['postData']['text'])) {
            return null;
        }
        
        $postData = $entry['request']['postData']['text'];
        return self::decryptAndAddToOutput($postData, $key);
    }
    
    /**
     * Check response content
     */
    private static function checkResponseData($entry, $key) {
        if (!isset($entry['response']['content']['text'])) {
            return null;
        }
        
        $responseText = $entry['response']['content']['text'];
        return self::decryptAndAddToOutput($responseText, $key);
    }
    
    /**
     * Check query parameters
     */
    private static function checkQueryParams($entry, $key) {
        if (!isset($entry['request']['queryString'])) {
            return null;
        }
        
        $queryString = $entry['request']['queryString'];
        foreach ($queryString as $param) {
            if (isset($param['value']) && !empty($param['value'])) {
                $result = self::decryptAndAddToOutput($param['value'], $key);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Decrypt data and add to output
     */
    private static function decryptAndAddToOutput($data, $key) {
        if (empty($data)) {
            return null;
        }
        
        // Try to decrypt
        $decrypted = Encryption::decryptData($data, $key);
        
        // Check if decryption was successful (contains readable text)
        if (!empty($decrypted) && self::isReadableText($decrypted)) {
            return $decrypted;
        }
        
        return null;
    }
    
    /**
     * Check if text is readable
     */
    private static function isReadableText($text) {
        // Check if text contains printable characters
        $printableCount = 0;
        $totalLength = strlen($text);
        
        for ($i = 0; $i < $totalLength; $i++) {
            $char = ord($text[$i]);
            if (($char >= 32 && $char <= 126) || $char === 9 || $char === 10 || $char === 13) {
                $printableCount++;
            }
        }
        
        // At least 80% should be printable
        return ($printableCount / $totalLength) >= 0.8;
    }
    
    /**
     * Format results as text
     */
    public static function formatResults($results) {
        if (empty($results)) {
            return "No encrypted data found or unable to decrypt.\n";
        }
        
        $output = "=== HAR EXTRACTION RESULTS ===\n\n";
        $output .= "Total entries found: " . count($results) . "\n\n";
        $output .= str_repeat("=", 50) . "\n\n";
        
        foreach ($results as $index => $result) {
            $output .= "Entry #" . ($index + 1) . "\n";
            $output .= "URL: " . $result['url'] . "\n";
            $output .= "Type: " . $result['type'] . "\n";
            $output .= "Decrypted Data:\n";
            $output .= str_repeat("-", 50) . "\n";
            $output .= $result['decrypted'] . "\n";
            $output .= str_repeat("=", 50) . "\n\n";
        }
        
        return $output;
    }
}
