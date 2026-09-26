/**
 * Exportación de resultados sin librerías externas:
 *  - PDF real (generador PDF 1.4 mínimo con Helvetica / WinAnsi).
 *  - Excel .xlsx real (SpreadsheetML empaquetado en ZIP sin compresión).
 *  - Impresión (solo el bloque de resultados).
 */
(function () {
	'use strict';

	/* ============================ Utilidades ============================ */

	function download(bytes, filename, mime) {
		var blob = new Blob([bytes], { type: mime });
		if (window.navigator && window.navigator.msSaveOrOpenBlob) {
			window.navigator.msSaveOrOpenBlob(blob, filename);
			return;
		}
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = filename;
		a.rel = 'noopener';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
	}

	function stamp() {
		var d = new Date();
		var p = function (n) { return (n < 10 ? '0' : '') + n; };
		return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '-' + p(d.getHours()) + p(d.getMinutes());
	}

	function fmt(n, d) {
		return Number(n).toLocaleString(document.documentElement.lang || 'es', { minimumFractionDigits: d || 0, maximumFractionDigits: d === undefined ? 2 : d });
	}

	/** Filas de información común a PDF y Excel. */
	function infoRows(r, T) {
		var s = r.summary;
		var rows = [
			[T.date, r.date],
			[T.room, fmt(s.largo) + ' × ' + fmt(s.ancho) + ' m'],
			[T.area, fmt(s.area) + ' m²'],
			[T.perimeter, fmt(s.perimetro) + ' m'],
			[T.installType, s.install_type],
			[T.direction, s.direction_label + ' (' + T.alongSide + ' ' + fmt(s.direction_side) + ' m)']
		];
		if (s.alto) { rows.push([T.height, fmt(s.alto) + ' m']); }
		return rows;
	}

	/* ============================== PDF ============================== */

	// Anchos Helvetica (1/1000 em) para ASCII 32..126.
	var HELV = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
	var CP1252 = { 8364: 128, 8230: 133, 8216: 145, 8217: 146, 8220: 147, 8221: 148, 8226: 149, 8211: 150, 8212: 151 };

	function toWinAnsi(str) {
		var out = '';
		str = String(str === null || str === undefined ? '' : str);
		for (var i = 0; i < str.length; i++) {
			var c = str.charCodeAt(i);
			if (c < 128 || (c >= 160 && c < 256)) { out += String.fromCharCode(c); } else if (CP1252[c]) { out += String.fromCharCode(CP1252[c]); } else { out += '?'; }
		}
		return out;
	}

	function charWidth(ch) {
		var c = ch.charCodeAt(0);
		if (c >= 32 && c <= 126) { return HELV[c - 32]; }
		var base = ch.normalize ? ch.normalize('NFD').charAt(0) : ch;
		var b = base.charCodeAt(0);
		return b >= 32 && b <= 126 ? HELV[b - 32] : 556;
	}

	function textWidth(str, size, bold) {
		var w = 0;
		str = String(str);
		for (var i = 0; i < str.length; i++) { w += charWidth(str.charAt(i)); }
		return (w * size / 1000) * (bold ? 1.06 : 1);
	}

	function wrap(str, maxW, size, bold) {
		var words = String(str || '').split(/\s+/);
		var lines = [];
		var line = '';
		words.forEach(function (w) {
			var test = line ? line + ' ' + w : w;
			if (textWidth(test, size, bold) <= maxW || !line) { line = test; } else { lines.push(line); line = w; }
		});
		if (line) { lines.push(line); }
		return lines.length ? lines : [''];
	}

	function hexToRgb(hex) {
		var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
		if (!m) { return [0.043, 0.42, 0.796]; }
		return [parseInt(m[1], 16) / 255, parseInt(m[2], 16) / 255, parseInt(m[3], 16) / 255];
	}

	function PdfDoc() {
		this.pages = [];
		this.W = 595.28;
		this.H = 841.89;
		this.addPage();
	}
	PdfDoc.prototype.addPage = function () { this.cur = []; this.pages.push(this.cur); };
	PdfDoc.prototype.color = function (rgb, stroke) { return rgb.map(function (v) { return v.toFixed(3); }).join(' ') + (stroke ? ' RG' : ' rg'); };
	PdfDoc.prototype.text = function (x, y, str, size, opts) {
		opts = opts || {};
		var s = String(str === null || str === undefined ? '' : str);
		if (opts.align === 'right') { x -= textWidth(s, size, opts.bold); }
		if (opts.align === 'center') { x -= textWidth(s, size, opts.bold) / 2; }
		var esc = toWinAnsi(s).replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
		this.cur.push('BT ' + this.color(opts.color || [0.13, 0.13, 0.13]) + ' /' + (opts.bold ? 'F2' : 'F1') + ' ' + size + ' Tf ' + x.toFixed(2) + ' ' + (this.H - y).toFixed(2) + ' Td (' + esc + ') Tj ET');
	};
	PdfDoc.prototype.rect = function (x, y, w, h, rgb) {
		this.cur.push(this.color(rgb) + ' ' + x.toFixed(2) + ' ' + (this.H - y - h).toFixed(2) + ' ' + w.toFixed(2) + ' ' + h.toFixed(2) + ' re f');
	};
	PdfDoc.prototype.line = function (x1, y1, x2, y2, rgb, width) {
		this.cur.push(this.color(rgb || [0.85, 0.85, 0.85], true) + ' ' + (width || 0.6) + ' w ' + x1.toFixed(2) + ' ' + (this.H - y1).toFixed(2) + ' m ' + x2.toFixed(2) + ' ' + (this.H - y2).toFixed(2) + ' l S');
	};
	PdfDoc.prototype.output = function () {
		var objs = [];
		var n = this.pages.length;
		// 1 catálogo, 2 páginas, 3 F1, 4 F2, luego (página, contenido) por cada página.
		objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
		var kids = [];
		for (var i = 0; i < n; i++) { kids.push((5 + i * 2) + ' 0 R'); }
		objs[2] = '<< /Type /Pages /Kids [' + kids.join(' ') + '] /Count ' + n + ' >>';
		objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		for (var p = 0; p < n; p++) {
			var content = this.pages[p].join('\n');
			objs[5 + p * 2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + this.W + ' ' + this.H + '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' + (6 + p * 2) + ' 0 R >>';
			objs[6 + p * 2] = '<< /Length ' + content.length + ' >>\nstream\n' + content + '\nendstream';
		}
		var out = '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n';
		var offsets = [];
		for (var k = 1; k < objs.length; k++) {
			offsets[k] = out.length;
			out += k + ' 0 obj\n' + objs[k] + '\nendobj\n';
		}
		var xref = out.length;
		out += 'xref\n0 ' + objs.length + '\n0000000000 65535 f \n';
		for (var j = 1; j < objs.length; j++) { out += ('0000000000' + offsets[j]).slice(-10) + ' 00000 n \n'; }
		out += 'trailer\n<< /Size ' + objs.length + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';
		var bytes = new Uint8Array(out.length);
		for (var b = 0; b < out.length; b++) { bytes[b] = out.charCodeAt(b) & 255; }
		return bytes;
	};

	function pdf(r, meta) {
		var T = meta.i18n || {};
		var C = meta.company || {};
		var primary = hexToRgb((window.CCR_PUBLIC && window.CCR_PUBLIC.primary) || '#0b6bcb');
		var grey = [0.42, 0.45, 0.5];
		var doc = new PdfDoc();
		var M = 40;
		var right = doc.W - M;
		var y;

		function header() {
			doc.rect(0, 0, doc.W, 70, primary);
			doc.text(M, 30, C.name || '', 16, { bold: true, color: [1, 1, 1] });
			doc.text(M, 50, [C.phone, C.email, C.address].filter(Boolean).join('  ·  '), 9, { color: [1, 1, 1] });
			y = 100;
		}
		function ensure(h, redrawTableHead) {
			if (y + h > doc.H - 60) {
				doc.addPage();
				header();
				if (redrawTableHead) { tableHead(); }
			}
		}

		header();
		doc.text(M, y, T.quoteTitle, 15, { bold: true });
		y += 22;

		// Información.
		infoRows(r, T).forEach(function (row, i) {
			var col = i % 2;
			var x = M + col * ((right - M) / 2);
			doc.text(x, y, row[0] + ':', 9, { bold: true, color: grey });
			doc.text(x + 95, y, row[1], 9);
			if (col === 1) { y += 15; }
		});
		y += 22;

		// Tabla.
		var cols = [
			{ key: 'name', label: T.material, w: 0 },
			{ key: 'unit', label: T.unit, w: 55 },
			{ key: 'qty', label: T.qty, w: 55, align: 'right' },
			{ key: 'waste', label: T.waste, w: 60, align: 'right' }
		];
		if (r.show_prices) {
			cols.push({ key: 'unit_price', label: T.unitPrice, w: 80, align: 'right' });
			cols.push({ key: 'subtotal', label: T.subtotal, w: 80, align: 'right' });
		}
		var fixed = cols.reduce(function (a, c) { return a + c.w; }, 0);
		cols[0].w = right - M - fixed;
		var x0 = M;
		cols.forEach(function (c) { c.x = x0; x0 += c.w; });

		function tableHead() {
			doc.rect(M, y - 12, right - M, 20, [0.94, 0.95, 0.97]);
			cols.forEach(function (c) {
				doc.text(c.align === 'right' ? c.x + c.w - 4 : c.x + 4, y + 2, c.label, 8.5, { bold: true, align: c.align, color: grey });
			});
			y += 20;
		}
		tableHead();

		r.lines.forEach(function (l) {
			var nameLines = wrap(l.name + (l.sku ? ' [' + l.sku + ']' : ''), cols[0].w - 8, 9, true);
			var h = nameLines.length * 11 + 8;
			ensure(h, true);
			nameLines.forEach(function (t, i) { doc.text(cols[0].x + 4, y + i * 11, t, 9, { bold: true }); });
			var vals = {
				unit: l.unit,
				qty: l.qty_display,
				waste: l.waste_pct > 0 ? fmt(l.waste_pct) + ' %' : '-',
				unit_price: l.unit_price_display || '',
				subtotal: l.subtotal_display || ''
			};
			cols.slice(1).forEach(function (c) {
				doc.text(c.align === 'right' ? c.x + c.w - 4 : c.x + 4, y, vals[c.key], 9, { align: c.align });
			});
			y += h - 8;
			doc.line(M, y, right, y);
			y += 14;
		});

		if (r.show_prices) {
			ensure(30, false);
			doc.rect(M, y - 13, right - M, 24, primary);
			doc.text(M + 8, y + 3, r.total_label || T.total, 11, { bold: true, color: [1, 1, 1] });
			doc.text(right - 8, y + 3, r.total_display, 12, { bold: true, align: 'right', color: [1, 1, 1] });
			y += 34;
		}

		var s = r.summary;
		if (s.observations) {
			ensure(30, false);
			doc.text(M, y, T.observations + ':', 9, { bold: true });
			y += 13;
			wrap(s.observations, right - M, 9).forEach(function (t) { ensure(12, false); doc.text(M, y, t, 9); y += 12; });
			y += 8;
		}
		if (r.show_prices && r.prices_note) {
			wrap(r.prices_note, right - M, 8).forEach(function (t) { ensure(11, false); doc.text(M, y, t, 8, { color: grey }); y += 11; });
		}

		// Pie en todas las páginas.
		var total = doc.pages.length;
		doc.pages.forEach(function (page, i) {
			doc.cur = page;
			doc.line(M, doc.H - 40, right, doc.H - 40);
			doc.text(M, doc.H - 26, C.footer || '', 8, { color: grey });
			doc.text(right, doc.H - 26, (i + 1) + ' / ' + total, 8, { align: 'right', color: grey });
		});

		download(doc.output(), 'presupuesto-cielorraso-' + stamp() + '.pdf', 'application/pdf');
	}

	/* ============================== XLSX ============================== */

	var CRC_TABLE = (function () {
		var t = new Uint32Array(256);
		for (var n = 0; n < 256; n++) {
			var c = n;
			for (var k = 0; k < 8; k++) { c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; }
			t[n] = c >>> 0;
		}
		return t;
	})();

	function crc32(bytes) {
		var c = 0xFFFFFFFF;
		for (var i = 0; i < bytes.length; i++) { c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8); }
		return (c ^ 0xFFFFFFFF) >>> 0;
	}

	function utf8(str) {
		if (window.TextEncoder) { return new TextEncoder().encode(str); }
		var s = unescape(encodeURIComponent(str));
		var b = new Uint8Array(s.length);
		for (var i = 0; i < s.length; i++) { b[i] = s.charCodeAt(i); }
		return b;
	}

	/** ZIP sin compresión (método "store"). files: [{name, data: Uint8Array}] */
	function zip(files) {
		var parts = [];
		var central = [];
		var offset = 0;
		function u16(v) { return [v & 255, (v >>> 8) & 255]; }
		function u32(v) { return [v & 255, (v >>> 8) & 255, (v >>> 16) & 255, (v >>> 24) & 255]; }

		files.forEach(function (f) {
			var name = utf8(f.name);
			var crc = crc32(f.data);
			var size = f.data.length;
			var local = [].concat([0x50, 0x4b, 0x03, 0x04], u16(20), u16(0x0800), u16(0), u16(0), u16(0x21), u32(crc), u32(size), u32(size), u16(name.length), u16(0));
			parts.push(new Uint8Array(local), name, f.data);
			central.push(new Uint8Array([].concat([0x50, 0x4b, 0x01, 0x02], u16(20), u16(20), u16(0x0800), u16(0), u16(0), u16(0x21), u32(crc), u32(size), u32(size), u16(name.length), u16(0), u16(0), u16(0), u16(0), u32(0), u32(offset))), name);
			offset += local.length + name.length + size;
		});
		var cdSize = central.reduce(function (a, p) { return a + p.length; }, 0);
		var end = new Uint8Array([].concat([0x50, 0x4b, 0x05, 0x06], u16(0), u16(0), u16(files.length), u16(files.length), u32(cdSize), u32(offset), u16(0)));
		var all = parts.concat(central, [end]);
		var total = all.reduce(function (a, p) { return a + p.length; }, 0);
		var out = new Uint8Array(total);
		var pos = 0;
		all.forEach(function (p) { out.set(p, pos); pos += p.length; });
		return out;
	}

	function xmlEsc(s) {
		return String(s === null || s === undefined ? '' : s)
			.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '')
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

	function colName(i) {
		var s = '';
		i++;
		while (i > 0) { var m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); }
		return s;
	}

	/**
	 * Genera un .xlsx a partir de filas. Celda: string | number | {v, s} (s = índice de estilo).
	 * Estilos: 0 normal, 1 negrita, 2 encabezado, 3 título, 4 número 2 decimales, 5 total.
	 */
	function buildXlsx(rows, widths, sheetName) {
		var sheetRows = rows.map(function (row, ri) {
			var cells = (row || []).map(function (cell, ci) {
				if (cell === null || cell === undefined || cell === '') { return ''; }
				var v = typeof cell === 'object' ? cell.v : cell;
				var s = typeof cell === 'object' && cell.s ? ' s="' + cell.s + '"' : '';
				var ref = colName(ci) + (ri + 1);
				if (typeof v === 'number' && isFinite(v)) { return '<c r="' + ref + '"' + s + '><v>' + v + '</v></c>'; }
				return '<c r="' + ref + '" t="inlineStr"' + s + '><is><t xml:space="preserve">' + xmlEsc(v) + '</t></is></c>';
			}).join('');
			return '<row r="' + (ri + 1) + '">' + cells + '</row>';
		}).join('');

		var cols = widths.map(function (w, i) { return '<col min="' + (i + 1) + '" max="' + (i + 1) + '" width="' + w + '" customWidth="1"/>'; }).join('');
		var sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
			'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>' + cols + '</cols><sheetData>' + sheetRows + '</sheetData></worksheet>';

		var styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
			'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
			'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>' +
			'<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>' +
			'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEF1F5"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B6BCB"/></patternFill></fill></fills>' +
			'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' +
			'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
			'<cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' +
			'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
			'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' +
			'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
			'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' +
			'<xf numFmtId="164" fontId="3" fillId="3" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1"/></cellXfs>' +
			'</styleSheet>';

		var files = [
			{ name: '[Content_Types].xml', data: utf8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>') },
			{ name: '_rels/.rels', data: utf8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>') },
			{ name: 'xl/workbook.xml', data: utf8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' + xmlEsc(sheetName || 'Hoja1') + '" sheetId="1" r:id="rId1"/></sheets></workbook>') },
			{ name: 'xl/_rels/workbook.xml.rels', data: utf8('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>') },
			{ name: 'xl/worksheets/sheet1.xml', data: utf8(sheet) },
			{ name: 'xl/styles.xml', data: utf8(styles) }
		];
		return zip(files);
	}

	function xlsx(r, meta) {
		var T = meta.i18n || {};
		var C = meta.company || {};
		var rows = [];
		rows.push([{ v: C.name || T.quoteTitle, s: 3 }]);
		rows.push([T.quoteTitle]);
		rows.push([]);
		infoRows(r, T).forEach(function (row) { rows.push([{ v: row[0], s: 1 }, row[1]]); });
		rows.push([]);

		var head = [T.code, T.material, T.unit, T.qty, T.waste + ' %'];
		if (r.show_prices) { head.push(T.unitPrice, T.subtotal); }
		rows.push(head.map(function (h) { return { v: h, s: 2 }; }));

		r.lines.forEach(function (l) {
			var row = [l.sku || l.code, l.name, l.unit, Number(l.qty), Number(l.waste_pct)];
			if (r.show_prices) {
				var unit = Number(l.unit_price) / (Number(l.price_qty) || 1);
				row.push({ v: Math.round(unit * 10000) / 10000, s: 4 }, { v: Number(l.subtotal), s: 4 });
			}
			rows.push(row);
		});
		if (r.show_prices) {
			var tot = [];
			tot[r.show_prices ? 5 : 3] = { v: r.total_label || T.total, s: 2 };
			tot[6] = { v: Number(r.total), s: 5 };
			rows.push(tot);
		}
		if (r.summary.observations) {
			rows.push([]);
			rows.push([{ v: T.observations, s: 1 }, r.summary.observations]);
		}
		if (r.show_prices && r.prices_note) {
			rows.push([]);
			rows.push([r.prices_note]);
		}
		var widths = r.show_prices ? [16, 46, 12, 12, 14, 16, 16] : [16, 46, 12, 12, 14];
		download(buildXlsx(rows, widths, 'Presupuesto'), 'presupuesto-cielorraso-' + stamp() + '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	}

	/* ============================ Imprimir ============================ */

	function print(box) {
		var html = document.documentElement;
		html.classList.add('ccr-printing');
		box.classList.add('ccr-print-target');
		var done = false;
		var cleanup = function () {
			if (done) { return; }
			done = true;
			html.classList.remove('ccr-printing');
			box.classList.remove('ccr-print-target');
			window.removeEventListener('afterprint', cleanup);
		};
		window.addEventListener('afterprint', cleanup);
		window.print();
		setTimeout(cleanup, 1000);
	}

	window.CCRExport = { pdf: pdf, xlsx: xlsx, print: print, buildXlsx: buildXlsx, download: download };
})();
