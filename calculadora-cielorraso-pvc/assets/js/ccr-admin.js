/**
 * Calculadora de Cielorraso PVC - administración.
 */
(function () {
	'use strict';
	var A = window.CCR_ADMIN || {};
	var T = A.i18n || {};

	document.addEventListener('click', function (e) {
		var t = e.target;

		// Confirmaciones de borrado.
		if (t.closest('.ccr-confirm-delete') && !window.confirm(T.confirmDelete)) {
			e.preventDefault();
			return;
		}

		// Variantes: agregar / quitar filas.
		if (t.classList.contains('ccr-add-row')) {
			var wrap = t.closest('.ccr-variants');
			var tbody = wrap.querySelector('tbody');
			var tpl = wrap.querySelector('.ccr-variant-template').innerHTML;
			var i = Date.now();
			var tmp = document.createElement('tbody');
			tmp.innerHTML = tpl.replace(/__i__/g, String(i));
			tbody.appendChild(tmp.firstElementChild);
			return;
		}
		if (t.classList.contains('ccr-remove-row')) {
			t.closest('tr').remove();
			return;
		}

		// Probar fórmula.
		if (t.classList.contains('ccr-test-formula')) {
			e.preventDefault();
			testFormula(t);
		}
	});

	// Seleccionar todos (leads).
	document.addEventListener('change', function (e) {
		if (e.target.classList.contains('ccr-check-all')) {
			var table = e.target.closest('table');
			table.querySelectorAll('tbody input[type="checkbox"]').forEach(function (c) { c.checked = e.target.checked; });
		}
	});

	// Confirmación de restauración.
	document.querySelectorAll('.ccr-confirm-reset').forEach(function (f) {
		f.addEventListener('submit', function (e) { if (!window.confirm(T.confirmReset)) { e.preventDefault(); } });
	});

	function valueOf(scope, cls) {
		var n = scope.querySelector('.ccr-num-' + cls);
		return n ? n.value : '0';
	}

	function testFormula(btn) {
		var ta = document.getElementById(btn.getAttribute('data-target'));
		var out = btn.parentNode.querySelector('.ccr-test-result');
		// Contexto: la fila (edición masiva) o el formulario completo.
		var scope = btn.closest('.ccr-bulk') ? btn.closest('tr') : btn.closest('form');
		out.className = 'ccr-test-result';
		out.textContent = T.testing;

		var body = new FormData();
		body.append('action', 'ccr_test_formula');
		body.append('nonce', A.nonce);
		body.append('formula', ta.value);
		body.append('length', valueOf(scope, 'length'));
		body.append('width', valueOf(scope, 'width'));
		body.append('yield', valueOf(scope, 'yield'));
		body.append('price', valueOf(scope, 'price'));

		fetch(A.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res.success) {
					out.classList.add('is-ok');
					out.textContent = '= ' + res.data.value + '  (' + res.data.sample + ')';
				} else {
					out.classList.add('is-error');
					out.textContent = res.data && res.data.message ? res.data.message : 'Error';
				}
			})
			.catch(function () {
				out.classList.add('is-error');
				out.textContent = 'Error';
			});
	}
})();
