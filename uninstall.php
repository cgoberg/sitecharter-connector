<?php
/** Remove SiteCharter connection state when the plugin is deleted. */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('sitecharter_key_hash');
delete_option('sitecharter_key_raw');
delete_option('sitecharter_hostname');
delete_transient('sitecharter_new_app_password');

