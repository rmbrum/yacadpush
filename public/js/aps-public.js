(function($) {
	'use strict';

	$(document).ready(function() {
		// Toggle abstract visibility
		$('.dyzc-toggle-abstract').on('click', function(e) {
			e.preventDefault();

			var $button = $(this);
			var $abstract = $button.closest('.dyzc-pub-abstract');
			var $shortText = $abstract.find('.dyzc-abstract-short');
			var $fullText = $abstract.find('.dyzc-abstract-full');
			var isExpanded = $button.attr('data-expanded') === 'true';

			if (isExpanded) {
				// Show short, hide full
				$shortText.show();
				$fullText.hide();
				$button.attr('data-expanded', 'false');

				// Labels come from the server, already in the page language
				$button.text($button.attr('data-label-more') || 'Show more');
			} else {
				// Hide short, show full
				$shortText.hide();
				$fullText.show();
				$button.attr('data-expanded', 'true');

				$button.text($button.attr('data-label-less') || 'Show less');
			}
		});
	});

})(jQuery);
