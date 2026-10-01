(function () {
	'use strict';

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.urme-le-intro-toggle');
		if (!btn) {
			return;
		}

		var box = btn.closest('.urme-le-intro-collapsible');
		if (!box) {
			return;
		}

		var collapsed = box.getAttribute('data-collapsed') !== 'false';
		box.setAttribute('data-collapsed', collapsed ? 'false' : 'true');
		btn.setAttribute('aria-expanded', collapsed ? 'true' : 'false');

		var label = btn.querySelector('.urme-le-intro-toggle-label');
		if (label) {
			label.textContent = collapsed ? label.getAttribute('data-less') : label.getAttribute('data-more');
		}
	});
})();
