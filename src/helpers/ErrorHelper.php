<?php

namespace JosskiTools\Helpers;

/**
 * Error Helper - Handle error messages and HTTP status codes
 */
class ErrorHelper {
    
    /**
     * Get error message based on HTTP status code or error type
     * 
     * @param int|null $statusCode HTTP status code
     * @param string $errorMessage Original error message
     * @return string Formatted error message
     */
    public static function getErrorMessage($statusCode = null, $errorMessage = '') {
        $messages = [
            200 => '✅ Request successful',
            400 => '❌ Bad Request - Invalid parameters or missing required fields',
            405 => '❌ Method Not Allowed - HTTP method not supported',
            429 => '⚠️ Too Many Requests - Rate limit exceeded. Please try again later',
            500 => '❌ Internal Server Error - Server encountered an error',
            502 => '❌ Bad Gateway - Server is temporarily unavailable',
            503 => '❌ Service Unavailable - Server is temporarily down',
            504 => '❌ Gateway Timeout - Request took too long'
        ];
        
        // If status code is provided and exists in our messages
        if ($statusCode && isset($messages[$statusCode])) {
            return $messages[$statusCode];
        }
        
        // Check for common error patterns in error message
        if (!empty($errorMessage)) {
            $errorLower = strtolower($errorMessage);
            
            if (strpos($errorLower, 'rate limit') !== false || strpos($errorLower, 'too many') !== false) {
                return $messages[429];
            }
            
            if (strpos($errorLower, 'timeout') !== false) {
                return $messages[504];
            }
            
            if (strpos($errorLower, 'bad request') !== false || strpos($errorLower, 'invalid') !== false) {
                return $messages[400];
            }
            
            if (strpos($errorLower, 'not found') !== false) {
                return '❌ Resource not found - Please check your URL';
            }
            
            if (strpos($errorLower, 'unavailable') !== false) {
                return $messages[503];
            }
            
            // Return the original error message if no pattern matches
            return '❌ Error: ' . $errorMessage;
        }
        
        // Default fallback message
        return '❌ An unknown error occurred. Please try again later';
    }
    
    /**
     * Get helpful tips based on error status code
     * 
     * @param int $statusCode HTTP status code
     * @return string Helpful tip or empty string
     */
    public static function getErrorTip($statusCode) {
        $tips = [
            429 => "\n\n💡 Tip: Wait a few minutes before trying again",
            400 => "\n\n💡 Tip: Check if your URL is correct",
            500 => "\n\n💡 If this persists, contact admin",
            502 => "\n\n💡 If this persists, contact admin",
            503 => "\n\n💡 Server is temporarily down, try again later",
            504 => "\n\n💡 Request timeout, try again with a shorter video"
        ];
        
        return $tips[$statusCode] ?? '';
    }
}
