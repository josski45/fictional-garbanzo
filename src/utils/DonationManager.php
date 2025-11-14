<?php

namespace JosskiTools\Utils;

/**
 * DonationManager - Manage voluntary donations (no feature locking)
 */
class DonationManager {

    private static $dataDir;
    private static $donorsFile;

    /**
     * Donation tiers (for recognition only, not feature locking)
     */
    private static $tiers = [
        'supporter' => [
            'min_amount' => 10000,      // IDR
            'badge' => '🌟',
            'title' => 'Pendukung'
        ],
        'patron' => [
            'min_amount' => 50000,
            'badge' => '⭐',
            'title' => 'Sahabat'
        ],
        'benefactor' => [
            'min_amount' => 100000,
            'badge' => '💎',
            'title' => 'Dermawan'
        ],
        'legend' => [
            'min_amount' => 500000,
            'badge' => '👑',
            'title' => 'Legenda'
        ]
    ];

    /**
     * Initialize donation manager
     */
    public static function init($dataDir = null) {
        if ($dataDir === null) {
            self::$dataDir = __DIR__ . '/../../data';
        } else {
            self::$dataDir = $dataDir;
        }

        self::$donorsFile = self::$dataDir . '/donors.json';

        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777, true);
        }

        if (!file_exists(self::$donorsFile)) {
            file_put_contents(self::$donorsFile, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Load donors data
     */
    private static function loadDonors() {
        self::init();
        $content = file_get_contents(self::$donorsFile);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Save donors data
     */
    private static function saveDonors($data) {
        file_put_contents(self::$donorsFile, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Record donation
     */
    public static function recordDonation($userId, $amount, $method = 'manual', $note = null) {
        $donors = self::loadDonors();

        if (!isset($donors[$userId])) {
            $donors[$userId] = [
                'user_id' => $userId,
                'total_donated' => 0,
                'donations' => [],
                'first_donation' => date('Y-m-d H:i:s'),
                'tier' => null,
                'badge' => null
            ];
        }

        // Add donation record
        $donation = [
            'amount' => $amount,
            'method' => $method,
            'note' => $note,
            'date' => date('Y-m-d H:i:s'),
            'timestamp' => time()
        ];

        $donors[$userId]['donations'][] = $donation;
        $donors[$userId]['total_donated'] += $amount;
        $donors[$userId]['last_donation'] = date('Y-m-d H:i:s');

        // Update tier
        $tier = self::calculateTier($donors[$userId]['total_donated']);
        $donors[$userId]['tier'] = $tier['title'];
        $donors[$userId]['badge'] = $tier['badge'];

        self::saveDonors($donors);

        Logger::info("Donation recorded", [
            'user_id' => $userId,
            'amount' => $amount,
            'tier' => $tier['title']
        ]);

        UserLogger::log($userId, "Donation received", [
            'amount' => $amount,
            'tier' => $tier['title']
        ]);

        return [
            'success' => true,
            'tier' => $tier['title'],
            'badge' => $tier['badge'],
            'total_donated' => $donors[$userId]['total_donated']
        ];
    }

    /**
     * Calculate tier based on total donated
     */
    private static function calculateTier($totalAmount) {
        $tier = ['title' => null, 'badge' => null, 'min_amount' => 0];

        foreach (self::$tiers as $key => $tierData) {
            if ($totalAmount >= $tierData['min_amount'] && $tierData['min_amount'] > $tier['min_amount']) {
                $tier = $tierData;
                $tier['key'] = $key;
            }
        }

        return $tier;
    }

    /**
     * Get donor info
     */
    public static function getDonor($userId) {
        $donors = self::loadDonors();
        return $donors[$userId] ?? null;
    }

    /**
     * Check if user is donor
     */
    public static function isDonor($userId) {
        $donor = self::getDonor($userId);
        return $donor !== null && $donor['total_donated'] > 0;
    }

    /**
     * Get donor badge
     */
    public static function getBadge($userId) {
        $donor = self::getDonor($userId);

        if ($donor === null || $donor['total_donated'] == 0) {
            return null;
        }

        return $donor['badge'] ?? '🌟';
    }

    /**
     * Get donor tier
     */
    public static function getTier($userId) {
        $donor = self::getDonor($userId);

        if ($donor === null || $donor['total_donated'] == 0) {
            return null;
        }

            return $donor['tier'] ?? 'Pendukung';
    }

    /**
     * Get all donors
     */
    public static function getAllDonors() {
        return self::loadDonors();
    }

    /**
     * Get top donors
     */
    public static function getTopDonors($limit = 10) {
        $donors = self::loadDonors();

        // Sort by total donated
        uasort($donors, function($a, $b) {
            return $b['total_donated'] - $a['total_donated'];
        });

        return array_slice($donors, 0, $limit, true);
    }

    /**
     * Get donation statistics
     */
    public static function getStats() {
        $donors = self::loadDonors();

        $totalDonations = 0;
        $totalAmount = 0;
        $tierCounts = array_fill_keys(array_keys(self::$tiers), 0);
        $tierCounts['none'] = 0;

        foreach ($donors as $donor) {
            $totalAmount += $donor['total_donated'];
            $totalDonations += count($donor['donations']);

            if (isset($donor['tier'])) {
                $tierKey = array_search($donor['tier'], array_column(self::$tiers, 'title'));
                if ($tierKey !== false) {
                    $tierCounts[$tierKey]++;
                } else {
                    $tierCounts['none']++;
                }
            } else {
                $tierCounts['none']++;
            }
        }

        // Recent donations (last 30 days)
        $thirtyDaysAgo = time() - (30 * 86400);
        $recentDonations = 0;
        $recentAmount = 0;

        foreach ($donors as $donor) {
            foreach ($donor['donations'] as $donation) {
                if (($donation['timestamp'] ?? 0) > $thirtyDaysAgo) {
                    $recentDonations++;
                    $recentAmount += $donation['amount'];
                }
            }
        }

        return [
            'total_donors' => count($donors),
            'total_donations' => $totalDonations,
            'total_amount' => $totalAmount,
            'tier_counts' => $tierCounts,
            'recent_donations' => $recentDonations,
            'recent_amount' => $recentAmount,
            'average_donation' => $totalDonations > 0 ? round($totalAmount / $totalDonations) : 0
        ];
    }

    /**
     * Format amount to IDR
     */
    public static function formatAmount($amount) {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get donation info message
     */
    public static function getDonationInfo() {
        $message = "💝 *Dukungan via Donasi*\n\n";
        $message .= "Bot ini bisa dipakai gratis tanpa batas. Kalau kamu merasa terbantu, silakan dukung dengan donasi seikhlasnya. Nominal benar-benar bebas!\n\n";
        $message .= "📷 Scan QRIS pada gambar atau gunakan aplikasi bank pilihanmu.\n\n";
        $message .= "🎁 *Penghargaan Donatur*\n";

        foreach (self::$tiers as $tier) {
            $message .= "{$tier['badge']} {$tier['title']} - mulai dari " . self::formatAmount($tier['min_amount']) . "\n";
        }

        $message .= "\nℹ️ *Catatan:*\n";
        $message .= "• Donasi bersifat sukarela, fitur tetap gratis\n";
        $message .= "• Kirim bukti ke admin kalau mau namamu masuk leaderboard\n";
        $message .= "• Seluruh donasi dipakai untuk biaya server dan pengembangan\n\n";
        $message .= "Gunakan tombol di bawah untuk lihat profil donasimu atau papan peringkat.\n\n";
        $message .= "Terima kasih sudah bantu menjaga bot ini tetap hidup! 🙏";

        return $message;
    }

    /**
     * Get donor profile message
     */
    public static function getDonorProfile($userId) {
        $donor = self::getDonor($userId);

        if ($donor === null || $donor['total_donated'] == 0) {
            return "Kamu belum pernah donasi.\n\nKetik /donate untuk melihat cara dukung bot ini.";
        }

        $message = "👑 *Profil Donatur Kamu*\n\n";
        $message .= "Lencana: {$donor['badge']}\n";
        $message .= "Level: {$donor['tier']}\n";
        $message .= "Total Donasi: " . self::formatAmount($donor['total_donated']) . "\n";
        $message .= "Donasi Pertama: {$donor['first_donation']}\n";
        $message .= "Donasi Terakhir: {$donor['last_donation']}\n";
        $message .= "Jumlah Transaksi: " . count($donor['donations']) . "\n\n";
        $message .= "Terima kasih banyak! Dukunganmu bikin bot ini terus jalan dan berkembang. ❤️";

        return $message;
    }

    /**
     * Get leaderboard
     */
    public static function getLeaderboard($limit = 10) {
        $topDonors = self::getTopDonors($limit);

        $message = "🏆 *Papan Donatur Tertinggi*\n\n";

        $rank = 1;
        foreach ($topDonors as $userId => $donor) {
            $badge = $donor['badge'] ?? '🌟';
            $tier = $donor['tier'] ?? 'Pendukung';
            $amount = self::formatAmount($donor['total_donated']);

            $medal = '';
            if ($rank == 1) $medal = '🥇';
            elseif ($rank == 2) $medal = '🥈';
            elseif ($rank == 3) $medal = '🥉';

            $message .= "{$medal} *#{$rank}* {$badge} {$tier}\n";
            $message .= "   Total: {$amount}\n\n";

            $rank++;
        }

        $message .= "Terima kasih kepada semua yang sudah mendukung! ❤️";

        return $message;
    }

    /**
     * Remove donor (admin only - for corrections)
     */
    public static function removeDonor($userId) {
        $donors = self::loadDonors();

        if (isset($donors[$userId])) {
            unset($donors[$userId]);
            self::saveDonors($donors);

            Logger::warning("Donor removed", ['user_id' => $userId]);
            return true;
        }

        return false;
    }

    /**
     * Get tiers info
     */
    public static function getTiers() {
        return self::$tiers;
    }
}
