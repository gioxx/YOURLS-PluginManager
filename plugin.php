<?php
/*
Plugin Name: YOURLS Plugin Manager
Plugin URI: https://github.com/gioxx/YOURLS-PluginManager
Description: Download and install plugins from GitHub repositories directly from the YOURLS admin interface.
Version: 1.0.12
Author: Gioxx
Author URI: https://gioxx.org
Text Domain: yourls-plugin-manager
Domain Path: /languages
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die();

define( 'YPM_VERSION', '1.0.12' );

// Hook: register admin page
yourls_add_action('plugins_loaded', 'ypm_register_plugin_page');
function ypm_register_plugin_page() {
    yourls_register_plugin_page(
        'plugin_manager',
        yourls__( 'Plugin Manager', 'yourls-plugin-manager' ),
        'ypm_render_plugin_page'
    );
}

// Admin page content
yourls_add_action('plugins_loaded', 'ypm_load_textdomain');
function ypm_load_textdomain() {
    $locale = yourls_get_locale();
    $domain = 'yourls-plugin-manager';
    $path = dirname(__FILE__) . '/languages/';

    if ( file_exists( $path . "{$domain}-{$locale}.mo" ) ) {
        yourls_load_textdomain( $domain, $path . "{$domain}-{$locale}.mo" );
    } elseif ( file_exists( $path . "{$domain}-{$locale}.po" ) ) {
        yourls_load_textdomain( $domain, $path . "{$domain}-{$locale}.po" );
    }
}

// Handle plugin deletion
function ypm_delete_dir($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!ypm_delete_dir($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// Move directory recursively
// This function is used to move the extracted plugin folder to the correct location
function ypm_move_dir($src, $dst) {
    if (!is_dir($src)) return false;
    if (!mkdir($dst, 0755, true) && !is_dir($dst)) return false;

    foreach (scandir($src) as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;

        if (is_dir($s)) {
            ypm_move_dir($s, $d);
        } else {
            rename($s, $d);
        }
    }
    return true;
}

function ypm_render_plugin_page() {
    echo '<style>
        .plugin-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }
        .plugin-title {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 2em;
            font-weight: bold;
            background: -webkit-linear-gradient(#0073aa, #00a8e6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .plugin-version {
            font-size: 0.85em;
            color: #666;
            margin-top: 4px;
        }
        .form-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            border-radius: 5px;
        }
        .form-row {
            margin-bottom: 15px;
        }
        .form-row label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-row input[type="url"] {
            width: 100%;
            max-width: 600px;
            padding: 5px;
        }
        input[type="submit"].button {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 5px;
            cursor: pointer;
        }
        table.widefat {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.widefat th,
        table.widefat td {
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table.widefat tbody tr:nth-child(odd) {
            background-color: #f4f8fc;
        }
        table.widefat tbody tr:nth-child(even) {
            background-color: #ffffff;
        }
        input[type="submit"].button:disabled {
            background-color: #ccc;
            color: #666;
            border: 1px solid #bbb;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .plugin-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 0.9em;
            color: #666;
            text-align: center;
            opacity: 0.85;
        }
        .plugin-footer a {
            color: #0073aa;
            text-decoration: none;
        }
        .plugin-footer a:hover {
            text-decoration: underline;
        }
        .plugin-footer .github-icon {
            vertical-align: middle;
            width: 16px;
            height: 16px;
            margin-right: 4px;
            display: inline-block;
        }
    </style>';

    $message = '';

    // Handle plugin installation
    if ( isset($_POST['ypm_github_url']) && yourls_verify_nonce('ypm_install_plugin') ) {
        $repo_url = trim($_POST['ypm_github_url']);
        $result = ypm_process_github_url($repo_url);
        $message = $result['message'];
    }

    // Save or delete GitHub token
    if (isset($_POST['ypm_save_token_submit']) && yourls_verify_nonce('ypm_save_token')) {
        yourls_update_option('ypm_github_token', trim($_POST['ypm_github_token']));
        $message = yourls__('GitHub token saved successfully.', 'yourls-plugin-manager');
        $result = ['success' => true];
    }

    if (isset($_POST['ypm_delete_token']) && yourls_verify_nonce('ypm_save_token')) {
        yourls_delete_option('ypm_github_token');
        $message = yourls__('GitHub token deleted. You are now using unauthenticated API requests.', 'yourls-plugin-manager');
        $result = ['success' => true];
    }

    if (isset($_POST['ypm_edit_token']) && yourls_verify_nonce('ypm_save_token')) {
        // Allow user to edit the field again
        yourls_update_option('ypm_github_token', ''); // clear temporarily
    }

    // Handle plugin deletion
    if (isset($_POST['ypm_delete_plugin']) && yourls_verify_nonce('ypm_delete_plugin')) {
        $slug = basename($_POST['ypm_delete_plugin']);
        $path = YOURLS_USERDIR . '/plugins/' . $slug;
        $success = ypm_delete_dir($path);
        $message = $success
            ? yourls__('Plugin deleted successfully.', 'yourls-plugin-manager')
            : yourls__('Failed to delete plugin.', 'yourls-plugin-manager');
        $result = ['success' => $success, 'message' => $message];
    }

    echo '<div class="plugin-header">';
    echo '<h2 class="plugin-title">🔌 ' . yourls__('YOURLS Plugin Manager', 'yourls-plugin-manager') . '</h2>';
    echo '<p class="plugin-version">' . yourls__('Version: ' . YPM_VERSION, 'yourls-plugin-manager') . '</p>';
    echo '</div>';

    if ( $message ) {
        echo '<div style="margin: 10px 0; padding: 10px; border-left: 4px solid ' . ($result['success'] ? '#46b450' : '#dc3232') . '; background: ' . ($result['success'] ? '#e6ffed' : '#fbeaea') . ';">' . $message . '</div>';
    }
    $nonce = yourls_create_nonce('ypm_install_plugin');
    
    echo '<div class="form-section">';
    echo '<form method="post" id="github-plugin-form">';
    echo '<label for="ypm_github_url"><strong>' . yourls__('GitHub Repository URL:', 'yourls-plugin-manager') . '</strong></label><br>'; 
    echo '<input type="url" name="ypm_github_url" id="ypm_github_url" size="70" placeholder="https://github.com/username/plugin" required style="margin-top:5px;padding:5px;" />';
    echo '<input type="hidden" name="nonce" value="' . $nonce . '" />';
    echo '<br><br><input type="submit" value="📦 ' . yourls__('Download and Install Plugin', 'yourls-plugin-manager') . '" class="button button-primary" />';
    echo '</form>';
    echo '</div>';

    // Section for GitHub API token
    echo '<div class="form-section">';
    echo '<form method="post" id="github-token-form">';
    echo '<div class="form-row">';
    echo '<label for="ypm_github_token">' . yourls__('GitHub Personal Access Token (optional):', 'yourls-plugin-manager') . '</label>';

    $stored_token = yourls_get_option('ypm_github_token');
    $has_token = !empty($stored_token);

    echo '<input type="password" name="ypm_github_token" id="ypm_github_token" style="width:100%;max-width:600px;" value="' . yourls_esc_attr($stored_token) . '" ' . ($has_token ? 'readonly' : '') . ' />';
    echo '<button type="button" onclick="toggleTokenVisibility()" class="button" style="margin-left:10px;">👁</button>';
    echo '<br><small style="display:block;margin-top:8px;line-height:1.5em;">' .
     yourls__('By default, GitHub allows 60 unauthenticated API requests per hour per IP.', 'yourls-plugin-manager') . '<br>' .
     yourls__('With a personal access token, this limit increases to 5,000 requests per hour.', 'yourls-plugin-manager') . '<br>' .
     yourls__('To generate a token, visit your GitHub account settings:', 'yourls-plugin-manager') . ' ' .
     '<a href="https://github.com/settings/tokens/new" target="_blank" rel="noopener noreferrer">' .
     yourls__('Create a new GitHub token', 'yourls-plugin-manager') . '</a>.' . '<br>' .
     yourls__('No scopes are required – you can leave all permissions unchecked.', 'yourls-plugin-manager') .
     '</small>';
    echo '</div>';

    echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_save_token') . '" />';

    if ($has_token) {
        echo '<input type="submit" name="ypm_delete_token" value="🗑 ' . yourls__('Delete Token', 'yourls-plugin-manager') . '" class="button" style="margin-right:10px;" />';
        echo '<input type="submit" name="ypm_edit_token" value="✏️ ' . yourls__('Edit Token', 'yourls-plugin-manager') . '" class="button" />';
    } else {
        echo '<input type="submit" name="ypm_save_token_submit" value="💾 ' . yourls__('Save Token', 'yourls-plugin-manager') . '" class="button button-primary" />';
    }

    echo '</form>';
    echo '</div>';

    echo <<<JS
    <script>
    function toggleTokenVisibility() {
        const field = document.getElementById('ypm_github_token');
        field.type = (field.type === 'password') ? 'text' : 'password';
    }
    </script>
    JS;

    // Show installed plugins with delete option
    echo '<h3>' . yourls__('Installed Plugins', 'yourls-plugin-manager') . '</h3>';
    $plugin_dir = YOURLS_USERDIR . '/plugins/';
    $folders = array_filter(glob($plugin_dir . '*'), 'is_dir');

    $plugins = [];
    $active_plugins = yourls_get_option('active_plugins');
    $default_plugins = [
        'hyphens-in-urls',
        'random-bg',
        'random-shorturls',
        'sample-page',
        'sample-plugin',
        'sample-toolbar'
    ];    

    foreach ($folders as $folder) {
        $slug = basename($folder);
        $plugin_file = $folder . '/plugin.php';

        $name = $slug;
        $version = 'unknown';
        $author = 'unknown';

        if (file_exists($plugin_file)) {
            $content = file_get_contents($plugin_file);
            if (preg_match('/^\\s*Plugin Name:\\s*(.+)$/mi', $content, $matches)) {
                $name = trim($matches[1]);
            }
            if (preg_match('/^\\s*Version:\\s*(.+)$/mi', $content, $matches)) {
                $version = trim($matches[1]);
            }
            if (preg_match('/^\\s*Author:\\s*(.+)$/mi', $content, $matches)) {
                $author = trim($matches[1]);
            }
        }

        // Fallbacks
        if ($name == 'unknown') {
            $name = yourls__('Unknown Plugin', 'yourls-plugin-manager');
        }
        if ($author == 'unknown') {
            $author = yourls__('Unknown Author', 'yourls-plugin-manager');
        }
        if ($version == 'unknown') {
            $version = yourls__('Unknown Version', 'yourls-plugin-manager');
        }

        $plugins[] = [
            'slug' => $slug,
            'name' => $name,
            'version' => $version,
            'author' => $author,
        ];
    }

    // Sort plugins alphabetically by name
    usort($plugins, function($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    // Render table
    echo '<table class="widefat" style="margin-top:10px; width:100%; border-collapse: collapse;">';
    echo '<thead><tr>';
    echo '<th style="width:30%; text-align:left;">' . yourls__('Plugin Name', 'yourls-plugin-manager') . '</th>';
    echo '<th style="width:20%; text-align:left;">' . yourls__('Author', 'yourls-plugin-manager') . '</th>';
    echo '<th style="width:15%; text-align:left;">' . yourls__('Version', 'yourls-plugin-manager') . '</th>';
    echo '<th style="width:25%; text-align:left;">' . yourls__('Last Updated', 'yourls-plugin-manager') . '</th>';
    echo '<th style="width:15%; text-align:left;">' . yourls__('Status', 'yourls-plugin-manager') . '</th>';
    echo '<th style="width:15%; text-align:left;">' . yourls__('Actions', 'yourls-plugin-manager') . '</th>';
    echo '</tr></thead><tbody>';

    foreach ($plugins as $plugin) {
        // Check for active plugins
        $is_active = in_array($plugin['slug'] . '/plugin.php', $active_plugins);
        
        // Last updated
        $last_updated_ts = yourls_get_option('ypm_last_updated_' . $plugin['slug']);
        if ($last_updated_ts) {
            $now = time();
            $recent_threshold = 86400; // 24h
            $formatted = date('Y-m-d H:i', $last_updated_ts);

            if (($now - $last_updated_ts) <= $recent_threshold) {
                $plugin['last_updated'] = '<span title="' . $formatted . '">🆕 ' . yourls__('Updated recently', 'yourls-plugin-manager') . '</span>';
            } else {
                $plugin['last_updated'] = $formatted;
            }
        } else {
            if (in_array($plugin['slug'], $default_plugins)) {
                $plugin['last_updated'] = '<span style="color:#aaa;">' . yourls__('Installed by default', 'yourls-plugin-manager') . '</span>';
            } else {
                $plugin['last_updated'] = '<span style="color:#aaa;">' . yourls__('Never', 'yourls-plugin-manager') . '</span>';
            }
        }

        // Display plugin row
        echo '<tr>';
        echo '<td>' . htmlentities($plugin['name']) . '</td>';
        echo '<td>' . htmlentities($plugin['author']) . '</td>';
        echo '<td>' . htmlentities($plugin['version']) . '</td>';
        echo '<td>' . $plugin['last_updated'] . '</td>';
        echo '<td>';
        echo $is_active
            ? '<span style="color:green;">' . yourls__('Active', 'yourls-plugin-manager') . '</span>'
            : '<span style="color:#999;">' . yourls__('Inactive', 'yourls-plugin-manager') . '</span>';
        echo '</td>';
        
        echo '<td>';
        echo '<form method="post" style="margin:0;">';
        echo '<input type="hidden" name="ypm_delete_plugin" value="' . $plugin['slug'] . '" />';
        echo '<input type="hidden" name="nonce" value="' . yourls_create_nonce('ypm_delete_plugin') . '" />';
        if ($plugin['slug'] === 'yourls-plugin-manager') {
            echo '<input type="submit" class="button" value="🗑️ ' . yourls__('Delete', 'yourls-plugin-manager') . '" disabled />';
        } elseif ($is_active) {
            echo '<input type="submit" class="button" value="🗑️ ' . yourls__('Delete', 'yourls-plugin-manager') . '" disabled title="' . yourls__('This plugin is active and cannot be deleted.', 'yourls-plugin-manager') . '" />';
        } else {
            echo '<input type="submit" class="button" onclick="return confirm(\'' . yourls__('Are you sure you want to delete this plugin?', 'yourls-plugin-manager') . '\');" value="🗑️ ' . yourls__('Delete', 'yourls-plugin-manager') . '" />';
        }
        echo '</form>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<div class="plugin-footer">';
    echo '<a href="https://github.com/gioxx/YOURLS-PluginManager" target="_blank" rel="noopener noreferrer">';
    echo '<img src="https://github.githubassets.com/favicons/favicon.png" class="github-icon" alt="GitHub Icon" />';
    echo 'YOURLS Plugin Manager</a><br>';
    echo '❤️ Lovingly developed by the usually-on-vacation brain cell of ';
    echo '<a href="https://github.com/gioxx" target="_blank" rel="noopener noreferrer">Gioxx</a> – ';
    echo '<a href="https://gioxx.org" target="_blank" rel="noopener noreferrer">Gioxx\'s Wall</a>';
    echo '</div>';

}

// Process GitHub URL and install plugin
function ypm_process_github_url($url) {
    // Check if ZipArchive extension is available
    if (!class_exists('ZipArchive')) {
        return [
            'success' => false,
            'message' => yourls__('ZIP extraction requires the PHP ZipArchive extension, which is not available on this server.', 'yourls-plugin-manager')
        ];
    }

    // Validate GitHub URL format
    $url = rtrim($url, '/');
    if (!preg_match('#^https://github\.com/([^/]+)/([^/]+)$#', $url, $m)) {
        return ['success' => false, 'message' => yourls__('Invalid GitHub URL.', 'yourls-plugin-manager')];
    }

    $owner = $m[1];
    $repo = $m[2];

    // Check if the plugin is already installed and active, and if so, deactivate it before update it
    $active_plugins = yourls_get_option('active_plugins');
    $plugin_basename = $repo . '/plugin.php';
    $was_active = in_array($plugin_basename, $active_plugins);
    if ($was_active) {
        $active_plugins = array_filter($active_plugins, fn($p) => $p !== $plugin_basename);
        yourls_update_option('active_plugins', array_values($active_plugins));
    }

    // Try to get latest release
    $api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";
    $response = ypm_remote_get($api_url);

    // Fallback to tags if no release is found
    if (!$response || !isset($response['zipball_url'])) {
        $tags_api = "https://api.github.com/repos/$owner/$repo/tags";
        $tags_response = ypm_remote_get($tags_api);

        if (!$tags_response || !is_array($tags_response) || !isset($tags_response[0]['zipball_url'])) {
            return ['success' => false, 'message' => yourls__('Could not fetch any release or tag from GitHub.', 'yourls-plugin-manager')];
        }

        $zip_url = $tags_response[0]['zipball_url'];
    } else {
        $zip_url = $response['zipball_url'];
    }

    // Download ZIP file
    $tmp_file = download_url($zip_url);
    if (!file_exists($tmp_file)) {
        return ['success' => false, 'message' => yourls__('Download failed.', 'yourls-plugin-manager')];
    }

    // Extract into plugin directory
    $plugins_dir = YOURLS_USERDIR . '/plugins';
    $zip = new ZipArchive();
    if ($zip->open($tmp_file) === true) {
        $zip->extractTo($plugins_dir);
        $zip->close();
        unlink($tmp_file);
    } else {
        unlink($tmp_file);
        return [
            'success' => false,
            'message' => yourls__('Extraction failed: unable to open ZIP archive.', 'yourls-plugin-manager')
        ];
    }

    // Rename extracted folder to match plugin slug (clean name)
    $wildcard = $plugins_dir . '/' . $owner . '-' . $repo . '-*';
    $found = glob($wildcard);
    $target_dir = $plugins_dir . '/' . $repo;

    if (!empty($found)) {
        $extracted = $found[0];

        // 1. Check if plugin.php is directly in the extracted root
        $plugin_file_root = $extracted . '/plugin.php';
        if (file_exists($plugin_file_root)) {
            $contents = file_get_contents($plugin_file_root);
            if (preg_match('/^\\s*Plugin Name:\\s*(.+)$/mi', $contents)) {
                if (is_dir($target_dir)) {
                    ypm_delete_dir($target_dir);
                }
                rename($extracted, $target_dir);
            }
        } else {
            // 2. Otherwise look for subfolders with plugin.php inside
            $subdirs = array_filter(glob($extracted . '/*'), 'is_dir');

            foreach ($subdirs as $dir) {
                $plugin_file = $dir . '/plugin.php';
                if (file_exists($plugin_file)) {
                    $contents = file_get_contents($plugin_file);
                    if (preg_match('/^\\s*Plugin Name:\\s*(.+)$/mi', $contents)) {
                        if (is_dir($target_dir)) {
                            ypm_delete_dir($target_dir);
                        }
                        rename($dir, $target_dir);
                        ypm_delete_dir($extracted);
                        break;
                    }
                }
            }
        }
    }

    // Final validation: ensure target contains a valid YOURLS plugin
    $plugin_php = $target_dir . '/plugin.php';
    if (!file_exists($plugin_php)) {
        if (is_dir($target_dir)) ypm_delete_dir($target_dir);
        if (!empty($extracted) && is_dir($extracted)) ypm_delete_dir($extracted);
        return [
            'success' => false,
            'message' => yourls__('plugin.php not found in final plugin directory.', 'yourls-plugin-manager')
        ];
    }

    $content = file_get_contents($plugin_php);
    if (!preg_match('/^\\s*Plugin Name:\\s*(.+)$/mi', $content)) {
        if (is_dir($target_dir)) ypm_delete_dir($target_dir);
        if (!empty($extracted) && is_dir($extracted)) ypm_delete_dir($extracted);
        return [
            'success' => false,
            'message' => yourls__('plugin.php does not contain a valid YOURLS plugin header.', 'yourls-plugin-manager')
        ];
    }

    yourls_update_option('ypm_last_updated_' . $repo, time());

    // Re-enable plugin if it was previously active
    if ($was_active) {
        $active_plugins = yourls_get_option('active_plugins');
        if (!in_array($plugin_basename, $active_plugins)) {
            $active_plugins[] = $plugin_basename;
            yourls_update_option('active_plugins', array_values($active_plugins));
        }
    }

    return [
        'success' => true,
        'message' => yourls__('Plugin installed or updated successfully.', 'yourls-plugin-manager')
    ];
}

// Remote GET request to GitHub
function ypm_remote_get($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'YOURLS-Plugin-Manager',
        CURLOPT_TIMEOUT => 10
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        return [
            'message' => "GitHub API error: HTTP $http_code\nRaw response: " . htmlentities($res)
        ];
    }

    return json_decode($res, true);
}

// Download ZIP file to temp folder
function download_url($url) {
    $tmp = tempnam(sys_get_temp_dir(), 'ypm_');

    $fp = fopen($tmp, 'w+');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'YOURLS-Plugin-Manager',
        CURLOPT_FAILONERROR => true, // Fail on 4xx/5xx
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json']
    ]);
    $exec = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    // If download failed or not a valid ZIP, remove temp file
    if (!$exec || $http_code !== 200) {
        unlink($tmp);
        return false;
    }

    return $tmp;
}
