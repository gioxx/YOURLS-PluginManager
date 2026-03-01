<?php

function ypm_parse_github_repo_url($url) {
    $url = rtrim(trim((string) $url), '/');
    if (!preg_match('#^https://github\.com/([^/]+)/([^/]+)$#', $url, $m)) {
        return null;
    }
    return [
        'owner' => (string) $m[1],
        'repo' => (string) $m[2],
    ];
}

function ypm_extract_github_repo_from_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    $parsed = ypm_parse_github_repo_url($url);
    if ($parsed) {
        return $parsed;
    }

    // More permissive extractor used for Plugin URI headers.
    // Accepts additional path/query/fragment after owner/repo.
    if (preg_match('#^https://github\.com/([^/]+)/([^/?#]+)(?:[/?#].*)?$#', $url, $m)) {
        return [
            'owner' => (string) $m[1],
            'repo' => (string) $m[2],
        ];
    }

    return null;
}

function ypm_match_plugin_header_value($content, $field) {
    $content = (string) $content;
    $field = trim((string) $field);
    if ($content === '' || $field === '') {
        return '';
    }

    $pattern = '/^\s*(?:\*\s*)?' . preg_quote($field, '/') . ':\s*(.+)$/mi';
    if (!preg_match($pattern, $content, $matches)) {
        return '';
    }

    return trim((string) $matches[1]);
}

function ypm_get_plugin_header_value_from_file($plugin_file, $field) {
    $plugin_file = (string) $plugin_file;
    if ($plugin_file === '' || !file_exists($plugin_file)) {
        return '';
    }

    $content = file_get_contents($plugin_file);
    if ($content === false || $content === '') {
        return '';
    }

    return ypm_match_plugin_header_value($content, $field);
}

function ypm_plugin_file_has_valid_header($plugin_file) {
    $plugin_file = (string) $plugin_file;
    if ($plugin_file === '' || !file_exists($plugin_file)) {
        return false;
    }

    return ypm_get_plugin_header_value_from_file($plugin_file, 'Plugin Name') !== '';
}

function ypm_get_header_plugin_file_for_slug($slug, $active_plugins = null) {
    $slug = basename((string) $slug);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
        return '';
    }

    $direct_file_candidate = YOURLS_USERDIR . '/plugins/' . $slug . '.php';
    if (ypm_plugin_file_has_valid_header($direct_file_candidate)) {
        return $direct_file_candidate;
    }

    $plugin_dir = YOURLS_USERDIR . '/plugins/' . $slug;
    if (!is_dir($plugin_dir)) {
        return '';
    }

    if (!is_array($active_plugins)) {
        $active_plugins = yourls_get_option('active_plugins');
        if (!is_array($active_plugins)) {
            $active_plugins = [];
        }
    }

    foreach ($active_plugins as $active_entry) {
        $active_entry = trim((string) $active_entry);
        if ($active_entry === $slug . '.php') {
            $candidate = YOURLS_USERDIR . '/plugins/' . $active_entry;
            if (ypm_plugin_file_has_valid_header($candidate)) {
                return $candidate;
            }
            continue;
        }
        if (strpos($active_entry, $slug . '/') !== 0) {
            continue;
        }
        $relative = substr($active_entry, strlen($slug . '/'));
        if ($relative === '' || strpos($relative, '..') !== false) {
            continue;
        }
        $candidate = $plugin_dir . '/' . $relative;
        if (ypm_plugin_file_has_valid_header($candidate)) {
            return $candidate;
        }
    }

    $default_candidate = $plugin_dir . '/plugin.php';
    if (ypm_plugin_file_has_valid_header($default_candidate)) {
        return $default_candidate;
    }

    $php_files = glob($plugin_dir . '/*.php');
    if (is_array($php_files)) {
        foreach ($php_files as $php_file) {
            if (ypm_plugin_file_has_valid_header($php_file)) {
                return $php_file;
            }
        }
    }

    return '';
}

function ypm_is_plugin_slug_active($slug, $active_plugins = null) {
    $slug = basename((string) $slug);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
        return false;
    }

    if (!is_array($active_plugins)) {
        $active_plugins = yourls_get_option('active_plugins');
        if (!is_array($active_plugins)) {
            $active_plugins = [];
        }
    }

    foreach ($active_plugins as $active_entry) {
        $active_entry = trim((string) $active_entry);
        if ($active_entry === $slug . '.php' || strpos($active_entry, $slug . '/') === 0) {
            return true;
        }
    }

    return false;
}

function ypm_sanitize_filter($filter) {
    $allowed = ['all', 'updatable', 'no_repo', 'errors'];
    return in_array($filter, $allowed, true) ? $filter : 'all';
}

function ypm_default_plugin_slugs() {
    return ['hyphens-in-urls', 'random-bg', 'random-shorturls', 'sample-page', 'sample-plugin', 'sample-toolbar'];
}

function ypm_is_default_plugin($slug) {
    return in_array((string) $slug, ypm_default_plugin_slugs(), true);
}

function ypm_plugin_filter_bucket($slug, $update_statuses) {
    $repo_data = ypm_get_repo_binding($slug);
    $status = isset($update_statuses[$slug]['status']) ? (string) $update_statuses[$slug]['status'] : '';

    if (!$repo_data) {
        if (ypm_is_default_plugin($slug)) {
            return 'all';
        }
        return 'no_repo';
    }
    if ($status === 'error') {
        return 'errors';
    }
    if ($status === 'update_available') {
        return 'updatable';
    }
    return 'all';
}

function ypm_count_plugins_by_filter($plugins, $update_statuses) {
    $counts = [
        'all' => 0,
        'updatable' => 0,
        'no_repo' => 0,
        'errors' => 0,
    ];

    foreach ((array) $plugins as $plugin) {
        if (empty($plugin['slug'])) {
            continue;
        }
        $slug = (string) $plugin['slug'];
        $counts['all']++;
        $bucket = ypm_plugin_filter_bucket($slug, $update_statuses);
        if (isset($counts[$bucket])) {
            $counts[$bucket]++;
        }
    }

    return $counts;
}

function ypm_filter_plugins_for_view($plugins, $selected_filter, $update_statuses) {
    if ($selected_filter === 'all') {
        return $plugins;
    }

    $filtered = [];
    foreach ((array) $plugins as $plugin) {
        if (empty($plugin['slug'])) {
            continue;
        }
        $slug = (string) $plugin['slug'];
        $bucket = ypm_plugin_filter_bucket($slug, $update_statuses);
        if ($bucket === $selected_filter) {
            $filtered[] = $plugin;
        }
    }

    return $filtered;
}

function ypm_render_filter_link($filter, $label, $selected_filter, $count) {
    $count = (int) $count;
    $text = $label . ' (' . $count . ')';
    if ($filter === $selected_filter) {
        return '<strong>' . htmlentities($text) . '</strong>';
    }

    $href = '?page=plugin_manager&ypm_filter=' . rawurlencode($filter);
    return '<a href="' . $href . '">' . htmlentities($text) . '</a>';
}

function ypm_truncate($text, $max_len = 90) {
    $text = trim((string) $text);
    if (strlen($text) <= $max_len) {
        return $text;
    }
    return substr($text, 0, $max_len - 3) . '...';
}

function ypm_format_datetime($timestamp, $format = 'Y-m-d H:i') {
    $timestamp = (int) $timestamp;
    if ($timestamp <= 0) {
        return '';
    }

    if (defined('YOURLS_HOURS_OFFSET') && is_numeric(YOURLS_HOURS_OFFSET)) {
        $offset_seconds = (float) YOURLS_HOURS_OFFSET * 3600;
        return gmdate($format, (int) round($timestamp + $offset_seconds));
    }

    return date($format, $timestamp);
}

function ypm_asset_url($relative_path) {
    $relative_path = ltrim((string) $relative_path, '/');
    $plugin_dir = dirname(__DIR__);

    if (function_exists('yourls_plugin_url')) {
        return rtrim((string) yourls_plugin_url($plugin_dir), '/') . '/' . $relative_path;
    }

    if (defined('YOURLS_PLUGINDIRURL')) {
        $slug = basename($plugin_dir);
        return rtrim((string) YOURLS_PLUGINDIRURL, '/') . '/' . $slug . '/' . $relative_path;
    }

    if (defined('YOURLS_SITE') && defined('YOURLS_ABSPATH')) {
        $rel_plugin_dir = str_replace('\\', '/', str_replace((string) YOURLS_ABSPATH, '', $plugin_dir));
        $rel_plugin_dir = trim($rel_plugin_dir, '/');
        return rtrim((string) YOURLS_SITE, '/') . '/' . $rel_plugin_dir . '/' . $relative_path;
    }

    return '';
}
