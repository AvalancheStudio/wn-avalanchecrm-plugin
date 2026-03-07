<?php

namespace AvalancheStudio\AvalancheCRM\Classes;

use AvalancheStudio\AvalancheCRM\Models\Settings;

/**
 * Generates a <style> block containing CSS custom-properties derived from
 * the plugin's Theme settings. Each component injects these variables so
 * all client-portal CSS can reference them consistently.
 */
class ThemeStyles
{
    /**
     * Return an inline <style> tag with :root CSS variables.
     */
    public static function render(): string
    {
        $settings = Settings::instance();

        $primary = $settings->theme_color_primary ?: '#4a6cf7';
        $success = $settings->theme_color_success ?: '#28a745';
        $warning = $settings->theme_color_warning ?: '#d97706';
        $danger  = $settings->theme_color_danger  ?: '#dc2626';
        $info    = $settings->theme_color_info    ?: '#2563eb';

        $vars = implode("\n    ", [
            "--crm-primary: {$primary};",
            "--crm-primary-hover: " . self::darken($primary, 10) . ";",
            "--crm-primary-light: " . self::lighten($primary, 90) . ";",
            "--crm-primary-border: " . self::lighten($primary, 70) . ";",
            "--crm-primary-ring: " . self::alpha($primary, 0.12) . ";",
            "",
            "--crm-success: {$success};",
            "--crm-success-light: " . self::lighten($success, 90) . ";",
            "--crm-success-border: " . self::lighten($success, 70) . ";",
            "--crm-success-text: " . self::darken($success, 30) . ";",
            "",
            "--crm-warning: {$warning};",
            "--crm-warning-light: " . self::lighten($warning, 90) . ";",
            "--crm-warning-border: " . self::lighten($warning, 70) . ";",
            "--crm-warning-text: " . self::darken($warning, 30) . ";",
            "",
            "--crm-danger: {$danger};",
            "--crm-danger-hover: " . self::darken($danger, 10) . ";",
            "--crm-danger-light: " . self::lighten($danger, 90) . ";",
            "--crm-danger-border: " . self::lighten($danger, 70) . ";",
            "--crm-danger-text: " . self::darken($danger, 30) . ";",
            "",
            "--crm-info: {$info};",
            "--crm-info-light: " . self::lighten($info, 90) . ";",
            "--crm-info-border: " . self::lighten($info, 70) . ";",
            "--crm-info-text: " . self::darken($info, 30) . ";",
        ]);

        return "<style>:root {\n    {$vars}\n}</style>";
    }

    /* ------------------------------------------------------------------ */
    /*  Tiny colour helpers â€“ no external dependencies                     */
    /* ------------------------------------------------------------------ */

    /**
     * Convert a hex colour to an [r, g, b] array.
     */
    protected static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0] . $hex[1].$hex[1] . $hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Convert [r, g, b] back to a hex string.
     */
    protected static function rgbToHex(array $rgb): string
    {
        return '#' . implode('', array_map(function ($c) {
            return str_pad(dechex(max(0, min(255, round($c)))), 2, '0', STR_PAD_LEFT);
        }, $rgb));
    }

    /**
     * Convert hex to HSL [h, s, l] with h 0-360, s/l 0-100.
     */
    protected static function hexToHsl(string $hex): array
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        $r /= 255; $g /= 255; $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;
        $h   = $s = 0;

        if ($max !== $min) {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

            switch ($max) {
                case $r: $h = (($g - $b) / $d + ($g < $b ? 6 : 0)); break;
                case $g: $h = (($b - $r) / $d + 2); break;
                case $b: $h = (($r - $g) / $d + 4); break;
            }
            $h /= 6;
        }

        return [round($h * 360, 1), round($s * 100, 1), round($l * 100, 1)];
    }

    /**
     * Convert HSL back to hex.
     */
    protected static function hslToHex(float $h, float $s, float $l): string
    {
        $h /= 360; $s /= 100; $l /= 100;

        if ($s === 0.0) {
            $v = round($l * 255);
            return self::rgbToHex([$v, $v, $v]);
        }

        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;

        $r = self::hueToRgb($p, $q, $h + 1/3);
        $g = self::hueToRgb($p, $q, $h);
        $b = self::hueToRgb($p, $q, $h - 1/3);

        return self::rgbToHex([round($r * 255), round($g * 255), round($b * 255)]);
    }

    protected static function hueToRgb(float $p, float $q, float $t): float
    {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    /**
     * Darken a hex colour by $amount percent (lightness reduction).
     */
    protected static function darken(string $hex, float $amount): string
    {
        [$h, $s, $l] = self::hexToHsl($hex);
        $l = max(0, $l - $amount);
        return self::hslToHex($h, $s, $l);
    }

    /**
     * Lighten a hex colour so its lightness moves towards $targetLightness.
     */
    protected static function lighten(string $hex, float $targetLightness): string
    {
        [$h, $s, $l] = self::hexToHsl($hex);
        $l = min(100, $targetLightness);
        // Keep some saturation so it's still tinted, not pure white/grey
        $s = max($s * 0.35, 10);
        return self::hslToHex($h, $s, $l);
    }

    /**
     * Return an rgba() string with the given alpha.
     */
    protected static function alpha(string $hex, float $a): string
    {
        [$r, $g, $b] = self::hexToRgb($hex);
        return "rgba({$r}, {$g}, {$b}, {$a})";
    }
}
