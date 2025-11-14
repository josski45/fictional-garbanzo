<?php

namespace JosskiTools\Utils;

/**
 * Session Manager Class - Manage user sessions
 */
class SessionManager {
    private $sessionsDir;
    private $expirationTime;
    
    public function __construct($sessionsDir, $expirationTime = 3600) {
        $this->sessionsDir = $sessionsDir;
        $this->expirationTime = $expirationTime;
        
        // Create sessions directory if not exists
        if (!file_exists($sessionsDir)) {
            mkdir($sessionsDir, 0755, true);
        }
    }
    
    /**
     * Get session file path
     */
    private function getSessionFile($userId) {
        return $this->sessionsDir . '/session_' . $userId . '.json';
    }
    
    /**
     * Get user session
     */
    public function getSession($userId) {
        $file = $this->getSessionFile($userId);
        
        if (!file_exists($file)) {
            return [
                'state' => null,
                'data' => [],
                'timestamp' => time()
            ];
        }
        
        $content = file_get_contents($file);
        $session = json_decode($content, true);
        
        // Check expiration
        if (time() - $session['timestamp'] > $this->expirationTime) {
            $this->clearSession($userId);
            return [
                'state' => null,
                'data' => [],
                'timestamp' => time()
            ];
        }
        
        return $session;
    }
    
    /**
     * Get session state
     */
    public function getState($userId) {
        $session = $this->getSession($userId);
        return $session['state'] ?? 'idle';
    }
    
    /**
     * Set session state
     */
    public function setState($userId, $state, $data = null) {
        $session = $this->getSession($userId);
        $session['state'] = $state;
        $session['timestamp'] = time();
        
        if ($data !== null) {
            $session['data'] = array_merge($session['data'], $data);
        }
        
        file_put_contents($this->getSessionFile($userId), json_encode($session));
    }
    
    /**
     * Get session data
     */
    public function getData($userId, $key = null) {
        $session = $this->getSession($userId);
        
        if ($key === null) {
            return $session['data'];
        }
        
        return $session['data'][$key] ?? null;
    }
    
    /**
     * Set session data
     */
    public function setData($userId, $key, $value) {
        $session = $this->getSession($userId);
        $session['data'][$key] = $value;
        $session['timestamp'] = time();
        
        file_put_contents($this->getSessionFile($userId), json_encode($session));
    }
    
    /**
     * Clear user session
     */
    public function clearSession($userId) {
        $file = $this->getSessionFile($userId);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Clean expired sessions
     */
    public function cleanExpiredSessions() {
        $files = glob($this->sessionsDir . '/session_*.json');
        $now = time();
        $cleaned = 0;
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            $session = json_decode($content, true);
            
            if ($now - $session['timestamp'] > $this->expirationTime) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}
