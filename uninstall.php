<?php
/** Remove SiteCharter connection state when the plugin is deleted. */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('sitecharter_key_hash');
delete_option('sitecharter_key_raw');
delete_option('sitecharter_hostname');
$integration_user_id = (int) get_option('sitecharter_integration_user_id', 0);
if ($integration_user_id > 0) {
	WP_Application_Passwords::delete_all_application_passwords($integration_user_id);
}
delete_option('sitecharter_integration_user_id');
remove_role('sitecharter_integration');

// The integration user is retained without a role so uninstalling the plugin
// cannot delete or orphan content it may have authored.
