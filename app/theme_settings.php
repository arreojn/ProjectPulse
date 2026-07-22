<?php

declare(strict_types=1);

function theme_predefined_sets(): array
{
    return [
        'default' => [
            'name' => 'ProjectPulse Default',
            'colors' => [
                'theme_color_accent' => '#b45309',
                'theme_color_accent_strong' => '#7c2d12',
                'theme_color_bg' => '#efe5d6',
                'theme_color_bg_deep' => '#d8c3a5',
                'theme_color_surface' => 'rgba(255, 250, 242, 0.94)',
                'theme_color_surface_strong' => '#fffaf2',
                'theme_color_ink' => '#1f2933',
            ],
        ],
        'ocean_breeze' => [
            'name' => 'Ocean Breeze',
            'colors' => [
                'theme_color_accent' => '#0d9488',
                'theme_color_accent_strong' => '#115e59',
                'theme_color_bg' => '#f0f9ff',
                'theme_color_bg_deep' => '#e0f2fe',
                'theme_color_surface' => 'rgba(255, 255, 255, 0.94)',
                'theme_color_surface_strong' => '#ffffff',
                'theme_color_ink' => '#1f2937',
            ],
        ],
        'sunset_glow' => [
            'name' => 'Sunset Glow',
            'colors' => [
                'theme_color_accent' => '#ea580c',
                'theme_color_accent_strong' => '#9a3412',
                'theme_color_bg' => '#fff7ed',
                'theme_color_bg_deep' => '#ffedd5',
                'theme_color_surface' => 'rgba(255, 255, 255, 0.94)',
                'theme_color_surface_strong' => '#ffffff',
                'theme_color_ink' => '#1c1917',
            ],
        ],
        'forest_canopy' => [
            'name' => 'Forest Canopy',
            'colors' => [
                'theme_color_accent' => '#2f7d4a',
                'theme_color_accent_strong' => '#1f5130',
                'theme_color_bg' => '#eef7ef',
                'theme_color_bg_deep' => '#cfe7d2',
                'theme_color_surface' => 'rgba(255, 255, 255, 0.94)',
                'theme_color_surface_strong' => '#ffffff',
                'theme_color_ink' => '#1e3024',
            ],
        ],
        'lavender_mist' => [
            'name' => 'Lavender Mist',
            'colors' => [
                'theme_color_accent' => '#7357b6',
                'theme_color_accent_strong' => '#4c357f',
                'theme_color_bg' => '#f5f1ff',
                'theme_color_bg_deep' => '#e4daf8',
                'theme_color_surface' => 'rgba(255, 255, 255, 0.94)',
                'theme_color_surface_strong' => '#ffffff',
                'theme_color_ink' => '#29233a',
            ],
        ],
    ];
}

function theme_settings_bootstrap(): void
{
    static $bootstrapped = false;

    if ($bootstrapped) {
        return;
    }

    require_once __DIR__ . '/attendance_settings.php';
    attendance_settings_bootstrap();

    $statement = database()->prepare(
        'INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (:key, :value)'
    );
    $statement->execute(['key' => 'theme_active_key', 'value' => 'default']);

    $bootstrapped = true;
}

function theme_active_key(): string
{
    theme_settings_bootstrap();
    $statement = database()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = :key');
    $statement->execute(['key' => 'theme_active_key']);
    $key = $statement->fetchColumn();

    return array_key_exists((string) $key, theme_predefined_sets()) ? (string) $key : 'default';
}

function theme_colors(): array
{
    $sets = theme_predefined_sets();
    $activeKey = theme_active_key();

    return $sets[$activeKey]['colors'] ?? $sets['default']['colors'];
}

function theme_colors_save(string $themeKey): array
{
    theme_settings_bootstrap();
    $themes = theme_predefined_sets();
    $key = array_key_exists($themeKey, $themes) ? $themeKey : 'default';

    $statement = database()->prepare(
        'UPDATE system_settings SET setting_value = :setting_value WHERE setting_key = :setting_key'
    );
    $statement->execute([
        'setting_key' => 'theme_active_key',
        'setting_value' => $key,
    ]);

    return $themes[$key]['colors'];
}

function theme_colors_reset(): void
{
    theme_settings_bootstrap();

    $statement = database()->prepare(
        'INSERT INTO system_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'setting_key' => 'theme_active_key',
        'setting_value' => 'default',
    ]);
}

function theme_inline_styles(): string
{
    $colors = theme_colors();

    $css = ':root {'
        // This style is rendered before app.css, so !important ensures the saved theme overrides CSS defaults.
        . '--accent: ' . $colors['theme_color_accent'] . ' !important;'
        . '--accent-strong: ' . $colors['theme_color_accent_strong'] . ' !important;'
        . '--bg: ' . $colors['theme_color_bg'] . ' !important;'
        . '--bg-deep: ' . $colors['theme_color_bg_deep'] . ' !important;'
        . '--surface: ' . $colors['theme_color_surface'] . ' !important;'
        . '--surface-strong: ' . $colors['theme_color_surface_strong'] . ' !important;'
        . '--ink: ' . $colors['theme_color_ink'] . ' !important;'
        . '--line: color-mix(in srgb, var(--accent) 18%, transparent) !important;'
        . '--shadow: 0 24px 60px color-mix(in srgb, var(--accent-strong) 12%, transparent) !important;'
        . '}';

    return '<style id="projectpulse-theme">' . $css . '</style>';
}

function theme_settings_stylesheet_markup(): string
{
    return theme_inline_styles();
}
