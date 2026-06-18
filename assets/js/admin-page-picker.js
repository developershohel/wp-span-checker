/**
 * Admin page search + badge picker (Login Guard, Registration Guard, Pro guards).
 */
(function ($) {
	'use strict';

	if (typeof window.VEFGAdminPagePicker === 'undefined') {
		return;
	}

	var cfg = window.VEFGAdminPagePicker;
	var searchTimeout;

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function getSelectedIds() {
		var ids = [];
		$(cfg.selectedContainer).find('.vefg-page-badge').each(function () {
			ids.push(parseInt($(this).data('id'), 10));
		});
		return ids;
	}

	function updateHiddenInput() {
		var ids = getSelectedIds();
		$(cfg.hiddenInput).val(ids.join(','));
	}

	function addPageBadge(id, title) {
		var removeLabel = cfg.i18n && cfg.i18n.remove ? cfg.i18n.remove : 'Remove';
		var badge =
			'<span class="vefg-page-badge" data-id="' + id + '">' +
			escapeHtml(title) +
			'<button type="button" class="vefg-badge-remove" aria-label="' + escapeHtml(removeLabel) + '">&times;</button>' +
			'</span>';
		$(cfg.selectedContainer).append(badge);
		updateHiddenInput();
	}

	function togglePageIds() {
		var scope = $(cfg.scopeSelector).val();
		var isSpecific = scope === (cfg.specificValue || 'specific');
		if (cfg.pagesRowSelector) {
			$(cfg.pagesRowSelector).toggle(isSpecific);
		}
		if (cfg.selectorRowSelector) {
			$(cfg.selectorRowSelector).toggle(isSpecific);
		}
	}

	$(cfg.scopeSelector).on('change', togglePageIds);
	togglePageIds();

	$(cfg.searchInput).on('input', function () {
		var query = $(this).val().trim();
		clearTimeout(searchTimeout);

		if (query.length < 2) {
			$(cfg.resultsContainer).hide();
			return;
		}

		searchTimeout = setTimeout(function () {
			$.ajax({
				url: cfg.ajaxUrl,
				type: 'POST',
				data: {
					action: 'vefg_search_pages',
					nonce: cfg.nonce,
					search: query,
					per_page: 10,
				},
				success: function (response) {
					if (!response.success || !response.data.items) {
						return;
					}

					var html = '';
					var selectedIds = getSelectedIds();
					var noResults = cfg.i18n && cfg.i18n.noResults ? cfg.i18n.noResults : 'No pages found.';

					response.data.items.forEach(function (item) {
						if (selectedIds.indexOf(item.id) === -1) {
							html +=
								'<div class="vefg-page-item" data-id="' +
								item.id +
								'" data-title="' +
								escapeHtml(item.title) +
								'">' +
								escapeHtml(item.title) +
								'</div>';
						}
					});

					if (html === '') {
						html = '<div class="vefg-no-results">' + escapeHtml(noResults) + '</div>';
					}

					$(cfg.resultsContainer).html(html).show();
				},
			});
		}, 300);
	});

	$(document).on('click', cfg.resultsContainer + ' .vefg-page-item', function () {
		addPageBadge($(this).data('id'), $(this).data('title'));
		$(cfg.searchInput).val('');
		$(cfg.resultsContainer).hide();
	});

	$(document).on('click', cfg.selectedContainer + ' .vefg-badge-remove', function () {
		$(this).closest('.vefg-page-badge').remove();
		updateHiddenInput();
	});

	$(document).on('click', function (e) {
		if (!$(e.target).closest('.vefg-page-search-wrap').length) {
			$(cfg.resultsContainer).hide();
		}
	});
})(jQuery);
