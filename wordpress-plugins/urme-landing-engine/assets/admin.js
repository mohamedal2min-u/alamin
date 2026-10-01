(function () {
	'use strict';

	function update() {
		var scope = document.getElementById('urme_le_scope');
		var type = document.getElementById('urme_le_filter_type');
		if (!scope || !type) return;

		document.querySelectorAll('.urme-le-brand-row').forEach(function (row) {
			row.style.display = scope.value === 'brand' ? '' : 'none';
		});

		document.querySelectorAll('.urme-le-tax-row').forEach(function (row) {
			row.style.display = type.value === 'sale' ? 'none' : '';
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var scope = document.getElementById('urme_le_scope');
		var type = document.getElementById('urme_le_filter_type');
		if (scope) scope.addEventListener('change', update);
		if (type) type.addEventListener('change', update);
		update();
	});
})();
