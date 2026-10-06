/**
 * Calculadora de Cielorraso PVC - front-end.
 * Sin dependencias (no requiere jQuery). Todo el contenido dinámico se inserta con textContent (anti-XSS).
 */
(function () {
	'use strict';

	var G = window.CCR_PUBLIC || {};
	var T = G.i18n || {};
	var TOKEN_KEY = 'ccr_lead_token';

	function storageGet(key) {
		try { return window.sessionStorage.getItem(key) || ''; } catch (e) { return ''; }
	}
	function storageSet(key, val) {
		try { window.sessionStorage.setItem(key, val); } catch (e) { /* almacenamiento no disponible */ }
	}

	/** Crea un elemento. attrs: objeto de atributos; children: string | Node | array. */
	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		if (attrs) {
			Object.keys(attrs).forEach(function (k) {
				if (attrs[k] === null || attrs[k] === undefined || attrs[k] === false) { return; }
				if (k === 'class') { node.className = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
			});
		}
		append(node, children);
		return node;
	}
	function append(node, children) {
		if (children === null || children === undefined) { return; }
		if (!Array.isArray(children)) { children = [children]; }
		children.forEach(function (c) {
			if (c === null || c === undefined || c === false) { return; }
			node.appendChild(typeof c === 'object' ? c : document.createTextNode(String(c)));
		});
	}
	function parseNum(v) {
		v = String(v || '').trim().replace(/\s/g, '').replace(',', '.');
		if (v === '' || !/^\d*\.?\d+$/.test(v)) { return NaN; }
		return parseFloat(v);
	}
	function fmtNum(n, d) {
		return Number(n).toLocaleString(document.documentElement.lang || 'es', { minimumFractionDigits: d || 0, maximumFractionDigits: d === undefined ? 2 : d });
	}

	function Calculator(root) {
		this.root = root;
		this.form = root.querySelector('.ccr-form');
		this.leadForm = root.querySelector('.ccr-lead');
		this.results = root.querySelector('.ccr-results');
		this.config = {};
		this.lastPayload = null;
		this.lastResult = null;
		try { this.config = JSON.parse(root.querySelector('.ccr-config').textContent); } catch (e) { this.config = {}; }
		this.bind();
		this.filterByInstallType();
	}

	Calculator.prototype.bind = function () {
		var self = this;
		this.form.addEventListener('submit', function (e) { e.preventDefault(); self.onSubmit(); });
		this.form.addEventListener('change', function (e) {
			if (e.target.name === 'install_type') { self.filterByInstallType(); }
			if (e.target.classList.contains('ccr-select-material')) { self.fillVariants(e.target); }
		});
		if (this.leadForm) {
			this.leadForm.addEventListener('submit', function (e) { e.preventDefault(); self.onLeadSubmit(); });
			this.leadForm.querySelector('[data-ccr-back]').addEventListener('click', function () { self.show('form'); });
		}
	};

	Calculator.prototype.findItem = function (id) {
		var found = null;
		(this.config.categories || []).forEach(function (cat) {
			cat.items.forEach(function (it) { if (String(it.id) === String(id)) { found = it; } });
		});
		return found;
	};

	/** Oculta materiales que no aplican al tipo de instalación elegido. */
	Calculator.prototype.filterByInstallType = function () {
		var self = this;
		var checked = this.form.querySelector('input[name="install_type"]:checked');
		var typeId = null;
		if (checked) {
			(this.config.install_types || []).forEach(function (t) { if (t.slug === checked.value) { typeId = String(t.id); } });
		}
		this.form.querySelectorAll('.ccr-select-material').forEach(function (select) {
			var firstVisible = null;
			var visibleCount = 0;
			Array.prototype.forEach.call(select.options, function (opt) {
				var types = opt.getAttribute('data-types');
				var ok = !types || !typeId || types.split(',').indexOf(typeId) !== -1;
				opt.hidden = !ok;
				opt.disabled = !ok;
				if (ok && opt.value !== '0') { visibleCount++; }
				if (ok && firstVisible === null) { firstVisible = opt; }
			});
			if (select.selectedOptions[0] && select.selectedOptions[0].disabled && firstVisible) {
				select.value = firstVisible.value;
			}
			var field = select.closest('.ccr-category');
			if (field) { field.hidden = visibleCount === 0; }
			self.fillVariants(select);
		});
	};

	Calculator.prototype.fillVariants = function (select) {
		var vsel = select.parentNode.querySelector('.ccr-select-variant');
		if (!vsel) { return; }
		var item = this.findItem(select.value);
		vsel.innerHTML = '';
		if (!item || !item.variants || item.variants.length < 2) {
			vsel.hidden = true;
			return;
		}
		vsel.appendChild(el('option', { value: 'auto' }, this.config.variant_auto_label || 'Automático'));
		item.variants.forEach(function (v) { vsel.appendChild(el('option', { value: String(v.index) }, v.label)); });
		vsel.hidden = false;
	};

	Calculator.prototype.message = function (form, text) {
		var box = form.querySelector('.ccr-message');
		box.textContent = text || '';
		box.classList.toggle('is-visible', !!text);
	};

	Calculator.prototype.collect = function () {
		var f = this.form;
		var get = function (name) { var n = f.querySelector('[name="' + name + '"]'); return n ? n.value : ''; };
		var radio = function (name) { var n = f.querySelector('[name="' + name + '"]:checked'); return n ? n.value : ''; };
		var errors = [];
		f.querySelectorAll('.ccr-invalid').forEach(function (n) { n.classList.remove('ccr-invalid'); n.removeAttribute('aria-invalid'); });

		['largo', 'ancho', 'alto'].forEach(function (name) {
			var input = f.querySelector('[name="' + name + '"]');
			if (!input) { return; }
			var val = input.value.trim();
			if (val === '' && !input.required) { return; }
			if (val === '' || isNaN(parseNum(val)) || parseNum(val) <= 0 && name !== 'alto') {
				input.classList.add('ccr-invalid');
				input.setAttribute('aria-invalid', 'true');
				errors.push(input);
			}
		});
		if (errors.length) {
			errors[0].focus();
			return { error: errors[0].value.trim() === '' ? T.required : T.invalidNum };
		}

		var selections = {};
		var variants = {};
		f.querySelectorAll('.ccr-category').forEach(function (field) {
			var sel = field.querySelector('.ccr-select-material');
			selections[field.getAttribute('data-category')] = sel.value;
			var vsel = field.querySelector('.ccr-select-variant');
			if (vsel && !vsel.hidden && vsel.value !== '') { variants[sel.value] = vsel.value; }
		});

		return {
			data: {
				largo: String(parseNum(get('largo'))),
				ancho: String(parseNum(get('ancho'))),
				alto: get('alto').trim() === '' ? '' : String(parseNum(get('alto'))),
				install_type: radio('install_type'),
				direction: radio('direction'),
				selections: selections,
				variants: variants,
				observations: get('observations')
			},
			honeypot: get('ccr_website')
		};
	};

	Calculator.prototype.onSubmit = function () {
		this.message(this.form, '');
		var c = this.collect();
		if (c.error) { this.message(this.form, c.error); return; }
		this.lastPayload = c;
		if (G.leads && !storageGet(TOKEN_KEY)) {
			this.show('lead');
			var first = this.leadForm.querySelector('input');
			if (first) { first.focus(); }
			return;
		}
		this.send(this.form);
	};

	Calculator.prototype.onLeadSubmit = function () {
		var lf = this.leadForm;
		this.message(lf, '');
		var missing = null;
		lf.querySelectorAll('input[required]').forEach(function (i) {
			var bad = i.type === 'checkbox' ? !i.checked : i.value.trim() === '' || (i.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(i.value.trim()));
			i.classList.toggle('ccr-invalid', bad);
			if (bad && !missing) { missing = i; }
		});
		if (missing) { missing.focus(); this.message(lf, T.required); return; }
		var val = function (n) { var i = lf.querySelector('[name="' + n + '"]'); return i ? (i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value.trim()) : ''; };
		this.lastPayload.data.lead = { name: val('name'), company: val('company'), phone: val('phone'), email: val('email'), consent: val('consent') };
		this.send(lf);
	};

	Calculator.prototype.send = function (activeForm) {
		var self = this;
		var btn = activeForm.querySelector('button[type="submit"]');
		var original = btn.textContent;
		btn.disabled = true;
		btn.textContent = T.calculating || '...';
		this.root.classList.add('is-loading');

		var payload = this.lastPayload.data;
		payload.lead_token = storageGet(TOKEN_KEY);

		var body = new FormData();
		body.append('action', 'ccr_calculate');
		body.append('nonce', G.nonce);
		body.append('ccr_website', this.lastPayload.honeypot || '');
		body.append('data', JSON.stringify(payload));

		fetch(G.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { success: false, data: {} }; }); })
			.then(function (res) {
				if (!res || !res.success) {
					var msg = res && res.data && res.data.message ? res.data.message : T.error;
					if (res && res.data && res.data.lead_error && self.leadForm) {
						// Token vencido o datos inválidos: se vuelven a pedir los datos.
						storageSet(TOKEN_KEY, '');
						self.show('lead');
						self.message(self.leadForm, msg);
					} else {
						self.show('form');
						self.message(self.form, msg);
					}
					return;
				}
				if (res.data.lead_token) { storageSet(TOKEN_KEY, res.data.lead_token); }
				self.lastResult = res.data;
				self.renderResults(res.data);
				self.show('results');
			})
			.catch(function () { self.message(activeForm, T.error); })
			.then(function () {
				btn.disabled = false;
				btn.textContent = original;
				self.root.classList.remove('is-loading');
			});
	};

	/**
	 * Panel derecho: 'form' (marcador de posición), 'lead' o 'results'.
	 * El formulario de medidas queda siempre visible a la izquierda.
	 */
	Calculator.prototype.show = function (what) {
		var placeholder = this.root.querySelector('.ccr-placeholder');
		if (placeholder) { placeholder.hidden = what === 'lead' || what === 'results'; }
		if (this.leadForm) { this.leadForm.hidden = what !== 'lead'; }
		this.results.hidden = what !== 'results';

		var target = what === 'lead' ? this.leadForm : (what === 'results' ? this.results : this.form);
		if (!target) { return; }
		if (what === 'results') { this.results.focus({ preventScroll: true }); }
		// Solo se desplaza si el panel no está a la vista (en celular queda debajo del formulario).
		var rect = target.getBoundingClientRect();
		if (rect.top < 60 || rect.top > window.innerHeight * 0.6) {
			window.scrollTo({ top: rect.top + window.pageYOffset - 90, behavior: 'smooth' });
		}
	};

	/* ------------------------------------------------------------------ */

	Calculator.prototype.diagram = function (r) {
		var s = r.summary;
		var ns = 'http://www.w3.org/2000/svg';
		var maxW = 320, maxH = 200;
		var scale = Math.min(maxW / s.largo, maxH / s.ancho);
		var w = Math.max(40, s.largo * scale), h = Math.max(30, s.ancho * scale);
		var pad = 28;
		var svg = document.createElementNS(ns, 'svg');
		svg.setAttribute('viewBox', '0 0 ' + (w + pad * 2) + ' ' + (h + pad * 2));
		svg.setAttribute('class', 'ccr-diagram');
		svg.setAttribute('role', 'img');
		svg.setAttribute('aria-label', T.room + ' ' + fmtNum(s.largo) + ' × ' + fmtNum(s.ancho) + ' m');

		function node(tag, attrs, text) {
			var n = document.createElementNS(ns, tag);
			Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
			if (text !== undefined) { n.textContent = text; }
			svg.appendChild(n);
			return n;
		}
		node('rect', { x: pad, y: pad, width: w, height: h, rx: 3, class: 'ccr-d-room' });

		// Líneas de láminas (cada 20 cm aprox., limitadas para no saturar el dibujo).
		var alongLargo = s.direction === 'largo';
		var span = alongLargo ? s.ancho : s.largo;
		var count = Math.min(60, Math.max(2, Math.round(span / 0.2)));
		for (var i = 1; i < count; i++) {
			if (alongLargo) {
				var y = pad + (h * i) / count;
				node('line', { x1: pad, y1: y, x2: pad + w, y2: y, class: 'ccr-d-board' });
			} else {
				var x = pad + (w * i) / count;
				node('line', { x1: x, y1: pad, x2: x, y2: pad + h, class: 'ccr-d-board' });
			}
		}
		node('text', { x: pad + w / 2, y: pad - 9, 'text-anchor': 'middle', class: 'ccr-d-label' }, fmtNum(s.largo) + ' m');
		var t = node('text', { x: pad - 9, y: pad + h / 2, 'text-anchor': 'middle', class: 'ccr-d-label' }, fmtNum(s.ancho) + ' m');
		t.setAttribute('transform', 'rotate(-90 ' + (pad - 9) + ' ' + (pad + h / 2) + ')');
		return svg;
	};

	Calculator.prototype.renderResults = function (r) {
		var self = this;
		var s = r.summary;
		var box = this.results;
		box.innerHTML = '';

		// Encabezado: "— LISTA DE MATERIALES" + pastilla con el tipo de instalación.
		var head = el('div', { class: 'ccr-mat-head' }, [
			el('div', null, [
				el('span', { class: 'ccr-eyebrow' }, T.listTitle),
				el('p', { class: 'ccr-mat-desc' }, [
					T.roomOf + ' ',
					el('b', null, fmtNum(s.largo, 2) + ' m × ' + fmtNum(s.ancho, 2) + ' m'),
					' (' + fmtNum(s.area) + ' m²), ' + T.boardsAlong + ' ',
					el('b', null, fmtNum(s.direction_side, 2) + ' m'),
					' (' + s.direction_label.toLowerCase() + (s.direction_auto ? ', ' + T.cheapest : '') + ').'
				])
			]),
			el('span', { class: 'ccr-pill' }, s.install_type)
		]);

		// Tabla: Cant. | Material | Desp. | Unitario | Subtotal.
		var cols = [[T.qtyShort, 'ccr-q'], [T.material, ''], [T.waste, 'ccr-num ccr-col-waste']];
		if (r.show_prices) { cols.push([T.unitShort, 'ccr-num ccr-col-unit'], [T.subtotal, 'ccr-num']); }
		var thead = el('thead', null, el('tr', null, cols.map(function (c) { return el('th', { scope: 'col', class: c[1] || null }, c[0]); })));
		var tbody = el('tbody');
		if (!r.lines.length) {
			tbody.appendChild(el('tr', null, el('td', { colspan: cols.length }, T.noItems)));
		}
		r.lines.forEach(function (l) {
			var row = el('tr', null, [
				el('td', { class: 'ccr-q', 'data-label': T.qty }, l.qty_display),
				el('td', { class: 'ccr-mat', 'data-label': T.material }, [
					el('span', { class: 'ccr-mat-name' }, l.name),
					el('small', { class: 'ccr-mat-meta' }, [l.unit, l.sku].filter(Boolean).join(' · ')),
					l.description ? el('small', { class: 'ccr-desc' }, l.description) : null
				]),
				el('td', { class: 'ccr-num ccr-col-waste', 'data-label': T.waste }, l.waste_pct > 0 ? fmtNum(l.waste_pct) + ' %' : '—')
			]);
			if (r.show_prices) {
				row.appendChild(el('td', { class: 'ccr-num ccr-col-unit', 'data-label': T.unitShort }, l.unit_price_display));
				row.appendChild(el('td', { class: 'ccr-num ccr-s', 'data-label': T.subtotal }, l.subtotal_display));
			}
			tbody.appendChild(row);
		});
		var table = el('table', { class: 'ccr-table' }, [thead, tbody]);

		// Caja de total (degradé azul).
		var kv = function (label, value) { return el('div', { class: 'ccr-kv' }, [el('small', null, label), el('b', null, value)]); };
		var totalBox = el('div', { class: 'ccr-total-box' });
		var left = el('div', { class: 'ccr-total-kvs' }, [kv(T.area, fmtNum(s.area) + ' m²'), kv(T.items, String(s.items_count))]);
		if (r.show_prices && s.area > 0) {
			var perM2 = Number(r.total) / s.area;
			var cur = G.currency || {};
			var num = perM2.toLocaleString(document.documentElement.lang || 'es', { maximumFractionDigits: cur.decimals || 0 });
			left.insertBefore(kv(T.costPerM2, (cur.position === 'before' ? cur.symbol + ' ' + num : num + ' ' + (cur.symbol || '')) + ' / m²'), left.firstChild);
		}
		totalBox.appendChild(left);
		if (r.show_prices) {
			totalBox.appendChild(el('div', { class: 'ccr-total-main' }, [el('small', null, r.total_label || T.total), el('strong', null, r.total_display)]));
		}

		// Plano + resumen final.
		var summaryItems = [
			[T.perimeter, fmtNum(s.perimetro) + ' m'],
			[T.installType, s.install_type],
			[T.wasteApplied, s.general_waste_pct > 0 ? fmtNum(s.general_waste_pct) + ' % (general)' : 'Según material']
		];
		if (s.alto) { summaryItems.push([T.height, fmtNum(s.alto) + ' m']); }
		var dl = el('dl', { class: 'ccr-summary' });
		summaryItems.forEach(function (p) { dl.appendChild(el('dt', null, p[0])); dl.appendChild(el('dd', null, p[1])); });

		var extra = el('div', { class: 'ccr-extra' }, [
			el('div', { class: 'ccr-plan' }, [el('span', { class: 'ccr-mini-title' }, T.plan), this.diagram(r)]),
			el('div', { class: 'ccr-summary-box' }, [el('span', { class: 'ccr-mini-title' }, T.summary), dl])
		]);

		var notes = el('div', { class: 'ccr-notes' });
		if (s.observations) {
			notes.appendChild(el('p', { class: 'ccr-obs' }, [el('strong', null, T.observations + ': '), s.observations]));
		}
		if (r.show_prices && r.prices_note) {
			notes.appendChild(el('p', { class: 'ccr-note' }, r.prices_note));
		}

		// Acciones.
		var actions = el('div', { class: 'ccr-mat-actions ccr-no-print' });
		var btn = function (label, cls, fn) {
			var b = el('button', { type: 'button', class: 'ccr-btn ' + cls }, label);
			b.addEventListener('click', fn);
			actions.appendChild(b);
		};
		// Compra: agregar al carrito de WooCommerce y enviar por WhatsApp.
		var buy = null;
		var cartOn = G.cart && G.cart.enabled;
		var waOn = G.whatsapp && G.whatsapp.enabled;
		if (cartOn || waOn) {
			var buyMsg = el('p', { class: 'ccr-buy-msg', role: 'status', 'aria-live': 'polite', hidden: 'hidden' });
			var buyBtns = el('div', { class: 'ccr-buy-buttons' });
			if (cartOn) {
				var cartBtn = el('button', { type: 'button', class: 'ccr-btn ccr-btn-cart' }, G.cart.label);
				cartBtn.addEventListener('click', function () { self.addToCart(cartBtn, buyMsg); });
				buyBtns.appendChild(cartBtn);
			}
			if (waOn) {
				var waBtn = el('button', { type: 'button', class: 'ccr-btn ccr-btn-whatsapp' }, G.whatsapp.label);
				waBtn.addEventListener('click', function () { self.sendWhatsApp(r); });
				buyBtns.appendChild(waBtn);
			}
			buy = el('div', { class: 'ccr-buy ccr-no-print' }, [buyBtns, buyMsg]);
		}

		var X = window.CCRExport;
		var meta = { company: G.company, i18n: T };
		if (G.export && G.export.pdf && X) { btn(T.pdf, 'ccr-btn-primary', function () { X.pdf(r, meta); }); }
		if (G.export && G.export.excel && X) { btn(T.excel, 'ccr-btn-ghost', function () { X.xlsx(r, meta); }); }
		if (G.export && G.export.print && X) { btn(T.print, 'ccr-btn-ghost', function () { X.print(box); }); }
		btn(T.recalc, 'ccr-btn-ghost', function () {
			self.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
			self.form.querySelector('input').focus({ preventScroll: true });
		});

		var printHeader = el('div', { class: 'ccr-print-only ccr-print-header' }, [
			el('strong', null, G.company && G.company.name ? G.company.name : ''),
			el('span', null, [G.company && G.company.phone, G.company && G.company.email, G.company && G.company.address].filter(Boolean).join(' · '))
		]);

		append(box, [printHeader, head, el('div', { class: 'ccr-table-wrap' }, table), totalBox, extra, notes, buy, actions]);
	};

	/**
	 * Agrega los materiales al carrito. Se envían los mismos datos del formulario:
	 * el servidor recalcula las cantidades (no se confía en las del navegador).
	 */
	Calculator.prototype.addToCart = function (btn, msgBox) {
		if (!this.lastPayload) { return; }
		var original = btn.textContent;
		btn.disabled = true;
		btn.textContent = T.addingCart || '...';
		msgBox.hidden = true;
		msgBox.classList.remove('is-error');

		var payload = this.lastPayload.data;
		payload.lead_token = storageGet(TOKEN_KEY);
		var body = new FormData();
		body.append('action', 'ccr_add_to_cart');
		body.append('nonce', G.nonce);
		body.append('data', JSON.stringify(payload));

		var showMsg = function (text, url, isError) {
			msgBox.textContent = text;
			if (url) {
				msgBox.appendChild(document.createTextNode(' '));
				msgBox.appendChild(el('a', { href: url }, T.viewCart || url));
			}
			msgBox.classList.toggle('is-error', !!isError);
			msgBox.hidden = false;
		};

		fetch(G.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) { return r.json().catch(function () { return { success: false, data: {} }; }); })
			.then(function (res) {
				var d = (res && res.data) || {};
				if (!res || !res.success) { showMsg(d.message || T.error, null, true); return; }
				if (d.redirect && d.url) { window.location.href = d.url; return; }
				showMsg(d.message, d.url, d.missing && d.missing.length > 0);
			})
			.catch(function () { showMsg(T.error, null, true); })
			.then(function () {
				btn.disabled = false;
				btn.textContent = original;
			});
	};

	/** Abre WhatsApp con el presupuesto como texto, dirigido al número de la empresa. */
	Calculator.prototype.sendWhatsApp = function (r) {
		var s = r.summary;
		var out = [];
		if (G.whatsapp.intro) { out.push(G.whatsapp.intro, ''); }
		out.push('*' + T.room + ':* ' + fmtNum(s.largo, 2) + ' × ' + fmtNum(s.ancho, 2) + ' m (' + fmtNum(s.area) + ' m²)');
		out.push('*' + T.installType + ':* ' + s.install_type);
		out.push('*' + T.direction + ':* ' + s.direction_label);
		out.push('', '*' + T.materials + ':*');
		r.lines.forEach(function (l) {
			var line = '• ' + l.qty_display + ' ' + l.unit + ' — ' + l.name;
			if (r.show_prices && l.subtotal_display) { line += ' — ' + l.subtotal_display; }
			out.push(line);
		});
		if (r.show_prices && r.total_display) { out.push('', '*' + (r.total_label || T.total) + ':* ' + r.total_display); }
		if (s.observations) { out.push('', '*' + T.observations + ':* ' + s.observations); }
		var url = 'https://wa.me/' + encodeURIComponent(G.whatsapp.number) + '?text=' + encodeURIComponent(out.join('\n'));
		window.open(url, '_blank', 'noopener');
	};

	function init() {
		document.querySelectorAll('[data-ccr]').forEach(function (root) {
			if (root.__ccr) { return; }
			root.__ccr = new Calculator(root);
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
	// Compatibilidad con Elementor (widgets cargados dinámicamente en el editor/popups).
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', init);
		}
	});
	window.CCRInit = init;
})();
