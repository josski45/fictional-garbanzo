<?php

namespace JosskiTools\Helpers;

/**
 * Keyboard Helper - Manage keyboard layouts
 */
class KeyboardHelper {
    
    /**
     * Get main keyboard markup
     */
    public static function getMainKeyboard() {
        return [
            'keyboard' => [
                [
                    ['text' => '📥 Downloader'],
                    ['text' => '📚 Help']
                ],
                [
                    ['text' => '💝 Donasi'],
                    ['text' => '🎛️ Menu']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'input_field_placeholder' => 'Pilih menu atau ketik command...'
        ];
    }
    
    /**
     * Get downloader keyboard markup
     */
    public static function getDownloaderKeyboard() {
        return [
            'keyboard' => [
                [
                    ['text' => '🎵 TikTok'],
                    ['text' => '📘 Facebook'],
                    ['text' => '🎧 Spotify']
                ],
                [
                    ['text' => '📹 YouTube MP3'],
                    ['text' => '🎬 YouTube MP4']
                ],
                [
                    ['text' => '🎨 CapCut'],
                    ['text' => '💝 Donasi']
                ],
                [
                    ['text' => '🏠 Main Menu']
                ],
                [
                    ['text' => '🔙 Menu Awal']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'input_field_placeholder' => 'Pilih downloader atau kirim link...'
        ];
    }
    
    /**
     * Get cancel keyboard
     */
    public static function getCancelKeyboard() {
        return [
            'keyboard' => [
                [
                    ['text' => '❌ Cancel']
                ]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
    }
    
    /**
     * Remove keyboard
     */
    public static function removeKeyboard() {
        return [
            'remove_keyboard' => true
        ];
    }
}
