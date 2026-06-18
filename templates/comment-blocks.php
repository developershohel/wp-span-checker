<?php
/**
 * Blocked Users -- list of enforced rows, plus a "Block User Manually" tab
 * that lets administrators add a user (by ID, username, or email) directly
 * to the block list with a chosen scope.
 *
 * Reads from the plugin-owned
 * `{$wpdb->prefix}vms_elements_form_guard_comment_enforcement` custom table; the
 * identifier is hardcoded.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

use VMS_Elements_Form_Guard\AI_Span_Comments;
use VMS_Elements_Form_Guard\AI_Span_Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table  = $wpdb->prefix . 'vms_elements_form_guard_comment_enforcement';
$cfg    = AI_Span_Config::get();
$notice = array(
	'type' => '',
	'text' => '',
);

// phpcs:disable WordPress.Security.NonceVerification.Missing
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'list';
if ( ! in_array( $active_tab, array( 'list', 'manual' ), true ) ) {
	$active_tab = 'list';
}

// ---- Action: Unblock & Reset ---------------------------------------------
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['vefg_unblock_actor'] ) ) {
	if ( ! isset( $_POST['vefg_blocks_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vefg_blocks_nonce'] ) ), 'vefg_blocks_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'vms-elements-form-guard' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'vms-elements-form-guard' ) );
	}
	$key = isset( $_POST['actor_key'] ) ? sanitize_text_field( wp_unslash( $_POST['actor_key'] ) ) : '';
	if ( $key !== '' && AI_Span_Comments::admin_unblock( $key ) ) {
		$notice = array(
			'type' => 'success',
			'text' => __( 'User was unblocked and strikes were reset.', 'vms-elements-form-guard' ),
		);
	} elseif ( $key !== '' ) {
		$notice = array(
			'type' => 'error',
			'text' => __( 'Could not update that record.', 'vms-elements-form-guard' ),
		);
	}
}

// ---- Action: Manual block (form POST fallback when JS is disabled) -------
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && ! empty( $_POST['vefg_manual_block_submit'] ) ) {
	if ( ! isset( $_POST['vefg_manual_block_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vefg_manual_block_nonce'] ) ), 'vefg_manual_block_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'vms-elements-form-guard' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'vms-elements-form-guard' ) );
	}

	$input = isset( $_POST['vefg_manual_block_input'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['vefg_manual_block_input'] ) ) : '';
	$found = $input !== '' ? AI_Span_Comments::find_user_by_input( $input ) : null;
	if ( ! $found ) {
		$notice = array(
			'type' => 'error',
			'text' => __( 'Could not find a user matching that ID, username, or email.', 'vms-elements-form-guard' ),
		);
	} else {
		$scope_raw = isset( $_POST['vefg_manual_block_scope'] )
			? map_deep( (array) wp_unslash( $_POST['vefg_manual_block_scope'] ), 'sanitize_key' )
			: array();
		$scope = array();
		foreach ( $scope_raw as $scope_item ) {
			$scope_item = (string) $scope_item;
			if ( in_array( $scope_item, array( 'form', 'login', 'site' ), true ) ) {
				$scope[] = $scope_item;
			}
		}
		$reason       = isset( $_POST['vefg_manual_block_reason'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['vefg_manual_block_reason'] ) ) : '';
		$expiry_days  = isset( $_POST['vefg_manual_block_expiry'] ) ? absint( wp_unslash( $_POST['vefg_manual_block_expiry'] ) ) : 0;

		$result = AI_Span_Comments::admin_manual_block(
			(int) $found->ID,
			array(
				'scope'       => $scope,
				'reason'      => $reason,
				'expiry_days' => $expiry_days,
			)
		);
		$notice     = array(
			'type' => $result['success'] ? 'success' : 'error',
			'text' => $result['message'],
		);
		$active_tab = $result['success'] ? 'list' : 'manual';
	}
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name uses trusted prefix.
$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY login_blocked DESC, site_banned DESC, blocked DESC, strikes DESC, last_strike_at DESC LIMIT 500", ARRAY_A );
if ( ! is_array( $rows ) ) {
	$rows = array();
}

$base_url     = admin_url( 'admin.php?page=vms-elements-form-guard-comment-blocks' );
$list_url     = add_query_arg( 'tab', 'list', $base_url );
$manual_url   = add_query_arg( 'tab', 'manual', $base_url );
$max_strikes  = (int) ( $cfg['block_user_max_strikes'] ?? 5 );
?>

<div class="wrap vefg-admin">
	<?php
	vms_elements_form_guard_admin_page_header(
		__( 'Blocked Users', 'vms-elements-form-guard' ),
		__( 'Browse users blocked through strikes or block someone manually by ID, username, or email.', 'vms-elements-form-guard' )
	);
	?>

	<?php if ( ! empty( $notice['text'] ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( 'success' === $notice['type'] ? 'success' : 'error' ); ?> is-dismissible" style="margin: 12px 0;">
			<p><?php echo esc_html( $notice['text'] ); ?></p>
		</div>
	<?php endif; ?>

	<h2 class="nav-tab-wrapper">
		<a href="<?php echo esc_url( $list_url ); ?>" class="nav-tab <?php echo 'list' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-list-view" style="vertical-align: text-bottom;"></span>
			<?php esc_html_e( 'Blocked Users List', 'vms-elements-form-guard' ); ?>
			<?php if ( ! empty( $rows ) ) : ?>
				<span class="vefg-tab-count" style="background:#d63638;color:#fff;border-radius:10px;padding:0 8px;margin-left:6px;font-size:11px;line-height:18px;display:inline-block;">
					<?php echo esc_html( (string) count( $rows ) ); ?>
				</span>
			<?php endif; ?>
		</a>
		<a href="<?php echo esc_url( $manual_url ); ?>" class="nav-tab <?php echo 'manual' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<span class="dashicons dashicons-lock" style="vertical-align: text-bottom;"></span>
			<?php esc_html_e( 'Block User Manually', 'vms-elements-form-guard' ); ?>
		</a>
	</h2>

	<?php if ( 'manual' === $active_tab ) : ?>

		<div class="vefg-card" style="max-width: 760px; margin-top: 16px;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Add a user to the block list', 'vms-elements-form-guard' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Enter a user ID, login (username), or email address. The form auto-detects what you typed and shows a live preview before you block.', 'vms-elements-form-guard' ); ?>
			</p>

			<form method="post" id="vefg-manual-block-form">
				<?php wp_nonce_field( 'vefg_manual_block_action', 'vefg_manual_block_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="vefg-manual-block-input"><?php esc_html_e( 'User identifier', 'vms-elements-form-guard' ); ?> <span style="color:#d63638;">*</span></label>
						</th>
						<td>
							<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
								<input
									type="text"
									id="vefg-manual-block-input"
									name="vefg_manual_block_input"
									class="regular-text"
									placeholder="<?php esc_attr_e( 'e.g. 42, johndoe, john@example.com', 'vms-elements-form-guard' ); ?>"
									autocomplete="off"
								/>
								<button type="button" class="button" id="vefg-manual-block-lookup">
									<span class="dashicons dashicons-search" style="vertical-align: text-bottom;"></span>
									<?php esc_html_e( 'Look up', 'vms-elements-form-guard' ); ?>
								</button>
								<span id="vefg-manual-block-lookup-status" class="description" aria-live="polite" style="color:#666;"></span>
							</div>

							<div id="vefg-manual-block-preview" class="vefg-card" style="display:none;margin-top:12px;padding:12px;background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;">
								<div style="display:flex;gap:12px;align-items:center;">
									<img id="vefg-manual-block-avatar" src="" alt="" width="48" height="48" style="border-radius:50%;background:#e5e5e5;" />
									<div style="flex:1;">
										<strong id="vefg-manual-block-name" style="display:block;font-size:14px;"></strong>
										<small class="description" id="vefg-manual-block-meta" style="color:#555;"></small>
										<div id="vefg-manual-block-status" style="margin-top:4px;font-size:12px;"></div>
									</div>
									<a href="#" id="vefg-manual-block-edit-link" target="_blank" class="button button-small" style="display:none;">
										<?php esc_html_e( 'Edit user', 'vms-elements-form-guard' ); ?>
									</a>
								</div>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Block scope', 'vms-elements-form-guard' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Block scope', 'vms-elements-form-guard' ); ?></legend>

								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" name="vefg_manual_block_scope[]" value="form" checked>
									<strong><?php esc_html_e( 'Form / Comments', 'vms-elements-form-guard' ); ?></strong>
									<span class="description"> - <?php esc_html_e( 'Blocks submissions through comments, product reviews, and guarded forms.', 'vms-elements-form-guard' ); ?></span>
								</label>

								<label style="display:block;margin-bottom:8px;">
									<input type="checkbox" name="vefg_manual_block_scope[]" value="login">
									<strong><?php esc_html_e( 'Login', 'vms-elements-form-guard' ); ?></strong>
									<span class="description"> - <?php esc_html_e( 'Blocks the user from signing into wp-login.php.', 'vms-elements-form-guard' ); ?></span>
								</label>

								<label style="display:block;">
									<input type="checkbox" name="vefg_manual_block_scope[]" value="site">
									<strong><?php esc_html_e( 'Site-wide ban', 'vms-elements-form-guard' ); ?></strong>
									<span class="description"> - <?php esc_html_e( 'Forces logout and blocks every front-end page except the contact page.', 'vms-elements-form-guard' ); ?></span>
								</label>
							</fieldset>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="vefg-manual-block-reason"><?php esc_html_e( 'Reason', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<textarea
								name="vefg_manual_block_reason"
								id="vefg-manual-block-reason"
								class="large-text"
								rows="2"
								placeholder="<?php esc_attr_e( 'Optional. Shown in the Blocked Users list and the activity log.', 'vms-elements-form-guard' ); ?>"
							></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="vefg-manual-block-expiry"><?php esc_html_e( 'Auto-expire after', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<input
								type="number"
								id="vefg-manual-block-expiry"
								name="vefg_manual_block_expiry"
								class="small-text"
								min="0"
								step="1"
								value="0"
							/>
							<span class="description"><?php esc_html_e( 'days. Use 0 to keep the block until you remove it.', 'vms-elements-form-guard' ); ?></span>
						</td>
					</tr>
				</table>

				<p class="submit" style="display:flex;gap:8px;align-items:center;">
					<button type="submit" name="vefg_manual_block_submit" value="1" class="button button-primary" id="vefg-manual-block-submit" disabled>
						<span class="dashicons dashicons-lock" style="vertical-align: text-bottom;"></span>
						<?php esc_html_e( 'Block this user', 'vms-elements-form-guard' ); ?>
					</button>
					<a href="<?php echo esc_url( $list_url ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Cancel', 'vms-elements-form-guard' ); ?>
					</a>
				</p>
				<p class="description" id="vefg-manual-block-helper">
					<?php esc_html_e( 'Look up a user first; the block button activates once a valid user is matched.', 'vms-elements-form-guard' ); ?>
				</p>
			</form>
		</div>

	<?php else : // tab=list ?>

		<!-- Settings Summary -->
		<div class="vefg-card" style="margin: 16px 0; padding: 15px;">
			<h3 style="margin-top: 0;"><?php esc_html_e( 'Block User Settings', 'vms-elements-form-guard' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th><?php esc_html_e( 'Status', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php if ( ! empty( $cfg['block_user_enabled'] ) ) : ?>
							<span class="dashicons dashicons-yes-alt" style="color: #2e7d32;"></span>
							<?php esc_html_e( 'Enabled', 'vms-elements-form-guard' ); ?>
						<?php else : ?>
							<span class="dashicons dashicons-dismiss" style="color: #c62828;"></span>
							<?php esc_html_e( 'Disabled', 'vms-elements-form-guard' ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Max Strikes', 'vms-elements-form-guard' ); ?></th>
					<td><?php echo esc_html( (string) $max_strikes ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Block Login', 'vms-elements-form-guard' ); ?></th>
					<td><?php echo ! empty( $cfg['block_user_login_block'] ) ? esc_html__( 'Yes', 'vms-elements-form-guard' ) : esc_html__( 'No', 'vms-elements-form-guard' ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Strike Expiry', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						$expiry = (int) ( $cfg['block_user_strike_expiry_days'] ?? 30 );
						if ( $expiry > 0 ) {
							printf(
								/* translators: %d: number of days */
								esc_html__( '%d days', 'vms-elements-form-guard' ),
								(int) $expiry
							);
						} else {
							esc_html_e( 'Never', 'vms-elements-form-guard' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Admin Exempt', 'vms-elements-form-guard' ); ?></th>
					<td><?php echo ! empty( $cfg['block_user_exempt_admins'] ) ? esc_html__( 'Yes', 'vms-elements-form-guard' ) : esc_html__( 'No', 'vms-elements-form-guard' ); ?></td>
				</tr>
			</table>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vefg-block-user-settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Edit Settings', 'vms-elements-form-guard' ); ?>
				</a>
				<a href="<?php echo esc_url( $manual_url ); ?>" class="button button-primary" style="margin-left:6px;">
					<span class="dashicons dashicons-plus-alt2" style="vertical-align: text-bottom;"></span>
					<?php esc_html_e( 'Block a user manually', 'vms-elements-form-guard' ); ?>
				</a>
			</p>
		</div>

		<div class="vefg-card vefg-admin__table-wrap">
			<h3><?php esc_html_e( 'Blocked Users List', 'vms-elements-form-guard' ); ?></h3>
			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'No blocked users yet.', 'vms-elements-form-guard' ); ?></p>
				<p>
					<a href="<?php echo esc_url( $manual_url ); ?>" class="button button-primary">
						<?php esc_html_e( 'Add the first manual block', 'vms-elements-form-guard' ); ?>
					</a>
				</p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'User / Guest', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Strikes', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Scope', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Source', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Last Strike', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Reason', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'vms-elements-form-guard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$user_label = (string) ( $row['actor_label'] ?? '' );
							$user_id    = ! empty( $row['user_id'] ) ? (int) $row['user_id'] : 0;
							$last_ip    = (string) ( $row['last_ip'] ?? '' );
							$actor_key  = (string) ( $row['actor_key'] ?? '' );
							$is_guest   = ( strpos( $actor_key, 'ip_' ) === 0 || strpos( $actor_key, 'guest_' ) === 0 || strpos( $actor_key, 'g:' ) === 0 );

							if ( $user_id > 0 ) {
								$user_obj = get_userdata( $user_id );
								if ( $user_obj ) {
									$user_label = $user_obj->display_name . ' (' . $user_obj->user_login . ')';
								}
							}

							$source        = (string) ( $row['strike_source'] ?? 'comment' );
							$source_labels = array(
								'comment'         => __( 'Comment', 'vms-elements-form-guard' ),
								'product_review'  => __( 'Product Review', 'vms-elements-form-guard' ),
								'form_guard'      => __( 'Form Guard', 'vms-elements-form-guard' ),
								'contact_guard'   => __( 'Contact Guard', 'vms-elements-form-guard' ),
								'subscribe_guard' => __( 'Subscribe Guard', 'vms-elements-form-guard' ),
								'subscribe'       => __( 'Subscribe', 'vms-elements-form-guard' ),
								'login'           => __( 'Login', 'vms-elements-form-guard' ),
								'login_guard'     => __( 'Login Guard', 'vms-elements-form-guard' ),
								'registration'    => __( 'Registration', 'vms-elements-form-guard' ),
								'auth_forms'      => __( 'Auth Forms', 'vms-elements-form-guard' ),
								'manual'          => __( 'Manual', 'vms-elements-form-guard' ),
							);
							$source_label = isset( $source_labels[ $source ] ) ? $source_labels[ $source ] : ucfirst( str_replace( '_', ' ', $source ) );

							$expires = '';
							if ( ! empty( $row['strikes_expire_at'] ) ) {
								$exp_time = strtotime( $row['strikes_expire_at'] );
								if ( $exp_time < time() ) {
									$expires = __( 'Expired', 'vms-elements-form-guard' );
								} else {
									$expires = human_time_diff( time(), $exp_time );
								}
							} else {
								$expires = __( 'Never', 'vms-elements-form-guard' );
							}

							$scope_form  = ! empty( $row['blocked'] );
							$scope_login = ! empty( $row['login_blocked'] );
							$scope_site  = ! empty( $row['site_banned'] );
							$has_block   = ( $scope_form || $scope_login || $scope_site || (int) ( $row['strikes'] ?? 0 ) > 0 );
							?>
							<tr>
								<td>
									<?php if ( $user_id > 0 ) : ?>
										<strong><?php echo esc_html( $user_label ); ?></strong>
										<br><small class="description">
											<?php
											echo esc_html(
												sprintf(
													/* translators: %d: user ID */
													__( 'User ID: %d', 'vms-elements-form-guard' ),
													(int) $user_id
												)
											);
											?>
										</small>
									<?php elseif ( $is_guest ) : ?>
										<?php if ( ! empty( $user_label ) ) : ?>
											<span><?php echo esc_html( $user_label ); ?></span>
										<?php else : ?>
											<span><?php esc_html_e( 'Guest', 'vms-elements-form-guard' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $last_ip ) ) : ?>
											<br><small class="description" style="color: #666;">
												<?php esc_html_e( 'IP:', 'vms-elements-form-guard' ); ?> <code style="font-size: 11px;"><?php echo esc_html( $last_ip ); ?></code>
											</small>
										<?php endif; ?>
									<?php else : ?>
										<?php echo esc_html( $user_label ); ?>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( (string) (int) ( $row['strikes'] ?? 0 ) ); ?></strong>
									/ <?php echo esc_html( (string) $max_strikes ); ?>
								</td>
								<td>
									<?php if ( $scope_form ) : ?>
										<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fcf0f1;color:#a02b30;font-size:11px;margin-right:4px;">
											<span class="dashicons dashicons-format-chat" style="font-size:13px;vertical-align:middle;"></span>
											<?php esc_html_e( 'Form', 'vms-elements-form-guard' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $scope_login ) : ?>
										<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fff8e5;color:#996800;font-size:11px;margin-right:4px;">
											<span class="dashicons dashicons-lock" style="font-size:13px;vertical-align:middle;"></span>
											<?php esc_html_e( 'Login', 'vms-elements-form-guard' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( $scope_site ) : ?>
										<span class="vefg-scope-pill" style="display:inline-block;padding:1px 8px;border-radius:10px;background:#fcebec;color:#7e1c20;font-size:11px;">
											<span class="dashicons dashicons-shield" style="font-size:13px;vertical-align:middle;"></span>
											<?php esc_html_e( 'Site', 'vms-elements-form-guard' ); ?>
										</span>
									<?php endif; ?>
									<?php if ( ! $scope_form && ! $scope_login && ! $scope_site ) : ?>
										<span class="description">-</span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $source_label ); ?></td>
								<td><?php echo esc_html( $expires ); ?></td>
								<td><?php echo esc_html( (string) ( $row['last_strike_at'] ?? '' ) ); ?></td>
								<td>
									<span title="<?php echo esc_attr( (string) ( $row['last_reason'] ?? '' ) ); ?>">
										<?php echo esc_html( wp_trim_words( (string) ( $row['last_reason'] ?? '' ), 5, '...' ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( $has_block ) : ?>
										<div style="display:flex;gap:4px;flex-wrap:wrap;">
											<button
												type="button"
												class="button button-small vefg-edit-scope-btn"
												data-actor-key="<?php echo esc_attr( $actor_key ); ?>"
												data-form="<?php echo esc_attr( $scope_form ? '1' : '0' ); ?>"
												data-login="<?php echo esc_attr( $scope_login ? '1' : '0' ); ?>"
												data-site="<?php echo esc_attr( $scope_site ? '1' : '0' ); ?>"
												data-label="<?php echo esc_attr( $user_label ); ?>"
											>
												<span class="dashicons dashicons-edit" style="font-size:14px;vertical-align:middle;"></span>
												<?php esc_html_e( 'Edit', 'vms-elements-form-guard' ); ?>
											</button>
											<form method="post" style="display:inline;">
												<?php wp_nonce_field( 'vefg_blocks_action', 'vefg_blocks_nonce' ); ?>
												<input type="hidden" name="actor_key" value="<?php echo esc_attr( $actor_key ); ?>">
												<button type="submit" name="vefg_unblock_actor" value="1" class="button button-small">
													<span class="dashicons dashicons-unlock" style="font-size:14px;vertical-align:middle;"></span>
													<?php esc_html_e( 'Unblock', 'vms-elements-form-guard' ); ?>
												</button>
											</form>
										</div>
									<?php else : ?>
										-
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>
