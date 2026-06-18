(function ($) {
	'use strict';
	if (typeof window.VEFGUsers === 'undefined') {
		return;
	}
	var cfg = window.VEFGUsers;

	function toast(opts) {
		if (typeof Swal !== 'undefined') {
			Swal.fire(
				Object.assign(
					{
						toast: true,
						position: 'top-end',
						showConfirmButton: false,
						timer: 2200,
						timerProgressBar: true,
					},
					opts
				)
			);
		}
	}

	function openBlockDialog(opts) {
		var isEdit = opts.mode === 'edit';
		var title = isEdit ? cfg.i18n.edit_title : cfg.i18n.block_title;
		var checked = { form: !!opts.form, login: !!opts.login, site: !!opts.site };
		if (!isEdit) {
			checked.form = true;
		}

		var html =
			'<div style="text-align:left;">' +
			'<p style="margin:0 0 12px;"><strong>' +
			opts.label +
			'</strong></p>' +
			'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-block-form" ' +
			(checked.form ? 'checked' : '') +
			'> <strong>' +
			cfg.i18n.scope_form +
			'</strong></label>' +
			'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-block-login" ' +
			(checked.login ? 'checked' : '') +
			'> <strong>' +
			cfg.i18n.scope_login +
			'</strong></label>' +
			'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-block-site" ' +
			(checked.site ? 'checked' : '') +
			'> <strong>' +
			cfg.i18n.scope_site +
			'</strong></label>';

		if (!isEdit) {
			html +=
				'<label style="display:block;margin:12px 0 6px;font-weight:600;">' +
				cfg.i18n.reason +
				'</label>' +
				'<textarea id="vefg-block-reason" class="swal2-textarea" style="margin:0;width:100%;min-height:64px;"></textarea>' +
				'<label style="display:block;margin:12px 0 6px;font-weight:600;">' +
				cfg.i18n.expiry +
				'</label>' +
				'<input type="number" id="vefg-block-expiry" class="swal2-input" style="margin:0;width:100%;" value="0" min="0" step="1">';
		}

		html += '</div>';

		Swal.fire({
			title: title,
			html: html,
			showCancelButton: true,
			confirmButtonText: isEdit ? cfg.i18n.confirm_save : cfg.i18n.confirm_block,
			cancelButtonText: cfg.i18n.cancel,
			focusConfirm: false,
			preConfirm: function () {
				var scope = [];
				if (document.getElementById('vefg-block-form').checked) {
					scope.push('form');
				}
				if (document.getElementById('vefg-block-login').checked) {
					scope.push('login');
				}
				if (document.getElementById('vefg-block-site').checked) {
					scope.push('site');
				}
				if (scope.length === 0) {
					Swal.showValidationMessage(cfg.i18n.pick_scope);
					return false;
				}
				var payload = { scope: scope };
				if (!isEdit) {
					var r = document.getElementById('vefg-block-reason');
					var e = document.getElementById('vefg-block-expiry');
					payload.reason = r ? r.value : '';
					payload.expiry_days = e ? parseInt(e.value, 10) || 0 : 0;
				}
				return payload;
			},
		}).then(function (result) {
			if (!result.isConfirmed || !result.value) {
				return;
			}
			var data = { nonce: cfg.nonce };
			if (isEdit) {
				data.action = 'vefg_edit_block_scope';
				data.actor_key = 'u:' + opts.userId;
				data.scope = result.value.scope;
			} else {
				data.action = 'vefg_manual_block_user';
				data.user_id = opts.userId;
				data.scope = result.value.scope;
				data.reason = result.value.reason;
				data.expiry_days = result.value.expiry_days;
			}
			$.post(cfg.ajaxurl, data)
				.done(function (res) {
					if (res && res.success) {
						toast({ icon: 'success', title: (res.data && res.data.message) || cfg.i18n.success });
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						toast({
							icon: 'error',
							title: (res && res.data && res.data.message) || cfg.i18n.request_failed,
						});
					}
				})
				.fail(function () {
					toast({ icon: 'error', title: cfg.i18n.request_failed });
				});
		});
	}

	function openUnblockDialog(opts) {
		Swal.fire({
			title: cfg.i18n.unblock_title,
			html: '<p>' + cfg.i18n.unblock_confirm + ' <strong>' + opts.label + '</strong>.</p>',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: cfg.i18n.confirm_unblock,
			cancelButtonText: cfg.i18n.cancel,
			confirmButtonColor: '#d33',
		}).then(function (result) {
			if (!result.isConfirmed) {
				return;
			}
			$.post(cfg.ajaxurl, {
				action: 'vefg_unblock_user',
				nonce: cfg.nonce,
				user_id: opts.userId,
			})
				.done(function (res) {
					if (res && res.success) {
						toast({ icon: 'success', title: (res.data && res.data.message) || cfg.i18n.success });
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						toast({
							icon: 'error',
							title: (res && res.data && res.data.message) || cfg.i18n.request_failed,
						});
					}
				})
				.fail(function () {
					toast({ icon: 'error', title: cfg.i18n.request_failed });
				});
		});
	}

	$(document).on('click', '.vms-elements-form-guard-user-block-trigger', function (e) {
		e.preventDefault();
		var $a = $(this);
		openBlockDialog({
			userId: parseInt($a.data('user-id'), 10) || 0,
			label: String($a.data('user-label') || ''),
			mode: String($a.data('mode') || 'block'),
			form: String($a.data('form')) === '1',
			login: String($a.data('login')) === '1',
			site: String($a.data('site')) === '1',
		});
	});

	$(document).on('click', '.vms-elements-form-guard-user-unblock-trigger', function (e) {
		e.preventDefault();
		var $a = $(this);
		openUnblockDialog({
			userId: parseInt($a.data('user-id'), 10) || 0,
			label: String($a.data('user-label') || ''),
		});
	});
})(jQuery);
