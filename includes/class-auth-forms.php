<?php
/**
 * Auth Forms - Custom authentication form templates with validation.
 *
 * @package VMS_Elements_Form_Guard
 */

namespace VMS_Elements_Form_Guard;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth Forms handler.
 */
class Auth_Forms {

	/**
	 * Option key for form settings.
	 */
	const OPTION_KEY = 'vefg_auth_forms_settings';

	/**
	 * Option key for SMTP settings.
	 */
	const SMTP_OPTION_KEY = 'vefg_smtp_settings';

	/**
	 * Available form templates.
	 *
	 * @var array
	 */
	private static $form_types = array(
		'login'           => 'Login Form',
		'register'        => 'Registration Form',
		'forgot_password' => 'Forgot Password Form',
		'reset_password'  => 'Reset Password Form',
		'otp_verify'      => 'OTP Verification Form',
		'activation'      => 'Account Activation',
	);

	/**
	 * Constructor - register hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'wp_ajax_vefg_generate_auth_pages', array( $this, 'ajax_generate_auth_pages' ) );
		add_action( 'wp_ajax_vefg_save_auth_form_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_vefg_save_smtp_settings', array( $this, 'ajax_save_smtp_settings' ) );
		add_action( 'wp_ajax_vefg_test_smtp', array( $this, 'ajax_test_smtp' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_form_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_form_meta' ) );
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
		add_filter( 'wp_mail_from', array( $this, 'set_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'set_mail_from_name' ) );

		// Auth form AJAX handlers
		add_action( 'wp_ajax_nopriv_vefg_auth_login', array( $this, 'ajax_handle_login' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_register', array( $this, 'ajax_handle_register' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_forgot_password', array( $this, 'ajax_handle_forgot_password' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_reset_password', array( $this, 'ajax_handle_reset_password' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_verify_otp', array( $this, 'ajax_handle_verify_otp' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_resend_otp', array( $this, 'ajax_handle_resend_otp' ) );
		add_action( 'wp_ajax_nopriv_vefg_auth_activate', array( $this, 'ajax_handle_activation' ) );

		add_filter( 'authenticate', array( $this, 'block_unverified_user_login' ), 30, 3 );

		// Load email templates
		require_once VMS_ELEMENTS_FORM_GUARD_DIR . 'templates/emails/vefg-email-template-functions.php';
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'primary_color'      => '#2563eb',
			'secondary_color'    => '#1e40af',
			'text_color'         => '#1f2937',
			'background_color'   => '#ffffff',
			'border_color'       => '#d1d5db',
			'border_hover_color' => '#9ca3af',
			'border_focus_color' => '#2563eb',
			'input_bg_color'     => '#ffffff',
			'input_focus_bg'     => '#f9fafb',
			'error_color'        => '#dc2626',
			'success_color'      => '#16a34a',
			'border_radius'      => '8',
			'form_width'         => '400',
			'show_labels'        => true,
			'show_placeholders'  => true,
			'button_style'       => 'filled',
			'login_redirect'     => '',
			'register_redirect'  => '',
			'login_page_id'      => 0,
			'register_page_id'   => 0,
			'forgot_page_id'     => 0,
			'reset_page_id'      => 0,
			// Validation settings per form
			'login_recaptcha'    => false,
			'register_recaptcha' => false,
			'register_check_dns' => true,
			'register_check_mx'  => true,
			'register_check_disposable' => true,
			'register_webrisk'   => false,
			'register_virustotal' => false,
			// Email Verification (combined OTP + Activation Link)
			'enable_email_verification' => false,
			'otp_expires_minutes'       => 10,
			'link_expires_hours'        => 24,
			'verify_page_id'            => 0,
		);
	}

	/**
	 * Get SMTP defaults.
	 *
	 * @return array
	 */
	public static function get_smtp_defaults() {
		return array(
			'enabled'     => false,
			'host'        => '',
			'port'        => 587,
			'encryption'  => 'tls',
			'auth'        => true,
			'username'    => '',
			'password'    => '',
			'from_email'  => '',
			'from_name'   => '',
		);
	}

	/**
	 * Get current settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Get SMTP settings.
	 *
	 * @return array
	 */
	public static function get_smtp_settings() {
		$saved = get_option( self::SMTP_OPTION_KEY, array() );
		return wp_parse_args( $saved, self::get_smtp_defaults() );
	}

	/**
	 * Get form types.
	 *
	 * @return array
	 */
	public static function get_form_types() {
		return self::$form_types;
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'vefg_login_form', array( $this, 'render_login_form' ) );
		add_shortcode( 'vefg_register_form', array( $this, 'render_register_form' ) );
		add_shortcode( 'vefg_forgot_password_form', array( $this, 'render_forgot_password_form' ) );
		add_shortcode( 'vefg_reset_password_form', array( $this, 'render_reset_password_form' ) );
		add_shortcode( 'vefg_verify_form', array( $this, 'render_verify_form' ) );
		// Legacy shortcodes (redirect to combined verify form)
		add_shortcode( 'vefg_otp_verify_form', array( $this, 'render_verify_form' ) );
		add_shortcode( 'vefg_activation_form', array( $this, 'render_verify_form' ) );
	}

	/**
	 * Render login form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_login_form( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'You are already logged in.', 'vms-elements-form-guard' ) . '</p>';
		}

		$settings = self::get_settings();
		$ai_cfg   = AI_Span_Config::get();

		ob_start();
		$this->output_form_styles( $settings );
		?>
		<div class="vefg-auth-form-wrap" data-form-type="login">
			<form class="vefg-auth-form vefg-auth-login" id="vefg-login-form" method="post">
				<h2 class="vefg-auth-title"><?php esc_html_e( 'Login', 'vms-elements-form-guard' ); ?></h2>
				
				<div class="vefg-auth-message-area"></div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-login-user"><?php esc_html_e( 'Username or Email', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<input type="text" id="vefg-login-user" name="user_login" 
						<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Username or Email', 'vms-elements-form-guard' ) . '"' : ''; ?>
						required autocomplete="username">
				</div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-login-pass"><?php esc_html_e( 'Password', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<div class="vefg-auth-password-wrap">
						<input type="password" id="vefg-login-pass" name="user_password" 
							<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Password', 'vms-elements-form-guard' ) . '"' : ''; ?>
							required autocomplete="current-password">
						<button type="button" class="vefg-auth-toggle-pass" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'vms-elements-form-guard' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
						</button>
					</div>
				</div>

				<div class="vefg-auth-field vefg-auth-remember">
					<label>
						<input type="checkbox" name="remember" value="1">
						<?php esc_html_e( 'Remember me', 'vms-elements-form-guard' ); ?>
					</label>
				</div>

				<?php if ( ! empty( $settings['login_recaptcha'] ) && ! empty( $ai_cfg['recaptcha_site_key'] ) ) : ?>
					<div class="vefg-auth-recaptcha" id="vefg-login-recaptcha"></div>
				<?php endif; ?>

				<input type="hidden" name="action" value="vefg_auth_login">
				<?php wp_nonce_field( 'vefg_auth_login', 'vefg_auth_nonce' ); ?>

				<button type="submit" class="vefg-auth-submit">
					<span class="vefg-auth-submit-text"><?php esc_html_e( 'Login', 'vms-elements-form-guard' ); ?></span>
					<span class="vefg-auth-spinner"></span>
				</button>

				<div class="vefg-auth-links">
					<?php if ( $settings['forgot_page_id'] ) : ?>
						<a href="<?php echo esc_url( get_permalink( $settings['forgot_page_id'] ) ); ?>"><?php esc_html_e( 'Forgot Password?', 'vms-elements-form-guard' ); ?></a>
					<?php endif; ?>
					<?php if ( $settings['register_page_id'] && get_option( 'users_can_register' ) ) : ?>
						<a href="<?php echo esc_url( get_permalink( $settings['register_page_id'] ) ); ?>"><?php esc_html_e( 'Create an account', 'vms-elements-form-guard' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render registration form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_register_form( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'You are already logged in.', 'vms-elements-form-guard' ) . '</p>';
		}

		if ( ! get_option( 'users_can_register' ) ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'Registration is currently disabled.', 'vms-elements-form-guard' ) . '</p>';
		}

		$settings = self::get_settings();
		$ai_cfg   = AI_Span_Config::get();

		ob_start();
		$this->output_form_styles( $settings );
		?>
		<div class="vefg-auth-form-wrap" data-form-type="register">
			<form class="vefg-auth-form vefg-auth-register" id="vefg-register-form" method="post">
				<h2 class="vefg-auth-title"><?php esc_html_e( 'Create Account', 'vms-elements-form-guard' ); ?></h2>
				
				<div class="vefg-auth-message-area"></div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reg-user"><?php esc_html_e( 'Username', 'vms-elements-form-guard' ); ?> <span class="required">*</span></label>
					<?php endif; ?>
					<input type="text" id="vefg-reg-user" name="user_login" 
						<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Username', 'vms-elements-form-guard' ) . '"' : ''; ?>
						required autocomplete="username">
				</div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reg-email"><?php esc_html_e( 'Email', 'vms-elements-form-guard' ); ?> <span class="required">*</span></label>
					<?php endif; ?>
					<input type="email" id="vefg-reg-email" name="user_email" 
						<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Email Address', 'vms-elements-form-guard' ) . '"' : ''; ?>
						required autocomplete="email">
					<span class="vefg-auth-field-status"></span>
				</div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reg-pass"><?php esc_html_e( 'Password', 'vms-elements-form-guard' ); ?> <span class="required">*</span></label>
					<?php endif; ?>
					<div class="vefg-auth-password-wrap">
						<input type="password" id="vefg-reg-pass" name="user_password" 
							<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Password', 'vms-elements-form-guard' ) . '"' : ''; ?>
							required autocomplete="new-password">
						<button type="button" class="vefg-auth-toggle-pass" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'vms-elements-form-guard' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
						</button>
					</div>
					<div class="vefg-auth-password-strength"></div>
				</div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reg-pass-confirm"><?php esc_html_e( 'Confirm Password', 'vms-elements-form-guard' ); ?> <span class="required">*</span></label>
					<?php endif; ?>
					<div class="vefg-auth-password-wrap">
						<input type="password" id="vefg-reg-pass-confirm" name="user_password_confirm" 
							<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Confirm Password', 'vms-elements-form-guard' ) . '"' : ''; ?>
							required autocomplete="new-password">
					</div>
				</div>

				<?php if ( ! empty( $settings['register_recaptcha'] ) && ! empty( $ai_cfg['recaptcha_site_key'] ) ) : ?>
					<div class="vefg-auth-recaptcha" id="vefg-register-recaptcha"></div>
				<?php endif; ?>

				<!-- Validation rules info -->
				<div class="vefg-auth-validation-info">
					<?php
					$rules = array();
					if ( ! empty( $settings['register_check_dns'] ) ) {
						$rules[] = __( 'Valid domain', 'vms-elements-form-guard' );
					}
					if ( ! empty( $settings['register_check_mx'] ) ) {
						$rules[] = __( 'Email deliverable', 'vms-elements-form-guard' );
					}
					if ( ! empty( $settings['register_check_disposable'] ) ) {
						$rules[] = __( 'No disposable emails', 'vms-elements-form-guard' );
					}
					if ( ! empty( $rules ) ) :
						?>
						<p class="vefg-auth-rules-note">
							<span class="dashicons dashicons-shield"></span>
							<?php echo esc_html( implode( ' • ', $rules ) ); ?>
						</p>
					<?php endif; ?>
				</div>

				<input type="hidden" name="action" value="vefg_auth_register">
				<?php wp_nonce_field( 'vefg_auth_register', 'vefg_auth_nonce' ); ?>

				<button type="submit" class="vefg-auth-submit">
					<span class="vefg-auth-submit-text"><?php esc_html_e( 'Create Account', 'vms-elements-form-guard' ); ?></span>
					<span class="vefg-auth-spinner"></span>
				</button>

				<div class="vefg-auth-links">
					<?php if ( $settings['login_page_id'] ) : ?>
						<a href="<?php echo esc_url( get_permalink( $settings['login_page_id'] ) ); ?>"><?php esc_html_e( 'Already have an account? Login', 'vms-elements-form-guard' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render forgot password form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_forgot_password_form( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'You are already logged in.', 'vms-elements-form-guard' ) . '</p>';
		}

		$settings = self::get_settings();

		ob_start();
		$this->output_form_styles( $settings );
		?>
		<div class="vefg-auth-form-wrap" data-form-type="forgot_password">
			<form class="vefg-auth-form vefg-auth-forgot" id="vefg-forgot-form" method="post">
				<h2 class="vefg-auth-title"><?php esc_html_e( 'Reset Password', 'vms-elements-form-guard' ); ?></h2>
				<p class="vefg-auth-subtitle"><?php esc_html_e( 'Enter your email address and we\'ll send you a link to reset your password.', 'vms-elements-form-guard' ); ?></p>
				
				<div class="vefg-auth-message-area"></div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-forgot-email"><?php esc_html_e( 'Email Address', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<input type="email" id="vefg-forgot-email" name="user_email" 
						<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Email Address', 'vms-elements-form-guard' ) . '"' : ''; ?>
						required autocomplete="email">
				</div>

				<input type="hidden" name="action" value="vefg_auth_forgot_password">
				<?php wp_nonce_field( 'vefg_auth_forgot_password', 'vefg_auth_nonce' ); ?>

				<button type="submit" class="vefg-auth-submit">
					<span class="vefg-auth-submit-text"><?php esc_html_e( 'Send Reset Link', 'vms-elements-form-guard' ); ?></span>
					<span class="vefg-auth-spinner"></span>
				</button>

				<div class="vefg-auth-links">
					<?php if ( $settings['login_page_id'] ) : ?>
						<a href="<?php echo esc_url( get_permalink( $settings['login_page_id'] ) ); ?>"><?php esc_html_e( 'Back to Login', 'vms-elements-form-guard' ); ?></a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render reset password form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_reset_password_form( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'You are already logged in.', 'vms-elements-form-guard' ) . '</p>';
		}

		// Check for reset key and login.
		// These come from password-reset links generated by WordPress itself
		// and are validated by `check_password_reset_key()` below; a separate
		// nonce is not used for these read-only link parameters.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated via check_password_reset_key().
		$rp_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated via check_password_reset_key().
		$rp_login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';

		if ( empty( $rp_key ) || empty( $rp_login ) ) {
			$settings = self::get_settings();
			ob_start();
			$this->output_form_styles( $settings );
			?>
			<div class="vefg-auth-form-wrap">
				<div class="vefg-auth-form">
					<div class="vefg-auth-message vefg-auth-message--error">
						<?php esc_html_e( 'Invalid password reset link. Please request a new one.', 'vms-elements-form-guard' ); ?>
					</div>
					<?php if ( $settings['forgot_page_id'] ) : ?>
						<div class="vefg-auth-links">
							<a href="<?php echo esc_url( get_permalink( $settings['forgot_page_id'] ) ); ?>"><?php esc_html_e( 'Request new reset link', 'vms-elements-form-guard' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// Verify the key
		$user = check_password_reset_key( $rp_key, $rp_login );
		if ( is_wp_error( $user ) ) {
			$settings = self::get_settings();
			ob_start();
			$this->output_form_styles( $settings );
			?>
			<div class="vefg-auth-form-wrap">
				<div class="vefg-auth-form">
					<div class="vefg-auth-message vefg-auth-message--error">
						<?php esc_html_e( 'This password reset link has expired or is invalid. Please request a new one.', 'vms-elements-form-guard' ); ?>
					</div>
					<?php if ( $settings['forgot_page_id'] ) : ?>
						<div class="vefg-auth-links">
							<a href="<?php echo esc_url( get_permalink( $settings['forgot_page_id'] ) ); ?>"><?php esc_html_e( 'Request new reset link', 'vms-elements-form-guard' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$settings = self::get_settings();

		ob_start();
		$this->output_form_styles( $settings );
		?>
		<div class="vefg-auth-form-wrap" data-form-type="reset_password">
			<form class="vefg-auth-form vefg-auth-reset" id="vefg-reset-form" method="post">
				<h2 class="vefg-auth-title"><?php esc_html_e( 'Set New Password', 'vms-elements-form-guard' ); ?></h2>
				
				<div class="vefg-auth-message-area"></div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reset-pass"><?php esc_html_e( 'New Password', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<div class="vefg-auth-password-wrap">
						<input type="password" id="vefg-reset-pass" name="user_password" 
							<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'New Password', 'vms-elements-form-guard' ) . '"' : ''; ?>
							required autocomplete="new-password">
						<button type="button" class="vefg-auth-toggle-pass" aria-label="<?php esc_attr_e( 'Toggle password visibility', 'vms-elements-form-guard' ); ?>">
							<span class="dashicons dashicons-visibility"></span>
						</button>
					</div>
					<div class="vefg-auth-password-strength"></div>
				</div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label for="vefg-reset-pass-confirm"><?php esc_html_e( 'Confirm New Password', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<div class="vefg-auth-password-wrap">
						<input type="password" id="vefg-reset-pass-confirm" name="user_password_confirm" 
							<?php echo $settings['show_placeholders'] ? 'placeholder="' . esc_attr__( 'Confirm Password', 'vms-elements-form-guard' ) . '"' : ''; ?>
							required autocomplete="new-password">
					</div>
				</div>

				<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>">
				<input type="hidden" name="rp_login" value="<?php echo esc_attr( $rp_login ); ?>">
				<input type="hidden" name="action" value="vefg_auth_reset_password">
				<?php wp_nonce_field( 'vefg_auth_reset_password', 'vefg_auth_nonce' ); ?>

				<button type="submit" class="vefg-auth-submit">
					<span class="vefg-auth-submit-text"><?php esc_html_e( 'Reset Password', 'vms-elements-form-guard' ); ?></span>
					<span class="vefg-auth-spinner"></span>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render combined verification form (handles both OTP and activation link).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_verify_form( $atts = array() ) {
		if ( is_user_logged_in() ) {
			return '<p class="vefg-auth-message">' . esc_html__( 'You are already logged in.', 'vms-elements-form-guard' ) . '</p>';
		}

		$settings = self::get_settings();

		// Check for activation link parameters. These come from one-time email
		// activation links generated by this plugin; the `key` is validated
		// against `vefg_activation_key` user meta below before any action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated below.
		$key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated below.
		$login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated below.
		$email = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';

		ob_start();
		$this->output_form_styles( $settings );

		// If activation link provided, process it automatically
		if ( ! empty( $key ) && ! empty( $login ) ) {
			$user = get_user_by( 'login', $login );
			if ( $user ) {
				$stored_key = get_user_meta( $user->ID, 'vefg_activation_key', true );
				$expiry     = get_user_meta( $user->ID, 'vefg_activation_expiry', true );

				if ( $stored_key === $key && time() < $expiry ) {
					self::complete_email_verification( $user );

					// Send welcome email
					$login_url = $settings['login_page_id'] ? get_permalink( $settings['login_page_id'] ) : wp_login_url();
					$body      = function_exists( 'vefg_email_welcome' ) ? vefg_email_welcome( $user->display_name, $login_url ) : '';
					if ( $body ) {
						vefg_send_html_email(
							$user->user_email,
							sprintf(
								/* translators: %s: site name */
								__( '[%s] Welcome!', 'vms-elements-form-guard' ),
								get_bloginfo( 'name' )
							),
							$body
						);
					}
					?>
					<div class="vefg-auth-form-wrap" data-form-type="activation_success">
						<div class="vefg-auth-form">
							<div class="vefg-auth-success-icon">
								<span class="dashicons dashicons-yes-alt"></span>
							</div>
							<h2 class="vefg-auth-title"><?php esc_html_e( 'Account Verified!', 'vms-elements-form-guard' ); ?></h2>
							<p class="vefg-auth-subtitle"><?php esc_html_e( 'Your account has been successfully verified. You can now log in.', 'vms-elements-form-guard' ); ?></p>
							<a href="<?php echo esc_url( $login_url ); ?>" class="vefg-auth-submit"><?php esc_html_e( 'Login Now', 'vms-elements-form-guard' ); ?></a>
						</div>
					</div>
					<?php
					return ob_get_clean();
				} else {
					// Invalid or expired link - show form with error
					$email = $user->user_email;
					?>
					<div class="vefg-auth-form-wrap" data-form-type="verify">
						<div class="vefg-auth-form">
							<div class="vefg-auth-message vefg-auth-message--error">
								<?php esc_html_e( 'This activation link has expired. Please enter the verification code or request a new link.', 'vms-elements-form-guard' ); ?>
							</div>
						</div>
					</div>
					<?php
				}
			}
		}

		// Show verification form (OTP input)
		if ( empty( $email ) ) {
			?>
			<div class="vefg-auth-form-wrap">
				<div class="vefg-auth-form">
					<div class="vefg-auth-message vefg-auth-message--error">
						<?php esc_html_e( 'Invalid verification request. Please check your email for the verification link.', 'vms-elements-form-guard' ); ?>
					</div>
					<?php if ( $settings['login_page_id'] ) : ?>
						<div class="vefg-auth-links" style="margin-top: 20px;">
							<a href="<?php echo esc_url( get_permalink( $settings['login_page_id'] ) ); ?>"><?php esc_html_e( 'Back to Login', 'vms-elements-form-guard' ); ?></a>
						</div>
					<?php endif; ?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}
		?>
		<div class="vefg-auth-form-wrap" data-form-type="verify">
			<form class="vefg-auth-form vefg-auth-verify" id="vefg-verify-form" method="post">
				<h2 class="vefg-auth-title"><?php esc_html_e( 'Verify Your Email', 'vms-elements-form-guard' ); ?></h2>
				<p class="vefg-auth-subtitle">
					<?php
					printf(
						/* translators: %s: email address wrapped in strong tags */
						esc_html__( 'We sent a verification code and activation link to %s', 'vms-elements-form-guard' ),
						'<strong>' . esc_html( $email ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					?>
				</p>
				
				<div class="vefg-auth-message-area"></div>

				<div class="vefg-auth-field">
					<?php if ( $settings['show_labels'] ) : ?>
						<label><?php esc_html_e( 'Enter 6-digit verification code', 'vms-elements-form-guard' ); ?></label>
					<?php endif; ?>
					<div class="vefg-auth-otp-inputs">
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="one-time-code" autofocus>
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
						<input type="text" class="vefg-otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
					</div>
					<input type="hidden" name="otp_code" id="vefg-otp-code">
				</div>

				<input type="hidden" name="email" value="<?php echo esc_attr( $email ); ?>">
				<input type="hidden" name="action" value="vefg_auth_verify_otp">
				<?php wp_nonce_field( 'vefg_auth_verify_otp', 'vefg_auth_nonce' ); ?>

				<button type="submit" class="vefg-auth-submit">
					<span class="vefg-auth-submit-text"><?php esc_html_e( 'Verify Code', 'vms-elements-form-guard' ); ?></span>
					<span class="vefg-auth-spinner"></span>
				</button>

				<div class="vefg-auth-links">
					<p class="vefg-auth-resend">
						<?php esc_html_e( "Didn't receive the code?", 'vms-elements-form-guard' ); ?>
						<a href="#" id="vefg-resend-otp" data-email="<?php echo esc_attr( $email ); ?>"><?php esc_html_e( 'Resend', 'vms-elements-form-guard' ); ?></a>
					</p>
					<p class="vefg-auth-link-hint">
						<?php esc_html_e( 'You can also click the activation link in your email.', 'vms-elements-form-guard' ); ?>
					</p>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Legacy: Render activation page (redirects to verify form).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_activation_form( $atts = array() ) {
		return $this->render_verify_form( $atts );
	}

	/**
	 * Legacy: Render OTP verification form (redirects to verify form).
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_otp_verify_form( $atts = array() ) {
		return $this->render_verify_form( $atts );
	}

	/**
	 * Output form CSS custom properties.
	 *
	 * @param array $settings Settings.
	 */
	private function output_form_styles( $settings ) {
		// The per-site CSS custom properties are now emitted via wp_add_inline_style()
		// in VMS_Elements_Form_Guard\Enqueue_Scripts::enqueue_auth_forms(), so this
		// method intentionally no longer prints a raw <style> tag inside the form markup.
		unset( $settings );
	}

	/**
	 * Handle login AJAX.
	 */
	public function ajax_handle_login() {
		check_ajax_referer( 'vefg_auth_login', 'vefg_auth_nonce' );

		$user_login = isset( $_POST['user_login'] ) ? sanitize_text_field( wp_unslash( $_POST['user_login'] ) ) : '';
		// Passwords must NOT be passed through sanitize_text_field because that
		// would strip valid characters; the password is later hashed by
		// wp_signon() / wp_set_password().
		$password   = isset( $_POST['user_password'] ) ? wp_unslash( $_POST['user_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw password, hashed before use.
		$remember   = isset( $_POST['remember'] ) && $_POST['remember'] === '1';

		if ( empty( $user_login ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all fields.', 'vms-elements-form-guard' ) ) );
		}

		// Check reCAPTCHA if enabled
		$settings = self::get_settings();
		$ai_cfg   = AI_Span_Config::get();

		if ( ! empty( $settings['login_recaptcha'] ) && ! empty( $ai_cfg['recaptcha_site_key'] ) ) {
			$recaptcha_token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
			if ( empty( $recaptcha_token ) ) {
				wp_send_json_error( array( 'message' => __( 'Please complete the reCAPTCHA.', 'vms-elements-form-guard' ) ) );
			}

			$ajax = new Ajax();
			$result = $ajax->verify_recaptcha( $recaptcha_token );
			if ( ! $result['success'] ) {
				wp_send_json_error( array( 'message' => __( 'reCAPTCHA verification failed.', 'vms-elements-form-guard' ) ) );
			}
		}

		$user = wp_signon(
			array(
				'user_login'    => $user_login,
				'user_password' => $password,
				'remember'      => $remember,
			)
		);

		if ( is_wp_error( $user ) ) {
			$error_codes = $user->get_error_codes();

			if ( in_array( 'vefg_email_not_verified', $error_codes, true ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Your account is not verified yet. Please verify your email first, then log in.', 'vms-elements-form-guard' ),
					)
				);
			}

			if ( array_intersect( $error_codes, array( 'invalid_username', 'incorrect_password', 'invalid_email' ) ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Login failed. Please check your username/email and password, then try again.', 'vms-elements-form-guard' ),
					)
				);
			}

			$fallback_message = wp_strip_all_tags( $user->get_error_message() );
			if ( '' === $fallback_message ) {
				$fallback_message = __( 'Unable to log in right now. Please try again in a moment.', 'vms-elements-form-guard' );
			}

			wp_send_json_error( array( 'message' => $fallback_message ) );
		}

		if ( ! self::is_user_email_verified( $user->ID ) ) {
			wp_logout();
			wp_send_json_error(
				array(
					'message' => __( 'Your account is not verified yet. Please verify your email first, then log in.', 'vms-elements-form-guard' ),
				)
			);
		}

		$redirect = $settings['login_redirect'];
		if ( empty( $redirect ) ) {
			$redirect = home_url();
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Login successful! Redirecting...', 'vms-elements-form-guard' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Handle registration AJAX.
	 */
	public function ajax_handle_register() {
		check_ajax_referer( 'vefg_auth_register', 'vefg_auth_nonce' );

		$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ) ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		// Passwords must NOT be passed through sanitize_text_field; they are
		// later hashed by wp_create_user() / wp_set_password().
		$password         = isset( $_POST['user_password'] ) ? wp_unslash( $_POST['user_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw password, hashed before use.
		$password_confirm = isset( $_POST['user_password_confirm'] ) ? wp_unslash( $_POST['user_password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw password, used only for equality check.

		// Basic validation
		if ( empty( $user_login ) || empty( $user_email ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'vms-elements-form-guard' ) ) );
		}

		if ( $password !== $password_confirm ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'vms-elements-form-guard' ) ) );
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'vms-elements-form-guard' ) ) );
		}

		$settings = self::get_settings();
		$ai_cfg   = AI_Span_Config::get();

		// Check reCAPTCHA if enabled
		if ( ! empty( $settings['register_recaptcha'] ) && ! empty( $ai_cfg['recaptcha_site_key'] ) ) {
			$recaptcha_token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
			if ( empty( $recaptcha_token ) ) {
				wp_send_json_error( array( 'message' => __( 'Please complete the reCAPTCHA.', 'vms-elements-form-guard' ) ) );
			}

			$ajax = new Ajax();
			$result = $ajax->verify_recaptcha( $recaptcha_token );
			if ( ! $result['success'] ) {
				wp_send_json_error( array( 'message' => __( 'reCAPTCHA verification failed.', 'vms-elements-form-guard' ) ) );
			}
		}

		// Email domain validation
		$domain = substr( strrchr( $user_email, '@' ), 1 );
		$needs_api_checks = ! empty( $settings['register_webrisk'] ) || ! empty( $settings['register_virustotal'] );

		// DNS check (mandatory if API checks enabled)
		if ( ! empty( $settings['register_check_dns'] ) || $needs_api_checks ) {
			if ( ! vms_elements_form_guard_check_domain_dns( $domain ) ) {
				wp_send_json_error( array( 'message' => __( 'Email domain does not exist.', 'vms-elements-form-guard' ) ) );
			}
		}

		// MX check (mandatory if API checks enabled)
		if ( ! empty( $settings['register_check_mx'] ) || $needs_api_checks ) {
			if ( ! vms_elements_form_guard_check_mx_record( $domain ) ) {
				wp_send_json_error( array( 'message' => __( 'Email domain cannot receive emails.', 'vms-elements-form-guard' ) ) );
			}
		}

		// Disposable check
		if ( ! empty( $settings['register_check_disposable'] ) ) {
			if ( vms_elements_form_guard_is_disposable_domain( $domain ) ) {
				wp_send_json_error( array( 'message' => __( 'Disposable email addresses are not allowed.', 'vms-elements-form-guard' ) ) );
			}
		}

		// Web Risk check
		if ( ! empty( $settings['register_webrisk'] ) ) {
			$webrisk_result = vms_elements_form_guard_check_webrisk( $domain );
			if ( $webrisk_result && ! empty( $webrisk_result['threat'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Email domain flagged for security issues.', 'vms-elements-form-guard' ) ) );
			}
		}

		// VirusTotal check
		if ( ! empty( $settings['register_virustotal'] ) ) {
			$vt_result = vms_elements_form_guard_check_virustotal( $domain );
			if ( $vt_result && isset( $vt_result['malicious'] ) && $vt_result['malicious'] > 0 ) {
				wp_send_json_error( array( 'message' => __( 'Email domain flagged as potentially harmful.', 'vms-elements-form-guard' ) ) );
			}
		}

		// Check if username exists
		if ( username_exists( $user_login ) ) {
			wp_send_json_error( array( 'message' => __( 'Username already exists.', 'vms-elements-form-guard' ) ) );
		}

		// Check if email exists
		if ( email_exists( $user_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email address already registered.', 'vms-elements-form-guard' ) ) );
		}

		// Check if email verification is enabled (combined OTP + activation link)
		if ( ! empty( $settings['enable_email_verification'] ) ) {
			$user_id = wp_create_user( $user_login, $password, $user_email );

			if ( is_wp_error( $user_id ) ) {
				wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
			}

			$otp             = self::generate_otp();
			$otp_expires     = (int) ( $settings['otp_expires_minutes'] ?? 10 );
			$activation_key  = wp_generate_password( 32, false );
			$link_expires    = (int) ( $settings['link_expires_hours'] ?? 24 );
			$link_expires_ts = time() + ( $link_expires * HOUR_IN_SECONDS );

			self::store_pending_verification_meta( $user_id, $otp, $otp_expires, $activation_key, $link_expires_ts );

			$verify_page_id = $settings['verify_page_id'] ?? 0;
			$activation_url = $verify_page_id
				? add_query_arg(
					array(
						'key'   => $activation_key,
						'login' => $user_login,
						'email' => $user_email,
					),
					get_permalink( $verify_page_id )
				)
				: add_query_arg(
					array(
						'key'   => $activation_key,
						'login' => $user_login,
						'email' => $user_email,
					),
					home_url( '/verify/' )
				);

			if ( function_exists( 'vefg_email_verification' ) && function_exists( 'vefg_send_html_email' ) ) {
				/* translators: %s: site name */
				$subject = sprintf( __( '[%s] Verify Your Email', 'vms-elements-form-guard' ), get_bloginfo( 'name' ) );
				$body    = vefg_email_verification( $activation_url, $otp, $user_login, $user_email, $otp_expires, $link_expires );
				$sent    = vefg_send_html_email( $user_email, $subject, $body );

				if ( ! $sent ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					wp_delete_user( $user_id );
					wp_send_json_error( array( 'message' => __( 'Failed to send verification email. Please try again.', 'vms-elements-form-guard' ) ) );
				}
			}

			$redirect = $verify_page_id
				? add_query_arg( 'email', rawurlencode( $user_email ), get_permalink( $verify_page_id ) )
				: add_query_arg( 'email', rawurlencode( $user_email ), home_url( '/verify/' ) );

			wp_send_json_success(
				array(
					'message'        => __( 'Verification email sent! Check your inbox for the code or activation link.', 'vms-elements-form-guard' ),
					'redirect'       => $redirect,
					'require_verify' => true,
				)
			);
			return;
		}

		// Standard registration (no OTP or activation)
		$user_id = wp_create_user( $user_login, $password, $user_email );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		update_user_meta( $user_id, '_vefg_account_activated', true );
		update_user_meta( $user_id, 'vefg_account_verified', '1' );

		$redirect = $settings['login_page_id'] ? get_permalink( $settings['login_page_id'] ) : wp_login_url();

		wp_send_json_success(
			array(
				'message'  => __( 'Account created successfully! Please log in to continue.', 'vms-elements-form-guard' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Handle forgot password AJAX.
	 */
	public function ajax_handle_forgot_password() {
		check_ajax_referer( 'vefg_auth_forgot_password', 'vefg_auth_nonce' );

		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';

		if ( empty( $user_email ) || ! is_email( $user_email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ) ) );
		}

		$user = get_user_by( 'email', $user_email );

		// Don't reveal if user exists
		if ( ! $user ) {
			wp_send_json_success( array( 'message' => __( 'If an account exists with that email, you will receive a password reset link.', 'vms-elements-form-guard' ) ) );
			return;
		}

		// Generate reset key
		$key = get_password_reset_key( $user );

		if ( is_wp_error( $key ) ) {
			wp_send_json_error( array( 'message' => __( 'Unable to generate reset link. Please try again.', 'vms-elements-form-guard' ) ) );
		}

		// Build reset URL
		$settings  = self::get_settings();
		$reset_url = $settings['reset_page_id']
			? add_query_arg(
				array(
					'key' => $key,
					'login' => rawurlencode( $user->user_login ),
				),
				get_permalink( $settings['reset_page_id'] )
			)
			: network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );

		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] Password Reset', 'vms-elements-form-guard' ), get_bloginfo( 'name' ) );
		$message = sprintf(
			/* translators: 1: user display name, 2: password reset URL, 3: site name */
			__( "Hello %1\$s,\n\nYou requested a password reset for your account.\n\nClick the link below to reset your password:\n%2\$s\n\nIf you didn't request this, you can ignore this email.\n\nThanks,\n%3\$s", 'vms-elements-form-guard' ),
			$user->display_name,
			$reset_url,
			get_bloginfo( 'name' )
		);

		$sent = wp_mail( $user_email, $subject, $message );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please check SMTP settings.', 'vms-elements-form-guard' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'If an account exists with that email, you will receive a password reset link.', 'vms-elements-form-guard' ) ) );
	}

	/**
	 * Handle reset password AJAX.
	 */
	public function ajax_handle_reset_password() {
		check_ajax_referer( 'vefg_auth_reset_password', 'vefg_auth_nonce' );

		$rp_key   = isset( $_POST['rp_key'] ) ? sanitize_text_field( wp_unslash( $_POST['rp_key'] ) ) : '';
		$rp_login = isset( $_POST['rp_login'] ) ? sanitize_user( wp_unslash( $_POST['rp_login'] ) ) : '';
		// Passwords must NOT be passed through sanitize_text_field; they are
		// later hashed by wp_set_password().
		$password         = isset( $_POST['user_password'] ) ? wp_unslash( $_POST['user_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw password, hashed before use.
		$password_confirm = isset( $_POST['user_password_confirm'] ) ? wp_unslash( $_POST['user_password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw password, used only for equality check.

		if ( empty( $rp_key ) || empty( $rp_login ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid reset link.', 'vms-elements-form-guard' ) ) );
		}

		if ( empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a new password.', 'vms-elements-form-guard' ) ) );
		}

		if ( $password !== $password_confirm ) {
			wp_send_json_error( array( 'message' => __( 'Passwords do not match.', 'vms-elements-form-guard' ) ) );
		}

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Password must be at least 8 characters.', 'vms-elements-form-guard' ) ) );
		}

		$user = check_password_reset_key( $rp_key, $rp_login );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => __( 'Reset link has expired or is invalid.', 'vms-elements-form-guard' ) ) );
		}

		reset_password( $user, $password );

		$settings = self::get_settings();
		$redirect = $settings['login_page_id'] ? get_permalink( $settings['login_page_id'] ) : wp_login_url();

		wp_send_json_success(
			array(
				'message'  => __( 'Password reset successfully! Redirecting to login...', 'vms-elements-form-guard' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * AJAX: Generate auth pages.
	 */
	public function ajax_generate_auth_pages() {
		check_ajax_referer( 'vms_elements_form_guard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'vms-elements-form-guard' ) ) );
		}

		$pages = array(
			'login' => array(
				'title'     => __( 'Login', 'vms-elements-form-guard' ),
				'shortcode' => '[vefg_login_form]',
				'option'    => 'login_page_id',
			),
			'register' => array(
				'title'     => __( 'Register', 'vms-elements-form-guard' ),
				'shortcode' => '[vefg_register_form]',
				'option'    => 'register_page_id',
			),
			'forgot_password' => array(
				'title'     => __( 'Forgot Password', 'vms-elements-form-guard' ),
				'shortcode' => '[vefg_forgot_password_form]',
				'option'    => 'forgot_page_id',
			),
			'reset_password' => array(
				'title'     => __( 'Reset Password', 'vms-elements-form-guard' ),
				'shortcode' => '[vefg_reset_password_form]',
				'option'    => 'reset_page_id',
			),
			'verify' => array(
				'title'     => __( 'Verify Email', 'vms-elements-form-guard' ),
				'shortcode' => '[vefg_verify_form]',
				'option'    => 'verify_page_id',
			),
		);

		$settings = self::get_settings();
		$created  = array();

		foreach ( $pages as $key => $page ) {
			// Skip if page already exists
			if ( ! empty( $settings[ $page['option'] ] ) && get_post( $settings[ $page['option'] ] ) ) {
				continue;
			}

			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_content' => $page['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'meta_input'   => array(
						'_vefg_auth_form_type' => $key,
					),
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$settings[ $page['option'] ] = $page_id;
				$created[] = $page['title'];
			}
		}

		update_option( self::OPTION_KEY, $settings );

		if ( empty( $created ) ) {
			wp_send_json_success( array( 'message' => __( 'All auth pages already exist.', 'vms-elements-form-guard' ) ) );
		}

		wp_send_json_success(
			array(
				/* translators: %s: comma-separated list of created page titles */
				'message' => sprintf( __( 'Created pages: %s', 'vms-elements-form-guard' ), implode( ', ', $created ) ),
				'settings' => $settings,
			)
		);
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'vms_elements_form_guard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'vms-elements-form-guard' ) ) );
		}

		$settings = array(
			'primary_color'      => sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '' ) ) ?: '#2563eb',
			'secondary_color'    => sanitize_hex_color( wp_unslash( $_POST['secondary_color'] ?? '' ) ) ?: '#1e40af',
			'text_color'         => sanitize_hex_color( wp_unslash( $_POST['text_color'] ?? '' ) ) ?: '#1f2937',
			'background_color'   => sanitize_hex_color( wp_unslash( $_POST['background_color'] ?? '' ) ) ?: '#ffffff',
			// Input field colors
			'border_color'       => sanitize_hex_color( wp_unslash( $_POST['border_color'] ?? '' ) ) ?: '#d1d5db',
			'border_hover_color' => sanitize_hex_color( wp_unslash( $_POST['border_hover_color'] ?? '' ) ) ?: '#9ca3af',
			'border_focus_color' => sanitize_hex_color( wp_unslash( $_POST['border_focus_color'] ?? '' ) ) ?: '#2563eb',
			'input_bg_color'     => sanitize_hex_color( wp_unslash( $_POST['input_bg_color'] ?? '' ) ) ?: '#ffffff',
			'input_focus_bg'     => sanitize_hex_color( wp_unslash( $_POST['input_focus_bg'] ?? '' ) ) ?: '#f9fafb',
			'error_color'        => sanitize_hex_color( wp_unslash( $_POST['error_color'] ?? '' ) ) ?: '#dc2626',
			'success_color'      => sanitize_hex_color( wp_unslash( $_POST['success_color'] ?? '' ) ) ?: '#16a34a',
			// Layout
			'border_radius'      => absint( $_POST['border_radius'] ?? 8 ),
			'form_width'         => absint( $_POST['form_width'] ?? 400 ),
			'show_labels'        => ! empty( $_POST['show_labels'] ),
			'show_placeholders'  => ! empty( $_POST['show_placeholders'] ),
			'button_style'       => sanitize_text_field( wp_unslash( $_POST['button_style'] ?? 'filled' ) ),
			'login_redirect'     => esc_url_raw( wp_unslash( $_POST['login_redirect'] ?? '' ) ),
			'register_redirect'  => esc_url_raw( wp_unslash( $_POST['register_redirect'] ?? '' ) ),
			'login_page_id'      => absint( $_POST['login_page_id'] ?? 0 ),
			'register_page_id'   => absint( $_POST['register_page_id'] ?? 0 ),
			'forgot_page_id'     => absint( $_POST['forgot_page_id'] ?? 0 ),
			'reset_page_id'      => absint( $_POST['reset_page_id'] ?? 0 ),
			'login_recaptcha'    => ! empty( $_POST['login_recaptcha'] ),
			'register_recaptcha' => ! empty( $_POST['register_recaptcha'] ),
			'register_check_dns' => ! empty( $_POST['register_check_dns'] ),
			'register_check_mx'  => ! empty( $_POST['register_check_mx'] ),
			'register_check_disposable' => ! empty( $_POST['register_check_disposable'] ),
			'register_webrisk'   => ! empty( $_POST['register_webrisk'] ),
			'register_virustotal' => ! empty( $_POST['register_virustotal'] ),
			// Email Verification (OTP + Activation Link combined)
			'enable_email_verification' => ! empty( $_POST['enable_otp_verification'] ),
			'otp_expires_minutes'       => absint( $_POST['otp_expires_minutes'] ?? 10 ),
			'link_expires_hours'        => absint( $_POST['link_expires_hours'] ?? 24 ),
			'verify_page_id'            => absint( $_POST['verify_page_id'] ?? 0 ),
		);

		update_option( self::OPTION_KEY, $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'vms-elements-form-guard' ) ) );
	}

	/**
	 * AJAX: Save SMTP settings.
	 */
	public function ajax_save_smtp_settings() {
		check_ajax_referer( 'vms_elements_form_guard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'vms-elements-form-guard' ) ) );
		}

		$settings = array(
			'enabled'    => ! empty( $_POST['smtp_enabled'] ),
			'host'       => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
			'port'       => absint( $_POST['smtp_port'] ?? 587 ),
			'encryption' => sanitize_text_field( wp_unslash( $_POST['smtp_encryption'] ?? 'tls' ) ),
			'auth'       => ! empty( $_POST['smtp_auth'] ),
			'username'   => sanitize_text_field( wp_unslash( $_POST['smtp_username'] ?? '' ) ),
			// SMTP password is stored verbatim for the SMTP client; sanitize_text_field would strip valid characters.
			'password'   => wp_unslash( $_POST['smtp_password'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Raw SMTP password.
			'from_email' => sanitize_email( wp_unslash( $_POST['smtp_from_email'] ?? '' ) ),
			'from_name'  => sanitize_text_field( wp_unslash( $_POST['smtp_from_name'] ?? '' ) ),
		);

		update_option( self::SMTP_OPTION_KEY, $settings );

		wp_send_json_success( array( 'message' => __( 'SMTP settings saved.', 'vms-elements-form-guard' ) ) );
	}

	/**
	 * AJAX: Test SMTP.
	 */
	public function ajax_test_smtp() {
		check_ajax_referer( 'vms_elements_form_guard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'vms-elements-form-guard' ) ) );
		}

		$to = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';

		if ( empty( $to ) || ! is_email( $to ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'vms-elements-form-guard' ) ) );
		}

		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] SMTP Test Email', 'vms-elements-form-guard' ), get_bloginfo( 'name' ) );
		$message = __( 'This is a test email to verify your SMTP configuration is working correctly.', 'vms-elements-form-guard' );

		$sent = wp_mail( $to, $subject, $message );

		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Test email sent successfully!', 'vms-elements-form-guard' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to send test email. Check your SMTP settings.', 'vms-elements-form-guard' ) ) );
		}
	}

	/**
	 * Configure PHPMailer SMTP.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_smtp( $phpmailer ) {
		$settings = self::get_smtp_settings();

		if ( empty( $settings['enabled'] ) || empty( $settings['host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		// PHPMailer uses upper-camel-case properties; cannot be renamed.
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = $settings['port'];
		$phpmailer->SMTPSecure = $settings['encryption'];
		$phpmailer->SMTPAuth   = $settings['auth'];

		if ( $settings['auth'] ) {
			$phpmailer->Username = $settings['username'];
			$phpmailer->Password = $settings['password'];
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Set mail from address.
	 *
	 * @param string $from From address.
	 * @return string
	 */
	public function set_mail_from( $from ) {
		$settings = self::get_smtp_settings();

		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['from_email'] ) ) {
			return $settings['from_email'];
		}

		return $from;
	}

	/**
	 * Set mail from name.
	 *
	 * @param string $name From name.
	 * @return string
	 */
	public function set_mail_from_name( $name ) {
		$settings = self::get_smtp_settings();

		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['from_name'] ) ) {
			return $settings['from_name'];
		}

		return $name;
	}

	/**
	 * Add meta box for form selection.
	 */
	public function add_form_meta_box() {
		add_meta_box(
			'vefg_auth_form_meta',
			__( 'VMS Elements Form Guard Auth Form', 'vms-elements-form-guard' ),
			array( $this, 'render_form_meta_box' ),
			'page',
			'side',
			'default'
		);
	}

	/**
	 * Render form meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_form_meta_box( $post ) {
		$current = get_post_meta( $post->ID, '_vefg_auth_form_type', true );
		wp_nonce_field( 'vefg_auth_form_meta', 'vefg_auth_form_nonce' );
		?>
		<p>
			<label for="vefg_auth_form_type"><?php esc_html_e( 'Select Auth Form', 'vms-elements-form-guard' ); ?></label>
			<select name="vefg_auth_form_type" id="vefg_auth_form_type" class="widefat">
				<option value=""><?php esc_html_e( '— None —', 'vms-elements-form-guard' ); ?></option>
				<?php foreach ( self::$form_types as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			<?php esc_html_e( 'If selected, the page content will display the chosen auth form with built-in validation.', 'vms-elements-form-guard' ); ?>
		</p>
		<p class="description" style="margin-top: 10px;">
			<strong><?php esc_html_e( 'Shortcodes:', 'vms-elements-form-guard' ); ?></strong><br>
			<code>[vefg_login_form]</code><br>
			<code>[vefg_register_form]</code><br>
			<code>[vefg_forgot_password_form]</code><br>
			<code>[vefg_reset_password_form]</code><br>
			<code>[vefg_otp_verify_form]</code><br>
			<code>[vefg_activation_form]</code>
		</p>
		<?php
	}

	/**
	 * Save form meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_form_meta( $post_id ) {
		if (
			! isset( $_POST['vefg_auth_form_nonce'] )
			|| ! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST['vefg_auth_form_nonce'] ) ),
				'vefg_auth_form_meta'
			)
		) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$form_type = isset( $_POST['vefg_auth_form_type'] ) ? sanitize_text_field( wp_unslash( $_POST['vefg_auth_form_type'] ) ) : '';

		if ( empty( $form_type ) ) {
			delete_post_meta( $post_id, '_vefg_auth_form_type' );
		} else {
			update_post_meta( $post_id, '_vefg_auth_form_type', $form_type );
		}
	}

	/**
	 * Whether a user has completed email verification.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_user_email_verified( int $user_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}

		if ( get_user_meta( $user_id, 'vefg_account_verified', true ) ) {
			return true;
		}

		if ( get_user_meta( $user_id, '_vefg_account_activated', true ) ) {
			return true;
		}

		if ( get_user_meta( $user_id, 'vefg_email_verified', true ) ) {
			return true;
		}

		if ( get_user_meta( $user_id, 'vefg_activation_key', true ) || get_user_meta( $user_id, 'vefg_otp_code', true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Persist OTP + activation link metadata for a pending user.
	 *
	 * @param int    $user_id         User ID.
	 * @param string $otp             OTP code.
	 * @param int    $otp_expires_min OTP lifetime in minutes.
	 * @param string $activation_key  Activation key.
	 * @param int    $link_expires_ts Activation link expiry timestamp.
	 */
	private static function store_pending_verification_meta( int $user_id, string $otp, int $otp_expires_min, string $activation_key, int $link_expires_ts ): void {
		update_user_meta( $user_id, 'vefg_account_verified', '0' );
		update_user_meta( $user_id, 'vefg_otp_code', $otp );
		update_user_meta( $user_id, 'vefg_otp_expiry', time() + ( $otp_expires_min * MINUTE_IN_SECONDS ) );
		update_user_meta( $user_id, 'vefg_activation_key', $activation_key );
		update_user_meta( $user_id, 'vefg_activation_expiry', $link_expires_ts );
	}

	/**
	 * Mark a pending user as verified and clear one-time verification meta.
	 *
	 * @param \WP_User $user User object.
	 */
	private static function complete_email_verification( \WP_User $user ): void {
		delete_user_meta( $user->ID, 'vefg_activation_key' );
		delete_user_meta( $user->ID, 'vefg_activation_expiry' );
		delete_user_meta( $user->ID, 'vefg_otp_code' );
		delete_user_meta( $user->ID, 'vefg_otp_expiry' );
		update_user_meta( $user->ID, 'vefg_account_verified', '1' );
		update_user_meta( $user->ID, 'vefg_email_verified', '1' );
		update_user_meta( $user->ID, '_vefg_account_activated', true );
		delete_transient( 'vefg_verify_' . md5( $user->user_email ) );
		delete_transient( 'vefg_otp_' . md5( $user->user_email ) );
	}

	/**
	 * Block wp-login.php and core auth for users pending email verification.
	 *
	 * @param \WP_User|\WP_Error|null $user     User or error.
	 * @param string                  $username Login name.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error|null
	 */
	public function block_unverified_user_login( $user, $username, $password ) {
		unset( $username, $password );

		if ( ! $user instanceof \WP_User ) {
			return $user;
		}

		$settings = self::get_settings();
		if ( empty( $settings['enable_email_verification'] ) ) {
			return $user;
		}

		if ( ! self::is_user_email_verified( $user->ID ) ) {
			return new \WP_Error(
				'vefg_email_not_verified',
				__( 'Your account is not verified yet. Please verify your email first, then log in.', 'vms-elements-form-guard' )
			);
		}

		return $user;
	}

	/**
	 * Generate OTP code.
	 *
	 * @param int $length OTP length.
	 * @return string
	 */
	public static function generate_otp( $length = 6 ) {
		$otp = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$otp .= wp_rand( 0, 9 );
		}
		return $otp;
	}

	/**
	 * Send OTP email.
	 *
	 * @param string $email User email.
	 * @param string $otp   OTP code.
	 * @param string $name  User name.
	 * @return bool
	 */
	public static function send_otp_email( $email, $otp, $name = '' ) {
		if ( ! function_exists( 'vefg_email_otp_verification' ) || ! function_exists( 'vefg_send_html_email' ) ) {
			return false;
		}

		$settings = self::get_settings();
		$expires  = (int) ( $settings['otp_expires_minutes'] ?? 10 );
		/* translators: %s: site name */
		$subject  = sprintf( __( '[%s] Your Verification Code', 'vms-elements-form-guard' ), get_bloginfo( 'name' ) );
		$body     = vefg_email_otp_verification( $otp, $name, $expires );

		return vefg_send_html_email( $email, $subject, $body );
	}

	/**
	 * AJAX: Verify OTP.
	 */
	public function ajax_handle_verify_otp() {
		check_ajax_referer( 'vefg_auth_verify_otp', 'vefg_auth_nonce' );

		$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$otp_code = isset( $_POST['otp_code'] ) ? sanitize_text_field( wp_unslash( $_POST['otp_code'] ) ) : '';

		if ( empty( $email ) || empty( $otp_code ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'vms-elements-form-guard' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'Verification code expired. Please request a new one.', 'vms-elements-form-guard' ) ) );
		}

		if ( self::is_user_email_verified( $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'This account is already verified. Please log in.', 'vms-elements-form-guard' ) ) );
		}

		$otp_expires_ts = (int) get_user_meta( $user->ID, 'vefg_otp_expiry', true );
		if ( $otp_expires_ts > 0 && time() > $otp_expires_ts ) {
			wp_send_json_error( array( 'message' => __( 'Verification code expired. Please request a new one or use the activation link.', 'vms-elements-form-guard' ) ) );
		}

		$stored_otp = (string) get_user_meta( $user->ID, 'vefg_otp_code', true );
		if ( $stored_otp !== $otp_code ) {
			wp_send_json_error( array( 'message' => __( 'Invalid verification code.', 'vms-elements-form-guard' ) ) );
		}

		self::complete_email_verification( $user );

		$settings  = self::get_settings();
		$login_url = $settings['login_page_id'] ? get_permalink( $settings['login_page_id'] ) : wp_login_url();

		if ( function_exists( 'vefg_email_welcome' ) && function_exists( 'vefg_send_html_email' ) ) {
			$body = vefg_email_welcome( $user->user_login, $login_url );
			vefg_send_html_email(
				$email,
				sprintf(
					/* translators: %s: site name */
					__( '[%s] Welcome!', 'vms-elements-form-guard' ),
					get_bloginfo( 'name' )
				),
				$body
			);
		}

		$redirect = $login_url;

		wp_send_json_success(
			array(
				'message'  => __( 'Email verified successfully! Redirecting to login...', 'vms-elements-form-guard' ),
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * AJAX: Resend OTP.
	 */
	public function ajax_handle_resend_otp() {
		// Reuse the verify-OTP nonce; the resend link lives inside the same
		// verify form and is rendered with that nonce.
		check_ajax_referer( 'vefg_auth_verify_otp', 'vefg_auth_nonce' );

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'vms-elements-form-guard' ) ) );
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user || self::is_user_email_verified( $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please register again.', 'vms-elements-form-guard' ) ) );
		}

		$settings    = self::get_settings();
		$otp_expires = (int) ( $settings['otp_expires_minutes'] ?? 10 );
		$new_otp     = self::generate_otp();

		update_user_meta( $user->ID, 'vefg_otp_code', $new_otp );
		update_user_meta( $user->ID, 'vefg_otp_expiry', time() + ( $otp_expires * MINUTE_IN_SECONDS ) );

		$sent = self::send_otp_email( $email, $new_otp, $user->user_login );

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send email. Please try again.', 'vms-elements-form-guard' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'A new verification code has been sent.', 'vms-elements-form-guard' ) ) );
	}

	/**
	 * AJAX: Handle activation.
	 */
	public function ajax_handle_activation() {
		// One-time activation link parameters; the `key` is validated against
		// `vefg_activation_key` user meta below, which is the equivalent of a
		// server-side nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated below.
		$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated below.
		$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';

		if ( empty( $key ) || empty( $login ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activation link.', 'vms-elements-form-guard' ) ) );
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			wp_send_json_error( array( 'message' => __( 'User not found.', 'vms-elements-form-guard' ) ) );
		}

		$stored_key = get_user_meta( $user->ID, 'vefg_activation_key', true );
		$expires    = (int) get_user_meta( $user->ID, 'vefg_activation_expiry', true );

		if ( $stored_key !== $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid activation key.', 'vms-elements-form-guard' ) ) );
		}

		if ( $expires > 0 && time() > $expires ) {
			wp_send_json_error( array( 'message' => __( 'Activation link has expired.', 'vms-elements-form-guard' ) ) );
		}

		self::complete_email_verification( $user );

		$settings  = self::get_settings();
		$login_url = $settings['login_page_id'] ? get_permalink( $settings['login_page_id'] ) : wp_login_url();

		wp_send_json_success(
			array(
				'message'  => __( 'Account activated! You can now log in.', 'vms-elements-form-guard' ),
				'redirect' => $login_url,
			)
		);
	}
}
