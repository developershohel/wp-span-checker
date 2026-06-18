<?php
/**
 * Registration guard — block fake signups using the same domain pipeline as forms.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

use VMS_Elements_Form_Guard\Registration_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cfg = Registration_Guard::get();

// phpcs:disable WordPress.Security.NonceVerification.Missing
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! isset( $_POST['vefg_registration_guard_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vefg_registration_guard_nonce'] ) ), 'vefg_registration_guard_save' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'vms-elements-form-guard' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to save these settings.', 'vms-elements-form-guard' ) );
	}

	Registration_Guard::update(
		array(
			'enabled'                    => ! empty( $_POST['rg_enabled'] ),
			'use_webrisk'                => ! empty( $_POST['rg_webrisk'] ),
			'use_virustotal'             => ! empty( $_POST['rg_virustotal'] ),
			'require_dns_live'           => ! empty( $_POST['rg_require_dns_live'] ),
			'require_mx'                 => ! empty( $_POST['rg_require_mx'] ),
			'mx_allow_a_fallback'        => ! empty( $_POST['rg_mx_a_fallback'] ),
			'skip_https_check'           => ! empty( $_POST['rg_skip_https'] ),
			'rate_limit_enabled'         => ! empty( $_POST['rg_rate_limit_enabled'] ),
			'rate_limit_max_burst'       => isset( $_POST['rg_rate_burst'] ) ? (int) $_POST['rg_rate_burst'] : 5,
			'rate_limit_lockout_seconds' => isset( $_POST['rg_rate_lockout_sec'] ) ? (int) $_POST['rg_rate_lockout_sec'] : 18000,
			'rate_limit_max_per_day'     => isset( $_POST['rg_rate_per_day'] ) ? (int) $_POST['rg_rate_per_day'] : 10,
		)
	);

	// Save frontend settings to AI config
	$ai_cfg_data = array(
		'registration_guard_frontend'      => ! empty( $_POST['rg_frontend'] ),
		'registration_guard_recaptcha'     => ! empty( $_POST['rg_recaptcha'] ),
		'registration_guard_scope'         => isset( $_POST['rg_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['rg_scope'] ) ) : 'default',
		'registration_guard_page_ids'      => isset( $_POST['rg_page_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['rg_page_ids'] ) ) : '',
		'registration_guard_form_selector' => isset( $_POST['rg_form_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['rg_form_selector'] ) ) : '',
	);
	\VMS_Elements_Form_Guard\AI_Span_Config::update( $ai_cfg_data );
	$cfg = Registration_Guard::get();
	echo '<div class="updated"><p>' . esc_html__( 'Registration guard saved.', 'vms-elements-form-guard' ) . '</p></div>';
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

$can_register = get_option( 'users_can_register' );
?>

<div class="wrap vefg-admin">
	<?php
	vms_elements_form_guard_admin_page_header(
		__( 'Registration guard', 'vms-elements-form-guard' ),
		__( 'Runs before WordPress creates the account: DNS “live” check, MX (if enabled), disposable list, then Google Web Risk and optionally VirusTotal. Failed attempts are counted per IP with lockout and a daily cap; reference IDs help match server logs.', 'vms-elements-form-guard' )
	);
	?>

	<?php if ( ! $can_register ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'WordPress “Anyone can register” is currently disabled under Settings → General. This guard only runs when registration is allowed (or when WooCommerce creates accounts).', 'vms-elements-form-guard' ); ?></p></div>
	<?php endif; ?>

	<div class="vefg-card">
		<form method="post">
			<?php wp_nonce_field( 'vefg_registration_guard_save', 'vefg_registration_guard_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable registration guard', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_enabled',
								'checked'     => ! empty( $cfg['enabled'] ),
								'description' => __( 'Run validation when a new user registers.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Google Web Risk', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_webrisk',
								'checked'     => ! empty( $cfg['use_webrisk'] ),
								'description' => __( 'Google threat-list check (malware/phishing/unwanted software). If clean, it means Google has not flagged this domain URL.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'VirusTotal', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_virustotal',
								'checked'     => ! empty( $cfg['use_virustotal'] ),
								'description' => __( 'Multi-engine reputation check. A domain can pass Web Risk but still fail here if your malicious/suspicious thresholds are exceeded.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require DNS “live” domain', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_require_dns_live',
								'checked'     => ! empty( $cfg['require_dns_live'] ),
								'description' => __( 'Hostname must have at least one of MX, A, AAAA, NS, or SOA in public DNS (catches dead or typo domains before MX-specific rules).', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require MX / mail DNS', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_require_mx',
								'checked'     => ! empty( $cfg['require_mx'] ),
								'description' => __( 'Domain must have MX records (or see fallback below).', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allow A-record fallback', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_mx_a_fallback',
								'checked'     => ! empty( $cfg['mx_allow_a_fallback'] ),
								'description' => __( 'If no MX exists, accept domains that have an A record (some small hosts).', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Skip HTTPS check', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'rg_skip_https',
								'checked'     => ! empty( $cfg['skip_https_check'] ),
								'description' => __( 'Recommended for registration: many valid mail domains do not serve a public HTTPS site on the bare hostname.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Rate limit failed signups', 'vms-elements-form-guard' ); ?></th>
				<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name'        => 'rg_rate_limit_enabled',
							'checked'     => ! empty( $cfg['rate_limit_enabled'] ),
							'description' => __( 'Count failed registration attempts by IP. Lockout and daily cap apply to the validation step (after DNS/MX/disposable, including API failures).', 'vms-elements-form-guard' ),
						)
					);
					?>
					<p class="description" style="margin-top:10px;">
						<label for="rg_rate_burst"><?php esc_html_e( 'Max failures before lockout', 'vms-elements-form-guard' ); ?></label><br>
						<input name="rg_rate_burst" id="rg_rate_burst" type="number" min="1" max="100" class="small-text" value="<?php echo esc_attr( (string) (int) $cfg['rate_limit_max_burst'] ); ?>">
					</p>
					<p class="description">
						<label for="rg_rate_lockout_sec"><?php esc_html_e( 'Lockout duration (seconds)', 'vms-elements-form-guard' ); ?></label><br>
						<input name="rg_rate_lockout_sec" id="rg_rate_lockout_sec" type="number" min="60" class="small-text" value="<?php echo esc_attr( (string) (int) $cfg['rate_limit_lockout_seconds'] ); ?>">
						<?php esc_html_e( '(default 18000 ≈ 5 hours)', 'vms-elements-form-guard' ); ?>
					</p>
					<p class="description">
						<label for="rg_rate_per_day"><?php esc_html_e( 'Max failures per calendar day (site timezone)', 'vms-elements-form-guard' ); ?></label><br>
						<input name="rg_rate_per_day" id="rg_rate_per_day" type="number" min="1" max="1000" class="small-text" value="<?php echo esc_attr( (string) (int) $cfg['rate_limit_max_per_day'] ); ?>">
					</p>
				</td>
			</tr>
		</table>

		<?php
		$ai_cfg            = \VMS_Elements_Form_Guard\AI_Span_Config::get();
		$recaptcha_cfg     = get_option( 'vefg-recaptcha-config', array() );
		$has_recaptcha_key = ! empty( $recaptcha_cfg['site_key'] ) && ! empty( $recaptcha_cfg['secret_key'] );
		?>
		<h3><?php esc_html_e( 'Frontend Validation', 'vms-elements-form-guard' ); ?></h3>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable frontend validation', 'vms-elements-form-guard' ); ?></th>
				<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name'        => 'rg_frontend',
							'checked'     => ! empty( $ai_cfg['registration_guard_frontend'] ),
							'description' => __( 'Adds a validation button to registration forms. Email is validated via AJAX before form submission. Stores a validation token (IP-based) to verify backend.', 'vms-elements-form-guard' ),
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
							'name'        => 'rg_recaptcha',
							'checked'     => ! empty( $ai_cfg['registration_guard_recaptcha'] ),
							'description' => __( 'Add Google reCAPTCHA to registration forms for bot protection.', 'vms-elements-form-guard' ),
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
				<th scope="row"><label for="rg_scope"><?php esc_html_e( 'Registration Page', 'vms-elements-form-guard' ); ?></label></th>
				<td>
					<select name="rg_scope" id="rg_scope">
						<option value="default" <?php selected( (string) ( $ai_cfg['registration_guard_scope'] ?? 'default' ), 'default' ); ?>><?php esc_html_e( 'Default WordPress registration (wp-login.php?action=register)', 'vms-elements-form-guard' ); ?></option>
						<option value="specific" <?php selected( (string) ( $ai_cfg['registration_guard_scope'] ?? 'default' ), 'specific' ); ?>><?php esc_html_e( 'Custom registration page(s)', 'vms-elements-form-guard' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Choose where to apply frontend validation and reCAPTCHA.', 'vms-elements-form-guard' ); ?></p>
				</td>
			</tr>
			<tr id="rg_pages_row" style="display:none;">
				<th scope="row"><label><?php esc_html_e( 'Select Registration Pages', 'vms-elements-form-guard' ); ?></label></th>
				<td>
					<div class="vefg-page-selector">
						<div class="vefg-page-search-wrap">
							<input type="text" id="rg_page_search" class="regular-text" placeholder="<?php esc_attr_e( 'Search pages...', 'vms-elements-form-guard' ); ?>" autocomplete="off">
							<div id="rg_page_results" class="vefg-page-results" style="display:none;"></div>
						</div>
						<div id="rg_selected_pages" class="vefg-selected-pages">
							<?php
							$selected_ids = array_filter( array_map( 'absint', explode( ',', $ai_cfg['registration_guard_page_ids'] ?? '' ) ) );
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
						<input type="hidden" name="rg_page_ids" id="rg_page_ids" value="<?php echo esc_attr( (string) ( $ai_cfg['registration_guard_page_ids'] ?? '' ) ); ?>">
					</div>
					<p class="description"><?php esc_html_e( 'Select pages that contain custom registration forms (e.g., WooCommerce registration, membership plugin signup pages).', 'vms-elements-form-guard' ); ?></p>
				</td>
			</tr>
			<tr id="rg_selector_row" style="display:none;">
				<th scope="row"><label for="rg_form_selector"><?php esc_html_e( 'Form Selector', 'vms-elements-form-guard' ); ?> <span style="color:#d63638;">*</span></label></th>
				<td>
					<input type="text" name="rg_form_selector" id="rg_form_selector" class="regular-text" value="<?php echo esc_attr( (string) ( $ai_cfg['registration_guard_form_selector'] ?? '' ) ); ?>" placeholder=".woocommerce-form-register, #register-form">
					<p class="description">
						<?php esc_html_e( 'CSS selector to identify the registration form. Examples: .woocommerce-form-register, #my-register-form', 'vms-elements-form-guard' ); ?>
						<br>
						<strong><?php esc_html_e( 'Required for custom registration pages to ensure validation is applied only to the correct form.', 'vms-elements-form-guard' ); ?></strong>
					</p>
				</td>
			</tr>
		</table>
			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save settings', 'vms-elements-form-guard' ); ?>">
			</p>
		</form>
	</div>
</div>
