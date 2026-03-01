<?php

function ypm_get_installed_plugin_slugs() {
    $plugin_dir = YOURLS_USERDIR . '/plugins/';
    $folders = array_filter(glob($plugin_dir . '*'), 'is_dir');
    $slugs = [];
    $active_plugins = yourls_get_option('active_plugins');
    if (!is_array($active_plugins)) {
        $active_plugins = [];
    }
    foreach ($folders as $folder) {
        $slug = basename($folder);
        $plugin_file = ypm_get_header_plugin_file_for_slug($slug, $active_plugins);
        if ($plugin_file === '') {
            continue;
        }
        $slugs[] = $slug;
    }
    return $slugs;
}

function ypm_get_repo_map() {
    $map = yourls_get_option('ypm_repo_map');
    return is_array($map) ? $map : [];
}

function ypm_save_repo_map($map) {
    yourls_update_option('ypm_repo_map', is_array($map) ? $map : []);
}

function ypm_set_repo_binding($slug, $owner, $repo) {
    $slug = basename((string) $slug);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
        return;
    }
    $map = ypm_get_repo_map();
    $map[$slug] = [
        'owner' => (string) $owner,
        'repo' => (string) $repo,
        'url' => 'https://github.com/' . $owner . '/' . $repo,
        'updated_at' => time(),
    ];
    ypm_save_repo_map($map);
}

function ypm_get_repo_binding($slug) {
    $slug = basename((string) $slug);
    $map = ypm_get_repo_map();
    if (!isset($map[$slug]) || !is_array($map[$slug])) {
        return null;
    }
    if (empty($map[$slug]['owner']) || empty($map[$slug]['repo'])) {
        return null;
    }
    return $map[$slug];
}

function ypm_auto_associate_repo_bindings($slugs) {
    $added = 0;
    foreach ((array) $slugs as $slug) {
        $slug = basename((string) $slug);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            continue;
        }
        if (ypm_is_default_plugin($slug)) {
            continue;
        }
        if (ypm_get_repo_binding($slug)) {
            continue;
        }

        $plugin_file = ypm_get_header_plugin_file_for_slug($slug);
        if ($plugin_file === '') {
            continue;
        }
        $plugin_uri = ypm_get_plugin_header_value_from_file($plugin_file, 'Plugin URI');
        if ($plugin_uri === '') {
            continue;
        }

        $parsed = ypm_extract_github_repo_from_url(trim((string) $plugin_uri));
        if (!$parsed) {
            continue;
        }

        ypm_set_repo_binding($slug, $parsed['owner'], $parsed['repo']);
        $added++;
    }

    return $added;
}

function ypm_count_plugins_without_repo_metadata($slugs) {
    $count = 0;
    foreach ((array) $slugs as $slug) {
        $slug = basename((string) $slug);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            continue;
        }
        if (ypm_is_default_plugin($slug)) {
            continue;
        }
        if (!ypm_get_repo_binding($slug)) {
            $count++;
        }
    }
    return $count;
}

function ypm_get_update_statuses() {
    $statuses = yourls_get_option('ypm_update_statuses');
    return is_array($statuses) ? $statuses : [];
}

function ypm_set_update_status($slug, $status_data) {
    $slug = basename((string) $slug);
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
        return;
    }
    $statuses = ypm_get_update_statuses();
    $statuses[$slug] = is_array($status_data) ? $status_data : [];
    yourls_update_option('ypm_update_statuses', $statuses);
}

function ypm_clear_plugin_metadata($slug) {
    $slug = basename((string) $slug);

    $map = ypm_get_repo_map();
    if (isset($map[$slug])) {
        unset($map[$slug]);
        ypm_save_repo_map($map);
    }

    $statuses = ypm_get_update_statuses();
    if (isset($statuses[$slug])) {
        unset($statuses[$slug]);
        yourls_update_option('ypm_update_statuses', $statuses);
    }
}

function ypm_check_updates_for_slugs($slugs) {
    ypm_auto_associate_repo_bindings($slugs);
    $token = trim((string) yourls_get_option('ypm_github_token'));
    $statuses = ypm_get_update_statuses();

    foreach ((array) $slugs as $slug) {
        $slug = basename((string) $slug);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            continue;
        }
        $statuses[$slug] = ypm_check_single_plugin_update($slug, $token);
    }

    yourls_update_option('ypm_update_statuses', $statuses);
    return $statuses;
}

function ypm_check_single_plugin_update($slug, $token = '') {
    $repo_data = ypm_get_repo_binding($slug);
    if (!$repo_data) {
        return [
            'status' => 'no_repo',
            'remote_version' => '',
            'checked_at' => time(),
            'source' => '',
            'message' => '',
        ];
    }

    $latest = ypm_get_latest_package_info($repo_data['owner'], $repo_data['repo'], $token);
    if (!$latest['success']) {
        return [
            'status' => 'error',
            'remote_version' => '',
            'checked_at' => time(),
            'source' => '',
            'message' => (string) $latest['error'],
        ];
    }

    $local_version = ypm_get_local_plugin_version($slug);
    $remote_version = trim((string) $latest['version']);
    $is_update_available = ypm_is_remote_version_newer($local_version, $remote_version);

    return [
        'status' => $is_update_available ? 'update_available' : 'up_to_date',
        'remote_version' => $remote_version,
        'checked_at' => time(),
        'source' => (string) $latest['source'],
        'message' => '',
    ];
}

function ypm_get_local_plugin_version($slug) {
    $slug = basename((string) $slug);
    $plugin_file = ypm_get_header_plugin_file_for_slug($slug);
    if ($plugin_file === '') {
        return '';
    }

    return ypm_get_plugin_header_value_from_file($plugin_file, 'Version');
}

function ypm_is_remote_version_newer($local_version, $remote_version) {
    $local_version = trim((string) $local_version);
    $remote_version = trim((string) $remote_version);
    if ($local_version === '' || $remote_version === '') {
        return false;
    }

    $normalized_local = ltrim($local_version, 'vV');
    $normalized_remote = ltrim($remote_version, 'vV');
    $is_semver_like = '/^[0-9]+(\.[0-9]+)*([\-+].+)?$/';

    if (
        preg_match($is_semver_like, $normalized_local)
        && preg_match($is_semver_like, $normalized_remote)
    ) {
        return version_compare($normalized_remote, $normalized_local, '>');
    }

    return $normalized_remote !== $normalized_local;
}
