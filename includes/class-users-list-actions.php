<?php
/**
 * WordPress Users list integration — adds a "Block Status" column,
 * "Block / Unblock" row actions, and Block/Unblock bulk actions to
 * the wp-admin/users.php screen.
 *
 * Direct `$wpdb` queries below target the plugin-owned
 * `{$wpdb->prefix}vms_elements_form_guard_comment_enforcement` custom table; identifiers
 * are hardcoded and values pass through `$wpdb->prepare()`.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 */

namespace VMS_Elements_Form_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into wp-admin/users.php to expose Block / Unblock controls.
 */
class Users_List_Actions {

	/**
	 * Column slug.
	 */
	const COLUMN_KEY = 'vms_elements_form_guard_block_status';

	/**
	 * Cached scope-by-user-id map for the rows being rendered on the current request.
	 *
	 * @var array<int, array{form:bool,login:bool,site:bool,strikes:int,last_reason:string}>
	 */
	private $row_cache = array();

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_filter( 'manage_users_columns', array( $this, 'add_block_status_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_block_status_column' ), 10, 3 );
		add_filter( 'user_row_actions', array( $this, 'add_row_action' ), 10, 2 );

		add_filter( 'bulk_actions-users', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'maybe_render_bulk_notice' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Inject the Block Status column right before the "Posts" column.
	 *
	 * @param array<string, string> $columns Existing column map (key => label).
	 * @return array<string, string>
	 */
	public function add_block_status_column( $columns ) {
		if ( ! is_array( $columns ) ) {
			return $columns;
		}
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'posts' === $key ) {
				$new[ self::COLUMN_KEY ] = __( 'Block Status', 'vms-elements-form-guard' );
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new[ self::COLUMN_KEY ] ) ) {
			$new[ self::COLUMN_KEY ] = __( 'Block Status', 'vms-elements-form-guard' );
		}
		return $new;
	}

	/**
	 * Render the Block Status cell for one user row.
	 *
	 * @param string $output      Default column output (typically empty for custom columns).
	 * @param string $column_name Column slug.
	 * @param int    $user_id     User ID for the row.
	 * @return string
	 */
	public function render_block_status_column( $output, $column_name, $user_id ) {
		if ( self::COLUMN_KEY !== $column_name ) {
			return $output;
		}

		$row = $this->get_enforcement_row( (int) $user_id );

		if ( ! $row || ( ! $row['form'] && ! $row['login'] && ! $row['site'] && $row['strikes'] === 0 ) ) {
			return '<span class="description" style="color:#666;">—</span>';
		}

		$pills = '';
		if ( $row['form'] ) {
			$pills .= '<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fcf0f1;color:#a02b30;font-size:11px;margin:0 4px 2px 0;">'
				. '<span class="dashicons dashicons-format-chat" style="font-size:13px;vertical-align:middle;"></span> '
				. esc_html__( 'Form', 'vms-elements-form-guard' ) . '</span>';
		}
		if ( $row['login'] ) {
			$pills .= '<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fff8e5;color:#996800;font-size:11px;margin:0 4px 2px 0;">'
				. '<span class="dashicons dashicons-lock" style="font-size:13px;vertical-align:middle;"></span> '
				. esc_html__( 'Login', 'vms-elements-form-guard' ) . '</span>';
		}
		if ( $row['site'] ) {
			$pills .= '<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fcebec;color:#7e1c20;font-size:11px;margin:0 4px 2px 0;">'
				. '<span class="dashicons dashicons-shield" style="font-size:13px;vertical-align:middle;"></span> '
				. esc_html__( 'Site', 'vms-elements-form-guard' ) . '</span>';
		}

		if ( '' === $pills && $row['strikes'] > 0 ) {
			$pills .= '<span class="description" style="color:#996800;">'
				. sprintf(
					/* translators: %d: strike count */
					esc_html__( '%d strike(s)', 'vms-elements-form-guard' ),
					(int) $row['strikes']
				)
				. '</span>';
		}

		return $pills;
	}

	/**
	 * Append "Block" / "Unblock" links to the row action list.
	 *
	 * @param array<string, string> $actions Existing action HTML map.
	 * @param \WP_User              $user    Target user.
	 * @return array<string, string>
	 */
	public function add_row_action( $actions, $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return $actions;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		// Never offer block controls for the current admin's own row.
		if ( (int) get_current_user_id() === (int) $user->ID ) {
			return $actions;
		}

		$row = $this->get_enforcement_row( (int) $user->ID );
		$is_blocked = $row && ( $row['form'] || $row['login'] || $row['site'] );

		$label = $user->display_name . ' (' . $user->user_login . ')';

		if ( $is_blocked ) {
			$actions['vms_elements_form_guard_block'] = sprintf(
				'<a href="#" class="vms-elements-form-guard-user-block-trigger" data-user-id="%1$d" data-user-label="%2$s" data-form="%3$s" data-login="%4$s" data-site="%5$s" data-mode="edit" style="color:#0073aa;">%6$s</a>',
				(int) $user->ID,
				esc_attr( $label ),
				esc_attr( $row['form'] ? '1' : '0' ),
				esc_attr( $row['login'] ? '1' : '0' ),
				esc_attr( $row['site'] ? '1' : '0' ),
				esc_html__( 'Edit Block', 'vms-elements-form-guard' )
			);
			$actions['vms_elements_form_guard_unblock'] = sprintf(
				'<a href="#" class="vms-elements-form-guard-user-unblock-trigger" data-user-id="%1$d" data-user-label="%2$s" style="color:#a02b30;">%3$s</a>',
				(int) $user->ID,
				esc_attr( $label ),
				esc_html__( 'Unblock', 'vms-elements-form-guard' )
			);
		} else {
			$actions['vms_elements_form_guard_block'] = sprintf(
				'<a href="#" class="vms-elements-form-guard-user-block-trigger" data-user-id="%1$d" data-user-label="%2$s" data-mode="block" style="color:#a02b30;">%3$s</a>',
				(int) $user->ID,
				esc_attr( $label ),
				esc_html__( 'Block', 'vms-elements-form-guard' )
			);
		}

		return $actions;
	}

	/**
	 * Register bulk-action options on users.php.
	 *
	 * @param array<string, string> $actions Bulk action map.
	 * @return array<string, string>
	 */
	public function register_bulk_actions( $actions ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $actions;
		}
		$actions['vms_elements_form_guard_block_users']   = __( 'Block (VMS Elements Form Guard)', 'vms-elements-form-guard' );
		$actions['vms_elements_form_guard_unblock_users'] = __( 'Unblock (VMS Elements Form Guard)', 'vms-elements-form-guard' );
		return $actions;
	}

	/**
	 * Handle the bulk Block / Unblock actions.
	 *
	 * @param string     $redirect_to Default redirect.
	 * @param string     $action      Selected bulk action.
	 * @param array<int> $user_ids    Selected user IDs.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect_to, $action, $user_ids ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $redirect_to;
		}
		if ( 'vms_elements_form_guard_block_users' !== $action && 'vms_elements_form_guard_unblock_users' !== $action ) {
			return $redirect_to;
		}
		if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
			return $redirect_to;
		}

		$processed = 0;
		$skipped   = 0;

		foreach ( $user_ids as $uid ) {
			$uid = (int) $uid;
			if ( $uid <= 0 ) {
				continue;
			}
			if ( $uid === (int) get_current_user_id() ) {
				++$skipped;
				continue;
			}

			if ( 'vms_elements_form_guard_block_users' === $action ) {
				$result = AI_Span_Comments::admin_manual_block(
					$uid,
					array(
						'scope'  => array( 'form' ),
						'reason' => __( 'Bulk block from Users list.', 'vms-elements-form-guard' ),
					)
				);
				if ( ! empty( $result['success'] ) ) {
					++$processed;
				} else {
					++$skipped;
				}
			} else {
				$ok = AI_Span_Comments::admin_unblock( 'u:' . $uid );
				if ( $ok ) {
					++$processed;
				} else {
					++$skipped;
				}
			}
		}

		$key = 'vms_elements_form_guard_block_users' === $action
			? 'vms_elements_form_guard_bulk_blocked'
			: 'vms_elements_form_guard_bulk_unblocked';

		$redirect_to = add_query_arg(
			array(
				$key                                  => $processed,
				'vms_elements_form_guard_bulk_skipped'        => $skipped,
			),
			$redirect_to
		);
		return $redirect_to;
	}

	/**
	 * Render the success/skip notice after a bulk action redirect.
	 */
	public function maybe_render_bulk_notice() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only notice from server redirect.
		$blocked   = isset( $_GET['vms_elements_form_guard_bulk_blocked'] ) ? absint( wp_unslash( $_GET['vms_elements_form_guard_bulk_blocked'] ) ) : 0;
		$unblocked = isset( $_GET['vms_elements_form_guard_bulk_unblocked'] ) ? absint( wp_unslash( $_GET['vms_elements_form_guard_bulk_unblocked'] ) ) : 0;
		$skipped   = isset( $_GET['vms_elements_form_guard_bulk_skipped'] ) ? absint( wp_unslash( $_GET['vms_elements_form_guard_bulk_skipped'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $blocked <= 0 && $unblocked <= 0 && $skipped <= 0 ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>';
		if ( $blocked > 0 ) {
			printf(
				/* translators: %d: number of users blocked */
				esc_html( _n( '%d user blocked.', '%d users blocked.', $blocked, 'vms-elements-form-guard' ) ),
				(int) $blocked
			);
			echo ' ';
		}
		if ( $unblocked > 0 ) {
			printf(
				/* translators: %d: number of users unblocked */
				esc_html( _n( '%d user unblocked.', '%d users unblocked.', $unblocked, 'vms-elements-form-guard' ) ),
				(int) $unblocked
			);
			echo ' ';
		}
		if ( $skipped > 0 ) {
			printf(
				/* translators: %d: number of users skipped */
				esc_html( _n( '%d user skipped (yourself or an exempt admin).', '%d users skipped (yourself or exempt admins).', $skipped, 'vms-elements-form-guard' ) ),
				(int) $skipped
			);
		}
		echo '</p></div>';
	}

	/**
	 * Enqueue assets only on the users.php list screen.
	 *
	 * @param string $hook_suffix Current admin screen hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'users.php' !== $hook_suffix ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_enqueue_style(
			'vms-elements-form-guard-sweetalert',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.min.css',
			array(),
			VMS_ELEMENTS_FORM_GUARD_VERSION
		);
		wp_enqueue_script(
			'vms-elements-form-guard-sweetalert',
			VMS_ELEMENTS_FORM_GUARD_ASSETS_URL . 'plugins/sweetalert2/sweetalert2.all.min.js',
			array( 'jquery' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);

		wp_register_script(
			'vms-elements-form-guard-users-actions',
			vms_elements_form_guard_js_asset( 'users-actions' ),
			array( 'jquery', 'vms-elements-form-guard-sweetalert' ),
			VMS_ELEMENTS_FORM_GUARD_VERSION,
			true
		);
		wp_enqueue_script( 'vms-elements-form-guard-users-actions' );

		$labels = array(
			'block_title'     => __( 'Block user', 'vms-elements-form-guard' ),
			'edit_title'      => __( 'Edit block scope', 'vms-elements-form-guard' ),
			'unblock_title'   => __( 'Unblock user', 'vms-elements-form-guard' ),
			'scope_form'      => __( 'Form / Comments', 'vms-elements-form-guard' ),
			'scope_login'     => __( 'Login', 'vms-elements-form-guard' ),
			'scope_site'      => __( 'Site-wide ban', 'vms-elements-form-guard' ),
			'reason'          => __( 'Reason (optional)', 'vms-elements-form-guard' ),
			'expiry'          => __( 'Auto-expire after (days, 0 = permanent)', 'vms-elements-form-guard' ),
			'confirm_block'   => __( 'Block this user', 'vms-elements-form-guard' ),
			'confirm_save'    => __( 'Save', 'vms-elements-form-guard' ),
			'confirm_unblock' => __( 'Yes, unblock', 'vms-elements-form-guard' ),
			'cancel'          => __( 'Cancel', 'vms-elements-form-guard' ),
			'pick_scope'      => __( 'Pick at least one block scope.', 'vms-elements-form-guard' ),
			'unblock_confirm' => __( 'This will clear all strikes and block flags for', 'vms-elements-form-guard' ),
			'request_failed'  => __( 'Request failed. Try again.', 'vms-elements-form-guard' ),
			'success'         => __( 'Done.', 'vms-elements-form-guard' ),
		);

		$boot = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'vms_elements_form_guard_nonce' ),
			'i18n'    => $labels,
		);

		wp_localize_script( 'vms-elements-form-guard-users-actions', 'VEFGUsers', $boot );
	}

	/**
	 * Fetch a single enforcement row, using a per-request cache.
	 *
	 * @return array{form:bool,login:bool,site:bool,strikes:int,last_reason:string}|null
	 */
	private function get_enforcement_row( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		if ( isset( $this->row_cache[ $user_id ] ) ) {
			return $this->row_cache[ $user_id ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT blocked, login_blocked, site_banned, strikes, last_reason FROM {$table} WHERE actor_key = %s",
				'u:' . $user_id
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			$this->row_cache[ $user_id ] = null;
			return null;
		}

		$this->row_cache[ $user_id ] = array(
			'form'        => ! empty( $row['blocked'] ),
			'login'       => ! empty( $row['login_blocked'] ),
			'site'        => ! empty( $row['site_banned'] ),
			'strikes'     => (int) ( $row['strikes'] ?? 0 ),
			'last_reason' => (string) ( $row['last_reason'] ?? '' ),
		);
		return $this->row_cache[ $user_id ];
	}
}
