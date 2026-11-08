/*
 *	Tells the Print.App runtime which WooCommerce variation is selected.
 *
 *	Designs assigned per variation in Print.App are keyed by the
 *	variation's SKU. The runtime (run.print.app/.../wp) reads the selected
 *	variant from an input with the class product-variant-id, or from a
 *	?variation= URL parameter, and sends it to the editor when it opens.
 *	A WooCommerce product page has neither: its variation_id field holds
 *	the variation's post ID. So the variant never resolved and every
 *	variation opened the parent product's first design (A3 opened A4,
 *	circuitposter.com, 2026-11-07).
 *
 *	The input exists only while a variation is selected, so a cleared
 *	form falls back to the parent product's designs, as before.
 */
(function ($) {
	if (!$) return;

	var CLASS = 'product-variant-id';

	function setVariant(form, value) {
		var input = form.querySelector('input.' + CLASS);
		if (!value) {
			if (input) input.parentNode.removeChild(input);
			return;
		}
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.className = CLASS;
			form.appendChild(input);
		}
		input.value = String(value);
	}

	//	The SKU, or the variation ID for a variation without one.
	function variantOf(variation) {
		if (!variation) return '';
		return variation.sku || variation.variation_id || '';
	}

	//	A variation already selected when this script runs (a default
	//	attribute, or a ?attribute_… URL) may have fired found_variation
	//	before we listened. Resolve it from the form's own data.
	function current(form) {
		var idInput = form.querySelector('input.variation_id'),
			id = idInput ? idInput.value : '';
		if (!id || id === '0') return '';
		var list = [];
		try { list = JSON.parse(form.getAttribute('data-product_variations')) || []; } catch (e) {}
		for (var i = 0; i < list.length; i++) {
			if (String(list[i].variation_id) === String(id)) return variantOf(list[i]);
		}
		return id;
	}

	$(function () {
		$('form.variations_form').each(function () {
			var form = this;
			setVariant(form, current(form));
			$(form)
				.on('found_variation', function (event, variation) {
					setVariant(form, variantOf(variation));
				})
				.on('reset_data hide_variation', function () {
					setVariant(form, '');
				});
		});
	});
})(window.jQuery);
