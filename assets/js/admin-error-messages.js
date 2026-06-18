/**
 * Error Messages admin screen.
 */
(function ($) {
	'use strict';

	var i18n = (typeof window.VEFGErrorMessages !== 'undefined' && window.VEFGErrorMessages.i18n) || {};

	$('#vefg-reset-defaults').on('click', function () {
		var confirmMsg = i18n.resetConfirm || 'Reset all messages to defaults?';
		if (confirm(confirmMsg)) {
			$('.vefg-msg-field').each(function () {
				$(this).val($(this).data('default') || '');
			});
		}
	});

	$(document).on('click', '.vefg-reset-single', function (e) {
		e.preventDefault();
		var fieldId = $(this).data('field');
		var $field = $('#' + fieldId);
		var defaultVal = $field.data('default') || '';
		$field.val(defaultVal);

		var $desc = $(this).closest('p.description');
		$desc.find('.vefg-custom-badge').remove();
		$desc.find('a.vefg-reset-single:not(.vefg-default-badge)').remove();

		if (!$desc.find('.vefg-default-badge').length) {
			var defaultLabel = i18n.defaultLabel || 'Default';
			var defaultTitle = i18n.defaultTitle || 'Click to reset to default';
			$desc.append(
				'<a href="#" class="vefg-reset-single vefg-default-badge" data-field="' +
					fieldId +
					'" style="background: #ddd; color: #50575e; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-decoration: none; cursor: pointer;" title="' +
					defaultTitle +
					'">' +
					defaultLabel +
					'</a>'
			);
		}
	});

	$(document).on('input change', '.vefg-msg-field', function () {
		var $field = $(this);
		var fieldId = $field.attr('id');
		var defaultVal = $field.data('default') || '';
		var currentVal = $field.val();
		var $desc = $field.closest('td').find('p.description');
		var customLabel = i18n.customLabel || 'Custom';
		var resetLabel = i18n.resetSingle || 'Reset to default';
		var defaultLabel = i18n.defaultLabel || 'Default';
		var defaultTitle = i18n.defaultTitle || 'Click to reset to default';

		if (currentVal !== defaultVal) {
			if (!$desc.find('.vefg-custom-badge').length) {
				$desc.find('.vefg-default-badge').remove();
				$desc.find('a.vefg-reset-single').remove();
				$desc.append(
					'<span class="vefg-custom-badge" style="background: #2271b1; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' +
						customLabel +
						'</span> '
				);
				$desc.append(
					'<a href="#" class="vefg-reset-single" data-field="' +
						fieldId +
						'" style="margin-left: 8px;">' +
						resetLabel +
						'</a>'
				);
			}
		} else {
			$desc.find('.vefg-custom-badge').remove();
			$desc.find('a.vefg-reset-single:not(.vefg-default-badge)').remove();
			if (!$desc.find('.vefg-default-badge').length) {
				$desc.append(
					'<a href="#" class="vefg-reset-single vefg-default-badge" data-field="' +
						fieldId +
						'" style="background: #ddd; color: #50575e; padding: 2px 6px; border-radius: 3px; font-size: 11px; text-decoration: none; cursor: pointer;" title="' +
						defaultTitle +
						'">' +
						defaultLabel +
						'</a>'
				);
			}
		}
	});
})(jQuery);
