<?php
// Minimal plugin installer using WordPress core classes (no TGMPA).
// Works with wp.org slugs and external ZIP URLs. Continues on errors.

namespace WT\PluginInstaller;

if (!defined('ABSPATH')) exit;

class Installer {
    public static function install_many(array $list, bool $auto_activate = true): array {
        self::bootstrap();
        $results = [];
        foreach ($list as $item) {
            $results[$item['slug'] ?? ($item['source'] ?? 'unknown')] = self::install_one($item, $auto_activate);
        }
        return $results;
    }

    public static function install_one(array $item, bool $auto_activate = true) {
        $slug   = sanitize_key($item['slug'] ?? '');
        $source = isset($item['source']) ? esc_url_raw($item['source']) : '';

        // Resolve download URL
        if ($source) {
            $package = $source;
        } else {
            // Ask wp.org API for the latest link (more robust than guessing)
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            $api = plugins_api('plugin_information', ['slug' => $slug, 'fields' => ['sections' => false]]);
            $package = (!is_wp_error($api) && !empty($api->download_link))
                ? $api->download_link
                : "https://downloads.wordpress.org/plugin/{$slug}.latest-stable.zip";
        }

        // Upgrader
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        $skin     = new \Automatic_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);

        // Network hygiene
        add_filter('http_request_timeout', fn($t) => max(45, (int)$t));
        add_filter('http_request_args', function($args, $url){
            $args['limit_response_size'] = false;
            $args['user-agent'] = 'WordPress/' . get_bloginfo('version') . '; ' . home_url('/');
            return $args;
        }, 10, 2);

        $result = $upgrader->install($package);
        if (is_wp_error($result)) return $result;

        // Determine destination folder
        $dest = $upgrader->result['destination_name'] ?? $slug;
        if (!$dest) return new \WP_Error('wt_no_dest', 'Could not determine plugin destination.');

        if ($auto_activate) {
            $main = self::guess_main_file($dest);
            if ($main) {
                $act = activate_plugin($main);
                if (is_wp_error($act)) return $act;
            }
        }
        return true;
    }

    private static function guess_main_file(string $folder): ?string {
        $dir = WP_PLUGIN_DIR . '/' . $folder;
        if (!is_dir($dir)) return null;

        $candidate = $dir . '/' . $folder . '.php';
        if (file_exists($candidate)) return plugin_basename($candidate);

        $files = glob($dir . '/*.php') ?: [];
        foreach ($files as $file) {
            $data = get_file_data($file, ['Name' => 'Plugin Name'], 'plugin');
            if (!empty($data['Name'])) return plugin_basename($file);
        }
        return null;
    }

    private static function bootstrap(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        if (!defined('FS_METHOD'))   define('FS_METHOD', 'direct');
        if (!defined('WP_TEMP_DIR')) define('WP_TEMP_DIR', WP_CONTENT_DIR . '/tmp');

        @wp_mkdir_p(WP_CONTENT_DIR . '/upgrade');
        @wp_mkdir_p(WP_TEMP_DIR);

        WP_Filesystem();
    }
}