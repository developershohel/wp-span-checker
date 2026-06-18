/**
 * Comment Guard settings — spam rule tab panels.
 */
(function ($) {
	'use strict';

	$('.vefg-spam-nav .nav-tab').on('click', function (e) {
		e.preventDefault();
		var id = $(this).data('vefg-tab');
		$('.vefg-spam-nav .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');
		$('.vefg-spam-tab-panel').hide();
		$('#' + id).show();
	});
})(jQuery);
