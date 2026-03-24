/* AI FAQ Generator — Admin JS */
(function ($) {
	'use strict';

	var rowIndex = 0; // counter for new rows added manually

	/* ── Initialise ─────────────────────────────────── */
	$(function () {
		// Fix AJAX URL to use correct host:port (handles Local by Flywheel non-standard ports)
		if ( AIFAQ && AIFAQ.ajax_url ) {
			AIFAQ.ajax_url = AIFAQ.ajax_url.replace(/^https?:\/\/[^\/]+/, window.location.origin);
		}
		initRowIndex();
		bindEvents();
		initSortable();
	});

	function initRowIndex() {
		$('.aifaq-faq-row').each(function () {
			var idx = parseInt($(this).data('index'), 10);
			if (!isNaN(idx) && idx >= rowIndex) {
				rowIndex = idx + 1;
			}
		});
	}

	function bindEvents() {
		// Generate button
		$('#aifaq-generate-btn').on('click', handleGenerate);

		// Delete all FAQs
		$(document).on('click', '#aifaq-delete-btn', handleDelete);

		// Remove single row
		$(document).on('click', '.aifaq-remove-row', function () {
			$(this).closest('.aifaq-faq-row').remove();
			checkEmpty();
		});

		// Add FAQ manually
		$('#aifaq-add-btn').on('click', function () {
			addRow({ question: '', answer: '' });
		});
	}

	function initSortable() {
		if ($.fn.sortable) {
			$('#aifaq-editor').sortable({
				handle: '.aifaq-faq-handle',
				axis: 'y',
				tolerance: 'pointer',
				update: reindexRows,
			});
		}
	}

	/* ── Generate ─────────────────────────────────── */
	function handleGenerate() {
		var $btn    = $('#aifaq-generate-btn');
		var postId  = parseInt($btn.data('post-id'), 10);
		var hasFaqs = $btn.data('has-faqs') === 1 || $btn.data('has-faqs') === '1';
		var count   = parseInt($('#aifaq_count_override').val(), 10) || 5;
		var tone    = $('#aifaq_tone_override').val() || 'neutral';

		// Post not saved yet — save it first via Gutenberg then retry
		if (!postId || postId === 0) {
			setStatus('loading', '💾 Saving post first…');
			$btn.prop('disabled', true);

			// Try Gutenberg's savePost
			if (window.wp && wp.data && wp.data.dispatch) {
				wp.data.dispatch('core/editor').savePost().then(function () {
					var newId = wp.data.select('core/editor').getCurrentPostId();
					if (newId && newId > 0) {
						$btn.data('post-id', newId);
						setStatus('', '');
						$btn.prop('disabled', false);
						handleGenerate();
					} else {
						setStatus('error', 'Error: Please save the post manually first, then click Generate FAQs.');
						$btn.prop('disabled', false);
					}
				}).catch(function () {
					setStatus('error', 'Error: Please save the post manually first, then click Generate FAQs.');
					$btn.prop('disabled', false);
				});
			} else {
				setStatus('error', 'Error: Please save the post as a draft first, then click Generate FAQs.');
				$btn.prop('disabled', false);
			}
			return;
		}

		// Ask confirmation before regenerating existing FAQs
		if (hasFaqs && !window.confirm(AIFAQ.i18n.confirm_regen)) {
			return;
		}

		setStatus('loading', AIFAQ.i18n.generating);
		$btn.prop('disabled', true);

		$.post(AIFAQ.ajax_url, {
			action:  'aifaq_generate',
			nonce:   AIFAQ.nonce,
			post_id: postId,
			count:   count,
			tone:    tone,
		})
		.done(function (res) {
			if (res.success && Array.isArray(res.data)) {
				populateFAQs(res.data);
				setStatus('success', '✓ ' + res.data.length + ' FAQs generated and saved.');
			} else {
				setStatus('error', AIFAQ.i18n.error + (res.data || 'Unknown error.'));
			}
		})
		.fail(function (xhr) {
			setStatus('error', AIFAQ.i18n.error + (xhr.statusText || 'Network error.'));
		})
		.always(function () {
			$btn.prop('disabled', false);
		});
	}

	function handleDelete() {
		if (!window.confirm(AIFAQ.i18n.confirm_del)) return;
		$('#aifaq-editor').empty();
		$('#aifaq-add-row').hide();
		setStatus('', '');
		$('#aifaq-status').hide();
	}

	/* ── Render FAQs ──────────────────────────────── */
	function populateFAQs(faqs) {
		$('#aifaq-editor').empty();
		$('.aifaq-placeholder').remove();
		rowIndex = 0;

		$.each(faqs, function (i, faq) {
			addRow(faq);
		});

		$('#aifaq-add-row').show();

		// Update generate button to say "Regenerate"
		$('#aifaq-generate-btn').data('has-faqs', '1').find('.dashicons').nextAll().remove();
		$('#aifaq-generate-btn').append(' Regenerate FAQs');

		// Show delete button
		if (!$('#aifaq-delete-btn').length) {
			$('#aifaq-generate-btn').after(
				'<button type="button" id="aifaq-delete-btn" class="button button-secondary">' +
				'<span class="dashicons dashicons-trash"></span> Clear</button>'
			);
		}
	}

	function addRow(faq) {
		var template = $('#aifaq-row-template').html();
		var html     = template.replace(/__INDEX__/g, rowIndex);
		var $row     = $(html);
		$row.find('.aifaq-question-input').val(faq.question || '');
		$row.find('.aifaq-answer-input').val(faq.answer || '');
		$row.attr('data-index', rowIndex);
		$('#aifaq-editor').append($row);
		rowIndex++;
		$('#aifaq-add-row').show();
	}

	function reindexRows() {
		$('#aifaq-editor .aifaq-faq-row').each(function (i) {
			$(this).attr('data-index', i);
			$(this).find('[name*="aifaq_faqs["]').each(function () {
				var name = $(this).attr('name').replace(/\[\d+\]/, '[' + i + ']');
				$(this).attr('name', name);
			});
		});
	}

	function checkEmpty() {
		if ($('#aifaq-editor .aifaq-faq-row').length === 0) {
			$('#aifaq-editor').html('<p class="aifaq-placeholder">No FAQs yet. Click "Generate FAQs" to create them automatically from your post content.</p>');
			$('#aifaq-add-row').hide();
		}
	}

	/* ── Status banner ───────────────────────────── */
	function setStatus(type, message) {
		var $s = $('#aifaq-status');
		$s.removeClass('is-loading is-error is-success').text(message);
		if (type) {
			$s.addClass('is-' + type).show();
		} else {
			$s.hide();
		}
	}

}(jQuery));
