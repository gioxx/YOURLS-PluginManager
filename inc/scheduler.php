<?php

// Run update checks automatically (at most once every 24 hours).
yourls_add_action('plugins_loaded', 'ypm_maybe_run_scheduled_update_check');
function ypm_maybe_run_scheduled_update_check() {
    if (function_exists('yourls_is_admin') && !yourls_is_admin()) {
        return;
    }

    $now = time();
    $interval = 86400; // 24 hours
    $lock_ttl = 300; // 5 minutes
    $last_check = (int) yourls_get_option('ypm_scheduled_update_check_at');
    $lock_time = (int) yourls_get_option('ypm_scheduled_update_check_lock');

    if ($last_check > 0 && ($now - $last_check) < $interval) {
        return;
    }
    if ($lock_time > 0 && ($now - $lock_time) < $lock_ttl) {
        return;
    }

    yourls_update_option('ypm_scheduled_update_check_lock', $now);
    ypm_check_updates_for_slugs(ypm_get_installed_plugin_slugs());
    yourls_update_option('ypm_scheduled_update_check_at', $now);
    yourls_delete_option('ypm_scheduled_update_check_lock');
}
