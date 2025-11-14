<?php

namespace JosskiTools\Utils;

class StatsManager {
    private $statsFile;
    
    public function __construct($statsDir = null) {
        if (!$statsDir) {
            $statsDir = __DIR__ . '/../../data/stats';
        }
        
        if (!is_dir($statsDir)) {
            mkdir($statsDir, 0777, true);
        }
        
        $this->statsFile = $statsDir . '/bot_stats.json';
        
        // Initialize stats file if not exists
        if (!file_exists($this->statsFile)) {
            $this->resetStats();
        }
    }
    
    /**
     * Get current stats
     */
    public function getStats() {
        if (!file_exists($this->statsFile)) {
            return $this->getDefaultStats();
        }
        
        $data = json_decode(file_get_contents($this->statsFile), true);
        if (!$data) {
            return $this->getDefaultStats();
        }
        
        // Auto reset if day/week/month changed
        $this->checkAndResetPeriods($data);
        
        return $data;
    }
    
    /**
     * Increment request counter
     */
    public function incrementRequest($success = true) {
        $stats = $this->getStats();
        
        $stats['total']['requests']++;
        $stats['today']['requests']++;
        $stats['week']['requests']++;
        $stats['month']['requests']++;
        
        if ($success) {
            $stats['total']['success']++;
            $stats['today']['success']++;
            $stats['week']['success']++;
            $stats['month']['success']++;
        } else {
            $stats['total']['failed']++;
            $stats['today']['failed']++;
            $stats['week']['failed']++;
            $stats['month']['failed']++;
        }
        
        $stats['last_updated'] = date('Y-m-d H:i:s');
        
        $this->saveStats($stats);
    }
    
    /**
     * Check and reset periods if needed
     */
    private function checkAndResetPeriods(&$stats) {
        $today = date('Y-m-d');
        $currentWeek = date('Y-W');
        $currentMonth = date('Y-m');
        $updated = false;
        
        // Reset daily stats
        if ($stats['today']['date'] !== $today) {
            $stats['today'] = [
                'date' => $today,
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ];
            $updated = true;
        }
        
        // Reset weekly stats
        if ($stats['week']['week'] !== $currentWeek) {
            $stats['week'] = [
                'week' => $currentWeek,
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ];
            $updated = true;
        }
        
        // Reset monthly stats
        if ($stats['month']['month'] !== $currentMonth) {
            $stats['month'] = [
                'month' => $currentMonth,
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ];
            $updated = true;
        }
        
        if ($updated) {
            $this->saveStats($stats);
        }
    }
    
    /**
     * Save stats to file
     */
    private function saveStats($stats) {
        file_put_contents($this->statsFile, json_encode($stats, JSON_PRETTY_PRINT));
    }
    
    /**
     * Get default stats structure
     */
    private function getDefaultStats() {
        return [
            'today' => [
                'date' => date('Y-m-d'),
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ],
            'week' => [
                'week' => date('Y-W'),
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ],
            'month' => [
                'month' => date('Y-m'),
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ],
            'total' => [
                'requests' => 0,
                'success' => 0,
                'failed' => 0
            ],
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Reset all stats
     */
    private function resetStats() {
        $this->saveStats($this->getDefaultStats());
    }
}
