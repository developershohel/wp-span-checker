<?php
/**
 * Shared helpers for VMS Elements Form Guard.
 *
 * Many helpers in this file query plugin-owned custom tables (form mappings,
 * activity logs, comment enforcement, etc.). Table identifiers are built from
 * `$wpdb->prefix` plus hardcoded suffixes, and values are always passed
 * through `$wpdb->prepare()` or the insert / update / delete helpers.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dump a WordPress table to a .sql file using $wpdb (requires mysqldump on server).
 *
 * @param string $table_name Table name without prefix, e.g., 'posts'.
 * @param string $file_path  Full path to save the SQL file.
 * @return string Success or error message.
 */
function vms_elements_form_guard_dump_table( $table_name, $file_path ) {
	global $wpdb;

	$full_table_name = $wpdb->prefix . $table_name;

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table name is built from trusted prefix + sanitized slug.
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) );
	if ( ! $exists ) {
		/* translators: %s: database table name */
		return sprintf( __( 'Table %s does not exist.', 'vms-elements-form-guard' ), $full_table_name );
	}

	$db_name = DB_NAME;
	$db_user = DB_USER;
	$db_pass = DB_PASSWORD;
	$db_host = DB_HOST;

	$file_path = str_replace( '\\', '/', $file_path );

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Intentional optional dev utility; server must have mysqldump.
	$command = sprintf(
		'mysqldump --user=%s --password=%s --host=%s %s %s > %s',
		escapeshellarg( $db_user ),
		escapeshellarg( $db_pass ),
		escapeshellarg( $db_host ),
		escapeshellarg( $db_name ),
		escapeshellarg( $full_table_name ),
		escapeshellarg( $file_path )
	);

	$output     = null;
	$return_var = null;
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
	exec( $command, $output, $return_var );

	if ( 0 === $return_var ) {
		/* translators: %s: export file path */
		return sprintf( __( 'Table exported successfully to %s.', 'vms-elements-form-guard' ), $file_path );
	}

	/* translators: %d: shell exit code */
	return sprintf( __( 'Export failed. Return code: %d', 'vms-elements-form-guard' ), $return_var );
}

/**
 * Best-effort visitor IP for logging.
 *
 * @return string
 */
function vms_elements_form_guard_get_user_ip() {
	$ip_keys = array(
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
	);

	foreach ( $ip_keys as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

		if ( false !== strpos( $ip, ',' ) ) {
			$parts = explode( ',', $ip );
			$ip    = $parts[0];
		}

		return trim( $ip );
	}

	return '0.0.0.0';
}

/**
 * Normalize user or URL input to a hostname for validation.
 *
 * @param string $input Raw domain, email host, or URL.
 * @return string Lowercase hostname or empty string.
 */
function vms_elements_form_guard_normalize_domain_input( $input ) {
	$input = is_string( $input ) ? trim( $input ) : '';
	if ( '' === $input ) {
		return '';
	}

	if ( preg_match( '#^https?://#i', $input ) ) {
		$host = wp_parse_url( $input, PHP_URL_HOST );
		return $host ? strtolower( $host ) : '';
	}

	$input = preg_replace( '#/.*$#', '', $input );
	$input = preg_replace( '#^www\.#i', '', $input );

	return strtolower( $input );
}

/**
 * Parse API validation toggles from AJAX POST (settings[0][is_webrisk] style).
 *
 * @param array $post Typically wp_unslash( $_POST ).
 * @return array{is_webrisk:bool,is_virustotal:bool}
 */
function vms_elements_form_guard_parse_validation_settings( array $post ) {
	$raw = isset( $post['settings'] ) ? $post['settings'] : array();
	if ( is_array( $raw ) && isset( $raw[0] ) && is_array( $raw[0] ) ) {
		$row = $raw[0];
		return array(
			'is_webrisk'    => ! empty( $row['is_webrisk'] ) && '0' !== (string) $row['is_webrisk'],
			'is_virustotal' => ! empty( $row['is_virustotal'] ) && '0' !== (string) $row['is_virustotal'],
		);
	}

	return array(
		'is_webrisk'    => false,
		'is_virustotal' => false,
	);
}

/**
 * Whether a string looks like a CSS selector for Form Guard (combined id/classes).
 *
 * @param string $raw Raw stored form_id value.
 */
function vms_elements_form_guard_form_guard_is_combined_selector( string $raw ): bool {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return false;
	}
	return false !== strpos( $raw, '#' ) || false !== strpos( $raw, '.' ) || false !== strpos( $raw, '[' );
}

/**
 * Safe preg_match for admin-supplied delimited patterns (ReDoS mitigation: length + delimiter form only).
 *
 * @param string $pattern Delimited regex e.g. /^abc$/u .
 * @param string $value   Subject string.
 */
function vms_elements_form_guard_form_guard_preg_match_safe( string $pattern, string $value ): bool {
	$pattern = trim( $pattern );
	if ( strlen( $pattern ) > 512 || strlen( $pattern ) < 3 ) {
		return false;
	}
	if ( ! preg_match( '#^/.+/[a-zA-Z]*$#', $pattern ) ) {
		return false;
	}
	$m = @preg_match( $pattern, $value );

	return 1 === $m;
}

/**
 * Extract URLs from plain text (textarea link policy).
 *
 * @return string[]
 */
function vms_elements_form_guard_form_guard_extract_urls( string $text ): array {
	if ( '' === trim( $text ) ) {
		return array();
	}
	if ( ! preg_match_all( '#https?://[^\s<>"\']+#i', $text, $matches ) ) {
		return array();
	}
	$out = array();
	foreach ( $matches[0] as $u ) {
		$u = rtrim( $u, '.,);]\'"' );
		if ( $u !== '' ) {
			$out[] = $u;
		}
	}

	return array_values( array_unique( $out ) );
}

/**
 * Merge per-field API flags with legacy row-level defaults.
 *
 * @param array<string, mixed> $field Field config from JSON settings.
 * @param array<string, mixed> $row   Full DB row.
 * @return array{is_webrisk:bool,is_virustotal:bool}
 */
function vms_elements_form_guard_form_guard_field_api_flags( array $field, array $row ): array {
	$wr = isset( $field['is_webrisk'] ) ? (int) $field['is_webrisk'] : null;
	$vt = isset( $field['is_virustotal'] ) ? (int) $field['is_virustotal'] : null;

	return array(
		'is_webrisk'    => null !== $wr ? (bool) $wr : ! empty( $row['is_webrisk'] ),
		'is_virustotal' => null !== $vt ? (bool) $vt : ! empty( $row['is_virustotal'] ),
	);
}

/**
 * Output the standard plugin admin page heading (kicker, H1, optional lede).
 *
 * Use the same translated strings as submenu titles in Admin_Menu for consistency.
 *
 * @param string $title Already-translated H1 text.
 * @param string $lede  Optional already-translated intro; pass '' to omit the lede paragraph.
 */
function vms_elements_form_guard_admin_page_header( $title, $lede = '' ) {
	$vefg_header_title = (string) $title;
	$vefg_header_lede  = (string) $lede;
	require VMS_ELEMENTS_FORM_GUARD_DIR . 'templates/partials/admin-page-header.php';
}

/**
 * Output an accessible toggle switch (styled checkbox) for plugin admin forms.
 *
 * @param array<string, mixed> $args {
 *     @type string $name        Input `name` attribute (required).
 *     @type bool   $checked     Whether the control is on.
 *     @type string $value       Value when checked. Default `1`.
 *     @type string $id          Optional `id` attribute.
 *     @type string $label       Primary line next to the switch.
 *     @type string $description Secondary muted line under the label.
 *     @type string $input_class Extra classes on the checkbox input.
 *     @type string $wrapper_class Classes on the outer `<label>`.
 *     @type bool   $compact     Smaller track (dense lists).
 * }
 */
function vms_elements_form_guard_admin_switch( array $args ): void {
	$name = isset( $args['name'] ) ? (string) $args['name'] : '';
	if ( $name === '' ) {
		return;
	}

	$checked     = ! empty( $args['checked'] );
	$value       = isset( $args['value'] ) ? (string) $args['value'] : '1';
	$id          = isset( $args['id'] ) ? (string) $args['id'] : '';
	$label       = isset( $args['label'] ) ? (string) $args['label'] : '';
	$description = isset( $args['description'] ) ? (string) $args['description'] : '';
	$input_class = isset( $args['input_class'] ) ? trim( (string) $args['input_class'] ) : '';
	$wrapper     = isset( $args['wrapper_class'] ) ? trim( (string) $args['wrapper_class'] ) : '';
	$compact     = ! empty( $args['compact'] );

	$label_class = 'vefg-switch';
	if ( $compact ) {
		$label_class .= ' vefg-switch--compact';
	}
	if ( $wrapper !== '' ) {
		$label_class .= ' ' . $wrapper;
	}

	$input_classes = trim( 'vefg-switch__input ' . $input_class );

	echo '<label class="' . esc_attr( $label_class ) . '">';
	echo '<input type="checkbox" class="' . esc_attr( $input_classes ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';
	if ( $id !== '' ) {
		echo ' id="' . esc_attr( $id ) . '"';
	}
	checked( $checked );
	echo ' />';
	echo '<span class="vefg-switch__track" aria-hidden="true"></span>';

	if ( $label !== '' || $description !== '' ) {
		echo '<span class="vefg-switch__body">';
		if ( $label !== '' ) {
			echo '<span class="vefg-switch__label">' . esc_html( $label ) . '</span>';
		}
		if ( $description !== '' ) {
			echo '<span class="vefg-switch__desc">' . esc_html( $description ) . '</span>';
		}
		echo '</span>';
	}

	echo '</label>';
}

/**
 * Post types shown in AI summary settings (all suitable types, not only `public`).
 *
 * Includes pages, WooCommerce products, and other CPTs that are public, publicly queryable,
 * or have an admin UI. Core infrastructure types (attachments, revisions, FSE templates, …)
 * are excluded.
 *
 * @return array<string, \WP_Post_Type> Post type name => object, sorted by singular label.
 */
function vms_elements_form_guard_summary_selectable_post_types(): array {
	static $cache = null;
	if ( is_array( $cache ) ) {
		return $cache;
	}

	$exclude = array(
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
	);

	$objects = get_post_types( array(), 'objects' );
	$out     = array();

	foreach ( $objects as $name => $pt ) {
		if ( ! ( $pt instanceof \WP_Post_Type ) || in_array( $name, $exclude, true ) ) {
			continue;
		}
		if ( $pt->show_ui || $pt->publicly_queryable || $pt->public ) {
			$out[ $name ] = $pt;
		}
	}

	uasort(
		$out,
		static function ( $a, $b ) {
			$la = $a->labels->singular_name ?? $a->labels->name ?? $a->name;
			$lb = $b->labels->singular_name ?? $b->labels->name ?? $b->name;
			return strcasecmp( (string) $la, (string) $lb );
		}
	);

	/**
	 * Filter post types listed under AI → Summaries.
	 *
	 * @param array<string, \WP_Post_Type> $out     Candidate types keyed by slug.
	 * @param array<int, string>           $exclude Internal slugs always omitted.
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Established hook name; renaming would break BC for existing filter consumers.
	$cache = apply_filters( 'vefg_summary_selectable_post_types', $out, $exclude );

	return is_array( $cache ) ? $cache : $out;
}

/**
 * Preset slugs for “where this mapping runs” (not numeric IDs).
 *
 * @return array<string, string> slug => translated label
 */
function vms_elements_form_guard_page_target_presets(): array {
	return array(
		'all-pages'        => __( 'Entire site (every page & view)', 'vms-elements-form-guard' ),
		'front-page'       => __( 'Front page', 'vms-elements-form-guard' ),
		'home-blog'        => __( 'Blog / posts index', 'vms-elements-form-guard' ),
		'singular-page'    => __( 'Any single page', 'vms-elements-form-guard' ),
		'singular-post'    => __( 'Any single post', 'vms-elements-form-guard' ),
		'singular-any'     => __( 'Any singular content', 'vms-elements-form-guard' ),
		'archive-any'      => __( 'Any archive', 'vms-elements-form-guard' ),
		'archive-category' => __( 'Category archives', 'vms-elements-form-guard' ),
		'archive-tag'      => __( 'Tag archives', 'vms-elements-form-guard' ),
		'search'           => __( 'Search results', 'vms-elements-form-guard' ),
		'404'              => __( '404 error page', 'vms-elements-form-guard' ),
	);
}

/**
 * Allowed preset keys (for sanitizing incoming JSON).
 *
 * @return array<int, string>
 */
function vms_elements_form_guard_page_target_preset_slugs(): array {
	return array_keys( vms_elements_form_guard_page_target_presets() );
}

/**
 * Get WordPress body class for each preset slug (for frontend matching).
 *
 * @return array<string, string> slug => body class
 */
function vms_elements_form_guard_preset_body_classes(): array {
	return array(
		'all-pages'        => '',
		'front-page'       => 'home',
		'home-blog'        => 'blog',
		'singular-page'    => 'page',
		'singular-post'    => 'single-post',
		'singular-any'     => 'singular',
		'archive-any'      => 'archive',
		'archive-category' => 'category',
		'archive-tag'      => 'tag',
		'search'           => 'search',
		'404'              => 'error404',
	);
}

/**
 * Normalize DB page_id value to a list of targets (legacy single string or JSON array).
 *
 * @param mixed $raw page_id column value.
 * @return array<int, string>
 */
function vms_elements_form_guard_normalize_page_targets( $raw ): array {
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( '' === $raw ) {
		return array( 'all-pages' );
	}

	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) && array() !== $decoded ) {
		$out = array();
		foreach ( $decoded as $item ) {
			$item = is_string( $item ) || is_numeric( $item ) ? trim( (string) $item ) : '';
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	return array( $raw );
}

/**
 * Whether one target token matches the current request (front-end).
 *
 * @param string $target Preset slug or post ID string.
 */
function vms_elements_form_guard_current_request_matches_target( string $target ): bool {
	$target = trim( $target );
	if ( '' === $target || 'all-pages' === $target ) {
		return true;
	}

	switch ( $target ) {
		case 'front-page':
			return is_front_page();
		case 'home-blog':
			return is_home();
		case 'singular-page':
			return is_singular( 'page' );
		case 'singular-post':
			return is_singular( 'post' );
		case 'singular-any':
			return is_singular();
		case 'archive-any':
			return is_archive();
		case 'archive-category':
			return is_category();
		case 'archive-tag':
			return is_tag();
		case 'search':
			return is_search();
		case '404':
			return is_404();
	}

	if ( ctype_digit( $target ) ) {
		$id = (int) $target;
		if ( $id <= 0 ) {
			return false;
		}
		if ( is_singular() && (int) get_queried_object_id() === $id ) {
			return true;
		}
		$page_for_posts = (int) get_option( 'page_for_posts' );
		if ( $page_for_posts && $id === $page_for_posts && is_home() ) {
			return true;
		}
		return false;
	}

	return false;
}

/**
 * Whether a form_settings row should load on this request (OR across targets).
 *
 * @param array<string, mixed> $row DB row.
 */
function vms_elements_form_guard_row_matches_current_request( array $row ): bool {
	$targets = vms_elements_form_guard_normalize_page_targets( $row['page_id'] ?? '' );
	foreach ( $targets as $t ) {
		if ( vms_elements_form_guard_current_request_matches_target( (string) $t ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Get current page type for frontend localization.
 *
 * @return string page|post|common
 */
function vms_elements_form_guard_get_current_page_type(): string {
	if ( is_singular( 'page' ) ) {
		return 'page';
	}
	if ( is_singular( 'post' ) ) {
		return 'post';
	}
	return 'common';
}

/**
 * Get body classes that match current request for frontend targeting.
 *
 * @return array<int, string>
 */
function vms_elements_form_guard_get_current_body_classes(): array {
	$classes = array();

	if ( is_front_page() ) {
		$classes[] = 'home';
	}
	if ( is_home() ) {
		$classes[] = 'blog';
	}
	if ( is_singular( 'page' ) ) {
		$classes[] = 'page';
		$classes[] = 'page-id-' . get_queried_object_id();
	}
	if ( is_singular( 'post' ) ) {
		$classes[] = 'single-post';
		$classes[] = 'postid-' . get_queried_object_id();
	}
	if ( is_singular() ) {
		$classes[] = 'singular';
	}
	if ( is_archive() ) {
		$classes[] = 'archive';
	}
	if ( is_category() ) {
		$classes[] = 'category';
	}
	if ( is_tag() ) {
		$classes[] = 'tag';
	}
	if ( is_search() ) {
		$classes[] = 'search';
	}
	if ( is_404() ) {
		$classes[] = 'error404';
	}

	return $classes;
}

/**
 * Sanitize a submitted field value based on its declared type.
 *
 * @param string $field_type email|url|textarea|username|text|etc.
 * @param mixed  $value      Raw value from POST or decoded JSON.
 */
function vms_elements_form_guard_sanitize_field_value( string $field_type, $value ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$value = (string) $value;

	switch ( $field_type ) {
		case 'email':
			return sanitize_email( $value );
		case 'url':
			return esc_url_raw( $value );
		case 'textarea':
			return sanitize_textarea_field( $value );
		default:
			return sanitize_text_field( $value );
	}
}

/**
 * Whitelist validation rule flags from decoded JSON.
 *
 * @param mixed $rules Decoded rules array.
 * @return array<string, bool>
 */
function vms_elements_form_guard_sanitize_field_rules( $rules ): array {
	if ( ! is_array( $rules ) ) {
		return array();
	}

	$allowed = array( 'mx', 'disposable', 'webrisk', 'virustotal', 'dns', 'ai_spam', 'check_exists' );
	$clean   = array();

	foreach ( $allowed as $key ) {
		if ( array_key_exists( $key, $rules ) ) {
			$clean[ $key ] = ! empty( $rules[ $key ] );
		}
	}

	return $clean;
}

/**
 * Sanitize page targets from AJAX into a stored page_id string (JSON array).
 *
 * @param mixed $raw POST pageId (JSON string or legacy scalar).
 */
function vms_elements_form_guard_sanitize_page_targets_param( $raw ): string {
	$allowed = array_flip( vms_elements_form_guard_page_target_preset_slugs() );
	$clean   = array();

	if ( is_array( $raw ) ) {
		$list = $raw;
	} else {
		$s = is_string( $raw ) ? trim( wp_unslash( $raw ) ) : '';
		if ( '' === $s ) {
			return wp_json_encode( array( 'all-pages' ) );
		}
		$decoded = json_decode( $s, true );
		if ( is_array( $decoded ) ) {
			$list = $decoded;
		} else {
			$list = array( sanitize_text_field( $s ) );
		}
	}

	foreach ( $list as $item ) {
		$item = is_string( $item ) || is_numeric( $item ) ? trim( (string) $item ) : '';
		if ( '' === $item ) {
			continue;
		}
		if ( isset( $allowed[ $item ] ) ) {
			$clean[] = $item;
			continue;
		}
		if ( ctype_digit( $item ) ) {
			$clean[] = (string) absint( $item );
		}
	}

	$clean = array_values( array_unique( $clean ) );
	if ( array() === $clean ) {
		return wp_json_encode( array( 'all-pages' ) );
	}

	return wp_json_encode( $clean );
}

/**
 * Localized strings for front-end and admin JavaScript (wp_localize_script).
 *
 * @return array<string, string>
 */
function vms_elements_form_guard_get_js_i18n() {
	$m = vms_elements_form_guard_get_all_error_messages();

	return array(
		'formNotFound'             => __( 'Form not found. Check Form ID / class under VMS Elements Form Guard Form Guard.', 'vms-elements-form-guard' ),
		'emailInvalid'             => $m['email_invalid_format'],
		'validationFailed'         => $m['validation_failed'],
		'emailRequired'            => $m['email_invalid_format'],
		'emailFieldRequired'       => $m['field_required'],
		'passwordRequired'         => $m['field_required'],
		'passwordRequirements'     => __( 'Password must meet all requirements.', 'vms-elements-form-guard' ),
		'urlRequired'              => $m['field_required'],
		'urlNotValid'              => $m['url_invalid'],
		'urlValid'                 => __( 'URL is valid', 'vms-elements-form-guard' ),
		'fieldRequired'            => $m['field_required'],
		'serverError'              => $m['server_error'],
		'recaptchaRequired'        => $m['recaptcha_required'],
		'recaptchaFailed'          => $m['recaptcha_failed'],
		'emailDnsFailed'           => $m['email_dns_failed'],
		'emailMxFailed'            => $m['email_mx_failed'],
		'emailDisposable'          => $m['email_disposable'],
		'emailWebriskFlagged'      => $m['email_webrisk_flagged'],
		'emailVirustotalFlagged'   => $m['email_virustotal_flagged'],
		'urlDnsFailed'             => $m['url_dns_failed'],
		'urlWebriskFlagged'        => $m['url_webrisk_flagged'],
		'urlVirustotalFlagged'     => $m['url_virustotal_flagged'],
		'confirmDeleteDomain'      => __( 'Are you sure you want to delete this domain?', 'vms-elements-form-guard' ),
		'confirmDeleteDomainTitle' => __( 'Remove this domain?', 'vms-elements-form-guard' ),
		'confirmDeleteFormSetting' => __( 'Are you sure you want to delete this Form Guard mapping?', 'vms-elements-form-guard' ),
		'confirmDeleteFormTitle'   => __( 'Remove this Form Guard mapping?', 'vms-elements-form-guard' ),
		'cancel'                   => __( 'Cancel', 'vms-elements-form-guard' ),
		'domainAdded'              => __( 'Domain added.', 'vms-elements-form-guard' ),
		'domainRemoved'            => __( 'Domain removed.', 'vms-elements-form-guard' ),
		'formSettingRemoved'       => __( 'Form Guard mapping removed.', 'vms-elements-form-guard' ),
		'errorAddingDomain'        => __( 'Error adding domain.', 'vms-elements-form-guard' ),
		'errorDeletingDomain'      => __( 'Error deleting domain.', 'vms-elements-form-guard' ),
		'errorDeletingSetting'     => __( 'Could not delete Form Guard mapping.', 'vms-elements-form-guard' ),
		'saved'                    => __( 'Saved', 'vms-elements-form-guard' ),
		'delete'                   => __( 'Delete', 'vms-elements-form-guard' ),
		'edit'                     => __( 'Edit', 'vms-elements-form-guard' ),
		'copied'                   => __( 'Copied', 'vms-elements-form-guard' ),
		'copy'                     => __( 'Copy', 'vms-elements-form-guard' ),
		'examplePrefix'            => __( 'Example:', 'vms-elements-form-guard' ),
		'copyFailed'               => __( 'Could not copy.', 'vms-elements-form-guard' ),
		'requestFailed'            => __( 'Request failed', 'vms-elements-form-guard' ),
		'validating'               => __( 'Validating...', 'vms-elements-form-guard' ),
		'submitting'               => __( 'Submitting...', 'vms-elements-form-guard' ),
		'validationPassed'         => __( 'Validation passed', 'vms-elements-form-guard' ),
		'submit'                   => __( 'Submit', 'vms-elements-form-guard' ),
		'fieldType'                => __( 'Field type', 'vms-elements-form-guard' ),
		'fieldId'                  => __( 'Field ID', 'vms-elements-form-guard' ),
		'fieldClass'               => __( 'Field class', 'vms-elements-form-guard' ),
		'eventName'                => __( 'Event name', 'vms-elements-form-guard' ),
		'formField'                => __( 'Form field', 'vms-elements-form-guard' ),
		'javascriptEvent'          => __( 'JavaScript event', 'vms-elements-form-guard' ),
		'optionUrl'                => __( 'URL', 'vms-elements-form-guard' ),
		'optionEmail'              => __( 'Email', 'vms-elements-form-guard' ),
		'optionText'               => __( 'Text', 'vms-elements-form-guard' ),
		'optionUsername'           => __( 'Username', 'vms-elements-form-guard' ),
		'optionChange'             => __( 'Change', 'vms-elements-form-guard' ),
		'optionInput'              => __( 'Input', 'vms-elements-form-guard' ),
		'optionFormSubmit'         => __( 'Form submit', 'vms-elements-form-guard' ),
		'labelId'                  => __( 'ID', 'vms-elements-form-guard' ),
		'labelClass'               => __( 'Class', 'vms-elements-form-guard' ),
		'selectFieldType'          => __( 'Select field type', 'vms-elements-form-guard' ),
		'optionTextarea'           => __( 'Textarea', 'vms-elements-form-guard' ),
		'optionTel'                => __( 'Telephone', 'vms-elements-form-guard' ),
		'optionNumber'             => __( 'Number', 'vms-elements-form-guard' ),
		'optionPassword'           => __( 'Password', 'vms-elements-form-guard' ),
		'enable'                   => __( 'Enable', 'vms-elements-form-guard' ),
		'disable'                  => __( 'Disable', 'vms-elements-form-guard' ),
		'requiredField'            => __( 'Required field', 'vms-elements-form-guard' ),
		'requiredFieldHint'        => __( 'Mark the field as required in the browser.', 'vms-elements-form-guard' ),
		'requireValidation'        => __( 'Require validation', 'vms-elements-form-guard' ),
		'requireValidationHint'    => __( 'Run server-side validation for this field.', 'vms-elements-form-guard' ),
		'googleWebRisk'            => __( 'Google Web Risk', 'vms-elements-form-guard' ),
		'virusTotal'               => __( 'VirusTotal scanner', 'vms-elements-form-guard' ),
		'usernameTakenCheck'       => __( 'Reject if username exists (live check)', 'vms-elements-form-guard' ),
		'usernameTakenHint'        => __( 'Use for registration/login name inputs. When enabled, checks WordPress while typing (debounced) and on submit.', 'vms-elements-form-guard' ),
		'textareaAllowLinks'       => __( 'Allow links in message', 'vms-elements-form-guard' ),
		'textareaAiSpam'           => __( 'AI spam checker (textarea)', 'vms-elements-form-guard' ),
		'textareaAiSpamHint'       => __( 'Uses AI settings from VMS Elements Form Guard → AI. Runs on the server when validation is enabled.', 'vms-elements-form-guard' ),
		'textAllowUrls'            => __( 'Allow URLs in value', 'vms-elements-form-guard' ),
		'textAllowUrlsHint'        => __( 'Disable to reject http(s) URLs typed into this single-line field.', 'vms-elements-form-guard' ),
		'customRegex'              => __( 'Custom regex (delimited)', 'vms-elements-form-guard' ),
		'customRegexHint'          => __( 'Optional. Must look like /pattern/flags. Checked on the server when validation is enabled.', 'vms-elements-form-guard' ),
		'presetRegex'              => __( 'Preset patterns', 'vms-elements-form-guard' ),
		'validExample'             => __( 'Valid', 'vms-elements-form-guard' ),
		'invalidExample'           => __( 'Invalid', 'vms-elements-form-guard' ),
		'usePattern'               => __( 'Use pattern', 'vms-elements-form-guard' ),
		'fgNeedOneField'           => __( 'Keep at least one field row.', 'vms-elements-form-guard' ),
		'mappedFieldTitle'         => __( 'Mapped form control', 'vms-elements-form-guard' ),
		'mappedFieldGuardsBlurb'   => __( 'Guards in this row apply only to this field’s ID/class. Use “Add field” for each separate input (10 fields → 10 rows).', 'vms-elements-form-guard' ),
		'fieldGuardsLegend'        => __( 'Guards for this field only', 'vms-elements-form-guard' ),
		'securityMethodsLegend'    => __( 'Protection methods (based on field type)', 'vms-elements-form-guard' ),
		'securityMethodsIntro'     => __( 'Email and URL rows show Web Risk and VirusTotal (Web Risk defaults ON when you switch to Email). Username rows show live “already registered” checks. Plain Text adds URL-in-value rules; textarea adds links + AI spam screening.', 'vms-elements-form-guard' ),
		'webriskEmailUrlOnly'      => __( 'Used when “Form field” is Email or URL and “Require validation” is enabled for domain checks.', 'vms-elements-form-guard' ),
		'vtEmailUrlOnly'           => __( 'Same as Web Risk: applies together with Email or URL domain validation.', 'vms-elements-form-guard' ),
		'securityMethodsOtherHint' => __( 'Email and URL rows use the reputation toggles here together with validation above.', 'vms-elements-form-guard' ),
		'validationRulesLegend'    => __( 'Validation rules', 'vms-elements-form-guard' ),
		'labelWebRiskShort'        => __( 'Web Risk', 'vms-elements-form-guard' ),
		'labelVtShort'             => __( 'VirusTotal', 'vms-elements-form-guard' ),
		'onShort'                  => __( 'On', 'vms-elements-form-guard' ),
		'offShort'                 => __( 'Off', 'vms-elements-form-guard' ),
		'usernameCheckShort'       => __( 'Username exists check', 'vms-elements-form-guard' ),
		'linksAllowedShort'        => __( 'Links allowed', 'vms-elements-form-guard' ),
		'aiSpamShort'              => __( 'AI spam check', 'vms-elements-form-guard' ),
		'textUrlsInFieldShort'     => __( 'URLs in text field', 'vms-elements-form-guard' ),
		'regexShort'               => __( 'Regex', 'vms-elements-form-guard' ),
		'locationRequired'         => __( 'Please select at least one location (Common locations, Specific pages, or Specific posts).', 'vms-elements-form-guard' ),
		'formSelectorRequired'     => __( 'Please enter a Form id/class or Submit button selector to identify the form.', 'vms-elements-form-guard' ),
		'formSelectorRequiredForEntireSite' => __( 'Form id/class is required when targeting the entire site.', 'vms-elements-form-guard' ),
		'autoMode'                 => __( 'Auto', 'vms-elements-form-guard' ),
		'manualMode'               => __( 'Manual', 'vms-elements-form-guard' ),
		'defaultRules'             => __( 'Default rules', 'vms-elements-form-guard' ),
		'emailInvalidFormat'       => $m['email_invalid_format'],
		'emailDisposableMsg'       => $m['email_disposable'],
		'emailDomainInvalid'       => $m['email_mx_failed'],
		'urlInvalidFormat'         => $m['url_invalid'],
		'passwordWeak'             => __( 'Password is too weak. Use at least 8 characters with uppercase, lowercase, number, and symbol.', 'vms-elements-form-guard' ),
		'linksNotAllowed'          => __( 'Links are not allowed in this field.', 'vms-elements-form-guard' ),
		'urlsNotAllowed'           => __( 'URLs are not allowed in this field.', 'vms-elements-form-guard' ),
		'usernameExists'           => $m['username_taken'],
		'spamDetected'             => $m['spam_detected'],
		'userBlocked'              => $m['user_blocked'],
		'blocked'                  => __( 'Blocked', 'vms-elements-form-guard' ),
	);
}

/**
 * Check MX record for a domain.
 *
 * @param string $domain Domain to check.
 * @return bool True if MX records found.
 */
function vms_elements_form_guard_check_mx_record( string $domain ): bool {
	if ( empty( $domain ) ) {
		return false;
	}
	return (bool) checkdnsrr( $domain, 'MX' );
}

/**
 * Check if domain has valid A DNS record (domain is live/exists).
 *
 * @param string $domain Domain to check.
 * @return bool True if A record found.
 */
function vms_elements_form_guard_check_domain_dns( string $domain ): bool {
	if ( empty( $domain ) ) {
		return false;
	}

	return (bool) checkdnsrr( $domain, 'A' );
}

/**
 * Check if domain is in the disposable list.
 *
 * @param string $domain Domain to check.
 * @return bool True if disposable.
 */
function vms_elements_form_guard_is_disposable_domain( string $domain ): bool {
	global $wpdb;
	$table = $wpdb->prefix . 'vms_elements_form_guard_disposable_domains';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE domain = %s", strtolower( $domain ) ) );
	return (int) $count > 0;
}

/**
 * Check domain with Google Web Risk API.
 *
 * @param string $domain Domain to check.
 * @return array|null Result array with 'threat' key, or null on error.
 */
function vms_elements_form_guard_check_webrisk( string $domain ) {
	$google_config = get_option( 'vefg-google-config', array() );
	$api_key       = isset( $google_config['api_key'] ) ? $google_config['api_key'] : '';

	if ( empty( $api_key ) || empty( $domain ) ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'key'         => $api_key,
			'threatTypes' => 'MALWARE,SOCIAL_ENGINEERING,UNWANTED_SOFTWARE',
			'uri'         => 'https://' . $domain,
		),
		'https://webrisk.googleapis.com/v1/uris:search'
	);

	$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $response ) ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	return array(
		'threat' => ! empty( $data['threat'] ),
	);
}

/**
 * Check domain with VirusTotal API.
 *
 * @param string $domain Domain to check.
 * @return array|null Result array with 'malicious' count, or null on error.
 */
function vms_elements_form_guard_check_virustotal( string $domain ) {
	$vt_config = get_option( 'vefg-virustotal-config', array() );
	$api_key   = '';

	// Get first available key
	if ( ! empty( $vt_config['keys'] ) && is_array( $vt_config['keys'] ) ) {
		$api_key = reset( $vt_config['keys'] );
	}

	if ( empty( $api_key ) || empty( $domain ) ) {
		return null;
	}

	$response = wp_remote_get(
		'https://www.virustotal.com/api/v3/domains/' . urlencode( $domain ),
		array(
			'timeout' => 15,
			'headers' => array(
				'x-apikey' => $api_key,
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	$malicious = 0;
	if ( isset( $data['data']['attributes']['last_analysis_stats']['malicious'] ) ) {
		$malicious = (int) $data['data']['attributes']['last_analysis_stats']['malicious'];
	}

	return array(
		'malicious' => $malicious,
	);
}

/**
 * Record a strike against a user/visitor for spam behavior.
 *
 * @param string $reason      The reason for the strike.
 * @param string $source      The source of the strike (form_guard, comment, etc).
 * @param int    $user_id     Optional user ID for logged-in users.
 * @param string $guest_email Optional email for guest users.
 * @return array{blocked: bool, login_blocked: bool, strikes: int}
 */
function vms_elements_form_guard_record_strike( string $reason, string $source = 'form_guard', int $user_id = 0, string $guest_email = '' ): array {
	global $wpdb;

	// Check if admin is exempt
	$cfg = \VMS_Elements_Form_Guard\AI_Span_Config::get();
	if ( ! empty( $cfg['block_user_exempt_admins'] ) && current_user_can( 'manage_options' ) ) {
		return array(
			'blocked'       => false,
			'login_blocked' => false,
			'strikes'       => 0,
		);
	}

	if ( ! $cfg['block_user_enabled'] ) {
		return array(
			'blocked'       => false,
			'login_blocked' => false,
			'strikes'       => 0,
		);
	}

	$table       = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
	$ip          = vms_elements_form_guard_get_user_ip();
	$max_strikes = (int) ( $cfg['block_user_max_strikes'] ?? 5 );
	$expiry_days = (int) ( $cfg['block_user_strike_expiry_days'] ?? 30 );

	// Get current user ID if logged in
	if ( ! $user_id && is_user_logged_in() ) {
		$user_id = get_current_user_id();
	}

	// Generate actor key - for guests, use IP only so strikes accumulate by IP address
	if ( $user_id > 0 ) {
		$actor_key   = 'user_' . $user_id;
		$actor_label = '';
		$user_obj    = get_userdata( $user_id );
		if ( $user_obj ) {
			$actor_label = $user_obj->user_login;
		}
	} else {
		// Use IP-only hash for guests - all requests from same IP share strikes
		$actor_key   = 'ip_' . md5( $ip );
		$actor_label = 'Guest (' . $ip . ')';
		// Include email in label if provided
		if ( ! empty( $guest_email ) ) {
			$guest_email = sanitize_email( $guest_email );
			$actor_label = $guest_email . ' (' . $ip . ')';
		}
	}

	// Calculate expiry time
	$expire_at = null;
	if ( $expiry_days > 0 ) {
		$expire_at = gmdate( 'Y-m-d H:i:s', time() + ( $expiry_days * DAY_IN_SECONDS ) );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $actor_key ), ARRAY_A );

	$now = current_time( 'mysql', true );

	if ( $existing ) {
		// Check if strikes have expired
		$strikes = (int) $existing['strikes'];
		if ( ! empty( $existing['strikes_expire_at'] ) && strtotime( $existing['strikes_expire_at'] ) < time() ) {
			$strikes = 0; // Reset expired strikes
		}
		$strikes++;

		$blocked       = ( $strikes >= $max_strikes ) ? 1 : (int) $existing['blocked'];
		$login_blocked = 0;
		if ( ! empty( $cfg['block_user_login_block'] ) && $strikes >= $max_strikes ) {
			$login_blocked = 1;
		}

		$wpdb->update(
			$table,
			array(
				'strikes'           => $strikes,
				'blocked'           => $blocked,
				'login_blocked'     => $login_blocked,
				'last_ip'           => $ip,
				'last_strike_at'    => $now,
				'strikes_expire_at' => $expire_at,
				'last_reason'       => substr( $reason, 0, 500 ),
				'strike_source'     => $source,
				'user_id'           => $user_id > 0 ? $user_id : null,
				'blocked_at'        => $blocked && empty( $existing['blocked_at'] ) ? $now : $existing['blocked_at'],
			),
			array( 'actor_key' => $actor_key )
		);

		// Auto-logout if enabled and blocked
		if ( $login_blocked && ! empty( $cfg['block_user_auto_logout'] ) && $user_id > 0 ) {
			vms_elements_form_guard_force_logout_user( $user_id );
		}

		return array(
			'blocked'       => (bool) $blocked,
			'login_blocked' => (bool) $login_blocked,
			'strikes'       => $strikes,
		);
	}

	// New record
	$strikes       = 1;
	$blocked       = ( $strikes >= $max_strikes ) ? 1 : 0;
	$login_blocked = 0;
	if ( ! empty( $cfg['block_user_login_block'] ) && $strikes >= $max_strikes ) {
		$login_blocked = 1;
	}

	$wpdb->insert(
		$table,
		array(
			'actor_key'         => $actor_key,
			'actor_label'       => $actor_label,
			'user_id'           => $user_id > 0 ? $user_id : null,
			'strikes'           => $strikes,
			'blocked'           => $blocked,
			'login_blocked'     => $login_blocked,
			'last_ip'           => $ip,
			'blocked_at'        => $blocked ? $now : null,
			'last_strike_at'    => $now,
			'strikes_expire_at' => $expire_at,
			'last_reason'       => substr( $reason, 0, 500 ),
			'strike_source'     => $source,
		)
	);

	return array(
		'blocked'       => (bool) $blocked,
		'login_blocked' => (bool) $login_blocked,
		'strikes'       => $strikes,
	);
}

/**
 * Force logout a specific user by destroying their sessions.
 *
 * @param int $user_id User ID to logout.
 */
function vms_elements_form_guard_force_logout_user( int $user_id ): void {
	if ( $user_id <= 0 ) {
		return;
	}
	$sessions = \WP_Session_Tokens::get_instance( $user_id );
	$sessions->destroy_all();
}

/**
 * Check if current user/visitor is blocked from login.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function vms_elements_form_guard_is_login_blocked( int $user_id = 0 ): bool {
	global $wpdb;

	$cfg = \VMS_Elements_Form_Guard\AI_Span_Config::get();
	if ( ! $cfg['block_user_enabled'] || ! $cfg['block_user_login_block'] ) {
		return false;
	}

	$table = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
	$ip    = vms_elements_form_guard_get_user_ip();

	// Check by user ID
	if ( $user_id > 0 ) {
		$actor_key = 'user_' . $user_id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $actor_key ), ARRAY_A );
		if ( $row && ! empty( $row['login_blocked'] ) ) {
			// Check expiry
			if ( empty( $row['strikes_expire_at'] ) || strtotime( $row['strikes_expire_at'] ) > time() ) {
				return true;
			}
		}
	}

	// Also check by IP for guests (IP-only key)
	$guest_key = 'ip_' . md5( $ip );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $guest_key ), ARRAY_A );
	if ( $row && ! empty( $row['login_blocked'] ) ) {
		if ( empty( $row['strikes_expire_at'] ) || strtotime( $row['strikes_expire_at'] ) > time() ) {
			return true;
		}
	}

	// Legacy: also check old guest_ keys for backward compatibility
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$legacy_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key LIKE %s AND login_blocked = 1 AND last_ip = %s", 'guest_%', $ip ), ARRAY_A );
	foreach ( (array) $legacy_rows as $legacy_row ) {
		if ( empty( $legacy_row['strikes_expire_at'] ) || strtotime( $legacy_row['strikes_expire_at'] ) > time() ) {
			return true;
		}
	}

	return false;
}

/**
 * Get strike count for current user/visitor.
 *
 * @param int $user_id Optional user ID.
 * @return int
 */
function vms_elements_form_guard_get_strike_count( int $user_id = 0 ): int {
	global $wpdb;

	$table = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
	$ip    = vms_elements_form_guard_get_user_ip();

	// Check by user ID
	if ( $user_id > 0 ) {
		$actor_key = 'user_' . $user_id;
	} else {
		// Use IP-only key for guests
		$actor_key = 'ip_' . md5( $ip );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $actor_key ), ARRAY_A );
	if ( ! $row ) {
		// Check legacy guest_ keys by IP for backward compatibility
		if ( $user_id <= 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key LIKE %s AND last_ip = %s ORDER BY strikes DESC LIMIT 1", 'guest_%', $ip ), ARRAY_A );
		}
		if ( ! $row ) {
			return 0;
		}
	}

	// Check if strikes have expired
	if ( ! empty( $row['strikes_expire_at'] ) && strtotime( $row['strikes_expire_at'] ) < time() ) {
		return 0;
	}

	return (int) $row['strikes'];
}

/**
 * Check if current user/visitor is blocked from form submissions.
 *
 * @param int $user_id Optional user ID.
 * @return bool
 */
function vms_elements_form_guard_is_form_blocked( int $user_id = 0 ): bool {
	global $wpdb;

	$cfg = \VMS_Elements_Form_Guard\AI_Span_Config::get();
	if ( empty( $cfg['block_user_enabled'] ) ) {
		return false;
	}

	// Admin exemption
	if ( ! empty( $cfg['block_user_exempt_admins'] ) && current_user_can( 'manage_options' ) ) {
		return false;
	}

	$table = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
	$ip    = vms_elements_form_guard_get_user_ip();

	// Check by user ID
	if ( $user_id > 0 ) {
		$actor_key = 'user_' . $user_id;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $actor_key ), ARRAY_A );
		if ( $row && ! empty( $row['blocked'] ) ) {
			if ( empty( $row['strikes_expire_at'] ) || strtotime( $row['strikes_expire_at'] ) > time() ) {
				return true;
			}
		}
	}

	// Check by IP for guests (IP-only key)
	$guest_key = 'ip_' . md5( $ip );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key = %s", $guest_key ), ARRAY_A );
	if ( $row && ! empty( $row['blocked'] ) ) {
		if ( empty( $row['strikes_expire_at'] ) || strtotime( $row['strikes_expire_at'] ) > time() ) {
			return true;
		}
	}

	// Legacy: also check old guest_ keys by IP
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$legacy_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_key LIKE %s AND blocked = 1 AND last_ip = %s", 'guest_%', $ip ), ARRAY_A );
	foreach ( (array) $legacy_rows as $legacy_row ) {
		if ( empty( $legacy_row['strikes_expire_at'] ) || strtotime( $legacy_row['strikes_expire_at'] ) > time() ) {
			return true;
		}
	}

	return false;
}

/**
 * Check message content for spam using AI.
 *
 * @param string $content Content to check.
 * @return array|null Result array with 'is_spam' key, or null on error.
 */
function vms_elements_form_guard_check_ai_spam( string $content, array $context = array() ) {
	if ( empty( trim( $content ) ) ) {
		return null;
	}

	// Read from AI Span Config
	$settings      = get_option( 'vefg-ai-span-config', array() );
	$provider      = isset( $settings['provider'] ) ? $settings['provider'] : 'gemini';
	$openai_key    = isset( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '';
	$anthropic_key = isset( $settings['anthropic_api_key'] ) ? $settings['anthropic_api_key'] : '';
	$gemini_key    = isset( $settings['gemini_api_key'] ) ? $settings['gemini_api_key'] : '';
	$deepseek_key  = isset( $settings['deepseek_api_key'] ) ? $settings['deepseek_api_key'] : '';

	// Get configured models with defaults
	$openai_model    = isset( $settings['openai_model'] ) && $settings['openai_model'] ? $settings['openai_model'] : 'gpt-4o-mini';
	$anthropic_model = isset( $settings['anthropic_model'] ) && $settings['anthropic_model'] ? $settings['anthropic_model'] : 'claude-3-5-haiku-latest';
	$gemini_model    = isset( $settings['gemini_model'] ) && $settings['gemini_model'] ? $settings['gemini_model'] : 'gemini-2.0-flash-lite';
	$deepseek_model  = isset( $settings['deepseek_model'] ) && $settings['deepseek_model'] ? $settings['deepseek_model'] : 'deepseek-chat';

	// Validate provider has API key
	$valid_providers = array(
		'openai'    => ! empty( $openai_key ),
		'anthropic' => ! empty( $anthropic_key ),
		'gemini'    => ! empty( $gemini_key ),
		'deepseek'  => ! empty( $deepseek_key ),
	);

	if ( empty( $provider ) || ! isset( $valid_providers[ $provider ] ) || ! $valid_providers[ $provider ] ) {
		return null;
	}

	// Build context information for better AI analysis
	$form_name   = isset( $context['form_name'] ) ? $context['form_name'] : 'Contact Form';
	$field_type  = isset( $context['field_type'] ) ? $context['field_type'] : 'textarea';
	$field_name  = isset( $context['field_name'] ) ? $context['field_name'] : 'message';
	$page_title  = isset( $context['page_title'] ) ? $context['page_title'] : '';

	$prompt = "You are a strict spam moderator for website contact forms. Analyze the FORM_INPUT and determine if it is spam or legitimate.\n\n";
	$prompt .= "FORM_CONTEXT:\n";
	$prompt .= "- Form: {$form_name}\n";
	$prompt .= "- Field: {$field_name} ({$field_type})\n";
	if ( $page_title ) {
		$prompt .= "- Page: {$page_title}\n";
	}
	$prompt .= "\nFORM_INPUT:\n{$content}\n\n";
	$prompt .= "SPAM PATTERNS TO DETECT:\n";
	$prompt .= "- Promotional/affiliate content (earn money, work from home, click here)\n";
	$prompt .= "- SEO/backlink pitches (buy backlinks, rank #1, increase traffic)\n";
	$prompt .= "- Pharma/gambling/adult promotions (viagra, casino, xxx)\n";
	$prompt .= "- Crypto/loan/financial scams (bitcoin profit, instant loan)\n";
	$prompt .= "- Repeated text/emails (same content pasted multiple times)\n";
	$prompt .= "- Gibberish or random characters\n";
	$prompt .= "- Contact harvesting (email me at, whatsapp me)\n";
	$prompt .= "- Essay/homework writing services\n";
	$prompt .= "- Irrelevant content for a contact form\n";
	$prompt .= "- Suspicious or excessive links\n\n";
	$prompt .= "Respond with ONLY valid JSON (no markdown, no code fences):\n";
	$prompt .= "{\"status\":\"ok\"|\"spam\",\"reason\":\"Brief explanation if spam, or 'legitimate message' if ok\"}";

	$response = null;

	switch ( $provider ) {
		case 'openai':
			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $openai_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $openai_model,
							'messages'   => array(
								array(
									'role' => 'user',
									'content' => $prompt,
								),
							),
							'max_tokens' => 150,
						)
					),
				)
			);
			break;

		case 'anthropic':
			$response = wp_remote_post(
				'https://api.anthropic.com/v1/messages',
				array(
					'timeout' => 30,
					'headers' => array(
						'x-api-key'         => $anthropic_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $anthropic_model,
							'max_tokens' => 150,
							'messages'   => array(
								array(
									'role' => 'user',
									'content' => $prompt,
								),
							),
						)
					),
				)
			);
			break;

		case 'gemini':
			$response = wp_remote_post(
				'https://generativelanguage.googleapis.com/v1beta/models/' . $gemini_model . ':generateContent?key=' . $gemini_key,
				array(
					'timeout' => 30,
					'headers' => array(
						'Content-Type' => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'contents' => array(
								array(
									'parts' => array(
										array( 'text' => $prompt ),
									),
								),
							),
							'generationConfig' => array(
								'maxOutputTokens' => 150,
							),
						)
					),
				)
			);
			break;

		case 'deepseek':
			$response = wp_remote_post(
				'https://api.deepseek.com/chat/completions',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $deepseek_key,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'model'      => $deepseek_model,
							'messages'   => array(
								array(
									'role' => 'user',
									'content' => $prompt,
								),
							),
							'max_tokens' => 150,
						)
					),
				)
			);
			break;

		default:
			return null;
	}

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	// Extract reply based on provider response format
	$reply = '';
	switch ( $provider ) {
		case 'openai':
		case 'deepseek':
			// Both use OpenAI-compatible format
			if ( isset( $data['choices'][0]['message']['content'] ) ) {
				$reply = trim( $data['choices'][0]['message']['content'] );
			}
			break;

		case 'anthropic':
			if ( isset( $data['content'][0]['text'] ) ) {
				$reply = trim( $data['content'][0]['text'] );
			}
			break;

		case 'gemini':
			if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
				$reply = trim( $data['candidates'][0]['content']['parts'][0]['text'] );
			}
			break;
	}

	// Parse JSON response
	$ai_result = json_decode( $reply, true );
	if ( is_array( $ai_result ) && isset( $ai_result['status'] ) ) {
		return array(
			'is_spam' => ( 'spam' === strtolower( $ai_result['status'] ) ),
			'reason'  => isset( $ai_result['reason'] ) ? $ai_result['reason'] : '',
		);
	}

	// Fallback: check for SPAM keyword in response
	$upper_reply = strtoupper( $reply );
	return array(
		'is_spam' => ( strpos( $upper_reply, 'SPAM' ) !== false && strpos( $upper_reply, 'NOT_SPAM' ) === false && strpos( $upper_reply, '"OK"' ) === false ),
	);
}

/**
 * Get default error messages for validation.
 *
 * @return array<string, string>
 */
function vms_elements_form_guard_get_default_error_messages(): array {
	return array(
		// Registration Guard Messages
		'reg_blocked_title'      => __( 'Registration Blocked', 'vms-elements-form-guard' ),
		'reg_blocked_intro'      => __( 'We could not complete your registration due to security checks.', 'vms-elements-form-guard' ),
		'reg_dns_failed'         => __( 'The email domain does not appear to exist.', 'vms-elements-form-guard' ),
		'reg_mx_failed'          => __( 'The email domain cannot receive messages.', 'vms-elements-form-guard' ),
		'reg_disposable'         => __( 'Temporary email addresses are not permitted.', 'vms-elements-form-guard' ),
		'reg_rate_limit'         => __( 'Too many registration attempts. Please try again later.', 'vms-elements-form-guard' ),
		'reg_reputation_failed'  => __( 'This email domain did not pass our security screening.', 'vms-elements-form-guard' ),
		/* translators: 1: current attempt number, 2: max attempts allowed per day */
		'reg_rate_limit_count'   => __( 'Attempt %1$d of %2$d for today.', 'vms-elements-form-guard' ),
		'reg_contact_admin'      => __( 'Contact the site administrator if you need assistance.', 'vms-elements-form-guard' ),

		// Email Validation Messages
		'email_invalid_format'   => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ),
		'email_dns_failed'       => __( 'This email domain does not exist.', 'vms-elements-form-guard' ),
		'email_mx_failed'        => __( 'This email domain cannot receive messages.', 'vms-elements-form-guard' ),
		'email_disposable'       => __( 'Temporary email addresses are not allowed.', 'vms-elements-form-guard' ),
		'email_webrisk_flagged'  => __( 'This email domain has security issues.', 'vms-elements-form-guard' ),
		'email_virustotal_flagged' => __( 'This email domain may be unsafe.', 'vms-elements-form-guard' ),

		// URL Validation Messages
		'url_invalid'            => __( 'Please enter a valid URL.', 'vms-elements-form-guard' ),
		'url_dns_failed'         => __( 'This URL cannot be reached.', 'vms-elements-form-guard' ),
		'url_webrisk_flagged'    => __( 'This URL has been flagged for security issues.', 'vms-elements-form-guard' ),
		'url_virustotal_flagged' => __( 'This URL may be unsafe.', 'vms-elements-form-guard' ),

		// Content & Spam Messages
		'spam_detected'          => __( 'Your submission appears to be spam.', 'vms-elements-form-guard' ),
		'username_taken'         => __( 'This username is already in use.', 'vms-elements-form-guard' ),

		// reCAPTCHA Messages
		'recaptcha_required'     => __( 'Please complete the security verification.', 'vms-elements-form-guard' ),
		'recaptcha_failed'       => __( 'Security verification failed. Please try again.', 'vms-elements-form-guard' ),

		// General Messages
		'user_blocked'           => __( 'Access denied due to repeated violations.', 'vms-elements-form-guard' ),
		'validation_failed'      => __( 'Validation failed. Please check your input.', 'vms-elements-form-guard' ),
		'field_required'         => __( 'This field is required.', 'vms-elements-form-guard' ),
		'server_error'           => __( 'A server error occurred. Please try again.', 'vms-elements-form-guard' ),
	);
}

/**
 * Get a specific error message (custom or default).
 *
 * @param string $key     Message key.
 * @param array  $args    Optional sprintf arguments.
 * @return string
 */
function vms_elements_form_guard_get_error_message( string $key, array $args = array() ): string {
	static $custom_messages = null;
	static $defaults = null;

	if ( null === $custom_messages ) {
		$custom_messages = get_option( 'vefg-error-messages', array() );
	}
	if ( null === $defaults ) {
		$defaults = vms_elements_form_guard_get_default_error_messages();
	}

	$message = '';
	if ( isset( $custom_messages[ $key ] ) && '' !== trim( $custom_messages[ $key ] ) ) {
		$message = $custom_messages[ $key ];
	} elseif ( isset( $defaults[ $key ] ) ) {
		$message = $defaults[ $key ];
	}

	if ( '' === $message ) {
		return $message;
	}

	// Check if message contains placeholders like %1$d, %2$s, %d, %s, etc.
	$has_placeholders = preg_match( '/%(\d+\$)?[dfsb]/', $message );

	if ( $has_placeholders && ! empty( $args ) ) {
		// Message has placeholders and args provided - use vsprintf
		$message = vsprintf( $message, $args );
	} elseif ( $has_placeholders && empty( $args ) ) {
		// Message has placeholders but no args - strip placeholders for safe display
		// Replace %1$d, %2$d style placeholders with empty string or a safe value
		$message = preg_replace( '/%(\d+\$)?d/', '0', $message );
		$message = preg_replace( '/%(\d+\$)?s/', '', $message );
		$message = preg_replace( '/%(\d+\$)?f/', '0', $message );
		$message = preg_replace( '/%(\d+\$)?b/', '', $message );
	}
	// If no placeholders, just return message as-is

	return $message;
}

/**
 * Get all error messages for JavaScript localization.
 *
 * @return array<string, string>
 */
function vms_elements_form_guard_get_all_error_messages(): array {
	$defaults = vms_elements_form_guard_get_default_error_messages();
	$custom   = get_option( 'vefg-error-messages', array() );

	$messages = array();
	foreach ( $defaults as $key => $default ) {
		$messages[ $key ] = ( isset( $custom[ $key ] ) && '' !== trim( $custom[ $key ] ) ) ? $custom[ $key ] : $default;
	}

	return $messages;
}

/**
 * Whether the Pro plugin bootstrap constant is loaded (plugin active).
 */
function vms_elements_form_guard_is_pro_plugin_loaded(): bool {
	return defined( 'VMS_ELEMENTS_FORM_GUARD_PRO_VERSION' ) || defined( 'VMS_ELEMENTS_FORM_GUARD_PRO_FILE' );
}

/**
 * Licensed and Pro plugin present — Pro admin pages may register.
 */
function vms_elements_form_guard_pro_runtime_ready(): bool {
	if ( ! vms_elements_form_guard_is_pro_plugin_loaded() ) {
		return false;
	}
	return (bool) apply_filters( 'vms_elements_form_guard_is_pro_active', false );
}

/**
 * Mask an API key for display in admin placeholders.
 *
 * @param string $key Raw API key.
 * @return string Masked key or empty string.
 */
function vms_elements_form_guard_mask_api_key( string $key ): string {
	if ( '' === $key || strlen( $key ) < 10 ) {
		return '';
	}
	$start = substr( $key, 0, 6 );
	$end   = substr( $key, -4 );
	return $start . '••••••••••••' . $end;
}

/**
 * Base data passed to VEFGChecker for script localizations.
 *
 * @param array<string, mixed> $extra Additional keys merged on top.
 * @return array<string, mixed>
 */
function vms_elements_form_guard_script_localize_data( array $extra = array() ): array {
	$base = array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
	);
	if ( function_exists( 'vms_elements_form_guard_get_js_i18n' ) ) {
		$base['i18n'] = vms_elements_form_guard_get_js_i18n();
	}
	return array_merge( $base, $extra );
}

/**
 * URL for a plugin-owned JS file (minified in production when .min.js exists).
 *
 * @param string $name Basename without extension, e.g. `vms-elements-form-guard`.
 */
function vms_elements_form_guard_js_asset( string $name ): string {
	$name = preg_replace( '/\.(?:min\.)?js$/', '', $name );
	$dir  = defined( 'VMS_ELEMENTS_FORM_GUARD_DIR' ) ? VMS_ELEMENTS_FORM_GUARD_DIR : '';
	$base = defined( 'VMS_ELEMENTS_FORM_GUARD_ASSETS_URL' ) ? VMS_ELEMENTS_FORM_GUARD_ASSETS_URL : '';

	$use_min = ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG )
		&& '' !== $dir
		&& is_readable( $dir . 'assets/js/' . $name . '.min.js' );

	if ( $use_min ) {
		return $base . 'js/' . $name . '.min.js';
	}

	return $base . 'js/' . $name . '.js';
}
