<?php
/**
 * AI VMS Elements Form Guard -- provider choice (OpenAI, Anthropic, Gemini, DeepSeek).
 *
 * Variables below are received from the including admin handler scope; the
 * `vefg_mask_api_key` helper uses the legacy plugin prefix kept for BC.
 *
 * @package VMS_Elements_Form_Guard
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 */

use VMS_Elements_Form_Guard\AI_Span_Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cfg = AI_Span_Config::get();

$allowed_providers = array( 'openai', 'anthropic', 'gemini', 'deepseek' );

// phpcs:disable WordPress.Security.NonceVerification.Missing
if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	if ( ! isset( $_POST['vefg_ai_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['vefg_ai_settings_nonce'] ) ), 'vefg_ai_settings_save' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'vms-elements-form-guard' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to save these settings.', 'vms-elements-form-guard' ) );
	}

	$p = isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '';
	$incoming          = array(
		'ai_enabled'         => ! empty( $_POST['ai_enabled'] ),
		'provider'           => in_array( $p, $allowed_providers, true ) ? $p : (string) ( $cfg['provider'] ?? 'openai' ),
		'openai_model'       => isset( $_POST['openai_model'] ) ? sanitize_text_field( wp_unslash( $_POST['openai_model'] ) ) : '',
		'anthropic_model'    => isset( $_POST['anthropic_model'] ) ? sanitize_text_field( wp_unslash( $_POST['anthropic_model'] ) ) : '',
		'gemini_model'       => isset( $_POST['gemini_model'] ) ? sanitize_text_field( wp_unslash( $_POST['gemini_model'] ) ) : '',
		'deepseek_model'     => isset( $_POST['deepseek_model'] ) ? sanitize_text_field( wp_unslash( $_POST['deepseek_model'] ) ) : '',
		'summary_post_types' => isset( $_POST['summary_post_types'] ) && is_array( $_POST['summary_post_types'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['summary_post_types'] ) ) : array(),
	);

	$secret_fields = array(
		'openai_api_key',
		'anthropic_api_key',
		'gemini_api_key',
		'deepseek_api_key',
	);
	foreach ( $secret_fields as $field ) {
		$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		if ( $raw === '' ) {
			$incoming[ $field ] = (string) ( $cfg[ $field ] ?? '' );
		} else {
			$incoming[ $field ] = $raw;
		}
	}

	AI_Span_Config::update( $incoming );
	$cfg = AI_Span_Config::get();
	echo '<div class="updated"><p>' . esc_html__( 'AI settings saved.', 'vms-elements-form-guard' ) . '</p></div>';
}
// phpcs:enable WordPress.Security.NonceVerification.Missing

$post_types = vms_elements_form_guard_summary_selectable_post_types();

$current_provider = in_array( (string) ( $cfg['provider'] ?? '' ), $allowed_providers, true )
	? (string) $cfg['provider']
	: 'openai';
?>

<div class="wrap vefg-admin">
	<?php
	vms_elements_form_guard_admin_page_header(
		__( 'AI Span Settings', 'vms-elements-form-guard' ),
		__( 'Choose one provider for summaries and comment moderation. Credentials for other providers are kept if you switch - only the active provider is used.', 'vms-elements-form-guard' )
	);
	?>

	<div class="vefg-card">
		<form method="post">
			<?php wp_nonce_field( 'vefg_ai_settings_save', 'vefg_ai_settings_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable AI VMS Elements Form Guard', 'vms-elements-form-guard' ); ?></th>
					<td>
						<?php
						vms_elements_form_guard_admin_switch(
							array(
								'name'        => 'ai_enabled',
								'checked'     => ! empty( $cfg['ai_enabled'] ),
								'description' => __( 'Generate post summaries and run AI comment checks when a summary exists.', 'vms-elements-form-guard' ),
							)
						);
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="vefg_ai_provider"><?php esc_html_e( 'AI provider', 'vms-elements-form-guard' ); ?></label></th>
					<td>
						<select name="provider" id="vefg_ai_provider">
							<option value="openai" <?php selected( $current_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'vms-elements-form-guard' ); ?></option>
							<option value="anthropic" <?php selected( $current_provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (API)', 'vms-elements-form-guard' ); ?></option>
							<option value="gemini" <?php selected( $current_provider, 'gemini' ); ?>><?php esc_html_e( 'Google Gemini', 'vms-elements-form-guard' ); ?></option>
							<option value="deepseek" <?php selected( $current_provider, 'deepseek' ); ?>><?php esc_html_e( 'DeepSeek', 'vms-elements-form-guard' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<div class="vefg-ai-provider-panel" data-vefg-provider="openai">
				<h2 class="title"><?php esc_html_e( 'OpenAI', 'vms-elements-form-guard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Uses Chat Completions with JSON object mode. Create a key in the OpenAI dashboard.', 'vms-elements-form-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="openai_api_key"><?php esc_html_e( 'API key', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<div class="vefg-api-key-field">
								<input type="password" name="openai_api_key" id="openai_api_key" value="" class="regular-text vefg-api-key-input" autocomplete="off" placeholder="<?php echo esc_attr( vms_elements_form_guard_mask_api_key( $cfg['openai_api_key'] ?? '' ) ?: __( 'Enter API key', 'vms-elements-form-guard' ) ); ?>" data-has-key="<?php echo ! empty( $cfg['openai_api_key'] ) ? '1' : '0'; ?>">
								<button type="button" class="button vefg-toggle-key" data-target="openai_api_key">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</div>
							<?php if ( ! empty( $cfg['openai_api_key'] ) ) : ?>
								<p class="description" style="color: #2e7d32;"><span class="dashicons dashicons-yes-alt" style="font-size: 14px;"></span> <?php esc_html_e( 'API key configured', 'vms-elements-form-guard' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="openai_model"><?php esc_html_e( 'Model', 'vms-elements-form-guard' ); ?></label></th>
						<td><input type="text" name="openai_model" id="openai_model" value="<?php echo esc_attr( (string) ( $cfg['openai_model'] ?? '' ) ); ?>" class="regular-text" placeholder="gpt-4o-mini"></td>
					</tr>
				</table>
			</div>

			<div class="vefg-ai-provider-panel" data-vefg-provider="anthropic">
				<h2 class="title"><?php esc_html_e( 'Anthropic', 'vms-elements-form-guard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Direct Anthropic Messages API.', 'vms-elements-form-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="anthropic_api_key"><?php esc_html_e( 'API key', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<div class="vefg-api-key-field">
								<input type="password" name="anthropic_api_key" id="anthropic_api_key" value="" class="regular-text vefg-api-key-input" autocomplete="off" placeholder="<?php echo esc_attr( vms_elements_form_guard_mask_api_key( $cfg['anthropic_api_key'] ?? '' ) ?: __( 'Enter API key', 'vms-elements-form-guard' ) ); ?>" data-has-key="<?php echo ! empty( $cfg['anthropic_api_key'] ) ? '1' : '0'; ?>">
								<button type="button" class="button vefg-toggle-key" data-target="anthropic_api_key">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</div>
							<?php if ( ! empty( $cfg['anthropic_api_key'] ) ) : ?>
								<p class="description" style="color: #2e7d32;"><span class="dashicons dashicons-yes-alt" style="font-size: 14px;"></span> <?php esc_html_e( 'API key configured', 'vms-elements-form-guard' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="anthropic_model"><?php esc_html_e( 'Model', 'vms-elements-form-guard' ); ?></label></th>
						<td><input type="text" name="anthropic_model" id="anthropic_model" value="<?php echo esc_attr( (string) ( $cfg['anthropic_model'] ?? '' ) ); ?>" class="regular-text" placeholder="claude-3-5-haiku-latest"></td>
					</tr>
				</table>
			</div>

			<div class="vefg-ai-provider-panel" data-vefg-provider="gemini">
				<h2 class="title"><?php esc_html_e( 'Google Gemini', 'vms-elements-form-guard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Uses the Generative Language API. Get your key from Google AI Studio.', 'vms-elements-form-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="gemini_api_key"><?php esc_html_e( 'API key', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<div class="vefg-api-key-field">
								<input type="password" name="gemini_api_key" id="gemini_api_key" value="" class="regular-text vefg-api-key-input" autocomplete="off" placeholder="<?php echo esc_attr( vms_elements_form_guard_mask_api_key( $cfg['gemini_api_key'] ?? '' ) ?: __( 'Enter API key', 'vms-elements-form-guard' ) ); ?>" data-has-key="<?php echo ! empty( $cfg['gemini_api_key'] ) ? '1' : '0'; ?>">
								<button type="button" class="button vefg-toggle-key" data-target="gemini_api_key">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</div>
							<?php if ( ! empty( $cfg['gemini_api_key'] ) ) : ?>
								<p class="description" style="color: #2e7d32;"><span class="dashicons dashicons-yes-alt" style="font-size: 14px;"></span> <?php esc_html_e( 'API key configured', 'vms-elements-form-guard' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_model"><?php esc_html_e( 'Model', 'vms-elements-form-guard' ); ?></label></th>
						<td><input type="text" name="gemini_model" id="gemini_model" value="<?php echo esc_attr( (string) ( $cfg['gemini_model'] ?? '' ) ); ?>" class="regular-text" placeholder="gemini-2.0-flash-lite"></td>
					</tr>
				</table>
			</div>

			<div class="vefg-ai-provider-panel" data-vefg-provider="deepseek">
				<h2 class="title"><?php esc_html_e( 'DeepSeek', 'vms-elements-form-guard' ); ?></h2>
				<p class="description"><?php esc_html_e( 'OpenAI-compatible Chat Completions at api.deepseek.com.', 'vms-elements-form-guard' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="deepseek_api_key"><?php esc_html_e( 'API key', 'vms-elements-form-guard' ); ?></label></th>
						<td>
							<div class="vefg-api-key-field">
								<input type="password" name="deepseek_api_key" id="deepseek_api_key" value="" class="regular-text vefg-api-key-input" autocomplete="off" placeholder="<?php echo esc_attr( vms_elements_form_guard_mask_api_key( $cfg['deepseek_api_key'] ?? '' ) ?: __( 'Enter API key', 'vms-elements-form-guard' ) ); ?>" data-has-key="<?php echo ! empty( $cfg['deepseek_api_key'] ) ? '1' : '0'; ?>">
								<button type="button" class="button vefg-toggle-key" data-target="deepseek_api_key">
									<span class="dashicons dashicons-visibility"></span>
								</button>
							</div>
							<?php if ( ! empty( $cfg['deepseek_api_key'] ) ) : ?>
								<p class="description" style="color: #2e7d32;"><span class="dashicons dashicons-yes-alt" style="font-size: 14px;"></span> <?php esc_html_e( 'API key configured', 'vms-elements-form-guard' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="deepseek_model"><?php esc_html_e( 'Model', 'vms-elements-form-guard' ); ?></label></th>
						<td><input type="text" name="deepseek_model" id="deepseek_model" value="<?php echo esc_attr( (string) ( $cfg['deepseek_model'] ?? '' ) ); ?>" class="regular-text" placeholder="deepseek-chat"></td>
					</tr>
				</table>
			</div>

			<h2 class="title"><?php esc_html_e( 'Summaries', 'vms-elements-form-guard' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'When an item of a selected type is published or scheduled, the plugin requests a summary using the active provider above. Include "product" for WooCommerce - those summaries use catalog fields (descriptions, categories, attributes) and appear under', 'vms-elements-form-guard' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=vms-elements-form-guard-ai-product-summaries' ) ); ?>"><?php esc_html_e( 'AI Product Summaries', 'vms-elements-form-guard' ); ?></a>.
			</p>
			<div class="vefg-post-type-grid" role="group" aria-label="<?php esc_attr_e( 'Post types for summaries', 'vms-elements-form-guard' ); ?>">
				<?php
				$selected = $cfg['summary_post_types'] ?? array( 'post' );
				foreach ( $post_types as $pt ) {
					if ( ! ( $pt instanceof \WP_Post_Type ) ) {
						continue;
					}
					$vefg_pt_id = 'vefg-pt-' . $pt->name;
					vms_elements_form_guard_admin_switch(
						array(
							'name'        => 'summary_post_types[]',
							'id'          => $vefg_pt_id,
							'value'       => $pt->name,
							'checked'     => in_array( $pt->name, $selected, true ),
							'label'       => (string) ( $pt->labels->singular_name ?? $pt->name ),
							'compact'     => true,
						)
					);
				}
				?>
			</div>

			<p class="submit">
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save settings', 'vms-elements-form-guard' ); ?>">
			</p>
		</form>
	</div>
</div>
