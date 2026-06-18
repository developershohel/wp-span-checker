/**
 * Blocked Users admin — manual block tab and scope editor.
 */
(function ($) {
	'use strict';

	if (typeof window.VEFGChecker === 'undefined') {
		return;
	}

	var ajaxurl = window.VEFGChecker.ajaxurl;
	var nonce = window.VEFGChecker.nonce;
	var i18n = (typeof window.VEFGCommentBlocks !== 'undefined' && window.VEFGCommentBlocks.i18n) || {};

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	function toast(opts) {
		if (typeof Swal !== 'undefined') {
			Swal.fire(
				Object.assign(
					{
						toast: true,
						position: 'top-end',
						showConfirmButton: false,
						timer: 2500,
						timerProgressBar: true,
					},
					opts
				)
			);
		} else if (opts && opts.title) {
			alert(opts.title);
		}
	}

	var $form = $('#vefg-manual-block-form');
	var $input = $('#vefg-manual-block-input');
	var $lookupBtn = $('#vefg-manual-block-lookup');
	var $status = $('#vefg-manual-block-lookup-status');
	var $preview = $('#vefg-manual-block-preview');
	var $avatar = $('#vefg-manual-block-avatar');
	var $name = $('#vefg-manual-block-name');
	var $meta = $('#vefg-manual-block-meta');
	var $blockStatus = $('#vefg-manual-block-status');
	var $editLink = $('#vefg-manual-block-edit-link');
	var $submit = $('#vefg-manual-block-submit');
	var $helper = $('#vefg-manual-block-helper');
	var matchedUserId = 0;
	var lookupTimer = null;

	function resetPreview() {
		matchedUserId = 0;
		$preview.hide();
		$submit.prop('disabled', true);
		$helper.text(t('helperLookup', 'Look up a user first; the block button activates once a valid user is matched.'));
	}

	function runLookup() {
		var q = ($input.val() || '').toString().trim();
		if (q === '') {
			$status.text(t('enterQuery', 'Enter an ID, username, or email.'));
			resetPreview();
			return;
		}
		$status.text(t('lookingUp', 'Looking up…'));

		$.post(ajaxurl, {
			action: 'vefg_lookup_user',
			nonce: nonce,
			query: q,
		})
			.done(function (res) {
				if (!res || !res.success) {
					resetPreview();
					$status.text((res && res.data && res.data.message) || t('noMatch', 'No user matches that input.'));
					return;
				}
				var d = res.data;
				matchedUserId = parseInt(d.user.id, 10) || 0;
				$avatar.attr('src', d.user.avatar || '');
				$name.text(d.user.display_name + ' (' + d.user.login + ')');
				$meta.text(
					t('idLabel', 'ID') +
						': ' +
						d.user.id +
						'  •  ' +
						d.user.email +
						'  •  ' +
						(d.user.roles && d.user.roles.length ? d.user.roles.join(', ') : t('noRole', 'no role'))
				);
				if (d.user.edit_url) {
					$editLink.attr('href', d.user.edit_url).show();
				} else {
					$editLink.hide();
				}
				if (d.block && d.block.is_blocked) {
					var pills = [];
					if (d.block.form) {
						pills.push(t('scopeForm', 'Form'));
					}
					if (d.block.login) {
						pills.push(t('scopeLogin', 'Login'));
					}
					if (d.block.site) {
						pills.push(t('scopeSite', 'Site'));
					}
					$blockStatus.html(
						'<strong style="color:#a02b30;">' + t('alreadyBlocked', 'Already blocked') + ':</strong> ' + pills.join(', ')
					);
				} else {
					$blockStatus.html('<span style="color:#2e7d32;">' + t('notBlocked', 'Not currently blocked.') + '</span>');
				}
				$preview.show();
				$submit.prop('disabled', false);
				$helper.text(t('helperReview', 'Review the preview, then click "Block this user".'));
				$status.text('');
			})
			.fail(function () {
				resetPreview();
				$status.text(t('lookupFailed', 'Lookup failed. Try again.'));
			});
	}

	$lookupBtn.on('click', runLookup);

	$input.on('input', function () {
		resetPreview();
		$status.text('');
		clearTimeout(lookupTimer);
		if (($input.val() || '').toString().trim().length >= 2) {
			lookupTimer = setTimeout(runLookup, 400);
		}
	});

	$input.on('keydown', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
			runLookup();
		}
	});

	$form.on('submit', function (e) {
		if (matchedUserId <= 0) {
			e.preventDefault();
			$status.text(t('lookupFirst', 'Look up a user first.'));
			return;
		}
		var scopes = [];
		$form.find('input[name="vefg_manual_block_scope[]"]:checked').each(function () {
			scopes.push($(this).val());
		});
		if (scopes.length === 0) {
			e.preventDefault();
			$status.text(t('pickScope', 'Pick at least one block scope.'));
		}
	});

	$(document).on('click', '.vefg-edit-scope-btn', function () {
		var $btn = $(this);
		var actorKey = $btn.data('actor-key');
		var label = $btn.data('label');
		var curForm = String($btn.data('form')) === '1';
		var curLogin = String($btn.data('login')) === '1';
		var curSite = String($btn.data('site')) === '1';

		if (typeof Swal === 'undefined') {
			return;
		}

		Swal.fire({
			title: t('editScopeTitle', 'Edit block scope') + ' — ' + label,
			html:
				'<div style="text-align:left;">' +
				'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-swal-form" ' +
				(curForm ? 'checked' : '') +
				'> <strong>' +
				t('scopeFormComments', 'Form / Comments') +
				'</strong></label>' +
				'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-swal-login" ' +
				(curLogin ? 'checked' : '') +
				'> <strong>' +
				t('scopeLogin', 'Login') +
				'</strong></label>' +
				'<label style="display:block;margin:6px 0;"><input type="checkbox" id="vefg-swal-site" ' +
				(curSite ? 'checked' : '') +
				'> <strong>' +
				t('scopeSiteBan', 'Site-wide ban') +
				'</strong></label>' +
				'</div>',
			showCancelButton: true,
			confirmButtonText: t('save', 'Save'),
			cancelButtonText: t('cancel', 'Cancel'),
			focusConfirm: false,
			preConfirm: function () {
				var scope = [];
				if (document.getElementById('vefg-swal-form').checked) {
					scope.push('form');
				}
				if (document.getElementById('vefg-swal-login').checked) {
					scope.push('login');
				}
				if (document.getElementById('vefg-swal-site').checked) {
					scope.push('site');
				}
				return scope;
			},
		}).then(function (result) {
			if (!result.isConfirmed) {
				return;
			}
			var scope = result.value || [];
			$.post(ajaxurl, {
				action: 'vefg_edit_block_scope',
				nonce: nonce,
				actor_key: actorKey,
				scope: scope,
			})
				.done(function (res) {
					if (res && res.success) {
						toast({ icon: 'success', title: (res.data && res.data.message) || t('saved', 'Saved.') });
						setTimeout(function () {
							window.location.reload();
						}, 600);
					} else {
						toast({
							icon: 'error',
							title: (res && res.data && res.data.message) || t('saveFailed', 'Could not save.'),
						});
					}
				})
				.fail(function () {
					toast({ icon: 'error', title: t('requestFailed', 'Request failed.') });
				});
		});
	});
})(jQuery);
