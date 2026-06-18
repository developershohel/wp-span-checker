<?php
/**
 * Auth Forms admin template.
 *
 * Variables below are received from the including admin handler scope.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings      = \VMS_Elements_Form_Guard\Auth_Forms::get_settings();
$smtp_settings = \VMS_Elements_Form_Guard\Auth_Forms::get_smtp_settings();
$form_types    = \VMS_Elements_Form_Guard\Auth_Forms::get_form_types();
$ai_cfg        = \VMS_Elements_Form_Guard\AI_Span_Config::get();
$has_recaptcha = ! empty( $ai_cfg['recaptcha_site_key'] ) && ! empty( $ai_cfg['recaptcha_secret_key'] );

// Get all pages for dropdowns
$vefg_pages = get_pages( array( 'post_status' => 'publish' ) );
?>
<div class="wrap vefg-auth-forms-admin">
	<h1><?php esc_html_e( 'Auth Form Templates', 'vms-elements-form-guard' ); ?></h1>

	<div class="vefg-auth-admin-tabs">
		<button class="vefg-auth-tab active" data-tab="templates"><?php esc_html_e( 'Form Templates', 'vms-elements-form-guard' ); ?></button>
		<button class="vefg-auth-tab" data-tab="design"><?php esc_html_e( 'Design & Preview', 'vms-elements-form-guard' ); ?></button>
		<button class="vefg-auth-tab" data-tab="validation"><?php esc_html_e( 'Validation Rules', 'vms-elements-form-guard' ); ?></button>
		<button class="vefg-auth-tab" data-tab="smtp"><?php esc_html_e( 'SMTP Settings', 'vms-elements-form-guard' ); ?></button>
		<button class="vefg-auth-tab" data-tab="guide"><?php esc_html_e( 'How to Use', 'vms-elements-form-guard' ); ?></button>
	</div>

	<!-- Templates Tab -->
	<div class="vefg-auth-panel active" id="panel-templates">
		<div class="vefg-auth-card">
			<h2><?php esc_html_e( 'Available Form Templates', 'vms-elements-form-guard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use these shortcodes to add authentication forms to any page. Each form includes built-in validation and spam protection.', 'vms-elements-form-guard' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Form Type', 'vms-elements-form-guard' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'vms-elements-form-guard' ); ?></th>
						<th><?php esc_html_e( 'Assigned Page', 'vms-elements-form-guard' ); ?></th>
						<th><?php esc_html_e( 'Status', 'vms-elements-form-guard' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Login Form', 'vms-elements-form-guard' ); ?></strong></td>
						<td><code>[vefg_login_form]</code> <button type="button" class="button button-small vefg-copy-btn" data-copy="[vefg_login_form]"><?php esc_html_e( 'Copy', 'vms-elements-form-guard' ); ?></button></td>
						<td>
							<select name="login_page_id" class="vefg-page-select" data-setting="login_page_id">
								<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
								<?php foreach ( $vefg_pages as $vefg_page ) : ?>
									<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['login_page_id'], $vefg_page->ID ); ?>>
										<?php echo esc_html( $vefg_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( $settings['login_page_id'] ) : ?>
								<span class="vefg-status vefg-status--active"><?php esc_html_e( 'Active', 'vms-elements-form-guard' ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $settings['login_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'vms-elements-form-guard' ); ?></a>
							<?php else : ?>
								<span class="vefg-status vefg-status--inactive"><?php esc_html_e( 'Not Assigned', 'vms-elements-form-guard' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Registration Form', 'vms-elements-form-guard' ); ?></strong></td>
						<td><code>[vefg_register_form]</code> <button type="button" class="button button-small vefg-copy-btn" data-copy="[vefg_register_form]"><?php esc_html_e( 'Copy', 'vms-elements-form-guard' ); ?></button></td>
						<td>
							<select name="register_page_id" class="vefg-page-select" data-setting="register_page_id">
								<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
								<?php foreach ( $vefg_pages as $vefg_page ) : ?>
									<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['register_page_id'], $vefg_page->ID ); ?>>
										<?php echo esc_html( $vefg_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( $settings['register_page_id'] ) : ?>
								<span class="vefg-status vefg-status--active"><?php esc_html_e( 'Active', 'vms-elements-form-guard' ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $settings['register_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'vms-elements-form-guard' ); ?></a>
							<?php else : ?>
								<span class="vefg-status vefg-status--inactive"><?php esc_html_e( 'Not Assigned', 'vms-elements-form-guard' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Forgot Password Form', 'vms-elements-form-guard' ); ?></strong></td>
						<td><code>[vefg_forgot_password_form]</code> <button type="button" class="button button-small vefg-copy-btn" data-copy="[vefg_forgot_password_form]"><?php esc_html_e( 'Copy', 'vms-elements-form-guard' ); ?></button></td>
						<td>
							<select name="forgot_page_id" class="vefg-page-select" data-setting="forgot_page_id">
								<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
								<?php foreach ( $vefg_pages as $vefg_page ) : ?>
									<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['forgot_page_id'], $vefg_page->ID ); ?>>
										<?php echo esc_html( $vefg_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( $settings['forgot_page_id'] ) : ?>
								<span class="vefg-status vefg-status--active"><?php esc_html_e( 'Active', 'vms-elements-form-guard' ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $settings['forgot_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'vms-elements-form-guard' ); ?></a>
							<?php else : ?>
								<span class="vefg-status vefg-status--inactive"><?php esc_html_e( 'Not Assigned', 'vms-elements-form-guard' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Reset Password Form', 'vms-elements-form-guard' ); ?></strong></td>
						<td><code>[vefg_reset_password_form]</code> <button type="button" class="button button-small vefg-copy-btn" data-copy="[vefg_reset_password_form]"><?php esc_html_e( 'Copy', 'vms-elements-form-guard' ); ?></button></td>
						<td>
							<select name="reset_page_id" class="vefg-page-select" data-setting="reset_page_id">
								<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
								<?php foreach ( $vefg_pages as $vefg_page ) : ?>
									<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['reset_page_id'], $vefg_page->ID ); ?>>
										<?php echo esc_html( $vefg_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( $settings['reset_page_id'] ) : ?>
								<span class="vefg-status vefg-status--active"><?php esc_html_e( 'Active', 'vms-elements-form-guard' ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $settings['reset_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'vms-elements-form-guard' ); ?></a>
							<?php else : ?>
								<span class="vefg-status vefg-status--inactive"><?php esc_html_e( 'Not Assigned', 'vms-elements-form-guard' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Email Verify Form', 'vms-elements-form-guard' ); ?></strong><br><small style="color:#6b7280;"><?php esc_html_e( 'OTP + Activation Link', 'vms-elements-form-guard' ); ?></small></td>
						<td><code>[vefg_verify_form]</code> <button type="button" class="button button-small vefg-copy-btn" data-copy="[vefg_verify_form]"><?php esc_html_e( 'Copy', 'vms-elements-form-guard' ); ?></button></td>
						<td>
							<select name="verify_page_id" class="vefg-page-select" data-setting="verify_page_id">
								<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
								<?php foreach ( $vefg_pages as $vefg_page ) : ?>
									<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['verify_page_id'] ?? 0, $vefg_page->ID ); ?>>
										<?php echo esc_html( $vefg_page->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( ! empty( $settings['verify_page_id'] ) ) : ?>
								<span class="vefg-status vefg-status--active"><?php esc_html_e( 'Active', 'vms-elements-form-guard' ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $settings['verify_page_id'] ) ); ?>" target="_blank"><?php esc_html_e( 'View', 'vms-elements-form-guard' ); ?></a>
							<?php else : ?>
								<span class="vefg-status vefg-status--inactive"><?php esc_html_e( 'Not Assigned', 'vms-elements-form-guard' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="vefg-auth-actions" style="margin-top: 20px;">
				<button type="button" class="button button-primary" id="vefg-generate-pages">
					<span class="dashicons dashicons-admin-page" style="margin-top: 4px;"></span>
					<?php esc_html_e( 'Auto-Generate All Auth Pages', 'vms-elements-form-guard' ); ?>
				</button>
				<p class="description"><?php esc_html_e( 'Creates Login, Register, Forgot Password, Reset Password, and Email Verify pages automatically with the correct shortcodes.', 'vms-elements-form-guard' ); ?></p>
			</div>
		</div>

		<div class="vefg-auth-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Redirect Settings', 'vms-elements-form-guard' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><label for="login_redirect"><?php esc_html_e( 'After Login Redirect', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="url" id="login_redirect" name="login_redirect" value="<?php echo esc_url( $settings['login_redirect'] ); ?>" class="regular-text" placeholder="<?php echo esc_url( home_url() ); ?>">
						<p class="description"><?php esc_html_e( 'URL to redirect after successful login. Leave empty for home page.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="register_redirect"><?php esc_html_e( 'After Registration Redirect', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="url" id="register_redirect" name="register_redirect" value="<?php echo esc_url( $settings['register_redirect'] ); ?>" class="regular-text" placeholder="<?php echo esc_url( home_url() ); ?>">
						<p class="description"><?php esc_html_e( 'URL to redirect after successful registration. Leave empty for home page.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Design Tab -->
	<div class="vefg-auth-panel" id="panel-design">
		<div class="vefg-auth-design-wrap">
			<div class="vefg-auth-design-controls">
				<div class="vefg-auth-card">
					<h2><?php esc_html_e( 'Form Design', 'vms-elements-form-guard' ); ?></h2>

					<table class="form-table">
						<tr>
							<th><label for="primary_color"><?php esc_html_e( 'Primary Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="primary_color" name="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="secondary_color"><?php esc_html_e( 'Secondary Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="secondary_color" name="secondary_color" value="<?php echo esc_attr( $settings['secondary_color'] ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="text_color"><?php esc_html_e( 'Text Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="text_color" name="text_color" value="<?php echo esc_attr( $settings['text_color'] ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="background_color"><?php esc_html_e( 'Background Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="background_color" name="background_color" value="<?php echo esc_attr( $settings['background_color'] ); ?>" class="vefg-color-picker"></td>
						</tr>
					</table>
				</div>

				<div class="vefg-auth-card" style="margin-top: 20px;">
					<h2><?php esc_html_e( 'Input Field Colors', 'vms-elements-form-guard' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="border_color"><?php esc_html_e( 'Border Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="border_color" name="border_color" value="<?php echo esc_attr( $settings['border_color'] ?? '#d1d5db' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="border_hover_color"><?php esc_html_e( 'Border Hover Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="border_hover_color" name="border_hover_color" value="<?php echo esc_attr( $settings['border_hover_color'] ?? '#9ca3af' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="border_focus_color"><?php esc_html_e( 'Border Focus Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="border_focus_color" name="border_focus_color" value="<?php echo esc_attr( $settings['border_focus_color'] ?? '#2563eb' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="input_bg_color"><?php esc_html_e( 'Input Background', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="input_bg_color" name="input_bg_color" value="<?php echo esc_attr( $settings['input_bg_color'] ?? '#ffffff' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="input_focus_bg"><?php esc_html_e( 'Input Focus Background', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="input_focus_bg" name="input_focus_bg" value="<?php echo esc_attr( $settings['input_focus_bg'] ?? '#f9fafb' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="error_color"><?php esc_html_e( 'Error Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="error_color" name="error_color" value="<?php echo esc_attr( $settings['error_color'] ?? '#dc2626' ); ?>" class="vefg-color-picker"></td>
						</tr>
						<tr>
							<th><label for="success_color"><?php esc_html_e( 'Success Color', 'vms-elements-form-guard' ); ?></label></th>
							<td><input type="color" id="success_color" name="success_color" value="<?php echo esc_attr( $settings['success_color'] ?? '#16a34a' ); ?>" class="vefg-color-picker"></td>
						</tr>
					</table>
				</div>

				<div class="vefg-auth-card" style="margin-top: 20px;">
					<h2><?php esc_html_e( 'Layout & Style', 'vms-elements-form-guard' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="border_radius"><?php esc_html_e( 'Border Radius', 'vms-elements-form-guard' ); ?></label></th>
							<td>
								<input type="range" id="border_radius" name="border_radius" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" min="0" max="24" class="vefg-range-input">
								<span class="vefg-range-value"><?php echo esc_html( $settings['border_radius'] ); ?>px</span>
							</td>
						</tr>
						<tr>
							<th><label for="form_width"><?php esc_html_e( 'Form Width', 'vms-elements-form-guard' ); ?></label></th>
							<td>
								<input type="range" id="form_width" name="form_width" value="<?php echo esc_attr( $settings['form_width'] ); ?>" min="300" max="600" step="20" class="vefg-range-input">
								<span class="vefg-range-value"><?php echo esc_html( $settings['form_width'] ); ?>px</span>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Display Options', 'vms-elements-form-guard' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="show_labels" value="1" <?php checked( $settings['show_labels'] ); ?>>
									<?php esc_html_e( 'Show field labels', 'vms-elements-form-guard' ); ?>
								</label><br>
								<label>
									<input type="checkbox" name="show_placeholders" value="1" <?php checked( $settings['show_placeholders'] ); ?>>
									<?php esc_html_e( 'Show placeholders', 'vms-elements-form-guard' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th><label for="button_style"><?php esc_html_e( 'Button Style', 'vms-elements-form-guard' ); ?></label></th>
							<td>
								<select id="button_style" name="button_style">
									<option value="filled" <?php selected( $settings['button_style'], 'filled' ); ?>><?php esc_html_e( 'Filled', 'vms-elements-form-guard' ); ?></option>
									<option value="outline" <?php selected( $settings['button_style'], 'outline' ); ?>><?php esc_html_e( 'Outline', 'vms-elements-form-guard' ); ?></option>
									<option value="gradient" <?php selected( $settings['button_style'], 'gradient' ); ?>><?php esc_html_e( 'Gradient', 'vms-elements-form-guard' ); ?></option>
								</select>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<div class="vefg-auth-design-preview">
				<div class="vefg-auth-card">
					<h2><?php esc_html_e( 'Live Preview', 'vms-elements-form-guard' ); ?></h2>
					<div class="vefg-auth-preview-select">
						<select id="preview-form-type">
							<option value="login"><?php esc_html_e( 'Login Form', 'vms-elements-form-guard' ); ?></option>
							<option value="register"><?php esc_html_e( 'Registration Form', 'vms-elements-form-guard' ); ?></option>
							<option value="forgot"><?php esc_html_e( 'Forgot Password', 'vms-elements-form-guard' ); ?></option>
						</select>
					</div>
					<div class="vefg-auth-preview-container" id="vefg-preview-container">
						<!-- Preview will be rendered here -->
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Validation Tab -->
	<div class="vefg-auth-panel" id="panel-validation">
		<div class="vefg-auth-card">
			<h2><?php esc_html_e( 'Login Form Validation', 'vms-elements-form-guard' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'reCAPTCHA Protection', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name' => 'login_recaptcha',
								'checked' => $settings['login_recaptcha'],
							)
						);
						?>
						<?php if ( ! $has_recaptcha ) : ?>
							<p class="description" style="color: #d63638;"><?php esc_html_e( 'Configure reCAPTCHA keys in API Settings first.', 'vms-elements-form-guard' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<div class="vefg-auth-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Registration Form Validation', 'vms-elements-form-guard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These validation rules are automatically applied when users register through your auth forms.', 'vms-elements-form-guard' ); ?></p>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'reCAPTCHA Protection', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name' => 'register_recaptcha',
								'checked' => $settings['register_recaptcha'],
							)
						);
						?>
						<?php if ( ! $has_recaptcha ) : ?>
							<p class="description" style="color: #d63638;"><?php esc_html_e( 'Configure reCAPTCHA keys in API Settings first.', 'vms-elements-form-guard' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Check DNS (Domain Exists)', 'vms-elements-form-guard' ); ?></th>
					<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name' => 'register_check_dns',
							'checked' => $settings['register_check_dns'],
						)
					);
					?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Check MX (Can Receive Email)', 'vms-elements-form-guard' ); ?></th>
					<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name' => 'register_check_mx',
							'checked' => $settings['register_check_mx'],
						)
					);
					?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Block Disposable Emails', 'vms-elements-form-guard' ); ?></th>
					<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name' => 'register_check_disposable',
							'checked' => $settings['register_check_disposable'],
						)
					);
					?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Google Web Risk Check', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name' => 'register_webrisk',
								'checked' => $settings['register_webrisk'],
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Requires Web Risk API key. DNS and MX checks become mandatory.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'VirusTotal Check', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name' => 'register_virustotal',
								'checked' => $settings['register_virustotal'],
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Requires VirusTotal API key. DNS and MX checks become mandatory.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="vefg-auth-card" style="margin-top: 20px;">
			<h2><?php esc_html_e( 'Email Verification (Choose One)', 'vms-elements-form-guard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Add extra security by requiring users to verify their email before completing registration.', 'vms-elements-form-guard' ); ?></p>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Email Verification', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name' => 'enable_otp_verification',
								'checked' => $settings['enable_email_verification'] ?? false,
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Require email verification before account creation. Users receive both OTP code and activation link.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="otp_expires_minutes"><?php esc_html_e( 'OTP Expires (minutes)', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="number" id="otp_expires_minutes" name="otp_expires_minutes" value="<?php echo esc_attr( $settings['otp_expires_minutes'] ?? 10 ); ?>" min="1" max="60" class="small-text">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Verification Page', 'vms-elements-form-guard' ); ?></th>
					<td>
						<select name="verify_page_id" class="vefg-page-select" data-setting="verify_page_id">
							<option value=""><?php esc_html_e( '-- Select Page --', 'vms-elements-form-guard' ); ?></option>
							<?php foreach ( $vefg_pages as $vefg_page ) : ?>
								<option value="<?php echo esc_attr( $vefg_page->ID ); ?>" <?php selected( $settings['verify_page_id'] ?? 0, $vefg_page->ID ); ?>>
									<?php echo esc_html( $vefg_page->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Verification page with [vefg_verify_form] shortcode. Users can verify via OTP code OR activation link.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="link_expires_hours"><?php esc_html_e( 'Link Expires (hours)', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="number" id="link_expires_hours" name="link_expires_hours" value="<?php echo esc_attr( $settings['link_expires_hours'] ?? 24 ); ?>" min="1" max="168" class="small-text">
						<p class="description"><?php esc_html_e( 'How long the activation link in email remains valid.', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
			</table>

			<div class="notice notice-info inline" style="margin: 15px 0;">
				<p><?php esc_html_e( 'When enabled, users receive both an activation link AND a 6-digit OTP code. They can verify using either method.', 'vms-elements-form-guard' ); ?></p>
			</div>
		</div>
	</div>

	<!-- SMTP Tab -->
	<div class="vefg-auth-panel" id="panel-smtp">
		<div class="vefg-auth-card">
			<h2><?php esc_html_e( 'SMTP Configuration', 'vms-elements-form-guard' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Configure SMTP to ensure password reset emails are delivered reliably.', 'vms-elements-form-guard' ); ?></p>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable SMTP', 'vms-elements-form-guard' ); ?></th>
					<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name' => 'smtp_enabled',
							'checked' => $smtp_settings['enabled'],
						)
					);
					?>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_host"><?php esc_html_e( 'SMTP Host', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr( $smtp_settings['host'] ); ?>" class="regular-text" placeholder="smtp.example.com">
					</td>
				</tr>
				<tr>
					<th><label for="smtp_port"><?php esc_html_e( 'SMTP Port', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr( $smtp_settings['port'] ); ?>" class="small-text" placeholder="587">
						<p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS)', 'vms-elements-form-guard' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_encryption"><?php esc_html_e( 'Encryption', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<select id="smtp_encryption" name="smtp_encryption">
							<option value="" <?php selected( $smtp_settings['encryption'], '' ); ?>><?php esc_html_e( 'None', 'vms-elements-form-guard' ); ?></option>
							<option value="tls" <?php selected( $smtp_settings['encryption'], 'tls' ); ?>>TLS</option>
							<option value="ssl" <?php selected( $smtp_settings['encryption'], 'ssl' ); ?>>SSL</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Authentication', 'vms-elements-form-guard' ); ?></th>
					<td>
					<?php
					vms_elements_form_guard_admin_switch(
						array(
							'name' => 'smtp_auth',
							'checked' => $smtp_settings['auth'],
						)
					);
					?>
					</td>
				</tr>
				<tr>
					<th><label for="smtp_username"><?php esc_html_e( 'Username', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="text" id="smtp_username" name="smtp_username" value="<?php echo esc_attr( $smtp_settings['username'] ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>
				<tr>
					<th><label for="smtp_password"><?php esc_html_e( 'Password', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="password" id="smtp_password" name="smtp_password" value="<?php echo esc_attr( $smtp_settings['password'] ); ?>" class="regular-text" autocomplete="new-password">
					</td>
				</tr>
				<tr>
					<th><label for="smtp_from_email"><?php esc_html_e( 'From Email', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo esc_attr( $smtp_settings['from_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
					</td>
				</tr>
				<tr>
					<th><label for="smtp_from_name"><?php esc_html_e( 'From Name', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo esc_attr( $smtp_settings['from_name'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
					</td>
				</tr>
			</table>

			<div class="vefg-smtp-test" style="margin-top: 20px; padding: 15px; background: #f0f0f1; border-radius: 4px;">
				<h3><?php esc_html_e( 'Test SMTP Configuration', 'vms-elements-form-guard' ); ?></h3>
				<p>
					<input type="email" id="smtp_test_email" class="regular-text" placeholder="<?php esc_attr_e( 'Enter email to send test', 'vms-elements-form-guard' ); ?>">
					<button type="button" class="button" id="vefg-test-smtp"><?php esc_html_e( 'Send Test Email', 'vms-elements-form-guard' ); ?></button>
				</p>
				<div id="smtp-test-result"></div>
			</div>
		</div>
	</div>

	<!-- Guide Tab -->
	<div class="vefg-auth-panel" id="panel-guide">
		<div class="vefg-auth-card">
			<h2><?php esc_html_e( 'How to Use Auth Form Templates', 'vms-elements-form-guard' ); ?></h2>

			<div class="vefg-guide-section">
				<h3><?php esc_html_e( 'Quick Start (Recommended)', 'vms-elements-form-guard' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Click "Auto-Generate All Auth Pages" button in the Form Templates tab.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'This creates Login, Register, Forgot Password, and Reset Password pages automatically.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Customize the design in the "Design & Preview" tab.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Configure validation rules in the "Validation Rules" tab.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Set up SMTP for reliable email delivery.', 'vms-elements-form-guard' ); ?></li>
				</ol>
			</div>

			<div class="vefg-guide-section">
				<h3><?php esc_html_e( 'Manual Setup', 'vms-elements-form-guard' ); ?></h3>
				<p><?php esc_html_e( 'If you prefer to create pages manually:', 'vms-elements-form-guard' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Create a new page in WordPress.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Add the appropriate shortcode to the page content:', 'vms-elements-form-guard' ); ?>
						<ul style="margin-left: 20px; list-style: disc;">
							<li><code>[vefg_login_form]</code> - <?php esc_html_e( 'Login form', 'vms-elements-form-guard' ); ?></li>
							<li><code>[vefg_register_form]</code> - <?php esc_html_e( 'Registration form', 'vms-elements-form-guard' ); ?></li>
							<li><code>[vefg_forgot_password_form]</code> - <?php esc_html_e( 'Forgot password form', 'vms-elements-form-guard' ); ?></li>
							<li><code>[vefg_reset_password_form]</code> - <?php esc_html_e( 'Reset password form', 'vms-elements-form-guard' ); ?></li>
						</ul>
					</li>
					<li><?php esc_html_e( 'Publish the page.', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Assign the page in the Form Templates tab so forms can link to each other.', 'vms-elements-form-guard' ); ?></li>
				</ol>
			</div>

			<div class="vefg-guide-section">
				<h3><?php esc_html_e( 'Using the Page Editor Meta Box', 'vms-elements-form-guard' ); ?></h3>
				<p><?php esc_html_e( 'When editing any page, you\'ll see a "VMS Elements Form Guard Auth Form" meta box in the sidebar. Select a form type from the dropdown to indicate this page uses one of our auth forms. This helps with:', 'vms-elements-form-guard' ); ?></p>
				<ul style="margin-left: 20px; list-style: disc;">
					<li><?php esc_html_e( 'Organizing your auth pages', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Automatic linking between forms', 'vms-elements-form-guard' ); ?></li>
					<li><?php esc_html_e( 'Quick reference for which pages have auth forms', 'vms-elements-form-guard' ); ?></li>
				</ul>
			</div>

			<div class="vefg-guide-section">
				<h3><?php esc_html_e( 'Built-in Features', 'vms-elements-form-guard' ); ?></h3>
				<ul style="margin-left: 20px; list-style: disc;">
					<li><strong><?php esc_html_e( 'AJAX Submissions', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'Forms submit without page reload', 'vms-elements-form-guard' ); ?></li>
					<li><strong><?php esc_html_e( 'Email Validation', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'DNS, MX, disposable, and reputation checks', 'vms-elements-form-guard' ); ?></li>
					<li><strong><?php esc_html_e( 'reCAPTCHA', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'Optional bot protection', 'vms-elements-form-guard' ); ?></li>
					<li><strong><?php esc_html_e( 'Password Strength', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'Visual indicator for password quality', 'vms-elements-form-guard' ); ?></li>
					<li><strong><?php esc_html_e( 'Auto Redirect', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'Configurable redirects after login/register', 'vms-elements-form-guard' ); ?></li>
					<li><strong><?php esc_html_e( 'Mobile Responsive', 'vms-elements-form-guard' ); ?></strong> - <?php esc_html_e( 'Works on all screen sizes', 'vms-elements-form-guard' ); ?></li>
				</ul>
			</div>

			<div class="vefg-guide-section">
				<h3><?php esc_html_e( 'Advantages Over Default WordPress Forms', 'vms-elements-form-guard' ); ?></h3>
				<table class="wp-list-table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'Default WordPress', 'vms-elements-form-guard' ); ?></th>
							<th><?php esc_html_e( 'VMS Elements Form Guard', 'vms-elements-form-guard' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php esc_html_e( 'Custom Design', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'No', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'Full customization', 'vms-elements-form-guard' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Email Validation', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'No', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'Basic format only', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'DNS, MX, disposable, reputation', 'vms-elements-form-guard' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Spam Protection', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'No', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'reCAPTCHA + validation', 'vms-elements-form-guard' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'AJAX Submission', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'No', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'vms-elements-form-guard' ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Page Integration', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'No', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'Separate wp-login.php', 'vms-elements-form-guard' ); ?></td>
							<td><?php esc_html_e( 'Yes', 'vms-elements-form-guard' ); ?> - <?php esc_html_e( 'Any page via shortcode', 'vms-elements-form-guard' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="vefg-auth-save-bar">
		<button type="button" class="button button-primary button-large" id="vefg-save-auth-settings">
			<?php esc_html_e( 'Save All Settings', 'vms-elements-form-guard' ); ?>
		</button>
		<span class="vefg-save-status"></span>
	</div>
</div>
