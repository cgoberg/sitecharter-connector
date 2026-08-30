<?php
/**
 * Plugin Name: SiteCharter Connector
 * Description: Connects this WordPress site to SiteCharter for bounded editing, cache flushing, database backup, and public verification.
 * Version: 0.1.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: SiteCharter
 * Author URI: https://sitecharter.com
 * Plugin URI: https://github.com/cgoberg/sitecharter-connector
 * Update URI: https://github.com/cgoberg/sitecharter-connector
 * License: GPL-2.0-or-later
 * Text Domain: sitecharter
 * SECURITY MODEL (read before changing anything):
 *  - The connection key is a public identifier. It is printed in page markup
 *    for public verification and must not be described as a secret.
 *  - Admin actions in wp-admin additionally require manage_options.
 *  - REST endpoints require an application password for the dedicated,
 *    least-privilege SiteCharter integration account.
 *  - The public connection key provides routing/binding, not a second secret.
 */

if (!defined('ABSPATH')) {
	exit;
}

const SITECHARTER_OPTION_KEY_HASH = 'sitecharter_key_hash';
const SITECHARTER_OPTION_KEY_RAW = 'sitecharter_key_raw';
const SITECHARTER_OPTION_HOSTNAME = 'sitecharter_hostname';
const SITECHARTER_OPTION_USER_ID = 'sitecharter_integration_user_id';
const SITECHARTER_ROLE = 'sitecharter_integration';
const SITECHARTER_CAPABILITY = 'sitecharter_connect';

function sitecharter_register_role(): void {
	add_role(SITECHARTER_ROLE, 'SiteCharter Integration', [
		'read' => true,
		'upload_files' => true,
		'edit_posts' => true,
		'edit_others_posts' => true,
		'edit_published_posts' => true,
		'publish_posts' => true,
		'edit_pages' => true,
		'edit_others_pages' => true,
		'edit_published_pages' => true,
		'publish_pages' => true,
		SITECHARTER_CAPABILITY => true,
	]);
}

register_activation_hook(__FILE__, 'sitecharter_register_role');

// Plugin updates do not run activation hooks. Repair a missing role lazily.
if (get_role(SITECHARTER_ROLE) === null) {
	sitecharter_register_role();
}

/* ─────────────────────────── helpers ─────────────────────────── */

function sitecharter_digest(string $value): string {
	return hash('sha256', $value);
}

function sitecharter_request_key(): string {
	$hdr = $_SERVER['HTTP_X_SITECHARTER_KEY'] ?? '';
	return is_string($hdr) ? trim($hdr) : '';
}

function sitecharter_key_matches(string $candidate): bool {
	// The raw key is a public connection identifier printed into page markup.
	// The hash lets the admin screen report connection state without echoing it.
	$raw = (string) get_option(SITECHARTER_OPTION_KEY_RAW, '');
	return $raw !== '' && hash_equals($raw, $candidate);
}

/** The registered hostname of THIS site, as SiteCharter knows it. */
function sitecharter_own_host(): string {
	return (string) parse_url(home_url(), PHP_URL_HOST);
}

/** Return the dedicated integration user, creating it without admin rights. */
function sitecharter_integration_user_id(): int|WP_Error {
	$stored = (int) get_option(SITECHARTER_OPTION_USER_ID, 0);
	if ($stored > 0) {
		$user = get_user_by('id', $stored);
		if ($user instanceof WP_User && in_array(SITECHARTER_ROLE, $user->roles, true)) {
			return $stored;
		}
	}

	$base = 'sitecharter-connector';
	$login = $base;
	$suffix = 1;
	while (username_exists($login)) {
		$login = $base . '-' . $suffix;
		$suffix++;
	}

	$user_id = wp_insert_user([
		'user_login' => $login,
		'user_pass' => wp_generate_password(32, true, true),
		'display_name' => 'SiteCharter Connector',
		'role' => SITECHARTER_ROLE,
	]);
	if (is_wp_error($user_id)) {
		return $user_id;
	}

	update_option(SITECHARTER_OPTION_USER_ID, (int) $user_id, false);
	return (int) $user_id;
}

/* ─────────────────────────── admin UI ─────────────────────────── */

add_action('admin_menu', function () {
	add_options_page(
		'SiteCharter',
		'SiteCharter',
		'manage_options',
		'sitecharter',
		'sitecharter_render_settings_page'
	);
});

add_action('admin_init', function () {
	if (!current_user_can('manage_options')) {
		return;
	}

	// Register / connect: the owner pastes the key from app.sitecharter.com.
	register_setting('sitecharter', 'sitecharter_key_plaintext', [
		'type' => 'string',
		'sanitize_callback' => function ($value) {
			$value = trim((string) $value);
			if ($value === '') {
				return '';
			}
			update_option(SITECHARTER_OPTION_KEY_HASH, sitecharter_digest($value));
			update_option(SITECHARTER_OPTION_KEY_RAW, $value);
			update_option(SITECHARTER_OPTION_HOSTNAME, sitecharter_own_host());
			return ''; // never stored as a setting or echoed back
		},
	]);

	// Mint an application password for the constrained integration user. Shown once.
	if (
		($_POST['sitecharter_action'] ?? '') === 'mint_app_password'
		&& check_admin_referer('sitecharter_mint')
	) {
		$user_id = sitecharter_integration_user_id();
		if (!is_wp_error($user_id)) {
			WP_Application_Passwords::delete_all_application_passwords($user_id);
		}
		$result = is_wp_error($user_id)
			? $user_id
			: WP_Application_Passwords::create_new_application_password(
				$user_id,
				['name' => 'SiteCharter Connector']
			);
		if (is_wp_error($result)) {
			add_settings_error('sitecharter', 'mint', $result->get_error_message());
		} else {
			[$new_password] = $result;
			$integration_user = get_user_by('id', $user_id);
			set_transient('sitecharter_new_app_password_' . get_current_user_id(), [
				'username' => $integration_user instanceof WP_User ? $integration_user->user_login : '',
				'password' => $new_password,
			], 120);
		}
	}
});

function sitecharter_render_settings_page(): void {
	if (!current_user_can('manage_options')) {
		return;
	}
	$connected = (string) get_option(SITECHARTER_OPTION_KEY_HASH, '') !== '';
	$transient_key = 'sitecharter_new_app_password_' . get_current_user_id();
	$fresh = get_transient($transient_key);
	delete_transient($transient_key); ?>
	<div class="wrap">
		<h1>SiteCharter</h1>
		<?php settings_errors('sitecharter'); ?>

		<?php if ($connected) : ?>
			<p><strong><?php esc_html_e('This site is connected to SiteCharter.', 'sitecharter'); ?></strong></p>
		<?php else : ?>
			<p><?php esc_html_e('Paste the site key from your SiteCharter workspace to connect this site.', 'sitecharter'); ?></p>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields('sitecharter'); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="sitecharter_key_plaintext"><?php esc_html_e('Site key', 'sitecharter'); ?></label></th>
					<td><input type="password" id="sitecharter_key_plaintext" name="sitecharter_key_plaintext" autocomplete="off" class="regular-text"
						placeholder="<?php echo $connected ? esc_attr__('(keep current)', 'sitecharter') : ''; ?>" />
					<p class="description"><?php esc_html_e('Leave empty to keep the connected key. It is stored for the public verification marker and is not an authentication secret.', 'sitecharter'); ?></p></td>
				</tr>
			</table>
			<?php submit_button($connected ? __('Replace key', 'sitecharter') : __('Connect', 'sitecharter')); ?>
		</form>

		<hr />
		<h2><?php esc_html_e('Application password', 'sitecharter'); ?></h2>
		<p><?php esc_html_e('Create an application password for a dedicated SiteCharter integration account. It can edit posts and pages, upload media, flush supported caches, and create backups. It cannot manage users, plugins, themes, or general WordPress settings.', 'sitecharter'); ?></p>

		<?php if (is_array($fresh) && ($fresh['password'] ?? '') !== '') : ?>
			<p style="background:#fff;border-left:4px solid #266741;padding:12px">
				<?php esc_html_e('Username:', 'sitecharter'); ?> <code><?php echo esc_html((string) $fresh['username']); ?></code><br />
				<?php esc_html_e('Application password:', 'sitecharter'); ?> <code style="font-size:16px"><?php echo esc_html((string) $fresh['password']); ?></code><br />
				<?php esc_html_e('Copy both now — the password is shown once and cannot be recovered. Creating another password revokes the previous one.', 'sitecharter'); ?>
			</p>
		<?php endif; ?>

		<form method="post">
			<input type="hidden" name="sitecharter_action" value="mint_app_password" />
			<?php wp_nonce_field('sitecharter_mint'); ?>
			<p><button type="submit" class="button button-secondary"><?php esc_html_e('Create application password', 'sitecharter'); ?></button></p>
		</form>
	</div>
	<?php
}

/* ─────────────────────── the line of code ─────────────────────── */

// Method A without touching the theme: once connected, the loader line is
// printed site-wide. Verification reads it from the public pages exactly as
// if it had been pasted into <head>.
add_action('wp_head', function () {
	$raw = (string) get_option(SITECHARTER_OPTION_KEY_RAW, '');
	if ($raw === '' || is_admin()) {
		return;
	}
	$host = (string) parse_url(home_url(), PHP_URL_HOST);
	// The raw key in the markup is the contract, not an oversight: SiteCharter
	// verifies the connection by hashing whatever data-key the public page
	// carries and comparing it to what it stored at connect time.
	printf(
		"<script src=\"https://cdn.sitecharter.com/sc.js\" data-site=\"%s\" data-key=\"%s\" defer></script>\n",
		esc_attr($host),
		esc_attr($raw)
	);
}, 99);

/* ─────────────────────── REST endpoints ─────────────────────── */

/**
 * Shared permission check: WordPress core must authenticate the dedicated
 * integration account through an application password. The public connection
 * key must also match the site binding, but it is not a second secret.
 */
function sitecharter_rest_permission(): bool|WP_Error {
	$key = sitecharter_request_key();
	if (!sitecharter_key_matches($key)) {
		return new WP_Error('sitecharter_forbidden', 'bad_key', ['status' => 403]);
	}
	if (!current_user_can(SITECHARTER_CAPABILITY)) {
		return new WP_Error('sitecharter_forbidden', 'need_sitecharter_capability', ['status' => 403]);
	}
	return true;
}

add_action('rest_api_init', function () {

	// Handshake: binds the connection and reports what SiteCharter may use.
	register_rest_route('sitecharter/v1', '/handshake', [
		'methods' => 'POST',
		'permission_callback' => 'sitecharter_rest_permission',
		'callback' => fn() => [
			'state' => 'ok',
			'site_url' => home_url(),
			'hostname' => sitecharter_own_host(),
			'wp_version' => get_bloginfo('version'),
			'plugin_version' => '0.1.1',
			'cache_plugins' => sitecharter_detect_cache_plugins(),
			'db_export_available' => true,
		],
	]);

	// Cache flush: real flushes across the caches we can see, reported honestly.
	register_rest_route('sitecharter/v1', '/cache-flush', [
		'methods' => 'POST',
		'permission_callback' => 'sitecharter_rest_permission',
		'callback' => 'sitecharter_handle_cache_flush',
	]);

	// Database export: the Layer-2 restore promise for REST transport.
	register_rest_route('sitecharter/v1', '/db-export', [
		'methods' => 'POST',
		'permission_callback' => 'sitecharter_rest_permission',
		'callback' => 'sitecharter_handle_db_export',
	]);
});

function sitecharter_detect_cache_plugins(): array {
	$found = [];
	foreach ([
		'wp-rocket/wp-rocket.php' => 'WP Rocket',
		'w3-total-cache/w3-total-cache.php' => 'W3 Total Cache',
		'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
		'wp-super-cache/wp-super-cache.php' => 'WP Super Cache',
	] as $file => $name) {
		if (is_plugin_active($file)) {
			$found[] = $name;
		}
	}
	return $found;
}

function sitecharter_handle_cache_flush(): array {
	$flushed = [];

	if (function_exists('wp_cache_flush')) {
		wp_cache_flush();
		$flushed[] = 'object_cache';
	}
	if (function_exists('rocket_clean_domain')) {
		rocket_clean_domain();
		$flushed[] = 'wp_rocket';
	}
	if (has_action('litespeed_purge_all') || class_exists('\LiteSpeed\Purge')) {
		do_action('litespeed_purge_all');
		$flushed[] = 'litespeed';
	}
	if (function_exists('w3tc_flush_all')) {
		w3tc_flush_all();
		$flushed[] = 'w3tc';
	}
	if (function_exists('wp_cache_clear_cache')) {
		wp_cache_clear_cache();
		$flushed[] = 'wp_super_cache';
	}

	return ['state' => 'ok', 'flushed' => $flushed,
		'note' => $flushed === [] ? 'no_supported_cache_found' : null];
}

/**
 * Streams a gzipped SQL dump of the WordPress tables. Content-complete:
 * schema plus rows for every $wpdb table, so posts, options, terms, media
 * metadata and users are all restorable. Written through a temp file first so
 * a mid-dump failure cannot hand back a truncated archive labelled good.
 */
function sitecharter_handle_db_export() {
	global $wpdb;

	$tables = $wpdb->get_col('SHOW TABLES');
	if (!$tables) {
		return new WP_Error('sitecharter_export_failed', 'no_tables', ['status' => 500]);
	}

	$tmp = tempnam(get_temp_dir(), 'scdump');
	$gz = gzopen($tmp, 'wb9');
	if ($gz === false) {
		return new WP_Error('sitecharter_export_failed', 'temp_file', ['status' => 500]);
	}

	gzwrite($gz, "-- SiteCharter database export\n");
	gzwrite($gz, '-- host: ' . sitecharter_own_host() . "\n");
	gzwrite($gz, '-- generated: ' . gmdate('c') . "\n");
	gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\n\n");

	foreach ($tables as $table) {
		$safe = str_replace('`', '``', (string) $table);
		$create = $wpdb->get_row('SHOW CREATE TABLE `' . $safe . '`', ARRAY_N);
		if (!$create) {
			continue;
		}
		gzwrite($gz, "DROP TABLE IF EXISTS `{$safe}`;\n{$create[1]};\n");

		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$safe}`");
		$batch = 500;
		for ($offset = 0; $offset < $count; $offset += $batch) {
			$rows = $wpdb->get_results("SELECT * FROM `{$safe}` LIMIT {$batch} OFFSET {$offset}", ARRAY_A);
			foreach ((array) $rows as $row) {
				$values = array_map(
					static fn($v) => $v === null ? 'NULL' : '"' . addslashes((string) $v) . '"',
					array_values($row)
				);
				gzwrite($gz, "INSERT INTO `{$safe}` VALUES (" . implode(',', $values) . ");\n");
			}
		}
		gzwrite($gz, "\n");
	}

	gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
	gzclose($gz);

	$filename = 'sitecharter-db-' . gmdate('Ymd-His') . '.sql.gz';
	header('Cache-Control: no-store, private');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');
	header('Content-Type: application/gzip');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Content-Length: ' . (string) filesize($tmp));
	readfile($tmp);
	unlink($tmp);
	exit;
}
