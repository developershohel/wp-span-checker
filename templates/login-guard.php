<?php
/**
 * Login Guard settings screen - reCAPTCHA protection for login forms.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

use VMS_Elements_Form_Guard\AI_Span_Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cfg     = AI_Span_Config::get();
$updated = false;

// phpcs:disable WordPress.Security.NonceVerification.Missing
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['vefg_login_guard_save'] ) ) {
	if ( ! isset( $_POST['vefg_login_guard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vefg_login_guard_nonce'] ) ), 'vefg_login_guard_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'vms-elements-form-guard' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to save these settings.', 'vms-elements-form-guard' ) );
	}

	$incoming = array(
		'login_guard_enabled'       => ! empty( $_POST['login_guard_enabled'] ),
		'login_guard_recaptcha'     => ! empty( $_POST['login_guard_recaptcha'] ),
		'login_guard_scope'         => isset( $_POST['login_guard_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['login_guard_scope'] ) ) : 'default',
		'login_guard_page_ids'      => isset( $_POST['login_guard_page_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['login_guard_page_ids'] ) ) : '',
		'login_guard_form_selector' => isset( $_POST['login_guard_form_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['login_guard_form_selector'] ) ) : '',
	);

	AI_Span_Config::update( $incoming );
	$cfg     = AI_Span_Config::get();
	$updated = true;
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

$recaptcha_cfg     = get_option( 'vefg-recaptcha-config', array() );
$has_recaptcha_key = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );
?>

<div class="wrap vefg-admin">
	<?php
	vms_elements_form_guard_admin_page_header(
		__( 'Login Guard', 'vms-elements-form-guard' ),
		__( 'Add reCAPTCHA protection to WordPress login forms to prevent brute force attacks.', 'vms-elements-form-guard' )
	);
	?>

	<?php if ( $updated ) : ?>
		<div class="updated"><p><?php esc_html_e( 'Login Guard settings saved.', 'vms-elements-form-guard' ); ?></p></div>
	<?php endif; ?>

	<div class="vefg-card" style="max-width: 800px;">
		<form method="post">
			<?php wp_nonce_field( 'vefg_login_guard_action', 'vefg_login_guard_nonce' ); ?>

			<h3><?php esc_html_e( 'General Settings', 'vms-elements-form-guard' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Login Guard', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'login_guard_enabled',
								'checked'     => ! empty( $cfg['login_guard_enabled'] ),
								'label'       => __( 'Enable Login Guard protection', 'vms-elements-form-guard' ),
								'description' => __( 'Adds security features to the WordPress login page.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable reCAPTCHA', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'login_guard_recaptcha',
								'checked'     => ! empty( $cfg['login_guard_recaptcha'] ),
								'label'       => __( 'Add Google reCAPTCHA to login form', 'vms-elements-form-guard' ),
								'description' => __( 'Requires reCAPTCHA to be completed before login. Helps prevent automated login attempts.', 'vms-elements-form-guard' ),
							)
						);
						?>
						<?php if ( ! $has_recaptcha_key ) : ?>
							<p class="description" style="color: #d63638;"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'reCAPTCHA keys not configured.', 'vms-elements-form-guard' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=vms-elements-form-guard-api' ) ); ?>"><?php esc_html_e( 'Configure API Settings', 'vms-elements-form-guard' ); ?></a></p>
						<?php else : ?>
							<p class="description" style="color: #2e7d32;"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'reCAPTCHA configured', 'vms-elements-form-guard' ); ?> (<?php echo esc_html( ucfirst( $recaptcha_cfg['version'] ?? 'v2' ) ); ?>)</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="login_guard_scope"><?php esc_html_e( 'Login Page', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<select name="login_guard_scope" id="login_guard_scope">
							<option value="default" <?php selected( (string) ( $cfg['login_guard_scope'] ?? 'default' ), 'default' ); ?>><?php esc_html_e( 'Default WordPress login (wp-login.php)', 'vms-elements-form-guard' ); ?></option>
							<option value="specific" <?php selected( (string) ( $cfg['login_guard_scope'] ?? 'default' ), 'specific' ); ?>><?php esc_html_e( 'Custom login page(s)', 'vms-elements-form-guard' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Choose where to apply Login Guard protection.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr id="login_guard_pages_row" style="display:none;">
					<th scope="row"><label><?php esc_html_e( 'Select Login Pages', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<div class="vefg-page-selector">
							<div class="vefg-page-search-wrap">
								<input type="text" id="login_guard_page_search" class="regular-text" placeholder="<?php esc_attr_e( 'Search pages...', 'vms-elements-form-guard' ); ?>" autocomplete="off">
								<div id="login_guard_page_results" class="vefg-page-results" style="display:none;"></div>
							</div>
							<div id="login_guard_selected_pages" class="vefg-selected-pages">
								<?php
								$selected_ids = array_filter( array_map( 'absint', explode( ',', $cfg['login_guard_page_ids'] ?? '' ) ) );
								foreach ( $selected_ids as $pid ) :
									$page_post = get_post( $pid );
									if ( $page_post && 'page' === $page_post->post_type ) :
										?>
										<span class="vefg-page-badge" data-id="<?php echo esc_attr( $pid ); ?>">
											<?php echo esc_html( $page_post->post_title ); ?>
											<button type="button" class="vefg-badge-remove" aria-label="<?php esc_attr_e( 'Remove', 'vms-elements-form-guard' ); ?>">&times;</button>
										</span>
										<?php
									endif;
								endforeach;
								?>
							</div>
							<input type="hidden" name="login_guard_page_ids" id="login_guard_page_ids" value="<?php echo esc_attr( (string) ( $cfg['login_guard_page_ids'] ?? '' ) ); ?>">
						</div>
						<p class="description"><?php esc_html_e( 'Select pages that contain custom login forms (e.g., WooCommerce My Account, membership plugin login pages).', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr id="login_guard_selector_row" style="display:none;">
					<th scope="row"><label for="login_guard_form_selector"><?php esc_html_e( 'Form Selector', 'vms-elements-form-guard' ); ?> <span style="color:#d63638;">*</span></label></th>
					<td>
						<input type="text" name="login_guard_form_selector" id="login_guard_form_selector" class="regular-text" value="<?php echo esc_attr( (string) ( $cfg['login_guard_form_selector'] ?? '' ) ); ?>" placeholder=".woocommerce-form-login, #login-form">
						<p class="description">
							<?php esc_html_e( 'CSS selector to identify the login form. Examples: .woocommerce-form-login, #my-login-form', 'vms-elements-form-guard' ); ?>
							<br>
							<strong><?php esc_html_e( 'Required for custom login pages to ensure reCAPTCHA is applied only to the correct form.', 'vms-elements-form-guard' ); ?></strong>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="vefg_login_guard_save" value="1" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'vms-elements-form-guard' ); ?>
				</button>
			</p>
		</form>
	</div>

	<div class="vefg-card" style="max-width: 800px; margin-top: 20px;">
		<h3><?php esc_html_e( 'How It Works', 'vms-elements-form-guard' ); ?></h3>
		<ol>
			<li><?php esc_html_e( 'Login Guard adds reCAPTCHA verification to the WordPress login page.', 'vms-elements-form-guard' ); ?></li>
			<li><?php esc_html_e( 'Users must complete the reCAPTCHA before the login button becomes active (v2) or tokens are verified server-side (v3).', 'vms-elements-form-guard' ); ?></li>
			<li><?php esc_html_e( 'This helps prevent brute force attacks and automated login attempts.', 'vms-elements-form-guard' ); ?></li>
		</ol>
		<h4><?php esc_html_e( 'Recommended Setup', 'vms-elements-form-guard' ); ?></h4>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong><?php esc_html_e( 'reCAPTCHA v2', 'vms-elements-form-guard' ); ?>:</strong> <?php esc_html_e( 'Shows a checkbox challenge. Login button is disabled until reCAPTCHA is completed.', 'vms-elements-form-guard' ); ?></li>
			<li><strong><?php esc_html_e( 'reCAPTCHA v3', 'vms-elements-form-guard' ); ?>:</strong> <?php esc_html_e( 'Invisible scoring system. No user interaction required, but suspicious logins may be blocked.', 'vms-elements-form-guard' ); ?></li>
		</ul>
	</div>
</div>
