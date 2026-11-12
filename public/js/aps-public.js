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

		// Compact view: clicking a publication opens its full card in a dialog,
		// abstract already expanded. Without <dialog> support the title stays a DOI link.
		var lastTrigger = null;

		function closeDialog(dialog) {
			if (dialog.open) {
				dialog.close();
			}
		}

		$('.dyzc-card-dialog').each(function() {
			var dialog = this;
			if (typeof dialog.showModal !== 'function') {
				return;
			}
			$(dialog).on('close', function() {
				$(dialog).find('.dyzc-dialog-body').empty();
				if (lastTrigger) {
					lastTrigger.focus();
					lastTrigger = null;
				}
			});
			$(dialog).find('.dyzc-dialog-close').on('click', function() {
				closeDialog(dialog);
			});
			// A click on the backdrop lands on the <dialog> element itself
			$(dialog).on('click', function(e) {
				if (e.target === dialog) {
					closeDialog(dialog);
				}
			});
		});

		$('.dyzc-view-compact').on('click', '.dyzc-pub-item', function(e) {
			// Other links in the line (PDF, repository) keep working as links
			if ($(e.target).closest('a').length && !$(e.target).closest('a').hasClass('dyzc-open-card')) {
				return;
			}
			var $item = $(this);
			var dialog = $item.closest('.dyzc-publications-grid').find('.dyzc-card-dialog').get(0);
			var template = $item.find('.dyzc-card-template').get(0);
			if (!dialog || typeof dialog.showModal !== 'function' || !template || !template.content) {
				return;
			}
			e.preventDefault();
			lastTrigger = $item.find('.dyzc-open-card').get(0) || null;
			$(dialog).find('.dyzc-dialog-body').empty().append(document.importNode(template.content, true));
			dialog.showModal();
			$(dialog).find('.dyzc-dialog-close').trigger('focus');
		});

		// Year and citation style selectors: reload the list with the new choice
		$('.dyzc-auto-submit').on('change', function() {
			if (!this.value) {
				// "All years": leave the empty argument out of the URL
				if (!this.form.querySelector('input[type="hidden"]')) {
					window.location.href = this.form.action;
					return;
				}
				this.disabled = true;
			}
			this.form.submit();
		});
	});

})(jQuery);
