<?php

function ypm_process_github_url($url, $branch = '', $version = '') {
    if (!class_exists('ZipArchive')) {
        return [
            'success' => false,
            'message' => yourls__('ZIP extraction requires the PHP ZipArchive extension, which is not available on this server.', 'yourls-plugin-manager'),
        ];
    }

    $parsed = ypm_parse_github_repo_url($url);
    if (!$parsed) {
        return ['success' => false, 'message' => yourls__('Invalid GitHub URL.', 'yourls-plugin-manager')];
    }

    $owner = $parsed['owner'];
    $repo = $parsed['repo'];
    $token = trim((string) yourls_get_option('ypm_github_token'));

    $latest = ypm_resolve_package_info($owner, $repo, $token, $branch, $version);
    if (!$latest['success']) {
        return [
            'success' => false,
            'message' => sprintf(
                yourls__('GitHub API request failed (HTTP %d): %s', 'yourls-plugin-manager'),
                (int) $latest['http_code'],
                htmlentities((string) $latest['error'])
            ),
        ];
    }

    $plugins_dir = YOURLS_USERDIR . '/plugins';
    if (!is_dir($plugins_dir) || !is_writable($plugins_dir)) {
        return [
            'success' => false,
            'message' => ypm_build_manual_install_message($latest['zip_url'], $plugins_dir, $latest),
        ];
    }

    $tmp_file = download_url($latest['zip_url'], $token);
    if (!$tmp_file || !is_string($tmp_file) || !file_exists($tmp_file)) {
        return ['success' => false, 'message' => yourls__('Download failed.', 'yourls-plugin-manager')];
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp_file) === true) {
        $extract_ok = $zip->extractTo($plugins_dir);
        $zip->close();
        @unlink($tmp_file);
        if (!$extract_ok) {
            return [
                'success' => false,
                'message' => yourls__('Extraction failed: unable to extract ZIP archive.', 'yourls-plugin-manager'),
            ];
        }
    } else {
        @unlink($tmp_file);
        return [
            'success' => false,
            'message' => yourls__('Extraction failed: unable to open ZIP archive.', 'yourls-plugin-manager'),
        ];
    }

    $active_plugins = (array) yourls_get_option('active_plugins');
    $plugin_basename = $repo . '/plugin.php';
    $was_active = in_array($plugin_basename, $active_plugins, true);
    $is_deactivated = false;

    $deactivate_plugin = function () use ($plugin_basename, $was_active, &$is_deactivated) {
        if (!$was_active || $is_deactivated) {
            return;
        }
        $active_plugins = (array) yourls_get_option('active_plugins');
        $active_plugins = array_values(array_filter($active_plugins, function ($plugin) use ($plugin_basename) {
            return $plugin !== $plugin_basename;
        }));
        yourls_update_option('active_plugins', $active_plugins);
        $is_deactivated = true;
    };

    $restore_plugin = function () use ($plugin_basename, $was_active, &$is_deactivated) {
        if (!$was_active || !$is_deactivated) {
            return;
        }
        $active_plugins = (array) yourls_get_option('active_plugins');
        if (!in_array($plugin_basename, $active_plugins, true)) {
            $active_plugins[] = $plugin_basename;
            yourls_update_option('active_plugins', array_values($active_plugins));
        }
        $is_deactivated = false;
    };

    $wildcard = $plugins_dir . '/' . $owner . '-' . $repo . '-*';
    $found = glob($wildcard);
    $target_dir = $plugins_dir . '/' . $repo;
    $extracted = null;
    $target_replaced = false;

    if (!empty($found)) {
        $extracted = $found[0];
        $plugin_file_root = $extracted . '/plugin.php';

        if (file_exists($plugin_file_root)) {
            $contents = file_get_contents($plugin_file_root);
            if (ypm_match_plugin_header_value($contents, 'Plugin Name') !== '') {
                if (is_dir($target_dir)) {
                    $deactivate_plugin();
                    if (!ypm_delete_dir($target_dir)) {
                        $restore_plugin();
                        return ['success' => false, 'message' => yourls__('Failed to replace existing plugin directory.', 'yourls-plugin-manager')];
                    }
                }
                if (!rename($extracted, $target_dir)) {
                    $restore_plugin();
                    return ['success' => false, 'message' => yourls__('Failed to move extracted plugin directory.', 'yourls-plugin-manager')];
                }
                $target_replaced = true;
            }
        } else {
            $subdirs = array_filter(glob($extracted . '/*'), 'is_dir');
            foreach ($subdirs as $dir) {
                $plugin_file = $dir . '/plugin.php';
                if (!file_exists($plugin_file)) {
                    continue;
                }
                $contents = file_get_contents($plugin_file);
                if (ypm_match_plugin_header_value($contents, 'Plugin Name') === '') {
                    continue;
                }

                if (is_dir($target_dir)) {
                    $deactivate_plugin();
                    if (!ypm_delete_dir($target_dir)) {
                        $restore_plugin();
                        return ['success' => false, 'message' => yourls__('Failed to replace existing plugin directory.', 'yourls-plugin-manager')];
                    }
                }
                if (!rename($dir, $target_dir)) {
                    $restore_plugin();
                    return ['success' => false, 'message' => yourls__('Failed to move extracted plugin directory.', 'yourls-plugin-manager')];
                }
                $target_replaced = true;
                ypm_delete_dir($extracted);
                break;
            }
        }
    }

    $plugin_php = $target_dir . '/plugin.php';
    if (!file_exists($plugin_php)) {
        if ($target_replaced && is_dir($target_dir)) {
            ypm_delete_dir($target_dir);
        }
        if (!empty($extracted) && is_dir($extracted)) {
            ypm_delete_dir($extracted);
        }
        $restore_plugin();
        return [
            'success' => false,
            'message' => yourls__('plugin.php not found in final plugin directory.', 'yourls-plugin-manager'),
        ];
    }

    $content = file_get_contents($plugin_php);
    if (ypm_match_plugin_header_value($content, 'Plugin Name') === '') {
        if ($target_replaced && is_dir($target_dir)) {
            ypm_delete_dir($target_dir);
        }
        if (!empty($extracted) && is_dir($extracted)) {
            ypm_delete_dir($extracted);
        }
        $restore_plugin();
        return [
            'success' => false,
            'message' => yourls__('plugin.php does not contain a valid YOURLS plugin header.', 'yourls-plugin-manager'),
        ];
    }

    yourls_update_option('ypm_last_updated_' . $repo, time());
    ypm_set_repo_binding($repo, $owner, $repo);
    ypm_set_update_status($repo, [
        'status' => 'up_to_date',
        'remote_version' => (string) $latest['version'],
        'checked_at' => time(),
        'source' => $latest['source'],
        'message' => '',
    ]);

    $restore_plugin();

    return [
        'success' => true,
        'message' => yourls__('Plugin installed or updated successfully.', 'yourls-plugin-manager'),
    ];
}

function ypm_build_manual_install_message($zip_url, $plugins_dir, $latest = []) {
    $plugins_dir = (string) $plugins_dir;
    $zip_url = (string) $zip_url;
    $version = '';
    $source = '';

    if (is_array($latest)) {
        $version = trim((string) ($latest['version'] ?? ''));
        $source = trim((string) ($latest['source'] ?? ''));
    }

    $intro = sprintf(
        yourls__('Automatic installation is not possible because YOURLS cannot write to %s. Download the ZIP package below and extract it manually into that directory.', 'yourls-plugin-manager'),
        htmlentities($plugins_dir)
    );
    $meta = '';
    if ($version !== '' || $source !== '') {
        $meta = sprintf(
            '<br /><em>%s %s</em>',
            htmlentities(yourls__('Latest package:', 'yourls-plugin-manager')),
            htmlentities(trim($version . ($source !== '' ? ' (' . $source . ')' : '')))
        );
    }

    return sprintf(
        '<p>%s%s</p><p><a class="button button-primary" href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
        $intro,
        $meta,
        htmlentities($zip_url),
        htmlentities(yourls__('Download ZIP package', 'yourls-plugin-manager'))
    );
}
