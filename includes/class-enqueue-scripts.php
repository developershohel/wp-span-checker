<?php
/**
 * Front-end and admin assets.
 *
 * @package VMS_Elements_Form_Guard
 */

namespace VMS_Elements_Form_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Script and style registration.
 */
class Enqueue_Scripts {

	/**
	 * Regex examples for admin UI (translated labels). Built lazily on first enqueue (WP 6.7+).
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private $regex_list = null;

	/**
	 * Register hooks and data.
	 */
	public function __construct() {
		/* Priority 1: register assets before most plugins (default 10) so our JS runs first in footer. */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 5 );
		add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );

		// Login page scripts
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_scripts' ), 5 );
	}

	/**
	 * Scope admin styling for plugin screens.
	 *
	 * @param string $classes Space-separated body classes.
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return $classes;
		}
		$screen = get_current_screen();
		if ( $screen && false !== strpos( $screen->id, 'vms-elements-form-guard' ) ) {
			$classes .= ' vefg-plugin-admin';
		}
		return $classes;
	}

	/**
	 * Localized regex documentation rows (lazy — must not run before `init`).
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_cached_regex_list(): array {
		if ( null === $this->regex_list ) {
			$this->regex_list = $this->build_regex_list();
		}
		return $this->regex_list;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private function build_regex_list() {
		// Order: most-used contact / auth patterns first, niche formats later.
		return array(
			array(
				'key'             => 'strict_email_address_only',
				'name'            => __( 'Strict Email (addresses only)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/',
				'desc'            => __( 'One email-shaped address only—not a display name, sentence, or multiple addresses.', 'vms-elements-form-guard' ),
				'example'         => 'john.doe@gmail.com',
				'valid_example'   => 'john.doe@gmail.com',
				'invalid_example' => 'Jane Doe <jane@example.com>',
			),
			array(
				'key'             => 'strict_email_tld_limit',
				'name'            => __( 'Strict Email (TLD length limit)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,6}$/',
				'desc'            => __( 'Email format with TLD between 2 and 6 letters.', 'vms-elements-form-guard' ),
				'example'         => 'user@domain.co.uk',
				'valid_example'   => 'user@domain.co.uk',
				'invalid_example' => 'user@localhost',
			),
			array(
				'key'             => 'simple_email',
				'name'            => __( 'Simple Email (lenient)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/',
				'desc'            => __( 'Very loose UX check—allows unusual formats.', 'vms-elements-form-guard' ),
				'example'         => 'user@example.com',
				'valid_example'   => 'user@example.com',
				'invalid_example' => 'not-an-email',
			),
			array(
				'key'             => 'username_wp',
				'name'            => __( 'Username (3–16 characters)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[a-zA-Z0-9._-]{3,16}$/',
				'desc'            => __( 'Letters, numbers, dot, underscore, and hyphen.', 'vms-elements-form-guard' ),
				'example'         => 'john_doe',
				'valid_example'   => 'john_doe',
				'invalid_example' => 'ab',
			),
			array(
				'key'             => 'password_strong',
				'name'            => __( 'Strong Password (recommended)', 'vms-elements-form-guard' ),
				'pattern'         => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/',
				'desc'            => __( 'Minimum 8 characters with mixed case, number, and symbol.', 'vms-elements-form-guard' ),
				'example'         => 'Str0ng!Pass',
				'valid_example'   => 'Str0ng!Pass',
				'invalid_example' => 'weak',
			),
			array(
				'key'             => 'phone_e164',
				'name'            => __( 'International Phone (E.164)', 'vms-elements-form-guard' ),
				'pattern'         => '/^\+?[1-9]\d{1,14}$/',
				'desc'            => __( 'E.164 international phone format.', 'vms-elements-form-guard' ),
				'example'         => '+14155552671',
				'valid_example'   => '+14155552671',
				'invalid_example' => '++4400',
			),
			array(
				'key'             => 'phone_us',
				'name'            => __( 'US Phone (common)', 'vms-elements-form-guard' ),
				'pattern'         => '/^\(?([2-9][0-8][0-9])\)?[-.\s]?([2-9][0-9]{2})[-.\s]?([0-9]{4})$/',
				'desc'            => __( 'US phone numbers with optional parentheses or dashes.', 'vms-elements-form-guard' ),
				'example'         => '(415) 555-2671',
				'valid_example'   => '(415) 555-2671',
				'invalid_example' => '12345',
			),
			array(
				'key'             => 'url_http',
				'name'            => __( 'URL (http/https)', 'vms-elements-form-guard' ),
				'pattern'         => '/^(https?:\/\/)?([\w-]+\.)+[\w-]+(\/[-\w@:%_+.~#?&\/=]*)?$/',
				'desc'            => __( 'Checks basic HTTP/HTTPS URLs. Not fully RFC-compliant but practical.', 'vms-elements-form-guard' ),
				'example'         => 'https://example.com/path?x=1',
				'valid_example'   => 'https://example.com/path',
				'invalid_example' => 'ht!tp://bad',
			),
			array(
				'key'             => 'no_links_textarea',
				'name'            => __( 'Plain text (no URLs)', 'vms-elements-form-guard' ),
				'pattern'         => '/^(?!.*https?:\/\/).+$/is',
				'desc'            => __( 'Allows any text except strings that look like http(s) URLs.', 'vms-elements-form-guard' ),
				'example'         => __( 'Hello, thanks for your message.', 'vms-elements-form-guard' ),
				'valid_example'   => __( 'Hello, thanks for your message.', 'vms-elements-form-guard' ),
				'invalid_example' => 'Visit https://spam.example',
			),
			array(
				'key'             => 'alphanumeric_spaces',
				'name'            => __( 'Letters and numbers (spaces OK)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[a-zA-Z0-9\s]{1,200}$/',
				'desc'            => __( 'Safe short labels without punctuation.', 'vms-elements-form-guard' ),
				'example'         => 'Order 42 details',
				'valid_example'   => 'Order 42 details',
				'invalid_example' => 'Order 42!@#',
			),
			array(
				'key'             => 'numeric_only',
				'name'            => __( 'Digits only', 'vms-elements-form-guard' ),
				'pattern'         => '/^\d+$/',
				'desc'            => __( 'Whole numbers with no spaces or symbols.', 'vms-elements-form-guard' ),
				'example'         => '1024',
				'valid_example'   => '1024',
				'invalid_example' => '10a4',
			),
			array(
				'key'             => 'slug_lower',
				'name'            => __( 'Slug (lowercase, hyphen)', 'vms-elements-form-guard' ),
				'pattern'         => '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
				'desc'            => __( 'URL slug with lowercase words separated by hyphens.', 'vms-elements-form-guard' ),
				'example'         => 'my-blog-post-1',
				'valid_example'   => 'my-blog-post-1',
				'invalid_example' => 'CamelCase',
			),
			array(
				'key'             => 'date_iso',
				'name'            => __( 'Date YYYY-MM-DD', 'vms-elements-form-guard' ),
				'pattern'         => '/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',
				'desc'            => __( 'Simple date format (does not validate leap years).', 'vms-elements-form-guard' ),
				'example'         => '2026-09-21',
				'valid_example'   => '2026-09-21',
				'invalid_example' => '2026-13-40',
			),
			array(
				'key'             => 'hostname',
				'name'            => __( 'Domain (hostname)', 'vms-elements-form-guard' ),
				'pattern'         => '/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i',
				'desc'            => __( 'Hostname such as example.com or sub.example.net.', 'vms-elements-form-guard' ),
				'example'         => 'sub.example.com',
				'valid_example'   => 'sub.example.com',
				'invalid_example' => '-bad-.com',
			),
			array(
				'key'             => 'ipv4',
				'name'            => __( 'IPv4', 'vms-elements-form-guard' ),
				'pattern'         => '/^(25[0-5]|2[0-4]\d|1?\d?\d)(\.(25[0-5]|2[0-4]\d|1?\d?\d)){3}$/',
				'desc'            => __( 'Validates IPv4 addresses.', 'vms-elements-form-guard' ),
				'example'         => '192.168.0.1',
				'valid_example'   => '192.168.0.1',
				'invalid_example' => '999.0.0.1',
			),
			array(
				'key'             => 'ipv6_basic',
				'name'            => __( 'IPv6 (basic)', 'vms-elements-form-guard' ),
				'pattern'         => '/^([0-9a-f]{1,4}:){7}[0-9a-f]{1,4}$/i',
				'desc'            => __( 'Simple IPv6 validation (full form).', 'vms-elements-form-guard' ),
				'example'         => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
				'valid_example'   => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
				'invalid_example' => 'gggg::1',
			),
			array(
				'key'             => 'hex_color',
				'name'            => __( 'Hex Color (#rgb or #rrggbb)', 'vms-elements-form-guard' ),
				'pattern'         => '/^#?([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
				'desc'            => __( 'Hex color codes with or without a leading hash.', 'vms-elements-form-guard' ),
				'example'         => '#1a2b3c',
				'valid_example'   => '#1a2b3c',
				'invalid_example' => '#gg0000',
			),
			array(
				'key'             => 'uuid_v4',
				'name'            => __( 'UUID v4', 'vms-elements-form-guard' ),
				'pattern'         => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
				'desc'            => __( 'Validates UUID version 4.', 'vms-elements-form-guard' ),
				'example'         => '550e8400-e29b-41d4-a716-446655440000',
				'valid_example'   => '550e8400-e29b-41d4-a716-446655440000',
				'invalid_example' => '550e8400-e29b-41d4-a716',
			),
		);
	}

	/**
	 * Enqueue admin scripts (only on VMS Elements Form Guard screens).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook_suffix = '' ) {
		$hook_suffix = (string) $hook_suffix;
		$is_plugin_page = (
			false !== strpos( $hook_suffix, 'vms-elements-form-guard' )
			|| false !== strpos( $hook_suffix, 'vefg-' )
		);
		if ( ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style(
			'vms-elements-form-guard-dashboard',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'css/admin-dashboard.css',
			array(),
			VMS_ELEMENTS_FORM_GUARD_VERSION
		);

		wp_enqueue_style(
			'vms-elements-form-guard-ui',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'css/vms-elements-form-guard.css',
			array( 'vms-elements-form-guard-dashboard' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION
		);

		wp_enqueue_style(
			'vms-elements-form-guard-sweetalert',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.min.css',
			array( 'vms-elements-form-guard-ui' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION
		);
		wp_enqueue_script(
			'vms-elements-form-guard-sweetalert',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.all.min.js',
			array( 'jquery' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);
		$this->enqueue_shared_toast();

		wp_enqueue_script(
			'vefg-admin-toast',
			vms_elements_form_guard_js_asset( 'admin-toast' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		$needs_datatables = (
			false !== strpos( $hook_suffix, 'whitelist' )
			|| false !== strpos( $hook_suffix, 'disposable' )
			|| false !== strpos( $hook_suffix, 'form-settings' )
		);

		$needs_ai_summary = (
			false !== strpos( $hook_suffix, 'vms-elements-form-guard-ai-summaries' )
			|| false !== strpos( $hook_suffix, 'vms-elements-form-guard-ai-product-summaries' )
		);

		if ( $needs_ai_summary ) {
			wp_enqueue_script(
				'vefg-ai-admin',
				vms_elements_form_guard_js_asset( 'ai-admin' ),
				array( 'jquery', 'vms-elements-form-guard-sweetalert' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			wp_localize_script(
				'vefg-ai-admin',
				'VEFGAiAdmin',
				array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
					'i18n'    => array(
						'error'   => __( 'Request failed.', 'vms-elements-form-guard' ),
						'success' => __( 'Summary saved. Refreshing…', 'vms-elements-form-guard' ),
					),
				)
			);
		}

		// Email templates page needs media uploader for logo selection.
		$needs_media_uploader = ( false !== strpos( $hook_suffix, 'vefg-email-templates' ) );
		if ( $needs_media_uploader ) {
			wp_enqueue_media();
		}

		// Auth forms, email templates, and the Blocked Users page need a nonce + ajaxurl for AJAX.
		$needs_nonce_only = (
			false !== strpos( $hook_suffix, 'vefg-auth-forms' )
			|| false !== strpos( $hook_suffix, 'vefg-email-templates' )
			|| false !== strpos( $hook_suffix, 'vms-elements-form-guard-comment-blocks' )
		);
		if ( $needs_nonce_only ) {
			wp_localize_script(
				'vefg-admin-toast',
				'VEFGChecker',
				vms_elements_form_guard_script_localize_data()
			);
		}

		$this->enqueue_admin_screen_scripts( $hook_suffix );

		if ( ! $needs_datatables ) {
			return;
		}

		wp_enqueue_style( 'vms-elements-form-guard-datatable', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/DataTables/datatables.min.css', array( 'vms-elements-form-guard-ui' ), VMS_ELEMENTS_FORM_GUARD_VERSION );
		wp_enqueue_script( 'vms-elements-form-guard-datatable', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/DataTables/datatables.min.js', array( 'jquery' ), VMS_ELEMENTS_FORM_GUARD_VERSION, true );

		wp_enqueue_script(
			'vefg-domain-js',
			vms_elements_form_guard_js_asset( 'domains' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vefg-shared-toast' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_set_script_translations( 'vefg-domain-js', 'vms-elements-form-guard', VMS_ELEMENTS_FORM_GUARD_DIR . 'languages' );

		wp_localize_script(
			'vefg-domain-js',
			'VEFGChecker',
			vms_elements_form_guard_script_localize_data(
				array(
					'regexList'        => $this->get_cached_regex_list(),
					'pageTargetLabels' => vms_elements_form_guard_page_target_presets(),
				)
			)
		);
	}

	/**
	 * Shared SweetAlert toast singleton (avoids duplicate const across scripts).
	 */
	private function enqueue_shared_toast(): void {
		wp_enqueue_script(
			'vefg-shared-toast',
			vms_elements_form_guard_js_asset( 'vefg-shared-toast' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);
	}

	/**
	 * Enqueue public scripts when a form mapping applies to this request.
	 */
	public function enqueue_scripts() {
		$page_id  = get_queried_object_id();
		// Form Guard mappings live in a Pro-managed table. Free still ships the
		// enqueue / matching logic so the table can be read when Pro is on, but
		// gracefully handles the table-missing / class-missing cases.
		$all_rows = array();
		if ( class_exists( '\\VMS_Elements_Form_Guard\\Form_Settings' ) ) {
			$settings = new \VMS_Elements_Form_Guard\Form_Settings();
			$all_rows = $settings->get_settings() ?? array();
		}
		$filtered = array();

		foreach ( $all_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( vms_elements_form_guard_row_matches_current_request( $row ) ) {
				$filtered[] = $row;
			}
		}

		// Check Subscribe Guard and Contact Guard
		$subscribe_guard_active = $this->should_load_subscribe_guard( $page_id );
		$contact_guard_active = $this->should_load_contact_guard( $page_id );
		$login_guard_active = $this->should_load_login_guard_frontend( $page_id );
		$registration_guard_active = $this->should_load_registration_guard_frontend( $page_id );
		$auth_forms_active = $this->should_load_auth_forms();

		if ( empty( $filtered ) && ! $subscribe_guard_active && ! $contact_guard_active && ! $login_guard_active && ! $registration_guard_active && ! $auth_forms_active ) {
			return;
		}

		wp_enqueue_style( 'vms-elements-form-guard-sweetalert', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.min.css', array(), VMS_ELEMENTS_FORM_GUARD_VERSION );
		wp_enqueue_style( 'vms-elements-form-guard-frontend', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'css/vms-elements-form-guard.css', array( 'vms-elements-form-guard-sweetalert' ), VMS_ELEMENTS_FORM_GUARD_VERSION );
		wp_enqueue_script( 'vms-elements-form-guard-sweetalert', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.all.min.js', array( 'jquery' ), VMS_ELEMENTS_FORM_GUARD_VERSION, true );
		$this->enqueue_shared_toast();
		wp_enqueue_script(
			'vms-elements-form-guard',
			vms_elements_form_guard_js_asset( 'vms-elements-form-guard' ),
			array(
				'jquery',
				'vms-elements-form-guard-sweetalert',
				'vefg-shared-toast',
			),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_set_script_translations( 'vms-elements-form-guard', 'vms-elements-form-guard', VMS_ELEMENTS_FORM_GUARD_DIR . 'languages' );

		// Get reCAPTCHA config
		$recaptcha_config = get_option( 'vefg-recaptcha-config', array() );
		$recaptcha_data   = array(
			'enabled'  => false,
			'siteKey'  => '',
			'version'  => 'v2',
		);
		if ( ! empty( $recaptcha_config['site_key'] ) && ! empty( $recaptcha_config['secret_key'] ) ) {
			$recaptcha_data['siteKey'] = $recaptcha_config['site_key'];
			$recaptcha_data['version'] = $recaptcha_config['version'] ?? 'v2';
		}

		wp_localize_script(
			'vms-elements-form-guard',
			'VEFGChecker',
			vms_elements_form_guard_script_localize_data(
				array(
					'pageID'        => $page_id,
					'pageType'      => vms_elements_form_guard_get_current_page_type(),
					'bodyClasses'   => vms_elements_form_guard_get_current_body_classes(),
					'presetClasses' => vms_elements_form_guard_preset_body_classes(),
					'settings'      => $filtered,
					'regexList'     => $this->get_cached_regex_list(),
					'recaptcha'     => $recaptcha_data,
				)
			)
		);

		// Enqueue Subscribe Guard if active
		if ( $subscribe_guard_active ) {
			$this->enqueue_subscribe_guard();
		}

		// Enqueue Contact Guard if active
		if ( $contact_guard_active ) {
			$this->enqueue_contact_guard();
		}

		// Enqueue Login Guard on custom pages
		if ( $login_guard_active ) {
			$this->enqueue_login_guard_frontend();
		}

		// Enqueue Registration Guard on custom pages
		if ( $registration_guard_active ) {
			$this->enqueue_registration_guard_frontend();
		}

		// Enqueue Auth Forms if page has shortcode
		if ( $auth_forms_active ) {
			$this->enqueue_auth_forms();
		}
	}

	/**
	 * Check if Subscribe Guard should load on this page.
	 *
	 * @param int $page_id Current page ID.
	 * @return bool
	 */
	private function should_load_subscribe_guard( int $page_id ): bool {
		$cfg = AI_Span_Config::get();

		if ( empty( $cfg['subscribe_guard_enabled'] ) ) {
			return false;
		}

		// Form selector is required for Subscribe Guard
		if ( empty( $cfg['subscribe_guard_form_selector'] ) ) {
			return false;
		}

		$scope = $cfg['subscribe_guard_scope'] ?? 'site';

		if ( 'site' === $scope ) {
			return true;
		}

		// Check specific pages
		$page_ids_str = $cfg['subscribe_guard_page_ids'] ?? '';
		if ( empty( $page_ids_str ) ) {
			return false;
		}

		$page_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $page_ids_str ) ) ) );
		return in_array( $page_id, $page_ids, true );
	}

	/**
	 * Enqueue Subscribe Guard scripts.
	 */
	private function enqueue_subscribe_guard(): void {
		$cfg            = AI_Span_Config::get();
		$recaptcha_cfg  = get_option( 'vefg-recaptcha-config', array() );
		$recaptcha_enabled = ! empty( $cfg['subscribe_guard_recaptcha'] )
			&& ! empty( $recaptcha_cfg['site_key'] )
			&& ! empty( $recaptcha_cfg['secret_key'] );

		wp_enqueue_script(
			'vefg-subscribe-guard',
			vms_elements_form_guard_js_asset( 'subscribe-guard' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vms-elements-form-guard' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'vefg-subscribe-guard',
			'VEFGSubscribeGuard',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
				'formSelector'     => $cfg['subscribe_guard_form_selector'] ?? '',
				'submitSelector'   => $cfg['subscribe_guard_submit_selector'] ?? '',
				'recaptchaEnabled' => $recaptcha_enabled,
				'recaptchaSiteKey' => $recaptcha_enabled ? $recaptcha_cfg['site_key'] : '',
				'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
				'i18n'             => array(
					'validating'        => __( 'Validating...', 'vms-elements-form-guard' ),
					'submit'            => __( 'Subscribe', 'vms-elements-form-guard' ),
					'emailRequired'     => __( 'Email address is required.', 'vms-elements-form-guard' ),
					'emailInvalid'      => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ),
					'validationFailed'  => __( 'Validation failed.', 'vms-elements-form-guard' ),
					'serverError'       => __( 'Server error. Please try again.', 'vms-elements-form-guard' ),
					'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
					'userBlocked'       => __( 'You have been blocked due to repeated violations.', 'vms-elements-form-guard' ),
					'blocked'           => __( 'Blocked', 'vms-elements-form-guard' ),
				),
			)
		);
	}

	/**
	 * Check if Login Guard should load on frontend (custom login pages).
	 *
	 * @param int $page_id Current page ID.
	 * @return bool
	 */
	private function should_load_login_guard_frontend( int $page_id ): bool {
		$cfg           = AI_Span_Config::get();
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );

		if ( empty( $cfg['login_guard_enabled'] ) || empty( $cfg['login_guard_recaptcha'] ) || ! $has_recaptcha ) {
			return false;
		}

		$scope = $cfg['login_guard_scope'] ?? 'default';
		if ( 'default' === $scope ) {
			return false; // Handled by login_enqueue_scripts
		}

		// Form selector is required for custom pages
		if ( empty( $cfg['login_guard_form_selector'] ) ) {
			return false;
		}

		$page_ids_str = $cfg['login_guard_page_ids'] ?? '';
		if ( empty( $page_ids_str ) ) {
			return false;
		}

		$page_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $page_ids_str ) ) ) );
		return in_array( $page_id, $page_ids, true );
	}

	/**
	 * Check if Registration Guard should load on frontend (custom registration pages).
	 *
	 * @param int $page_id Current page ID.
	 * @return bool
	 */
	private function should_load_registration_guard_frontend( int $page_id ): bool {
		$cfg           = AI_Span_Config::get();
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );

		$reg_frontend_enabled  = ! empty( $cfg['registration_guard_frontend'] );
		$reg_recaptcha_enabled = ! empty( $cfg['registration_guard_recaptcha'] ) && $has_recaptcha;

		if ( ! $reg_frontend_enabled && ! $reg_recaptcha_enabled ) {
			return false;
		}

		$scope = $cfg['registration_guard_scope'] ?? 'default';
		if ( 'default' === $scope ) {
			return false; // Handled by login_enqueue_scripts
		}

		// Form selector is required for custom pages
		if ( empty( $cfg['registration_guard_form_selector'] ) ) {
			return false;
		}

		$page_ids_str = $cfg['registration_guard_page_ids'] ?? '';
		if ( empty( $page_ids_str ) ) {
			return false;
		}

		$page_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $page_ids_str ) ) ) );
		return in_array( $page_id, $page_ids, true );
	}

	/**
	 * Check if Contact Guard should load on this page.
	 *
	 * @param int $page_id Current page ID.
	 * @return bool
	 */
	private function should_load_contact_guard( int $page_id ): bool {
		$cfg = AI_Span_Config::get();

		if ( empty( $cfg['contact_guard_enabled'] ) ) {
			return false;
		}

		// Form selector is required for Contact Guard
		if ( empty( $cfg['contact_guard_form_selector'] ) ) {
			return false;
		}

		$scope = $cfg['contact_guard_scope'] ?? 'site';

		if ( 'site' === $scope ) {
			return true;
		}

		$page_ids_str = $cfg['contact_guard_page_ids'] ?? '';
		if ( empty( $page_ids_str ) ) {
			return false;
		}

		$page_ids = array_map( 'absint', array_filter( array_map( 'trim', explode( ',', $page_ids_str ) ) ) );
		return in_array( $page_id, $page_ids, true );
	}

	/**
	 * Enqueue Contact Guard scripts.
	 */
	private function enqueue_contact_guard(): void {
		$cfg            = AI_Span_Config::get();
		$recaptcha_cfg  = get_option( 'vefg-recaptcha-config', array() );
		$recaptcha_enabled = ! empty( $cfg['contact_guard_recaptcha'] )
			&& ! empty( $recaptcha_cfg['site_key'] )
			&& ! empty( $recaptcha_cfg['secret_key'] );

		wp_enqueue_script(
			'vefg-contact-guard',
			vms_elements_form_guard_js_asset( 'contact-guard' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vms-elements-form-guard' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'vefg-contact-guard',
			'VEFGContactGuard',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
				'formSelector'     => $cfg['contact_guard_form_selector'] ?? '',
				'submitSelector'   => $cfg['contact_guard_submit_selector'] ?? '',
				'recaptchaEnabled' => $recaptcha_enabled,
				'recaptchaSiteKey' => $recaptcha_enabled ? $recaptcha_cfg['site_key'] : '',
				'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
				'i18n'             => array(
					'validating'        => __( 'Validating...', 'vms-elements-form-guard' ),
					'submit'            => __( 'Submit', 'vms-elements-form-guard' ),
					'emailRequired'     => __( 'Email address is required.', 'vms-elements-form-guard' ),
					'emailInvalid'      => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ),
					'validationFailed'  => __( 'Validation failed.', 'vms-elements-form-guard' ),
					'serverError'       => __( 'Server error. Please try again.', 'vms-elements-form-guard' ),
					'spamDetected'      => __( 'Your message appears to be spam.', 'vms-elements-form-guard' ),
					'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
					'userBlocked'       => __( 'You have been blocked due to repeated violations.', 'vms-elements-form-guard' ),
					'blocked'           => __( 'Blocked', 'vms-elements-form-guard' ),
					'fieldRequired'     => __( 'This field is required.', 'vms-elements-form-guard' ),
					'checkFields'       => __( 'Please fill in all required fields.', 'vms-elements-form-guard' ),
				),
			)
		);
	}

	/**
	 * Enqueue Login Guard on custom frontend pages.
	 */
	private function enqueue_login_guard_frontend(): void {
		$cfg           = AI_Span_Config::get();
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );

		wp_enqueue_script(
			'vefg-login-guard',
			vms_elements_form_guard_js_asset( 'login-guard' ),
			array( 'jquery', 'vms-elements-form-guard' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'vefg-login-guard',
			'VEFGLoginGuard',
			array(
				'recaptchaEnabled' => true,
				'recaptchaSiteKey' => $recaptcha_cfg['site_key'],
				'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
				'formSelector'     => $cfg['login_guard_form_selector'] ?? '',
				'i18n'             => array(
					'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
				),
			)
		);
	}

	/**
	 * Enqueue Registration Guard on custom frontend pages.
	 */
	private function enqueue_registration_guard_frontend(): void {
		$cfg           = AI_Span_Config::get();
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );

		$reg_frontend_enabled  = ! empty( $cfg['registration_guard_frontend'] );
		$reg_recaptcha_enabled = ! empty( $cfg['registration_guard_recaptcha'] ) && $has_recaptcha;

		wp_enqueue_script(
			'vefg-registration-guard',
			vms_elements_form_guard_js_asset( 'registration-guard' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vms-elements-form-guard' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'vefg-registration-guard',
			'VEFGRegistrationGuard',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
				'frontendEnabled'  => $reg_frontend_enabled,
				'recaptchaEnabled' => $reg_recaptcha_enabled,
				'recaptchaSiteKey' => $has_recaptcha ? $recaptcha_cfg['site_key'] : '',
				'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
				'formSelector'     => $cfg['registration_guard_form_selector'] ?? '',
				'i18n'             => array(
					'validating'        => __( 'Validating...', 'vms-elements-form-guard' ),
					'register'          => __( 'Register', 'vms-elements-form-guard' ),
					'emailRequired'     => __( 'Email address is required.', 'vms-elements-form-guard' ),
					'emailInvalid'      => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ),
					'validationFailed'  => __( 'Validation failed.', 'vms-elements-form-guard' ),
					'serverError'       => __( 'Server error. Please try again.', 'vms-elements-form-guard' ),
					'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
				),
			)
		);
	}

	/**
	 * Enqueue login page scripts (Login Guard + Registration Guard).
	 */
	public function enqueue_login_scripts(): void {
		$cfg           = AI_Span_Config::get();
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );

		// Check if Login Guard should be active (scope check - default wp-login.php only)
		$login_guard_enabled = ! empty( $cfg['login_guard_enabled'] ) && ! empty( $cfg['login_guard_recaptcha'] ) && $has_recaptcha;
		$login_guard_scope   = $cfg['login_guard_scope'] ?? 'default';

		// For wp-login.php, only load if scope is 'default'
		if ( $login_guard_enabled && 'specific' === $login_guard_scope ) {
			$login_guard_enabled = false; // Will be loaded on frontend pages instead
		}

		// Check if Registration Guard should be active (scope check)
		$reg_frontend_enabled  = ! empty( $cfg['registration_guard_frontend'] );
		$reg_recaptcha_enabled = ! empty( $cfg['registration_guard_recaptcha'] ) && $has_recaptcha;
		$reg_guard_scope       = $cfg['registration_guard_scope'] ?? 'default';

		// For wp-login.php?action=register, only load if scope is 'default'
		if ( ( $reg_frontend_enabled || $reg_recaptcha_enabled ) && 'specific' === $reg_guard_scope ) {
			$reg_frontend_enabled  = false;
			$reg_recaptcha_enabled = false;
		}

		// Check current action. This is wp-login.php read-only routing; no
		// state change happens here so a nonce is not required.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		$is_login = empty( $action ) || 'login' === $action || 'postpass' === $action;
		$is_register = 'register' === $action;

		// Enqueue Login Guard
		if ( $login_guard_enabled && $is_login ) {
			wp_enqueue_script(
				'vefg-login-guard',
				vms_elements_form_guard_js_asset( 'login-guard' ),
				array( 'jquery' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);

			wp_localize_script(
				'vefg-login-guard',
				'VEFGLoginGuard',
				array(
					'recaptchaEnabled' => true,
					'recaptchaSiteKey' => $recaptcha_cfg['site_key'],
					'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
					'formSelector'     => '', // Default wp-login.php uses built-in selectors
					'i18n'             => array(
						'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
					),
				)
			);
		}

		// Enqueue Registration Guard
		if ( ( $reg_frontend_enabled || $reg_recaptcha_enabled ) && $is_register ) {
			wp_enqueue_style( 'vms-elements-form-guard-sweetalert', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.min.css', array(), VMS_ELEMENTS_FORM_GUARD_VERSION );
			wp_enqueue_script( 'vms-elements-form-guard-sweetalert', VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.all.min.js', array( 'jquery' ), VMS_ELEMENTS_FORM_GUARD_VERSION, true );

			wp_enqueue_script(
				'vefg-registration-guard',
				vms_elements_form_guard_js_asset( 'registration-guard' ),
				array( 'jquery', 'vms-elements-form-guard-sweetalert' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);

			wp_localize_script(
				'vefg-registration-guard',
				'VEFGRegistrationGuard',
				array(
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
					'frontendEnabled'  => $reg_frontend_enabled,
					'recaptchaEnabled' => $reg_recaptcha_enabled,
					'recaptchaSiteKey' => $has_recaptcha ? $recaptcha_cfg['site_key'] : '',
					'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
					'formSelector'     => '', // Default wp-login.php uses built-in selectors
					'i18n'             => array(
						'validating'        => __( 'Validating...', 'vms-elements-form-guard' ),
						'register'          => __( 'Register', 'vms-elements-form-guard' ),
						'emailRequired'     => __( 'Email address is required.', 'vms-elements-form-guard' ),
						'emailInvalid'      => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ),
						'validationFailed'  => __( 'Validation failed.', 'vms-elements-form-guard' ),
						'serverError'       => __( 'Server error. Please try again.', 'vms-elements-form-guard' ),
						'recaptchaRequired' => __( 'Please complete the reCAPTCHA verification.', 'vms-elements-form-guard' ),
					),
				)
			);
		}
	}

	/**
	 * Check if Auth Forms should be loaded.
	 *
	 * @return bool
	 */
	private function should_load_auth_forms(): bool {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		// Check for auth form shortcodes
		$shortcodes = array(
			'vefg_login_form',
			'vefg_register_form',
			'vefg_forgot_password_form',
			'vefg_reset_password_form',
			'vefg_otp_verify_form',
			'vefg_activation_form',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		// Check for page meta
		$form_type = get_post_meta( $post->ID, '_vefg_auth_form_type', true );
		if ( ! empty( $form_type ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue Auth Forms scripts and styles.
	 */
	private function enqueue_auth_forms(): void {
		$recaptcha_cfg = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );
		$settings      = Auth_Forms::get_settings();

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'vefg-auth-forms',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'css/auth-forms.css',
			array(),
			VMS_ELEMENTS_FORM_GUARD_VERSION
		);

		// Inject the per-site CSS custom properties as a properly enqueued inline style
		// (instead of a raw <style> tag echoed inside the shortcode output).
		$auth_css = sprintf(
			'.vefg-auth-form-wrap{--vefg-primary:%1$s;--vefg-secondary:%2$s;--vefg-text:%3$s;--vefg-bg:%4$s;--vefg-border:%5$s;--vefg-border-hover:%6$s;--vefg-border-focus:%7$s;--vefg-input-bg:%8$s;--vefg-input-focus-bg:%9$s;--vefg-error:%10$s;--vefg-success:%11$s;--vefg-radius:%12$spx;--vefg-width:%13$spx;}',
			esc_attr( $settings['primary_color'] ),
			esc_attr( $settings['secondary_color'] ),
			esc_attr( $settings['text_color'] ),
			esc_attr( $settings['background_color'] ),
			esc_attr( $settings['border_color'] ?? '#d1d5db' ),
			esc_attr( $settings['border_hover_color'] ?? '#9ca3af' ),
			esc_attr( $settings['border_focus_color'] ?? '#2563eb' ),
			esc_attr( $settings['input_bg_color'] ?? '#ffffff' ),
			esc_attr( $settings['input_focus_bg'] ?? '#f9fafb' ),
			esc_attr( $settings['error_color'] ?? '#dc2626' ),
			esc_attr( $settings['success_color'] ?? '#16a34a' ),
			esc_attr( $settings['border_radius'] ),
			esc_attr( $settings['form_width'] )
		);
		wp_add_inline_style( 'vefg-auth-forms', $auth_css );

		wp_enqueue_script(
			'vefg-auth-forms',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'js/auth-forms.js',
			array( 'jquery' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_localize_script(
			'vefg-auth-forms',
			'VEFGAuthForms',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'recaptchaSiteKey' => $has_recaptcha ? $recaptcha_cfg['site_key'] : '',
				'recaptchaVersion' => $recaptcha_cfg['version'] ?? 'v2',
				'i18n'             => array(
					'completeRecaptcha'  => __( 'Please complete the reCAPTCHA.', 'vms-elements-form-guard' ),
					'networkError'       => __( 'Network error. Please try again.', 'vms-elements-form-guard' ),
					'passwordsMismatch'  => __( 'Passwords do not match.', 'vms-elements-form-guard' ),
					'passwordTooShort'   => __( 'Password must be at least 8 characters.', 'vms-elements-form-guard' ),
				),
			)
		);
	}

	/**
	 * Enqueue page-specific admin scripts (replaces template ob_start inline JS).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	private function enqueue_admin_screen_scripts( string $hook_suffix ): void {
		if ( false !== strpos( $hook_suffix, 'vms-elements-form-guard-login-guard' ) ) {
			$this->enqueue_admin_page_picker(
				array(
					'scopeSelector'       => '#login_guard_scope',
					'pagesRowSelector'    => '#login_guard_pages_row',
					'selectorRowSelector' => '#login_guard_selector_row',
					'searchInput'         => '#login_guard_page_search',
					'resultsContainer'    => '#login_guard_page_results',
					'selectedContainer'   => '#login_guard_selected_pages',
					'hiddenInput'         => '#login_guard_page_ids',
				)
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vms-elements-form-guard-registration' ) ) {
			$this->enqueue_admin_page_picker(
				array(
					'scopeSelector'       => '#rg_scope',
					'pagesRowSelector'    => '#rg_pages_row',
					'selectorRowSelector' => '#rg_selector_row',
					'searchInput'         => '#rg_page_search',
					'resultsContainer'    => '#rg_page_results',
					'selectedContainer'   => '#rg_selected_pages',
					'hiddenInput'         => '#rg_page_ids',
				)
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vefg-error-messages' ) ) {
			wp_enqueue_script(
				'vefg-admin-error-messages',
				vms_elements_form_guard_js_asset( 'admin-error-messages' ),
				array( 'jquery', 'vefg-admin-toast' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			wp_localize_script(
				'vefg-admin-error-messages',
				'VEFGErrorMessages',
				array(
					'i18n' => array(
						'resetConfirm'  => __( 'Reset all messages to defaults? This will restore all default messages.', 'vms-elements-form-guard' ),
						'defaultLabel'  => __( 'Default', 'vms-elements-form-guard' ),
						'defaultTitle'  => __( 'Click to reset to default', 'vms-elements-form-guard' ),
						'customLabel'   => __( 'Custom', 'vms-elements-form-guard' ),
						'resetSingle'   => __( 'Reset to default', 'vms-elements-form-guard' ),
					),
				)
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vms-elements-form-guard-ai-settings' ) ) {
			$cfg = AI_Span_Config::get();
			wp_enqueue_script(
				'vefg-admin-ai-settings',
				vms_elements_form_guard_js_asset( 'admin-ai-settings' ),
				array( 'vefg-admin-toast' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			wp_localize_script(
				'vefg-admin-ai-settings',
				'VEFGAiSettings',
				array(
					'replacePlaceholder' => __( 'Enter new key to replace', 'vms-elements-form-guard' ),
					'keyMasks'           => array(
						'openai_api_key'    => vms_elements_form_guard_mask_api_key( $cfg['openai_api_key'] ?? '' ),
						'anthropic_api_key' => vms_elements_form_guard_mask_api_key( $cfg['anthropic_api_key'] ?? '' ),
						'gemini_api_key'    => vms_elements_form_guard_mask_api_key( $cfg['gemini_api_key'] ?? '' ),
						'deepseek_api_key'  => vms_elements_form_guard_mask_api_key( $cfg['deepseek_api_key'] ?? '' ),
					),
				)
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vms-elements-form-guard-comment-settings' ) ) {
			wp_enqueue_script(
				'vefg-admin-spam-tabs',
				vms_elements_form_guard_js_asset( 'admin-spam-tabs' ),
				array( 'jquery', 'vefg-admin-toast' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vms-elements-form-guard-comment-blocks' ) ) {
			wp_enqueue_script(
				'vefg-admin-comment-blocks',
				vms_elements_form_guard_js_asset( 'admin-comment-blocks' ),
				array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vefg-admin-toast' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			wp_localize_script(
				'vefg-admin-comment-blocks',
				'VEFGCommentBlocks',
				array(
					'i18n' => array(
						'helperLookup'      => __( 'Look up a user first; the block button activates once a valid user is matched.', 'vms-elements-form-guard' ),
						'enterQuery'        => __( 'Enter an ID, username, or email.', 'vms-elements-form-guard' ),
						'lookingUp'         => __( 'Looking up…', 'vms-elements-form-guard' ),
						'noMatch'           => __( 'No user matches that input.', 'vms-elements-form-guard' ),
						'idLabel'           => __( 'ID', 'vms-elements-form-guard' ),
						'noRole'            => __( 'no role', 'vms-elements-form-guard' ),
						'alreadyBlocked'    => __( 'Already blocked', 'vms-elements-form-guard' ),
						'scopeForm'         => __( 'Form', 'vms-elements-form-guard' ),
						'scopeLogin'        => __( 'Login', 'vms-elements-form-guard' ),
						'scopeSite'         => __( 'Site', 'vms-elements-form-guard' ),
						'notBlocked'        => __( 'Not currently blocked.', 'vms-elements-form-guard' ),
						'helperReview'      => __( 'Review the preview, then click "Block this user".', 'vms-elements-form-guard' ),
						'lookupFailed'      => __( 'Lookup failed. Try again.', 'vms-elements-form-guard' ),
						'lookupFirst'       => __( 'Look up a user first.', 'vms-elements-form-guard' ),
						'pickScope'         => __( 'Pick at least one block scope.', 'vms-elements-form-guard' ),
						'editScopeTitle'    => __( 'Edit block scope', 'vms-elements-form-guard' ),
						'scopeFormComments' => __( 'Form / Comments', 'vms-elements-form-guard' ),
						'scopeSiteBan'      => __( 'Site-wide ban', 'vms-elements-form-guard' ),
						'save'              => __( 'Save', 'vms-elements-form-guard' ),
						'cancel'            => __( 'Cancel', 'vms-elements-form-guard' ),
						'saved'             => __( 'Saved.', 'vms-elements-form-guard' ),
						'saveFailed'        => __( 'Could not save.', 'vms-elements-form-guard' ),
						'requestFailed'     => __( 'Request failed.', 'vms-elements-form-guard' ),
					),
				)
			);
			return;
		}

		if ( false !== strpos( $hook_suffix, 'vefg-auth-forms' ) ) {
			wp_enqueue_script(
				'vefg-admin-auth-forms-settings',
				vms_elements_form_guard_js_asset( 'admin-auth-forms-settings' ),
				array( 'jquery', 'vms-elements-form-guard-sweetalert', 'vefg-admin-toast' ),
				VMS_ELEMENTS_FORM_GUARD_VERSION,
				true
			);
			wp_localize_script(
				'vefg-admin-auth-forms-settings',
				'VEFGAuthFormsAdmin',
				array(
					'i18n' => array(
						'copied'          => __( 'Copied!', 'vms-elements-form-guard' ),
						'generating'      => __( 'Generating...', 'vms-elements-form-guard' ),
						'generatePages'   => __( 'Auto-Generate All Auth Pages', 'vms-elements-form-guard' ),
						'success'         => __( 'Success', 'vms-elements-form-guard' ),
						'error'           => __( 'Error', 'vms-elements-form-guard' ),
						'saving'          => __( 'Saving...', 'vms-elements-form-guard' ),
						'saved'           => __( 'Saved!', 'vms-elements-form-guard' ),
						'enterEmail'      => __( 'Please enter an email address.', 'vms-elements-form-guard' ),
						'sending'         => __( 'Sending...', 'vms-elements-form-guard' ),
						'typeHere'        => __( 'Type here...', 'vms-elements-form-guard' ),
						'login'           => __( 'Login', 'vms-elements-form-guard' ),
						'usernameOrEmail' => __( 'Username or Email', 'vms-elements-form-guard' ),
						'password'        => __( 'Password', 'vms-elements-form-guard' ),
						'createAccount'   => __( 'Create Account', 'vms-elements-form-guard' ),
						'username'        => __( 'Username', 'vms-elements-form-guard' ),
						'email'           => __( 'Email', 'vms-elements-form-guard' ),
						'confirmPassword' => __( 'Confirm Password', 'vms-elements-form-guard' ),
						'resetPassword'   => __( 'Reset Password', 'vms-elements-form-guard' ),
						'sendResetLink'   => __( 'Send Reset Link', 'vms-elements-form-guard' ),
						'resetHint'       => __( 'Enter your email to receive a reset link.', 'vms-elements-form-guard' ),
						'testStates'      => __( 'Test states:', 'vms-elements-form-guard' ),
						'errorState'      => __( 'Error', 'vms-elements-form-guard' ),
						'successState'    => __( 'Success', 'vms-elements-form-guard' ),
					),
				)
			);
		}
	}

	/**
	 * Shared admin page picker (Login / Registration guard settings).
	 *
	 * @param array<string, string> $selectors DOM selectors for the picker UI.
	 */
	private function enqueue_admin_page_picker( array $selectors ): void {
		wp_enqueue_script(
			'vefg-admin-page-picker',
			vms_elements_form_guard_js_asset( 'admin-page-picker' ),
			array( 'jquery', 'vefg-admin-toast' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);
		wp_localize_script(
			'vefg-admin-page-picker',
			'VEFGAdminPagePicker',
			array_merge(
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
					'specificValue' => 'specific',
					'i18n'          => array(
						'remove'    => __( 'Remove', 'vms-elements-form-guard' ),
						'noResults' => __( 'No pages found or all matching pages already selected.', 'vms-elements-form-guard' ),
					),
				),
				$selectors
			)
		);
	}
}
