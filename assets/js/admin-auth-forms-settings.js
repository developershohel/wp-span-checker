/**
 * Auth Form Templates admin settings screen.
 */
(function ($) {
	'use strict';

	var i18n = (typeof window.VEFGAuthFormsAdmin !== 'undefined' && window.VEFGAuthFormsAdmin.i18n) || {};

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	$('.vefg-auth-tab').on('click', function () {
		var tab = $(this).data('tab');
		$('.vefg-auth-tab').removeClass('active');
		$(this).addClass('active');
		$('.vefg-auth-panel').removeClass('active');
		$('#panel-' + tab).addClass('active');
	});

	$('.vefg-copy-btn').on('click', function () {
		var text = $(this).data('copy');
		navigator.clipboard.writeText(text).then(function () {
			Swal.fire({ icon: 'success', title: t('copied', 'Copied!'), timer: 1000, showConfirmButton: false });
		});
	});

	$('.vefg-range-input').on('input', function () {
		$(this).siblings('.vefg-range-value').text($(this).val() + 'px');
		updatePreview();
	});

	$('.vefg-color-picker').on('input', updatePreview);
	$('input[name="show_labels"], input[name="show_placeholders"]').on('change', updatePreview);
	$('#button_style').on('change', updatePreview);
	$('#preview-form-type').on('change', updatePreview);

	$('#vefg-generate-pages').on('click', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text(t('generating', 'Generating...'));

		$.post(
			ajaxurl,
			{
				action: 'vefg_generate_auth_pages',
				nonce: VEFGChecker.nonce,
			},
			function (response) {
				$btn.prop('disabled', false).html(
					'<span class="dashicons dashicons-admin-page" style="margin-top: 4px;"></span> ' + t('generatePages', 'Auto-Generate All Auth Pages')
				);
				if (response.success) {
					Swal.fire({ icon: 'success', title: t('success', 'Success'), text: response.data.message }).then(function () {
						location.reload();
					});
				} else {
					Swal.fire({ icon: 'error', title: t('error', 'Error'), text: response.data.message });
				}
			}
		);
	});

	$('#vefg-save-auth-settings').on('click', function () {
		var $btn = $(this);
		var $status = $('.vefg-save-status');
		$btn.prop('disabled', true);
		$status.text(t('saving', 'Saving...'));

		var formData = {
			action: 'vefg_save_auth_form_settings',
			nonce: VEFGChecker.nonce,
			primary_color: $('#primary_color').val(),
			secondary_color: $('#secondary_color').val(),
			text_color: $('#text_color').val(),
			background_color: $('#background_color').val(),
			border_color: $('#border_color').val(),
			border_hover_color: $('#border_hover_color').val(),
			border_focus_color: $('#border_focus_color').val(),
			input_bg_color: $('#input_bg_color').val(),
			input_focus_bg: $('#input_focus_bg').val(),
			error_color: $('#error_color').val(),
			success_color: $('#success_color').val(),
			border_radius: $('#border_radius').val(),
			form_width: $('#form_width').val(),
			show_labels: $('input[name="show_labels"]').is(':checked') ? 1 : 0,
			show_placeholders: $('input[name="show_placeholders"]').is(':checked') ? 1 : 0,
			button_style: $('#button_style').val(),
			login_redirect: $('#login_redirect').val(),
			register_redirect: $('#register_redirect').val(),
			login_page_id: $('select[data-setting="login_page_id"]').val(),
			register_page_id: $('select[data-setting="register_page_id"]').val(),
			forgot_page_id: $('select[data-setting="forgot_page_id"]').val(),
			reset_page_id: $('select[data-setting="reset_page_id"]').val(),
			login_recaptcha: $('input[name="login_recaptcha"]').is(':checked') ? 1 : 0,
			register_recaptcha: $('input[name="register_recaptcha"]').is(':checked') ? 1 : 0,
			register_check_dns: $('input[name="register_check_dns"]').is(':checked') ? 1 : 0,
			register_check_mx: $('input[name="register_check_mx"]').is(':checked') ? 1 : 0,
			register_check_disposable: $('input[name="register_check_disposable"]').is(':checked') ? 1 : 0,
			register_webrisk: $('input[name="register_webrisk"]').is(':checked') ? 1 : 0,
			register_virustotal: $('input[name="register_virustotal"]').is(':checked') ? 1 : 0,
			enable_otp_verification: $('input[name="enable_otp_verification"]').is(':checked') ? 1 : 0,
			otp_expires_minutes: $('#otp_expires_minutes').val(),
			link_expires_hours: $('#link_expires_hours').val(),
			verify_page_id: $('select[name="verify_page_id"]').val() || $('select[data-setting="verify_page_id"]').val(),
		};

		$.post(ajaxurl, formData, function () {
			var smtpData = {
				action: 'vefg_save_smtp_settings',
				nonce: VEFGChecker.nonce,
				smtp_enabled: $('input[name="smtp_enabled"]').is(':checked') ? 1 : 0,
				smtp_host: $('#smtp_host').val(),
				smtp_port: $('#smtp_port').val(),
				smtp_encryption: $('#smtp_encryption').val(),
				smtp_auth: $('input[name="smtp_auth"]').is(':checked') ? 1 : 0,
				smtp_username: $('#smtp_username').val(),
				smtp_password: $('#smtp_password').val(),
				smtp_from_email: $('#smtp_from_email').val(),
				smtp_from_name: $('#smtp_from_name').val(),
			};

			$.post(ajaxurl, smtpData, function () {
				$btn.prop('disabled', false);
				$status.text(t('saved', 'Saved!'));
				setTimeout(function () {
					$status.text('');
				}, 3000);
			});
		});
	});

	$('#vefg-test-smtp').on('click', function () {
		var email = $('#smtp_test_email').val();
		var $result = $('#smtp-test-result');

		if (!email) {
			$result.html('<span style="color: #d63638;">' + t('enterEmail', 'Please enter an email address.') + '</span>');
			return;
		}

		$result.text(t('sending', 'Sending...'));

		$.post(
			ajaxurl,
			{
				action: 'vefg_test_smtp',
				nonce: VEFGChecker.nonce,
				test_email: email,
			},
			function (response) {
				if (response.success) {
					$result.html('<span style="color: #00a32a;">' + response.data.message + '</span>');
				} else {
					$result.html('<span style="color: #d63638;">' + response.data.message + '</span>');
				}
			}
		);
	});

	function hexToRgba(hex, alpha) {
		var r = parseInt(hex.slice(1, 3), 16);
		var g = parseInt(hex.slice(3, 5), 16);
		var b = parseInt(hex.slice(5, 7), 16);
		return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
	}

	function generatePreviewHTML(type, s) {
		var labelDisplay = s.showLabels ? 'block' : 'none';
		var placeholderAttr = s.showPlaceholders ? 'placeholder="' + t('typeHere', 'Type here...') + '"' : '';

		var buttonStyle = '';
		if (s.buttonStyle === 'filled') {
			buttonStyle = 'background:' + s.primaryColor + ';color:#fff;border:none;';
		} else if (s.buttonStyle === 'outline') {
			buttonStyle = 'background:transparent;color:' + s.primaryColor + ';border:2px solid ' + s.primaryColor + ';';
		} else if (s.buttonStyle === 'gradient') {
			buttonStyle = 'background:linear-gradient(135deg,' + s.primaryColor + ',' + s.secondaryColor + ');color:#fff;border:none;';
		}

		var formStyle =
			'max-width:' +
			s.formWidth +
			';background:' +
			s.backgroundColor +
			';padding:30px;border-radius:' +
			s.borderRadius +
			';box-shadow:0 4px 6px rgba(0,0,0,0.1);margin:0 auto;';
		var inputStyle =
			'width:100%;padding:12px 14px;border:1px solid ' +
			s.borderColor +
			';border-radius:' +
			s.borderRadius +
			';box-sizing:border-box;background:' +
			s.inputBgColor +
			';color:' +
			s.textColor +
			';font-size:15px;transition:all 0.2s;outline:none;';
		var labelStyle =
			'display:' + labelDisplay + ';margin-bottom:6px;color:' + s.textColor + ';font-weight:500;font-size:14px;transition:color 0.2s;';
		var titleStyle = 'color:' + s.textColor + ';margin:0 0 24px 0;text-align:center;font-size:22px;';
		var fieldStyle = 'margin-bottom:18px;';
		var btnStyle = buttonStyle + 'width:100%;padding:14px;border-radius:' + s.borderRadius + ';cursor:pointer;font-size:16px;font-weight:500;margin-top:8px;';

		var title = t('login', 'Login');
		var fields = '';
		var btnText = t('login', 'Login');

		if (type === 'login') {
			fields =
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('usernameOrEmail', 'Username or Email') +
				'</label><input type="text" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>' +
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('password', 'Password') +
				'</label><input type="password" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>';
		} else if (type === 'register') {
			title = t('createAccount', 'Create Account');
			btnText = t('createAccount', 'Create Account');
			fields =
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('username', 'Username') +
				'</label><input type="text" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>' +
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('email', 'Email') +
				'</label><input type="email" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>' +
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('password', 'Password') +
				'</label><input type="password" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>' +
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('confirmPassword', 'Confirm Password') +
				'</label><input type="password" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>';
		} else if (type === 'forgot') {
			title = t('resetPassword', 'Reset Password');
			btnText = t('sendResetLink', 'Send Reset Link');
			fields =
				'<p style="color:' +
				s.textColor +
				';text-align:center;margin-bottom:20px;font-size:14px;">' +
				t('resetHint', 'Enter your email to receive a reset link.') +
				'</p>' +
				'<div class="vefg-preview-field" style="' +
				fieldStyle +
				'"><label style="' +
				labelStyle +
				'">' +
				t('email', 'Email') +
				'</label><input type="email" class="vefg-preview-input" style="' +
				inputStyle +
				'" ' +
				placeholderAttr +
				'></div>';
		}

		var demoButtons =
			'<div style="margin-top:16px;text-align:center;font-size:12px;">' +
			'<span style="color:#6b7280;">' +
			t('testStates', 'Test states:') +
			'</span> ' +
			'<a href="#" class="vefg-preview-demo-error" style="color:' +
			s.errorColor +
			';margin:0 8px;">' +
			t('errorState', 'Error') +
			'</a>' +
			'<a href="#" class="vefg-preview-demo-success" style="color:' +
			s.successColor +
			';">' +
			t('successState', 'Success') +
			'</a>' +
			'</div>';

		return (
			'<div style="' +
			formStyle +
			'">' +
			'<h2 style="' +
			titleStyle +
			'">' +
			title +
			'</h2>' +
			fields +
			'<button type="button" style="' +
			btnStyle +
			'">' +
			btnText +
			'</button>' +
			demoButtons +
			'</div>'
		);
	}

	function addPreviewInteractions(s) {
		var $container = $('#vefg-preview-container');

		$container.find('.vefg-preview-input').each(function () {
			var $input = $(this);

			$input
				.on('mouseenter', function () {
					if (!$(this).is(':focus')) {
						$(this).css('border-color', s.borderHoverColor);
					}
				})
				.on('mouseleave', function () {
					if (!$(this).is(':focus')) {
						$(this).css('border-color', s.borderColor);
					}
				})
				.on('focus', function () {
					$(this).css({
						'border-color': s.borderFocusColor,
						'background-color': s.inputFocusBg,
						'box-shadow': '0 0 0 3px ' + hexToRgba(s.borderFocusColor, 0.1),
					});
					$(this).closest('.vefg-preview-field').find('label').css('color', s.primaryColor);
				})
				.on('blur', function () {
					$(this).css({
						'border-color': s.borderColor,
						'background-color': s.inputBgColor,
						'box-shadow': 'none',
					});
					$(this).closest('.vefg-preview-field').find('label').css('color', s.textColor);
				});
		});

		$container.find('.vefg-preview-demo-error').on('click', function (e) {
			e.preventDefault();
			var $field = $container.find('.vefg-preview-field').first().find('.vefg-preview-input');
			$field.css({ 'border-color': s.errorColor, 'background-color': '#fef2f2' });
			setTimeout(function () {
				$field.css({ 'border-color': s.borderColor, 'background-color': s.inputBgColor });
			}, 2000);
		});

		$container.find('.vefg-preview-demo-success').on('click', function (e) {
			e.preventDefault();
			var $field = $container.find('.vefg-preview-field').eq(1).find('.vefg-preview-input');
			$field.css({ 'border-color': s.successColor, 'background-color': '#f0fdf4' });
			setTimeout(function () {
				$field.css({ 'border-color': s.borderColor, 'background-color': s.inputBgColor });
			}, 2000);
		});
	}

	function updatePreview() {
		var formType = $('#preview-form-type').val();
		var settings = {
			primaryColor: $('#primary_color').val(),
			secondaryColor: $('#secondary_color').val(),
			textColor: $('#text_color').val(),
			backgroundColor: $('#background_color').val(),
			borderColor: $('#border_color').val() || '#d1d5db',
			borderHoverColor: $('#border_hover_color').val() || '#9ca3af',
			borderFocusColor: $('#border_focus_color').val() || '#2563eb',
			inputBgColor: $('#input_bg_color').val() || '#ffffff',
			inputFocusBg: $('#input_focus_bg').val() || '#f9fafb',
			errorColor: $('#error_color').val() || '#dc2626',
			successColor: $('#success_color').val() || '#16a34a',
			borderRadius: $('#border_radius').val() + 'px',
			formWidth: $('#form_width').val() + 'px',
			showLabels: $('input[name="show_labels"]').is(':checked'),
			showPlaceholders: $('input[name="show_placeholders"]').is(':checked'),
			buttonStyle: $('#button_style').val(),
		};

		$('#vefg-preview-container').html(generatePreviewHTML(formType, settings));
		addPreviewInteractions(settings);
	}

	updatePreview();
})(jQuery);
