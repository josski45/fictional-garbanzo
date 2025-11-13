<?php

namespace JosskiTools\Utils;

/**
 * AdvancedStats - Advanced statistics and analytics dashboard
 */
class AdvancedStats {

    /**
     * Get comprehensive statistics
     */
    public static function getComprehensiveStats() {
        // User stats
        $userStats = UserManager::getStats();

        // Download history stats
        $downloadStats = DownloadHistory::getGlobalStats();

        // Donation stats
        $donationStats = DonationManager::getStats();

        // Bot stats
        $statsManager = new StatsManager();
        $botStats = $statsManager->getStats();

        return [
            'users' => $userStats,
            'downloads' => $downloadStats,
            'donations' => $donationStats,
            'bot' => $botStats
        ];
    }

    /**
     * Generate dashboard message
     */
    public static function generateDashboard() {
        $stats = self::getComprehensiveStats();

        $message = "📊 **ADVANCED STATISTICS DASHBOARD**\n\n";

        // Users Section
        $message .= "👥 **USERS**\n";
        $message .= "• Total: {$stats['users']['total_users']}\n";
        $message .= "• Active: {$stats['users']['active_users']}\n";
        $message .= "• Blocked: {$stats['users']['blocked_users']}\n";
        $message .= "• Admins: {$stats['users']['admin_users']}\n\n";

        // Downloads Section
        $message .= "📥 **DOWNLOADS**\n";
        $message .= "• Total Downloads: {$stats['downloads']['total_downloads']}\n";
        $message .= "• Total Favorites: {$stats['downloads']['total_favorites']}\n";
        $message .= "• Active Users: {$stats['downloads']['active_users']}\n";
        $message .= "• Most Popular: {$stats['downloads']['most_popular_platform']}\n\n";

        // Platform Breakdown
        if (!empty($stats['downloads']['platform_breakdown'])) {
            $message .= "🌐 **PLATFORM BREAKDOWN**\n";
            $platformStats = array_slice($stats['downloads']['platform_breakdown'], 0, 5, true);
            foreach ($platformStats as $platform => $count) {
                $percentage = $stats['downloads']['total_downloads'] > 0
                    ? round(($count / $stats['downloads']['total_downloads']) * 100, 1)
                    : 0;
                $bar = self::generateBar($percentage);
                $message .= "• {$platform}: {$count} ({$percentage}%)\n  {$bar}\n";
            }
            $message .= "\n";
        }

        // Bot Requests Section
        $message .= "🤖 **BOT ACTIVITY**\n";
        $message .= "• Today: " . ($stats['bot']['today']['requests'] ?? 0) . "\n";
        $message .= "• This Week: " . ($stats['bot']['week']['requests'] ?? 0) . "\n";
        $message .= "• This Month: " . ($stats['bot']['month']['requests'] ?? 0) . "\n";
        $message .= "• Total: " . ($stats['bot']['total']['requests'] ?? 0) . "\n";
        $message .= "• Success Rate: " . self::calculateSuccessRate($stats['bot']) . "%\n\n";

        // Donations Section
        $message .= "💝 **DONATIONS**\n";
        $message .= "• Total Donors: {$stats['donations']['total_donors']}\n";
        $message .= "• Total Amount: " . DonationManager::formatAmount($stats['donations']['total_amount']) . "\n";
        $message .= "• Recent (30d): " . DonationManager::formatAmount($stats['donations']['recent_amount']) . "\n";
        $message .= "• Average: " . DonationManager::formatAmount($stats['donations']['average_donation']) . "\n\n";

        // Donor Tiers
        if (!empty($stats['donations']['tier_counts'])) {
            $message .= "👑 **DONOR TIERS**\n";
            $tiers = DonationManager::getTiers();
            foreach ($tiers as $key => $tierData) {
                $count = $stats['donations']['tier_counts'][$key] ?? 0;
                if ($count > 0) {
                    $message .= "• {$tierData['badge']} {$tierData['title']}: {$count}\n";
                }
            }
            $message .= "\n";
        }

        $message .= "📅 **Report Generated:** " . date('Y-m-d H:i:s');

        return $message;
    }

    /**
     * Generate user growth report
     */
    public static function generateUserGrowthReport() {
        $allUsers = UserManager::getAllUsers();

        $now = time();
        $dayAgo = $now - 86400;
        $weekAgo = $now - (7 * 86400);
        $monthAgo = $now - (30 * 86400);

        $usersToday = 0;
        $usersThisWeek = 0;
        $usersThisMonth = 0;

        // Calculate growth per day (last 7 days)
        $dailyGrowth = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = strtotime('-' . $i . ' days', strtotime('today'));
            $dayEnd = $dayStart + 86400;
            $dailyGrowth[date('M d', $dayStart)] = 0;

            foreach ($allUsers as $user) {
                $firstSeen = strtotime($user['first_seen'] ?? '1970-01-01');
                if ($firstSeen >= $dayStart && $firstSeen < $dayEnd) {
                    $dailyGrowth[date('M d', $dayStart)]++;
                }
            }
        }

        foreach ($allUsers as $user) {
            $firstSeen = strtotime($user['first_seen'] ?? '1970-01-01');

            if ($firstSeen >= $dayAgo) $usersToday++;
            if ($firstSeen >= $weekAgo) $usersThisWeek++;
            if ($firstSeen >= $monthAgo) $usersThisMonth++;
        }

        $message = "📈 **USER GROWTH REPORT**\n\n";
        $message .= "**New Users:**\n";
        $message .= "• Today: {$usersToday}\n";
        $message .= "• This Week: {$usersThisWeek}\n";
        $message .= "• This Month: {$usersThisMonth}\n\n";

        $message .= "**Daily Growth (Last 7 Days):**\n";
        foreach ($dailyGrowth as $date => $count) {
            $bar = self::generateMiniBar($count, 10);
            $message .= "• {$date}: {$count} {$bar}\n";
        }

        return $message;
    }

    /**
     * Generate platform analytics
     */
    public static function generatePlatformAnalytics() {
        $downloadStats = DownloadHistory::getGlobalStats();
        $platformBreakdown = $downloadStats['platform_breakdown'] ?? [];

        if (empty($platformBreakdown)) {
            return "No download data available yet.";
        }

        $totalDownloads = array_sum($platformBreakdown);

        $message = "🌐 **PLATFORM ANALYTICS**\n\n";
        $message .= "**Total Downloads:** {$totalDownloads}\n\n";

        $message .= "**Platform Ranking:**\n";
        $rank = 1;
        foreach ($platformBreakdown as $platform => $count) {
            $percentage = round(($count / $totalDownloads) * 100, 1);
            $bar = self::generateBar($percentage, 20);

            $medal = '';
            if ($rank == 1) $medal = '🥇';
            elseif ($rank == 2) $medal = '🥈';
            elseif ($rank == 3) $medal = '🥉';

            $message .= "\n{$medal} **#{$rank} {$platform}**\n";
            $message .= "Count: {$count} ({$percentage}%)\n";
            $message .= "{$bar}\n";

            $rank++;

            if ($rank > 10) break; // Limit to top 10
        }

        return $message;
    }

    /**
     * Generate peak hours report
     */
    public static function generatePeakHoursReport() {
        $allUsers = UserManager::getAllUsers();

        // Count requests per hour (last 24 hours)
        $hourlyActivity = array_fill(0, 24, 0);

        foreach ($allUsers as $user) {
            $lastSeen = strtotime($user['last_seen'] ?? '1970-01-01');
            if ($lastSeen > (time() - 86400)) {
                $hour = (int)date('H', $lastSeen);
                $hourlyActivity[$hour]++;
            }
        }

        $maxActivity = max($hourlyActivity);

        $message = "⏰ **PEAK HOURS REPORT**\n\n";
        $message .= "**Activity by Hour (Last 24h):**\n\n";

        for ($hour = 0; $hour < 24; $hour++) {
            $count = $hourlyActivity[$hour];
            $percentage = $maxActivity > 0 ? ($count / $maxActivity) * 100 : 0;
            $bar = self::generateMiniBar($percentage, 15);

            $timeRange = sprintf("%02d:00-%02d:00", $hour, $hour + 1);
            $message .= "{$timeRange} [{$count}] {$bar}\n";
        }

        // Find peak hour
        $peakHour = array_search($maxActivity, $hourlyActivity);
        $message .= "\n🔥 **Peak Hour:** {$peakHour}:00-" . ($peakHour + 1) . ":00 ({$maxActivity} users)";

        return $message;
    }

    /**
     * Generate bar chart
     */
    private static function generateBar($percentage, $length = 15) {
        $percentage = max(0, min(100, $percentage));
        $filledLength = round(($percentage / 100) * $length);
        $emptyLength = $length - $filledLength;

        return str_repeat('▰', $filledLength) . str_repeat('▱', $emptyLength);
    }

    /**
     * Generate mini bar
     */
    private static function generateMiniBar($value, $max) {
        if ($max == 0) return '';

        $percentage = ($value / $max) * 100;
        $filledLength = round($percentage / 10); // 10 blocks max
        return str_repeat('█', $filledLength);
    }

    /**
     * Calculate success rate
     */
    private static function calculateSuccessRate($botStats) {
        $total = $botStats['total']['requests'] ?? 0;
        $success = $botStats['total']['success'] ?? 0;

        if ($total == 0) return 0;

        return round(($success / $total) * 100, 1);
    }

    /**
     * Generate performance report
     */
    public static function generatePerformanceReport() {
        $statsManager = new StatsManager();
        $stats = $statsManager->getStats();

        $totalRequests = $stats['total']['requests'] ?? 0;
        $totalSuccess = $stats['total']['success'] ?? 0;
        $totalFailed = $stats['total']['failed'] ?? 0;

        $successRate = $totalRequests > 0 ? round(($totalSuccess / $totalRequests) * 100, 1) : 0;
        $failureRate = 100 - $successRate;

        $message = "⚡ **PERFORMANCE REPORT**\n\n";
        $message .= "**Overall Statistics:**\n";
        $message .= "• Total Requests: {$totalRequests}\n";
        $message .= "• Successful: {$totalSuccess}\n";
        $message .= "• Failed: {$totalFailed}\n\n";

        $message .= "**Success Rate:**\n";
        $successBar = self::generateBar($successRate, 20);
        $message .= "{$successRate}% {$successBar}\n\n";

        $message .= "**Failure Rate:**\n";
        $failureBar = self::generateBar($failureRate, 20);
        $message .= "{$failureRate}% {$failureBar}\n\n";

        // Status indicator
        if ($successRate >= 95) {
            $message .= "✅ **Status:** Excellent\n";
        } elseif ($successRate >= 90) {
            $message .= "🟢 **Status:** Good\n";
        } elseif ($successRate >= 80) {
            $message .= "🟡 **Status:** Fair\n";
        } else {
            $message .= "🔴 **Status:** Needs Attention\n";
        }

        return $message;
    }

    /**
     * Generate export report (CSV-like text)
     */
    public static function generateExportReport() {
        $stats = self::getComprehensiveStats();

        $report = "JOSSKI TOOLS BOT - STATISTICS EXPORT\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $report .= str_repeat('=', 50) . "\n\n";

        $report .= "USERS\n";
        $report .= "Total Users: {$stats['users']['total_users']}\n";
        $report .= "Active Users: {$stats['users']['active_users']}\n";
        $report .= "Blocked Users: {$stats['users']['blocked_users']}\n";
        $report .= "Admin Users: {$stats['users']['admin_users']}\n";
        $report .= "Total Requests: {$stats['users']['total_requests']}\n\n";

        $report .= "DOWNLOADS\n";
        $report .= "Total Downloads: {$stats['downloads']['total_downloads']}\n";
        $report .= "Total Favorites: {$stats['downloads']['total_favorites']}\n";
        $report .= "Active Users: {$stats['downloads']['active_users']}\n";
        $report .= "Most Popular: {$stats['downloads']['most_popular_platform']}\n\n";

        $report .= "PLATFORM BREAKDOWN\n";
        foreach ($stats['downloads']['platform_breakdown'] as $platform => $count) {
            $report .= "{$platform}: {$count}\n";
        }
        $report .= "\n";

        $report .= "DONATIONS\n";
        $report .= "Total Donors: {$stats['donations']['total_donors']}\n";
        $report .= "Total Amount: {$stats['donations']['total_amount']}\n";
        $report .= "Total Donations: {$stats['donations']['total_donations']}\n";
        $report .= "Average Donation: {$stats['donations']['average_donation']}\n\n";

        $report .= "BOT ACTIVITY\n";
        $report .= "Today: " . ($stats['bot']['today']['requests'] ?? 0) . "\n";
        $report .= "This Week: " . ($stats['bot']['week']['requests'] ?? 0) . "\n";
        $report .= "This Month: " . ($stats['bot']['month']['requests'] ?? 0) . "\n";
        $report .= "Total: " . ($stats['bot']['total']['requests'] ?? 0) . "\n";
        $report .= "Success: " . ($stats['bot']['total']['success'] ?? 0) . "\n";
        $report .= "Failed: " . ($stats['bot']['total']['failed'] ?? 0) . "\n";

        return $report;
    }
}
