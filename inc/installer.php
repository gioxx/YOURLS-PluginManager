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

/**
 * Install a plugin from an uploaded .zip file. Accepts two layouts:
 *  - Flat: plugin.php sits at the ZIP root. Slug is derived from the upload
 *    filename (foo-bar.zip -> foo-bar).
 *  - Nested: a single top-level directory inside the ZIP holds plugin.php.
 *    Slug is the directory name.
 *
 * The installed plugin is never auto-activated; the user must enable it from
 * the standard YOURLS plugins page or from the Activate button in this UI.
 */
function ypm_process_uploaded_zip($uploaded_file) {
    if (!class_exists('ZipArchive')) {
        return [
            'success' => false,
            'message' => yourls__('ZIP extraction requires the PHP ZipArchive extension, which is not available on this server.', 'yourls-plugin-manager'),
        ];
    }

    if (!is_array($uploaded_file)) {
        return ['success' => false, 'message' => yourls__('No file was uploaded.', 'yourls-plugin-manager')];
    }

    $error_code = isset($uploaded_file['error']) ? (int) $uploaded_file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error_code !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => ypm_describe_upload_error($error_code)];
    }

    $tmp_name = (string) ($uploaded_file['tmp_name'] ?? '');
    $original_name = (string) ($uploaded_file['name'] ?? '');
    if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
        return ['success' => false, 'message' => yourls__('Upload was rejected (not a valid HTTP upload).', 'yourls-plugin-manager')];
    }

    if (strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION)) !== 'zip') {
        return ['success' => false, 'message' => yourls__('Only .zip files are accepted.', 'yourls-plugin-manager')];
    }

    $plugins_dir = YOURLS_USERDIR . '/plugins';
    if (!is_dir($plugins_dir) || !is_writable($plugins_dir)) {
        return [
            'success' => false,
            'message' => sprintf(
                yourls__('YOURLS cannot write to %s. Adjust the directory permissions and try again.', 'yourls-plugin-manager'),
                htmlentities($plugins_dir)
            ),
        ];
    }

    // Move the upload into a temp file we control.
    $controlled_zip = tempnam(sys_get_temp_dir(), 'ypm_upload_');
    if ($controlled_zip === false || !move_uploaded_file($tmp_name, $controlled_zip)) {
        return ['success' => false, 'message' => yourls__('Failed to move the uploaded file into a temporary location.', 'yourls-plugin-manager')];
    }

    // Stage the extraction in a hidden directory inside plugins/. Using the
    // plugins directory keeps subsequent rename() calls on the same filesystem.
    try {
        $stage_dir = $plugins_dir . '/.ypm-upload-' . bin2hex(random_bytes(8));
    } catch (\Throwable $e) {
        $stage_dir = $plugins_dir . '/.ypm-upload-' . dechex(mt_rand()) . dechex(time());
    }
    if (!@mkdir($stage_dir, 0755) || !is_dir($stage_dir)) {
        @unlink($controlled_zip);
        return ['success' => false, 'message' => yourls__('Failed to create a staging directory for extraction.', 'yourls-plugin-manager')];
    }

    $zip = new ZipArchive();
    if ($zip->open($controlled_zip) !== true) {
        @unlink($controlled_zip);
        ypm_delete_dir($stage_dir);
        return ['success' => false, 'message' => yourls__('Extraction failed: unable to open ZIP archive.', 'yourls-plugin-manager')];
    }

    // Reject zip-slip attempts: any entry with "../" or absolute paths.
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry_name = (string) $zip->getNameIndex($i);
        if ($entry_name === '' || strpos($entry_name, '..') !== false || $entry_name[0] === '/' || preg_match('#^[a-zA-Z]:[\\\\/]#', $entry_name)) {
            $zip->close();
            @unlink($controlled_zip);
            ypm_delete_dir($stage_dir);
            return ['success' => false, 'message' => yourls__('Extraction failed: ZIP archive contains unsafe paths.', 'yourls-plugin-manager')];
        }
    }

    if (!$zip->extractTo($stage_dir)) {
        $zip->close();
        @unlink($controlled_zip);
        ypm_delete_dir($stage_dir);
        return ['success' => false, 'message' => yourls__('Extraction failed: unable to extract ZIP archive.', 'yourls-plugin-manager')];
    }
    $zip->close();
    @unlink($controlled_zip);

    // Determine layout: plugin.php at stage root OR inside a single subdir.
    $plugin_dir_in_stage = '';
    $detected_slug = '';
    $is_flat_layout = false;

    $root_plugin_php = $stage_dir . '/plugin.php';
    if (file_exists($root_plugin_php)) {
        $contents = (string) file_get_contents($root_plugin_php);
        if (ypm_match_plugin_header_value($contents, 'Plugin Name') !== '') {
            $plugin_dir_in_stage = $stage_dir;
            $detected_slug = ypm_slugify_filename_for_plugin($original_name);
            $is_flat_layout = true;
        }
    }

    if ($plugin_dir_in_stage === '') {
        $entries = @scandir($stage_dir);
        $entries = is_array($entries) ? array_values(array_diff($entries, ['.', '..'])) : [];
        $subdirs = [];
        foreach ($entries as $entry) {
            $candidate = $stage_dir . '/' . $entry;
            if (is_dir($candidate)) {
                $subdirs[] = $candidate;
            }
        }
        if (count($subdirs) === 1) {
            $sub = $subdirs[0];
            $sub_plugin_php = $sub . '/plugin.php';
            if (file_exists($sub_plugin_php)) {
                $contents = (string) file_get_contents($sub_plugin_php);
                if (ypm_match_plugin_header_value($contents, 'Plugin Name') !== '') {
                    $plugin_dir_in_stage = $sub;
                    $detected_slug = basename($sub);
                }
            }
        }
    }

    if ($plugin_dir_in_stage === '' || $detected_slug === '') {
        ypm_delete_dir($stage_dir);
        return [
            'success' => false,
            'message' => yourls__('No valid YOURLS plugin.php was found at the ZIP root or inside a single top-level directory.', 'yourls-plugin-manager'),
        ];
    }

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $detected_slug)) {
        ypm_delete_dir($stage_dir);
        return [
            'success' => false,
            'message' => sprintf(
                yourls__('Computed plugin slug "%s" contains invalid characters. Rename the ZIP or its top-level folder to use only letters, digits, dots, underscores or hyphens.', 'yourls-plugin-manager'),
                htmlentities($detected_slug)
            ),
        ];
    }

    $target_dir = $plugins_dir . '/' . $detected_slug;

    // If the target already exists, deactivate the old plugin first (the same
    // safety dance ypm_process_github_url does for GitHub installs), then
    // delete and replace.
    $active_plugins = (array) yourls_get_option('active_plugins');
    $plugin_basename = $detected_slug . '/plugin.php';
    $was_active = in_array($plugin_basename, $active_plugins, true);

    if (is_dir($target_dir)) {
        if ($was_active) {
            $active_plugins = array_values(array_filter($active_plugins, function ($p) use ($plugin_basename) {
                return $p !== $plugin_basename;
            }));
            yourls_update_option('active_plugins', $active_plugins);
        }
        if (!ypm_delete_dir($target_dir)) {
            ypm_delete_dir($stage_dir);
            // Best effort: restore active flag so the user is left in a clean state.
            if ($was_active) {
                $active_plugins[] = $plugin_basename;
                yourls_update_option('active_plugins', array_values(array_unique($active_plugins)));
            }
            return ['success' => false, 'message' => yourls__('Failed to replace existing plugin directory.', 'yourls-plugin-manager')];
        }
    }

    if ($is_flat_layout) {
        if (!@rename($stage_dir, $target_dir)) {
            ypm_delete_dir($stage_dir);
            return ['success' => false, 'message' => yourls__('Failed to move extracted plugin directory.', 'yourls-plugin-manager')];
        }
    } else {
        if (!@rename($plugin_dir_in_stage, $target_dir)) {
            ypm_delete_dir($stage_dir);
            return ['success' => false, 'message' => yourls__('Failed to move extracted plugin directory.', 'yourls-plugin-manager')];
        }
        // Drop the now-empty staging shell.
        ypm_delete_dir($stage_dir);
    }

    // Confirm the final layout still has a valid plugin.php.
    $final_plugin_php = $target_dir . '/plugin.php';
    if (!file_exists($final_plugin_php)) {
        ypm_delete_dir($target_dir);
        return ['success' => false, 'message' => yourls__('plugin.php not found in final plugin directory.', 'yourls-plugin-manager')];
    }
    $final_contents = (string) file_get_contents($final_plugin_php);
    if (ypm_match_plugin_header_value($final_contents, 'Plugin Name') === '') {
        ypm_delete_dir($target_dir);
        return ['success' => false, 'message' => yourls__('plugin.php does not contain a valid YOURLS plugin header.', 'yourls-plugin-manager')];
    }

    yourls_update_option('ypm_last_updated_' . $detected_slug, time());

    // Try to harvest a Plugin URI -> GitHub binding, but never auto-activate.
    $plugin_uri = ypm_get_plugin_header_value_from_file($final_plugin_php, 'Plugin URI');
    if ($plugin_uri !== '') {
        $parsed = ypm_extract_github_repo_from_url(trim($plugin_uri));
        if ($parsed) {
            ypm_set_repo_binding($detected_slug, $parsed['owner'], $parsed['repo']);
        }
    }

    return [
        'success' => true,
        'message' => sprintf(
            yourls__('Plugin "%s" uploaded successfully. Activate it from the plugins list when you are ready.', 'yourls-plugin-manager'),
            htmlentities($detected_slug)
        ),
    ];
}

function ypm_slugify_filename_for_plugin($filename) {
    $base = (string) pathinfo((string) $filename, PATHINFO_FILENAME);
    $base = trim($base);
    if ($base === '') {
        return '';
    }
    // GitHub release ZIPs often look like `repo-1.2.3.zip`. Strip a trailing
    // `-1.2.3` so the resulting slug matches the GitHub install behaviour.
    $base = preg_replace('/-v?\d+(\.\d+)*([\-+].+)?$/', '', $base);
    // Replace any character outside the YOURLS-safe slug regex with hyphens.
    $base = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $base);
    $base = trim((string) $base, '-._');
    return (string) $base;
}

function ypm_describe_upload_error($error_code) {
    switch ((int) $error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return yourls__('Uploaded file exceeds the upload_max_filesize value in php.ini.', 'yourls-plugin-manager');
        case UPLOAD_ERR_FORM_SIZE:
            return yourls__('Uploaded file exceeds the form MAX_FILE_SIZE.', 'yourls-plugin-manager');
        case UPLOAD_ERR_PARTIAL:
            return yourls__('The uploaded file was only partially received.', 'yourls-plugin-manager');
        case UPLOAD_ERR_NO_FILE:
            return yourls__('No file was uploaded.', 'yourls-plugin-manager');
        case UPLOAD_ERR_NO_TMP_DIR:
            return yourls__('PHP has no temporary directory available for uploads.', 'yourls-plugin-manager');
        case UPLOAD_ERR_CANT_WRITE:
            return yourls__('Failed to write the uploaded file to disk.', 'yourls-plugin-manager');
        case UPLOAD_ERR_EXTENSION:
            return yourls__('A PHP extension stopped the upload.', 'yourls-plugin-manager');
        default:
            return yourls__('Unknown upload error.', 'yourls-plugin-manager');
    }
}
