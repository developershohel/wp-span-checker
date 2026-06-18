<?php
/**
 * Error Messages Settings Template
 *
 * Variables below are received from the including admin handler scope; the
 * `vefg_msg_field` helper uses the legacy plugin prefix kept for BC.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 */

defined( 'ABSPATH' ) || exit;

$option_key = 'vefg-error-messages';
$saved      = get_option( $option_key, array() );

$defaults = vms_elements_form_guard_get_default_error_messages();

if (
	isset( $_POST['vefg_error_messages_nonce'] )
	&& wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['vefg_error_messages_nonce'] ) ),
		'vefg_save_error_messages'
	)
) {
	$fields = array(
		// Registration Guard Messages
		'reg_blocked_title',
		'reg_blocked_intro',
		'reg_dns_failed',
		'reg_mx_failed',
		'reg_disposable',
		'reg_rate_limit',
		'reg_reputation_failed',
		'reg_rate_limit_count',
		'reg_contact_admin',

		// Email Validation Messages
		'email_invalid_format',
		'email_dns_failed',
		'email_mx_failed',
		'email_disposable',
		'email_webrisk_flagged',
		'email_virustotal_flagged',

		// URL Validation Messages
		'url_invalid',
		'url_dns_failed',
		'url_webrisk_flagged',
		'url_virustotal_flagged',

		// Spam Detection Messages
		'spam_detected',

		// Username Validation Messages
		'username_taken',

		// reCAPTCHA Messages
		'recaptcha_required',
		'recaptcha_failed',

		// General Messages
		'user_blocked',
		'validation_failed',
		'field_required',
		'server_error',
	);

	$new_saved = array();
	foreach ( $fields as $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$default_val = isset( $defaults[ $field ] ) ? $defaults[ $field ] : '';
		// If value equals default, save empty to use the default system
		if ( trim( $value ) === trim( $default_val ) ) {
			$value = '';
		}
		$new_saved[ $field ] = $value;
	}

	update_option( $option_key, $new_saved );
	$saved = $new_saved;
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Error messages saved.', 'vms-elements-form-guard' ) . '</p></div>';
}

function vefg_msg_field( $key, $saved, $defaults, $label, $description = '' ) {
	$value      = isset( $saved[ $key ] ) && '' !== $saved[ $key ] ? $saved[ $key ] : '';
	$default    = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$show_value = '' !== $value ? $value : $default;
	$is_custom  = '' !== $value;
	?>
	<tr>
		<th scope="row">
			<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		</th>
		<td>
			<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" rows="4" class="large-text vefg-msg-field" data-default="<?php echo esc_attr( $default ); ?>"><?php echo esc_textarea( $show_value ); ?></textarea>
			<p class="description">
				<?php if ( $description ) : ?>
					<?php echo esc_html( $description ); ?><br>
				<?php endif; ?>
				<?php if ( $is_custom ) : ?>
					<span class="vefg-custom-badge" style="background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;"><?php esc_html_e( 'Custom', 'vms-elements-form-guard' ); ?></span>
					<a href="#" class="vefg-reset-single" data-field="<?php echo esc_attr( $key ); ?>" style="margin-left: 8px;"><?php esc_html_e( 'Reset to default', 'vms-elements-form-guard' ); ?></a>
				<?php else : ?>
					<a href="#" class="vefg-reset-single vefg-default-badge" data-field="<?php echo esc_attr( $key ); ?>" style="background: #ddd; color: #50575e; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-decoration: none; cursor: pointer;" title="<?php esc_attr_e( 'Click to reset to default', 'vms-elements-form-guard' ); ?>"><?php esc_html_e( 'Default', 'vms-elements-form-guard' ); ?></a>
				<?php endif; ?>
			</p>
		</td>
	</tr>
	<?php
}
?>

<div class="wrap vefg-wrap">
	<h1><?php esc_html_e( 'Error Messages', 'vms-elements-form-guard' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Customize validation error messages shown to users. Leave empty to use the professional default message.', 'vms-elements-form-guard' ); ?></p>

	<form method="post" action="">
		<?php wp_nonce_field( 'vefg_save_error_messages', 'vefg_error_messages_nonce' ); ?>

		<!-- Registration Guard Messages -->
		<h2 class="title"><?php esc_html_e( 'Registration Guard Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'reg_blocked_title', $saved, $defaults, __( 'Blocked Title', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_blocked_intro', $saved, $defaults, __( 'Blocked Introduction', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_dns_failed', $saved, $defaults, __( 'DNS Check Failed', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_mx_failed', $saved, $defaults, __( 'MX Check Failed', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_disposable', $saved, $defaults, __( 'Disposable Email', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_rate_limit', $saved, $defaults, __( 'Rate Limit Reached', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'reg_reputation_failed', $saved, $defaults, __( 'Reputation Check Failed', 'vms-elements-form-guard' ) );
			vefg_msg_field(
				'reg_rate_limit_count',
				$saved,
				$defaults,
				__( 'Rate Limit Count', 'vms-elements-form-guard' ),
				/* translators: 1: placeholder for current attempts, 2: placeholder for maximum attempts */
				__( 'Use %1$d for current attempts and %2$d for maximum.', 'vms-elements-form-guard' )
			);
			vefg_msg_field( 'reg_contact_admin', $saved, $defaults, __( 'Contact Admin Message', 'vms-elements-form-guard' ) );
			?>
		</table>

		<!-- Email Validation Messages -->
		<h2 class="title"><?php esc_html_e( 'Email Validation Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'email_invalid_format', $saved, $defaults, __( 'Invalid Email Format', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'email_dns_failed', $saved, $defaults, __( 'Domain Not Found', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'email_mx_failed', $saved, $defaults, __( 'No Mail Server', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'email_disposable', $saved, $defaults, __( 'Disposable Email', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'email_webrisk_flagged', $saved, $defaults, __( 'Web Risk Flagged', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'email_virustotal_flagged', $saved, $defaults, __( 'VirusTotal Flagged', 'vms-elements-form-guard' ) );
			?>
		</table>

		<!-- URL Validation Messages -->
		<h2 class="title"><?php esc_html_e( 'URL Validation Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'url_invalid', $saved, $defaults, __( 'Invalid URL', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'url_dns_failed', $saved, $defaults, __( 'Domain Not Reachable', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'url_webrisk_flagged', $saved, $defaults, __( 'Web Risk Flagged', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'url_virustotal_flagged', $saved, $defaults, __( 'VirusTotal Flagged', 'vms-elements-form-guard' ) );
			?>
		</table>

		<!-- Content & Spam Messages -->
		<h2 class="title"><?php esc_html_e( 'Content & Spam Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'spam_detected', $saved, $defaults, __( 'Spam Detected', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'username_taken', $saved, $defaults, __( 'Username Taken', 'vms-elements-form-guard' ) );
			?>
		</table>

		<!-- reCAPTCHA Messages -->
		<h2 class="title"><?php esc_html_e( 'reCAPTCHA Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'recaptcha_required', $saved, $defaults, __( 'reCAPTCHA Required', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'recaptcha_failed', $saved, $defaults, __( 'reCAPTCHA Failed', 'vms-elements-form-guard' ) );
			?>
		</table>

		<!-- General Messages -->
		<h2 class="title"><?php esc_html_e( 'General Messages', 'vms-elements-form-guard' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			vefg_msg_field( 'user_blocked', $saved, $defaults, __( 'User Blocked', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'validation_failed', $saved, $defaults, __( 'Validation Failed', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'field_required', $saved, $defaults, __( 'Field Required', 'vms-elements-form-guard' ) );
			vefg_msg_field( 'server_error', $saved, $defaults, __( 'Server Error', 'vms-elements-form-guard' ) );
			?>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Messages', 'vms-elements-form-guard' ); ?></button>
			<button type="button" class="button" id="vefg-reset-defaults"><?php esc_html_e( 'Reset to Defaults', 'vms-elements-form-guard' ); ?></button>
		</p>
	</form>
</div>
