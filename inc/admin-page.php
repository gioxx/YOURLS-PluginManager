<?php

// Hook: register admin page
yourls_add_action('plugins_loaded', 'ypm_register_plugin_page');
function ypm_register_plugin_page() {
    yourls_register_plugin_page(
        'plugin_manager',
        yourls__('Advanced Plugin Manager', 'yourls-plugin-manager'),
        'ypm_render_plugin_page'
    );
}

// Admin page content
yourls_add_action('plugins_loaded', 'ypm_load_textdomain');
function ypm_load_textdomain() {
    $locale = yourls_get_locale();
    $domain = 'yourls-plugin-manager';
    $path = dirname(__FILE__) . '/../languages/';

    if (file_exists($path . "{$domain}-{$locale}.mo")) {
        yourls_load_textdomain($domain, $path . "{$domain}-{$locale}.mo");
    } elseif (file_exists($path . "{$domain}-{$locale}.po")) {
        yourls_load_textdomain($domain, $path . "{$domain}-{$locale}.po");
    }
}

function ypm_delete_dir($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (!ypm_delete_dir($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

function ypm_render_plugin_page() {
    $admin_css = ypm_asset_url('assets/admin.css');
    if ($admin_css !== '') {
        $admin_css_version = YPM_VERSION;
        $admin_css_file = dirname(__DIR__) . '/assets/admin.css';
        if (file_exists($admin_css_file)) {
            $admin_css_version = (string) filemtime($admin_css_file);
        }
        echo '<link rel="stylesheet" href="' . htmlentities($admin_css) . '?v=' . rawurlencode($admin_css_version) . '" />';
    }
    $admin_js = ypm_asset_url('assets/admin.js');
    if ($admin_js !== '') {
        $admin_js_version = YPM_VERSION;
        $admin_js_file = dirname(__DIR__) . '/assets/admin.js';
        if (file_exists($admin_js_file)) {
            $admin_js_version = (string) filemtime($admin_js_file);
        }
        echo '<script src="' . htmlentities($admin_js) . '?v=' . rawurlencode($admin_js_version) . '"></script>';
    }

    $message = '';
    $result = ['success' => true, 'message' => ''];
    $selected_filter = ypm_sanitize_filter(isset($_GET['ypm_filter']) ? (string) $_GET['ypm_filter'] : 'all');

    if (isset($_POST['ypm_github_url']) && yourls_verify_nonce('ypm_install_plugin')) {
        $repo_url = trim((string) $_POST['ypm_github_url']);
        $result = ypm_process_github_url($repo_url);
        $message = $result['message'];
    }

    $force_edit_token = false;

    if (isset($_POST['ypm_save_token_submit']) && yourls_verify_nonce('ypm_save_token')) {
        yourls_update_option('ypm_github_token', trim((string) $_POST['ypm_github_token']));
        $result = ['success' => true, 'message' => yourls__('GitHub token saved successfully.', 'yourls-plugin-manager')];
        $message = $result['message'];
    }

    if (isset($_POST['ypm_delete_token']) && yourls_verify_nonce('ypm_save_token')) {
        yourls_delete_option('ypm_github_token');
        $result = ['success' => true, 'message' => yourls__('GitHub token deleted. You are now using unauthenticated API requests.', 'yourls-plugin-manager')];
        $message = $result['message'];
    }

    if (isset($_POST['ypm_edit_token']) && yourls_verify_nonce('ypm_save_token')) {
        $force_edit_token = true;
    }

    if (isset($_POST['ypm_save_links_per_page']) && yourls_verify_nonce('ypm_save_links_per_page')) {
        $raw_links_per_page = trim((string) ($_POST['ypm_links_per_page'] ?? ''));
        if ($raw_links_per_page === '') {
            yourls_delete_option('ypm_admin_view_per_page');
            $result = ['success' => true, 'message' => yourls__('Custom links-per-page value removed. YOURLS default is now active.', 'yourls-plugin-manager')];
        } elseif (ctype_digit($raw_links_per_page) && (int) $raw_links_per_page >= 1 && (int) $raw_links_per_page <= 999) {
            yourls_update_option('ypm_admin_view_per_page', (int) $raw_links_per_page);
            $result = ['success' => true, 'message' => yourls__('Custom links-per-page value saved.', 'yourls-plugin-manager')];
        } else {
            $result = ['success' => false, 'message' => yourls__('Links-per-page must be an integer between 1 and 999.', 'yourls-plugin-manager')];
        }
        $message = $result['message'];
    }

    if (isset($_POST['ypm_delete_plugin']) && yourls_verify_nonce('ypm_delete_plugin')) {
        $slug = basename((string) $_POST['ypm_delete_plugin']);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            $result = ['success' => false, 'message' => yourls__('Invalid plugin slug.', 'yourls-plugin-manager')];
        } else {
            $path = YOURLS_USERDIR . '/plugins/' . $slug;
            $success = ypm_delete_dir($path);
            if ($success) {
                ypm_clear_plugin_metadata($slug);
            }
            $result = [
                'success' => $success,
                'message' => $success
                    ? yourls__('Plugin deleted successfully.', 'yourls-plugin-manager')
                    : yourls__('Failed to delete plugin.', 'yourls-plugin-manager'),
            ];
        }
        $message = $result['message'];
    }

    if (isset($_POST['ypm_check_updates']) && yourls_verify_nonce('ypm_check_updates')) {
        $slugs = ypm_get_installed_plugin_slugs();
        ypm_check_updates_for_slugs($slugs);
        yourls_update_option('ypm_last_manual_check_at', time());
        $result = [
            'success' => true,
            'message' => sprintf(yourls__('Update check completed for %d plugin(s).', 'yourls-plugin-manager'), count($slugs)),
        ];
        $message = $result['message'];
    }

    if (isset($_POST['ypm_update_plugin']) && yourls_verify_nonce('ypm_update_plugin')) {
        $slug = basename((string) $_POST['ypm_update_plugin']);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            $result = ['success' => false, 'message' => yourls__('Invalid plugin slug.', 'yourls-plugin-manager')];
        } else {
            $repo_data = ypm_get_repo_binding($slug);
            if (!$repo_data) {
                $result = ['success' => false, 'message' => yourls__('No GitHub repository metadata available for this plugin.', 'yourls-plugin-manager')];
            } else {
                $result = ypm_process_github_url('https://github.com/' . $repo_data['owner'] . '/' . $repo_data['repo']);
            }
        }
        $message = $result['message'];
    }

    if (isset($_POST['ypm_update_all']) && yourls_verify_nonce('ypm_update_all')) {
        $statuses = ypm_check_updates_for_slugs(ypm_get_installed_plugin_slugs());
        yourls_update_option('ypm_last_manual_check_at', time());
        $to_update = [];
        foreach ($statuses as $slug => $status) {
            if (is_array($status) && isset($status['status']) && $status['status'] === 'update_available') {
                $to_update[] = basename((string) $slug);
            }
        }

        if (empty($to_update)) {
            $result = ['success' => true, 'message' => yourls__('No updates available right now.', 'yourls-plugin-manager')];
            $message = $result['message'];
        } else {
            $updated = 0;
            $failed = 0;
            foreach ($to_update as $slug) {
                $repo_data = ypm_get_repo_binding($slug);
                if (!$repo_data) {
                    $failed++;
                    continue;
                }
                $bulk_result = ypm_process_github_url('https://github.com/' . $repo_data['owner'] . '/' . $repo_data['repo']);
                if (!empty($bulk_result['success'])) {
                    $updated++;
                } else {
                    $failed++;
                }
            }
            ypm_check_updates_for_slugs($to_update);
            $result = [
                'success' => $failed === 0,
                'message' => sprintf(yourls__('Bulk update completed: %d updated, %d failed.', 'yourls-plugin-manager'), $updated, $failed),
            ];
            $message = $result['message'];
        }
    }

    if (isset($_POST['ypm_associate_repo_submit']) && yourls_verify_nonce('ypm_associate_repo')) {
        $slug = basename((string) ($_POST['ypm_associate_plugin'] ?? ''));
        $repo_url = trim((string) ($_POST['ypm_associate_repo_url'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
            $result = ['success' => false, 'message' => yourls__('Invalid plugin slug.', 'yourls-plugin-manager')];
        } elseif (ypm_is_default_plugin($slug)) {
            $result = ['success' => false, 'message' => yourls__('Default plugins do not require repository association.', 'yourls-plugin-manager')];
        } else {
            $parsed = ypm_parse_github_repo_url($repo_url);
            if (!$parsed) {
                $result = ['success' => false, 'message' => yourls__('Invalid GitHub URL.', 'yourls-plugin-manager')];
            } else {
                $token = trim((string) yourls_get_option('ypm_github_token'));
                $latest = ypm_get_latest_package_info($parsed['owner'], $parsed['repo'], $token);
                if (!$latest['success']) {
                    $error_text = trim((string) ($latest['error'] ?? ''));
                    $is_no_package_case = (int) $latest['http_code'] === 200
                        && stripos($error_text, 'Could not fetch any release or tag from GitHub.') !== false;
                    if ($is_no_package_case) {
                        $exists_check = ypm_github_repository_exists($parsed['owner'], $parsed['repo'], $token);
                        if (!empty($exists_check['exists'])) {
                            ypm_set_repo_binding($slug, $parsed['owner'], $parsed['repo']);
                            ypm_set_update_status($slug, ypm_check_single_plugin_update($slug, $token));
                            $result = [
                                'success' => true,
                                'message' => yourls__('Repository associated successfully. No release/tag is available yet; update checks may fail until one is published.', 'yourls-plugin-manager'),
                            ];
                        } else {
                            $result = [
                                'success' => false,
                                'message' => sprintf(
                                    yourls__('Cannot verify repository (HTTP %d): %s', 'yourls-plugin-manager'),
                                    (int) $exists_check['http_code'],
                                    htmlentities((string) $exists_check['error'])
                                ),
                            ];
                        }
                    } else {
                        $result = [
                            'success' => false,
                            'message' => sprintf(
                                yourls__('Cannot verify repository (HTTP %d): %s', 'yourls-plugin-manager'),
                                (int) $latest['http_code'],
                                htmlentities((string) $latest['error'])
                            ),
                        ];
                    }
                } else {
                    ypm_set_repo_binding($slug, $parsed['owner'], $parsed['repo']);
                    ypm_set_update_status($slug, ypm_check_single_plugin_update($slug, $token));
                    $result = ['success' => true, 'message' => yourls__('Repository associated successfully.', 'yourls-plugin-manager')];
                }
            }
        }
        $message = $result['message'];
    }

    echo '<div class="plugin-header">';
    echo '<h2 class="plugin-title">🔌 ' . yourls__('YOURLS Advanced Plugin Manager', 'yourls-plugin-manager') . '</h2>';
    echo '<p class="plugin-version">' . yourls__('Version: ' . YPM_VERSION, 'yourls-plugin-manager') . '</p>';
    echo '</div>';

    if ($message) {
        echo '<div class="ypm-notice ' . ($result['success'] ? 'ypm-notice-success' : 'ypm-notice-error') . '">' . $message . '</div>';
    }

    $installed_slugs = ypm_get_installed_plugin_slugs();
    ypm_auto_associate_repo_bindings($installed_slugs);
    $update_statuses = ypm_get_update_statuses();
    $available_updates_count = 0;
    foreach ($installed_slugs as $installed_slug) {
        if (
            isset($update_statuses[$installed_slug]['status'])
            && $update_statuses[$installed_slug]['status'] === 'update_available'
        ) {
            $available_updates_count++;
        }
    }
    $active_plugins = yourls_get_option('active_plugins');
    if (!is_array($active_plugins)) {
        $active_plugins = [];
    }
    $legacy_display_links_plugin_installed = false;
    foreach ($installed_slugs as $installed_slug) {
        $normalized_slug = basename((string) $installed_slug);
        if ($normalized_slug === 'display-links' || $normalized_slug === 'custom-number-of-displayed-links') {
            $legacy_display_links_plugin_installed = true;
            break;
        }
        $legacy_plugin_file = ypm_get_header_plugin_file_for_slug($normalized_slug, $active_plugins);
        if ($legacy_plugin_file === '') {
            continue;
        }
        $legacy_plugin_name = strtolower(trim((string) ypm_get_plugin_header_value_from_file($legacy_plugin_file, 'Plugin Name')));
        if ($legacy_plugin_name === 'custom number of displayed links') {
            $legacy_display_links_plugin_installed = true;
            break;
        }
    }
    $active_installed_count = 0;
    foreach ($installed_slugs as $installed_slug) {
        if (ypm_is_plugin_slug_active($installed_slug, $active_plugins)) {
            $active_installed_count++;
        }
    }
    $legacy_count = ypm_count_plugins_without_repo_metadata($installed_slugs);
    $default_installed_count = 0;
    foreach ($installed_slugs as $installed_slug) {
        if (ypm_is_default_plugin($installed_slug)) {
            $default_installed_count++;
        }
    }
    $last_auto_check = (int) yourls_get_option('ypm_scheduled_update_check_at');
    $last_manual_check = (int) yourls_get_option('ypm_last_manual_check_at');
    $auto_check_label = $last_auto_check > 0 ? ypm_format_datetime($last_auto_check) : yourls__('Not run yet', 'yourls-plugin-manager');
    $manual_check_label = $last_manual_check > 0 ? ypm_format_datetime($last_manual_check) : yourls__('Not run yet', 'yourls-plugin-manager');
    $next_auto_check_ts = $last_auto_check > 0 ? ($last_auto_check + 86400) : (time() + 86400);
    $next_auto_check_label = ypm_format_datetime($next_auto_check_ts);
    $stored_token = yourls_get_option('ypm_github_token');
    $stored_links_per_page = (int) yourls_get_option('ypm_admin_view_per_page');
    $has_token = !empty($stored_token);
    $token_box_visible = $force_edit_token
        || isset($_POST['ypm_save_token_submit'])
        || isset($_POST['ypm_delete_token'])
        || isset($_POST['ypm_edit_token'])
        || isset($_POST['ypm_save_links_per_page']);

    $drawer_is_token = $token_box_visible;
    echo '<div class="ypm-drawer ' . ($drawer_is_token ? 'is-token-open' : 'is-main-open') . '" id="ypm-main-drawer">';
    echo '<button type="button" class="ypm-drawer-toggle ypm-drawer-toggle-install ypm-drawer-panel-toggle ' . ($drawer_is_token ? '' : 'is-active') . '" id="ypm-main-drawer-toggle-install" data-panel="main" aria-expanded="' . ($drawer_is_token ? 'false' : 'true') . '">';
    echo yourls__('Plugin install', 'yourls-plugin-manager');
    echo '</button>';
    echo '<div class="ypm-drawer-panels">';
    echo '<div class="ypm-drawer-track">';

    echo '<div class="ypm-panel-main">';
    echo '<div class="form-section">';
    echo '<form method="post" id="github-plugin-form">';
    echo '<label for="ypm_github_url"><strong>' . yourls__('GitHub Repository URL:', 'yourls-plugin-manager') . '</strong></label><br>';
    echo '<small class="ypm-help-text ypm-help-install">'
        . yourls__('Insert a public GitHub repository URL (owner/repo). The plugin downloads the latest Release package, or falls back to the latest Tag when no Release is available.', 'yourls-plugin-manager')
        . '</small>';
    echo '<input type="url" name="ypm_github_url" id="ypm_github_url" size="70" placeholder="https://github.com/username/plugin" required class="ypm-install-url" />';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_install_plugin') . '" />';
    echo '<div class="ypm-submit-row"><input type="submit" value="📦 ' . yourls__('Download and Install Plugin', 'yourls-plugin-manager') . '" class="button button-primary" /></div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div class="ypm-panel-token">';
    echo '<div class="form-section">';
    echo '<form method="post" id="github-token-form">';
    echo '<div class="form-row">';
    echo '<label for="ypm_github_token"><strong>' . yourls__('GitHub Personal Access Token (optional):', 'yourls-plugin-manager') . '</strong></label>';
    echo '<small class="ypm-help-text ypm-help-token">'
        . yourls__('By default, GitHub allows 60 unauthenticated API requests per hour per IP; with a personal access token, this limit increases to 5,000 requests per hour.', 'yourls-plugin-manager') . ' '
        . yourls__('To generate a token, visit your GitHub account settings:', 'yourls-plugin-manager') . ' '
        . '<a href="https://github.com/settings/tokens/new" target="_blank" rel="noopener noreferrer">'
        . yourls__('Create a new GitHub token', 'yourls-plugin-manager') . '</a>. '
        . yourls__('No scopes are required – you can leave all permissions unchecked.', 'yourls-plugin-manager')
        . '</small>';
    echo '<div class="ypm-token-input-row">';
    echo '<input type="password" name="ypm_github_token" id="ypm_github_token" class="ypm-token-input" value="' . yourls_esc_attr($stored_token) . '" ' . ($has_token && !$force_edit_token ? 'readonly' : '') . ' />';
    echo '<button type="button" class="button ypm-token-toggle ypm-token-visibility-toggle">👁</button>';
    echo '</div>';
    echo '</div>';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_save_token') . '" />';
    if ($has_token) {
        echo '<input type="submit" name="ypm_delete_token" value="🗑 ' . yourls__('Delete Token', 'yourls-plugin-manager') . '" class="button ypm-button-gap-right" />';
        echo '<input type="submit" name="ypm_edit_token" value="✏️ ' . yourls__('Edit Token', 'yourls-plugin-manager') . '" class="button" />';
    } else {
        echo '<input type="submit" name="ypm_save_token_submit" value="💾 ' . yourls__('Save Token', 'yourls-plugin-manager') . '" class="button button-primary" />';
    }
    echo '</form>';
    echo '</div>';

    echo '<div class="form-section">';
    echo '<form method="post" id="ypm-links-per-page-form">';
    echo '<div class="form-row">';
    echo '<label for="ypm_links_per_page"><strong>' . yourls__('Links displayed per page in YOURLS admin:', 'yourls-plugin-manager') . '</strong></label>';
    echo '<small class="ypm-help-text ypm-help-token">' . yourls__('Leave this field empty to use the YOURLS default value.', 'yourls-plugin-manager') . '</small>';
    if ($legacy_display_links_plugin_installed) {
        echo '<small class="ypm-help-text ypm-help-token ypm-settings-alert">'
            . '<span class="ypm-settings-alert-icon" aria-hidden="true">⚠️</span> '
            . yourls__('The plugin "Custom number of displayed links" appears to be installed. You can deactivate and delete it, because this feature is now built into Plugin Manager.', 'yourls-plugin-manager')
            . '</small>';
    }
    echo '<input type="number" name="ypm_links_per_page" id="ypm_links_per_page" class="ypm-token-input" min="1" max="999" step="1" value="' . ($stored_links_per_page > 0 ? (int) $stored_links_per_page : '') . '" placeholder="' . yourls_esc_attr(yourls__('YOURLS default', 'yourls-plugin-manager')) . '" />';
    echo '</div>';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_save_links_per_page') . '" />';
    echo '<input type="submit" name="ypm_save_links_per_page" value="💾 ' . yourls__('Save links-per-page', 'yourls-plugin-manager') . '" class="button button-primary" />';
    echo '<small class="ypm-help-text ypm-help-token"><a href="https://github.com/YOURLS/YOURLS/issues/2339#issuecomment-352127623" target="_blank" rel="noopener noreferrer">'
        . yourls__('Based on the snippet shared by ozh in YOURLS issue #2339.', 'yourls-plugin-manager')
        . '</a></small>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';
    echo '<button type="button" class="ypm-drawer-toggle ypm-drawer-toggle-token ypm-drawer-panel-toggle ' . ($drawer_is_token ? 'is-active' : '') . '" id="ypm-main-drawer-toggle-token" data-panel="token" aria-expanded="' . ($drawer_is_token ? 'true' : 'false') . '">';
    echo yourls__('Settings', 'yourls-plugin-manager');
    echo '</button>';
    echo '</div>';

    echo '<div class="ypm-installed-header">';
    echo '<div class="ypm-installed-header-top">';
    echo '<h1 class="ypm-section-title">' . yourls__('Installed Plugins', 'yourls-plugin-manager') . '</h1>';
    echo '<div class="ypm-installed-actions">';
    echo '<form method="get" action="' . yourls_esc_attr(yourls_admin_url('plugins.php')) . '">';
    echo '<button type="submit" class="button">🧩 ' . yourls__('Manage', 'yourls-plugin-manager') . '</button>';
    echo '</form>';
    echo '<form method="post">';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_check_updates') . '" />';
    echo '<button type="submit" name="ypm_check_updates" class="button">🔎 ' . yourls__('Check updates now', 'yourls-plugin-manager') . '</button>';
    echo '</form>';
    echo '<form method="post">';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_update_all') . '" />';
    $update_all_label = $available_updates_count > 0
        ? sprintf(yourls__('Update all available (%d)', 'yourls-plugin-manager'), $available_updates_count)
        : yourls__('Update all available', 'yourls-plugin-manager');
    $update_all_disabled_attr = $available_updates_count > 0 ? '' : ' disabled title="' . yourls_esc_attr(yourls__('No updates currently available. Run "Check updates now" first.', 'yourls-plugin-manager')) . '"';
    echo '<button type="submit" name="ypm_update_all" class="button"' . $update_all_disabled_attr . '>⬆️ ' . $update_all_label . '</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ypm-installed-meta">';
    echo '<div class="ypm-installed-meta-summary">' . sprintf(
        yourls__('%d plugin(s) installed, %d active.', 'yourls-plugin-manager'),
        count($installed_slugs),
        $active_installed_count
    ) . '</div>';
    if ($legacy_count > 0 || $default_installed_count > 0) {
        $notes = [];
        if ($legacy_count > 0) {
            $notes[] = sprintf(
                yourls__('%d plugin(s) were installed before repository metadata tracking and may show "No repository metadata" until reinstalled/updated once via Plugin Manager.', 'yourls-plugin-manager'),
                $legacy_count
            );
        }
        if ($default_installed_count > 0) {
            $notes[] = sprintf(
                yourls__('%d plugin(s) do not require repository association because they are installed by default.', 'yourls-plugin-manager'),
                $default_installed_count
            );
        }
        echo '<div class="ypm-installed-meta-note">' . implode(' ', $notes) . '</div>';
    }
    echo '<div><strong>' . yourls__('Automatic checks', 'yourls-plugin-manager') . ':</strong> ';
    echo yourls__('GitHub update checks run automatically every 24 hours.', 'yourls-plugin-manager') . '</div>';
    echo '<div class="ypm-installed-meta-times">';
    echo '<div><strong>' . yourls__('Last automatic check:', 'yourls-plugin-manager') . '</strong> <span class="ypm-meta-time">' . htmlentities($auto_check_label) . '</span></div>';
    echo '<div class="ypm-next-check-row"><strong>' . yourls__('Last manual check:', 'yourls-plugin-manager') . '</strong> <span class="ypm-meta-time">' . htmlentities($manual_check_label) . '</span></div>';
    echo '<div class="ypm-next-check-row"><strong>' . yourls__('Next scheduled check:', 'yourls-plugin-manager') . '</strong> <span class="ypm-meta-time">' . htmlentities($next_auto_check_label) . '</span></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    $plugins = [];

    foreach ($installed_slugs as $slug) {
        $slug = basename((string) $slug);
        $plugin_file = ypm_get_header_plugin_file_for_slug($slug, $active_plugins);
        if ($plugin_file === '') {
            continue;
        }
        $name = $slug;
        $version = 'unknown';
        $author = 'unknown';
        $plugin_uri = '';

        $header_name = ypm_get_plugin_header_value_from_file($plugin_file, 'Plugin Name');
        $header_version = ypm_get_plugin_header_value_from_file($plugin_file, 'Version');
        $header_author = ypm_get_plugin_header_value_from_file($plugin_file, 'Author');
        $header_plugin_uri = ypm_get_plugin_header_value_from_file($plugin_file, 'Plugin URI');

        if ($header_name !== '') {
            $name = $header_name;
        }
        if ($header_version !== '') {
            $version = $header_version;
        }
        if ($header_author !== '') {
            $author = $header_author;
        }
        if ($header_plugin_uri !== '') {
            $plugin_uri = $header_plugin_uri;
        }

        if ($name === 'unknown') {
            $name = yourls__('Unknown Plugin', 'yourls-plugin-manager');
        }
        if ($author === 'unknown') {
            $author = yourls__('Unknown Author', 'yourls-plugin-manager');
        }
        if ($version === 'unknown') {
            $version = yourls__('Unknown Version', 'yourls-plugin-manager');
        }

        $plugins[] = [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'author' => $author,
            'plugin_uri' => $plugin_uri,
        ];
    }

    usort($plugins, function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    $filter_counts = ypm_count_plugins_by_filter($plugins, $update_statuses);
    $plugins = ypm_filter_plugins_for_view($plugins, $selected_filter, $update_statuses);

    echo '<div class="ypm-filters">';
    echo ypm_render_filter_link('all', yourls__('All', 'yourls-plugin-manager'), $selected_filter, count($installed_slugs));
    echo ' | ' . ypm_render_filter_link('updatable', yourls__('Updatable', 'yourls-plugin-manager'), $selected_filter, $filter_counts['updatable']);
    echo ' | ' . ypm_render_filter_link('no_repo', yourls__('No metadata', 'yourls-plugin-manager'), $selected_filter, $filter_counts['no_repo']);
    echo ' | ' . ypm_render_filter_link('errors', yourls__('Errors', 'yourls-plugin-manager'), $selected_filter, $filter_counts['errors']);
    echo '</div>';

    echo '<table class="widefat ypm-plugins-table">';
    echo '<thead><tr>';
    echo '<th class="ypm-col-name">' . yourls__('Plugin Name', 'yourls-plugin-manager') . '</th>';
    echo '<th class="ypm-col-author">' . yourls__('Author', 'yourls-plugin-manager') . '</th>';
    echo '<th class="ypm-col-version">' . yourls__('Version', 'yourls-plugin-manager') . '</th>';
    echo '<th class="ypm-col-updated">' . yourls__('Last Updated', 'yourls-plugin-manager') . '</th>';
    echo '<th class="ypm-col-status">' . yourls__('Status', 'yourls-plugin-manager') . '</th>';
    echo '<th class="ypm-col-actions">' . yourls__('Actions', 'yourls-plugin-manager') . '</th>';
    echo '</tr></thead><tbody>';

    if (empty($plugins)) {
        echo '<tr><td colspan="6" class="ypm-empty-state">' . yourls__('No plugins match the selected filter.', 'yourls-plugin-manager') . '</td></tr>';
    }

    foreach ($plugins as $plugin) {
        $is_active = ypm_is_plugin_slug_active($plugin['slug'], $active_plugins);
        $is_default_plugin = ypm_is_default_plugin($plugin['slug']);

        $last_updated_ts = yourls_get_option('ypm_last_updated_' . $plugin['slug']);
        if ($last_updated_ts) {
            $formatted = ypm_format_datetime((int) $last_updated_ts);
            if ((time() - (int) $last_updated_ts) <= 86400) {
                $plugin['last_updated'] = '<span title="' . $formatted . '">🆕 ' . yourls__('Updated recently', 'yourls-plugin-manager') . '</span>';
            } else {
                $plugin['last_updated'] = $formatted;
            }
        } else {
            $plugin['last_updated'] = $is_default_plugin
                ? '<span class="ypm-text-muted">' . yourls__('Installed by default', 'yourls-plugin-manager') . '</span>'
                : '<span class="ypm-text-muted">' . yourls__('Never', 'yourls-plugin-manager') . '</span>';
        }

        $update_status = isset($update_statuses[$plugin['slug']]) && is_array($update_statuses[$plugin['slug']]) ? $update_statuses[$plugin['slug']] : null;
        $repo_data = ypm_get_repo_binding($plugin['slug']);

        $update_badge = '';
        $update_details = '';
        if ($update_status && isset($update_status['status'])) {
            $remote_version = isset($update_status['remote_version']) ? trim((string) $update_status['remote_version']) : '';
            if ($update_status['status'] === 'update_available') {
                $update_badge = '<br><span class="ypm-status-update-available">' . yourls__('Update available', 'yourls-plugin-manager') . ($remote_version !== '' ? ': ' . htmlentities($remote_version) : '') . '</span>';
            } elseif ($update_status['status'] === 'up_to_date') {
                $update_badge = '<br><span class="ypm-status-up-to-date">' . yourls__('Up to date', 'yourls-plugin-manager') . '</span>';
            } elseif ($update_status['status'] === 'no_repo' && !$is_default_plugin) {
                $update_badge = '<br><span class="ypm-status-no-repo">' . yourls__('No repository metadata', 'yourls-plugin-manager') . '</span>';
            } elseif ($update_status['status'] === 'error') {
                $update_badge = '<br><span class="ypm-status-error">' . yourls__('Update check failed', 'yourls-plugin-manager') . '</span>';
            }
            if (
                !$is_default_plugin
                && $update_status['status'] !== 'no_repo'
                && !empty($update_status['checked_at'])
            ) {
                $update_details .= '<br><span class="ypm-check-detail">' . yourls__('Checked:', 'yourls-plugin-manager') . ' ' . ypm_format_datetime((int) $update_status['checked_at']) . '</span>';
            }
            if (!$is_default_plugin && $update_status['status'] === 'error' && !empty($update_status['message'])) {
                $error_text = (string) $update_status['message'];
                $update_details .= '<br><span class="ypm-check-error" title="' . htmlentities($error_text) . '">' . htmlentities(ypm_truncate($error_text, 90)) . '</span>';
            }
        }

        echo '<tr>';
        $plugin_name_html = htmlentities($plugin['name']);
        $plugin_uri = isset($plugin['plugin_uri']) ? trim((string) $plugin['plugin_uri']) : '';
        if ($plugin_uri !== '' && filter_var($plugin_uri, FILTER_VALIDATE_URL)) {
            $plugin_name_html = '<a href="' . htmlentities($plugin_uri) . '" target="_blank" rel="noopener noreferrer" class="ypm-plugin-name-link">' . $plugin_name_html . '</a>';
        }
        echo '<td>' . $plugin_name_html . '</td>';
        echo '<td>' . htmlentities($plugin['author']) . '</td>';
        echo '<td>' . htmlentities($plugin['version']) . '</td>';
        echo '<td>' . $plugin['last_updated'] . $update_badge . $update_details . '</td>';
        echo '<td>';
        echo $is_active
            ? '<span class="ypm-status-active">' . yourls__('Active', 'yourls-plugin-manager') . '</span>'
            : '<span class="ypm-status-inactive">' . yourls__('Inactive', 'yourls-plugin-manager') . '</span>';
        echo '</td>';

        echo '<td class="ypm-actions-cell">';
        if ($update_status && isset($update_status['status']) && $update_status['status'] === 'update_available') {
            echo '<form method="post" class="ypm-inline-form ypm-inline-form-spaced ypm-update-form">';
            echo '<input type="hidden" name="ypm_update_plugin" value="' . $plugin['slug'] . '" />';
            echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_update_plugin') . '" />';
            echo '<input type="submit" class="button ypm-update-button" value="⬆️ ' . yourls__('Update', 'yourls-plugin-manager') . '" />';
            echo '</form>';
        }

        echo '<div class="ypm-actions-row">';
        if ($repo_data && !$is_default_plugin) {
            echo '<button type="button" class="button ypm-open-associate" data-plugin-slug="' . htmlentities($plugin['slug']) . '" data-plugin-name="' . htmlentities($plugin['name']) . '" data-repo-url="' . yourls_esc_attr((string) ($repo_data['url'] ?? '')) . '">';
            echo '<img src="https://github.githubassets.com/favicons/favicon.png" alt="" class="ypm-github-btn-icon" />';
            echo '<span>' . yourls__('Change repo', 'yourls-plugin-manager') . '</span>';
            echo '</button>';
        } elseif (!$repo_data && !$is_default_plugin) {
            echo '<button type="button" class="button ypm-open-associate" data-plugin-slug="' . htmlentities($plugin['slug']) . '" data-plugin-name="' . htmlentities($plugin['name']) . '">';
            echo '<img src="https://github.githubassets.com/favicons/favicon.png" alt="" class="ypm-github-btn-icon" />';
            echo '<span>' . yourls__('Associate repo', 'yourls-plugin-manager') . '</span>';
            echo '</button>';
        } elseif (!$repo_data && $is_default_plugin) {
            echo '<button type="button" class="button ypm-open-associate" disabled title="' . yourls_esc_attr(yourls__('Repository association not needed for default plugins.', 'yourls-plugin-manager')) . '">';
            echo '<img src="https://github.githubassets.com/favicons/favicon.png" alt="" class="ypm-github-btn-icon" />';
            echo '<span>' . yourls__('Associate repo', 'yourls-plugin-manager') . '</span>';
            echo '</button>';
        }

        echo '<form method="post" class="ypm-inline-form">';
        echo '<input type="hidden" name="ypm_delete_plugin" value="' . $plugin['slug'] . '" />';
        echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_delete_plugin') . '" />';
        if ($plugin['slug'] === 'yourls-plugin-manager') {
            echo '<button type="submit" class="button ypm-delete-icon-button" aria-label="' . yourls_esc_attr(yourls__('Delete', 'yourls-plugin-manager')) . '" title="' . yourls_esc_attr(yourls__('Delete', 'yourls-plugin-manager')) . '" disabled><span class="ypm-delete-icon" aria-hidden="true">🗑</span></button>';
        } elseif ($is_active) {
            echo '<button type="submit" class="button ypm-delete-icon-button" aria-label="' . yourls_esc_attr(yourls__('Delete', 'yourls-plugin-manager')) . '" title="' . yourls__('This plugin is active and cannot be deleted.', 'yourls-plugin-manager') . '" disabled><span class="ypm-delete-icon" aria-hidden="true">🗑</span></button>';
        } else {
            echo '<button type="submit" class="button ypm-delete-confirm ypm-delete-icon-button" data-confirm-message="' . yourls_esc_attr(yourls__('Are you sure you want to delete this plugin?', 'yourls-plugin-manager')) . '" aria-label="' . yourls_esc_attr(yourls__('Delete', 'yourls-plugin-manager')) . '" title="' . yourls_esc_attr(yourls__('Delete', 'yourls-plugin-manager')) . '"><span class="ypm-delete-icon" aria-hidden="true">🗑</span></button>';
        }
        echo '</form>';
        echo '</div>';

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<div class="ypm-table-infobox">';
    echo '<div class="ypm-table-infobox-title"><span class="ypm-table-infobox-icon" aria-hidden="true">ℹ️</span><strong>' . yourls__('Notes', 'yourls-plugin-manager') . '</strong></div>';
    echo '<ul class="ypm-table-infobox-list">';
    echo '<li class="ypm-table-infobox-note">' . yourls__('Repository association not needed for default plugins.', 'yourls-plugin-manager') . '</li>';
    echo '<li class="ypm-table-infobox-note">' . yourls__('Times shown in this page follow your YOURLS timezone configuration, or server time if that configuration is unavailable.', 'yourls-plugin-manager') . '</li>';
    echo '</ul>';
    echo '</div>';
    echo '<div class="ypm-modal" id="ypm-associate-modal" aria-hidden="true">';
    echo '<div class="ypm-modal-backdrop ypm-modal-close-action"></div>';
    echo '<div class="ypm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ypm-associate-title">';
    echo '<button type="button" class="ypm-modal-close ypm-modal-close-action" aria-label="' . yourls_esc_attr(yourls__('Close', 'yourls-plugin-manager')) . '">×</button>';
    echo '<h3 id="ypm-associate-title">' . yourls__('Associate repo', 'yourls-plugin-manager') . '</h3>';
    echo '<form method="post" class="ypm-modal-form">';
    echo '<div class="ypm-modal-plugin-line"><strong>' . yourls__('Plugin Name', 'yourls-plugin-manager') . ':</strong> <span id="ypm-associate-plugin-name"></span></div>';
    echo '<label for="ypm-associate-repo-url"><strong>' . yourls__('GitHub Repository URL:', 'yourls-plugin-manager') . '</strong></label>';
    echo '<p class="ypm-modal-help">' . yourls__('Insert a public GitHub repository URL (owner/repo). The plugin downloads the latest Release package, or falls back to the latest Tag when no Release is available.', 'yourls-plugin-manager') . '</p>';
    echo '<input type="hidden" name="ypm_associate_plugin" id="ypm-associate-plugin" value="" />';
    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_associate_repo') . '" />';
    echo '<input type="url" name="ypm_associate_repo_url" id="ypm-associate-repo-url" class="ypm-associate-input ypm-associate-input-modal" placeholder="https://github.com/owner/repo" required />';
    echo '<div class="ypm-modal-actions">';
    echo '<button type="button" class="button ypm-modal-close-action">' . yourls__('Cancel', 'yourls-plugin-manager') . '</button>';
    echo '<input type="submit" name="ypm_associate_repo_submit" class="button button-primary" value="🔗 ' . yourls__('Associate repo', 'yourls-plugin-manager') . '" />';
    echo '</div>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div class="plugin-footer">';
    echo '<a href="https://github.com/gioxx/YOURLS-PluginManager" target="_blank" rel="noopener noreferrer">';
    echo '<img src="https://github.githubassets.com/favicons/favicon.png" class="github-icon" alt="GitHub Icon" />';
    echo 'YOURLS Plugin Manager</a><br>';
    echo '❤️ Lovingly developed by the usually-on-vacation brain cell of ';
    echo '<a href="https://github.com/gioxx" target="_blank" rel="noopener noreferrer">Gioxx</a> – ';
    echo '<a href="https://gioxx.org" target="_blank" rel="noopener noreferrer">Gioxx\'s Wall</a>';
    echo '</div>';
}
