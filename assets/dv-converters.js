/*!
 * DimensionVista Converters: live updates for [dv_converter] and [dv_chart].
 * Mirrors includes/class-dv-math.php (exact rationals via BigInt) and
 * includes/class-dv-visual.php, so typed results match server-rendered ones.
 */
(function () {
	'use strict';

	var UNITS = {
		mm: { mm: [1n, 1n], short: 'mm', name: 'millimeters' },
		cm: { mm: [10n, 1n], short: 'cm', name: 'centimeters' },
		m: { mm: [1000n, 1n], short: 'm', name: 'meters' },
		in: { mm: [127n, 5n], short: 'in', name: 'inches' },
		ft: { mm: [1524n, 5n], short: 'ft', name: 'feet' },
		g: { mm: [1n, 1n], short: 'g', name: 'grams', dim: 'mass' },
		kg: { mm: [1000n, 1n], short: 'kg', name: 'kilograms', dim: 'mass' },
		lb: { mm: [45359237n, 100000n], short: 'lb', name: 'pounds', dim: 'mass' },
		oz: { mm: [45359237n, 1600000n], short: 'oz', name: 'ounces', dim: 'mass' }
	};
	var MAX_DP = 6;
	var MAX_VALUE = 100000n;

	/* ---------- rational math ---------- */
	function abs(a) { return a < 0n ? -a : a; }
	function gcd(a, b) { a = abs(a); b = abs(b); while (b) { var t = a % b; a = b; b = t; } return a || 1n; }
	function reduce(n, d) { if (d < 0n) { n = -n; d = -d; } var g = gcd(n, d); return [n / g, d / g]; }
	function mul(a, b) { return reduce(a[0] * b[0], a[1] * b[1]); }
	function div(a, b) { return mul(a, [b[1], b[0]]); }
	function convert(r, from, to) { return div(mul(r, UNITS[from].mm), UNITS[to].mm); }
	function toFloat(r) { return Number(r[0]) / Number(r[1]); }
	function pow10(n) { return 10n ** BigInt(n); }

	function parse(raw) {
		var s = String(raw).toLowerCase().trim()
			.replace(/⁄/g, '/').replace(/[″"”]/g, '')
			.replace(/inches|inch|in|mm|cm|kg|lbs|lb|oz|g/g, '').trim();
		var m, r;
		if (!s) return null;
		if ((m = s.match(/^(\d{1,6})[\s-]+(\d{1,4})\/(\d{1,4})$/))) {
			if (+m[3] === 0) return null;
			r = reduce(BigInt(m[1]) * BigInt(m[3]) + BigInt(m[2]), BigInt(m[3]));
		} else if ((m = s.match(/^(\d{1,6})\/(\d{1,4})$/))) {
			if (+m[2] === 0) return null;
			r = reduce(BigInt(m[1]), BigInt(m[2]));
		} else if ((m = s.match(/^(\d{0,6})(?:[.,](\d{1,6}))?$/)) && (m[1] !== '' || m[2] !== undefined)) {
			var frac = m[2] || '';
			r = reduce(BigInt((m[1] || '0') + frac), pow10(frac.length));
		} else {
			return null;
		}
		if (r[0] > MAX_VALUE * r[1]) return null;
		return r;
	}

	function decimal(r, dp) {
		var pow = pow10(dp), q = r[0] * pow;
		var exact = q % r[1] === 0n;
		var i = (2n * q + r[1]) / (2n * r[1]);
		var whole = i / pow, rest = i % pow;
		var text = whole.toLocaleString('en-US');
		if (dp > 0 && rest > 0n) text += '.' + rest.toString().padStart(dp, '0').replace(/0+$/, '');
		return { text: text, exact: exact };
	}
	function exactDecimal(r) {
		for (var dp = 0; dp <= MAX_DP; dp++) { var d = decimal(r, dp); if (d.exact) return d.text; }
		return null;
	}
	function fractionText(k, den, mark) {
		if (mark === undefined) mark = '″';
		var f = reduce(k, den), whole = f[0] / f[1], rest = f[0] % f[1];
		if (rest === 0n) return whole + mark;
		return (whole ? whole + ' ' : '') + rest + '/' + f[1] + mark;
	}
	function nearest(r, den) {
		den = BigInt(den);
		var k = (2n * r[0] * den + r[1]) / (2n * r[1]);
		var diff = r[0] * den - k * r[1];
		return { k: k, den: den, cmp: diff > 0n ? 1 : (diff < 0n ? -1 : 0), text: fractionText(k, den), value: reduce(k, den) };
	}
	function best(r, dens) {
		var pick = null;
		for (var i = 0; i < dens.length; i++) {
			var den = BigInt(dens[i]), f = nearest(r, dens[i]);
			if (128n * abs(r[0] * den - f.k * r[1]) <= den * r[1]) return f;
			pick = f;
		}
		return pick;
	}
	function diffMm(rIn, fv) {
		var d = reduce(abs(rIn[0] * fv[1] - fv[0] * rIn[1]), rIn[1] * fv[1]);
		var t = decimal(mul(d, [127n, 5n]), 2).text;
		return (t === '0' ? '<0.01' : t) + ' mm';
	}
	function valueText(r, unit) {
		if (unit === 'in' && (64n * r[0]) % r[1] === 0n && r[0] % r[1] !== 0n) return fractionText((64n * r[0]) / r[1], 64n);
		var ex = exactDecimal(r);
		var t = ex === null ? decimal(r, 4).text : ex;
		return t + (unit === 'in' ? '″' : ' ' + UNITS[unit].short);
	}
	function decWithSign(r, dp, short) {
		var d = decimal(r, dp);
		for (var p = dp + 1; d.text === '0' && r[0] > 0n && p <= MAX_DP; p++) d = decimal(r, p);
		return [d.exact ? '=' : '≈', d.text + ' ' + short];
	}

	/* ---------- output ---------- */
	function esc(s) {
		return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}
	function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

	function results(r, from, to, dp, dens) {
		if (!r) return '<p class="dv-r__err">Enter a number such as 8, 8.5 or 5/16.</p>';
		var res = convert(r, from, to), name = valueText(r, from);
		var sv = decWithSign(res, dp, UNITS[to].short);
		var rows = [[esc(cap(UNITS[to].name)), '<b>' + esc(sv[0] + ' ' + sv[1]) + '</b>']];
		if (to === 'in') {
			dens.forEach(function (den) {
				var f = nearest(res, den);
				var d = f.cmp === 0 ? 'exact' : name + ' is ' + diffMm(res, f.value) + ' ' + (f.cmp > 0 ? 'longer' : 'shorter');
				rows.push([esc('Nearest 1/' + den + '″'), '<b>' + esc(f.text) + '</b> <span class="dv-r__diff">' + esc(d) + '</span>']);
			});
		}
		if (to === 'lb') {
			var oz = mul(res, [16n, 1n]), whole = oz[0] / (16n * oz[1]);
			var rest = reduce(oz[0] - whole * 16n * oz[1], oz[1]), dd = decimal(rest, 1);
			if (dd.text === '16') { whole += 1n; dd = { text: '0', exact: false }; }
			rows.push(['Pounds and ounces', '<b>' + esc((dd.exact ? '' : '≈ ') + whole.toLocaleString('en-US') + ' lb ' + dd.text + ' oz') + '</b>']);
		}
		return '<dl class="dv-r">' + rows.map(function (x) { return '<div><dt>' + x[0] + '</dt><dd>' + x[1] + '</dd></div>'; }).join('') + '</dl>';
	}

	/* ---------- visual (mirror of DV_Visual) ---------- */
	var W = 640, PAD = 24, BY = 46, BH = 100;
	function rangeMm(mm) {
		var target = Math.max(5, mm / 0.75), steps = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8];
		for (var p = 0; p < 9; p++) for (var i = 0; i < steps.length; i++) { var v = steps[i] * Math.pow(10, p); if (v >= target) return v; }
		return target;
	}
	function firstFit(list, scale, min) { for (var i = 0; i < list.length; i++) if (list[i] * scale >= min) return list[i]; return list[list.length - 1]; }
	function n(v) { return (+v.toFixed(2)).toString(); }
	function fmod(a, b) { return a - b * Math.floor(a / b); }

	function svg(mm, top, bottom, topUnit) {
		var dv = topUnit === 'cm' ? 10 : 1;
		mm = Math.max(0, mm);
		var range = rangeMm(mm), usable = W - 2 * PAD, s = usable / range, x0 = PAD, yT = BY, yB = BY + BH, o = [];
		var xv = x0 + Math.min(mm, range) * s;
		o.push('<rect class="dv-v-body" x="' + n(x0 - 8) + '" y="' + yT + '" width="' + n(usable + 16) + '" height="' + BH + '" rx="6"/>');
		if (mm > 0) o.push('<rect class="dv-v-band" x="' + x0 + '" y="' + yT + '" width="' + n(xv - x0) + '" height="' + BH + '"/>');

		var mmMinor = firstFit([0.1, 0.2, 0.5, 1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000], s, 5);
		var mmLabel = firstFit([1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000, 20000, 50000], s, 46);
		var count = Math.round(range / mmMinor), ticks = '', labels = '', i, v, x, h;
		for (i = 0; i <= count; i++) {
			v = i * mmMinor; x = n(x0 + v * s);
			var isL = Math.abs(fmod(v + 1e-9, mmLabel)) < 1e-6, isH = Math.abs(fmod(v + 1e-9, mmLabel / 2)) < 1e-6;
			h = isL ? 18 : (isH ? 12 : 7);
			ticks += 'M' + x + ' ' + yT + 'v' + h;
			if (isL && v * s < usable - 40) labels += '<text x="' + x + '" y="' + (yT + 32) + '">' + n(v / dv) + '</text>';
		}
		o.push('<path class="dv-v-tick" d="' + ticks + '"/>', '<g class="dv-v-lab">' + labels + '</g>');
		o.push('<text class="dv-v-unit" x="' + n(x0 + usable + 2) + '" y="' + (yT + 32) + '" text-anchor="end">' + (topUnit === 'cm' ? 'cm' : 'mm') + '</text>');

		var inS = 25.4 * s, inRange = range / 25.4;
		var inMinor = firstFit([1 / 64, 1 / 32, 1 / 16, 1 / 8, 1 / 4, 1 / 2, 1, 2, 3, 6, 12, 24, 60, 120, 360], inS, 5);
		var inLabel = firstFit([1 / 32, 1 / 16, 1 / 8, 1 / 4, 1 / 2, 1, 2, 3, 6, 12, 24, 60, 120, 360, 1200], inS, 46);
		count = Math.floor(inRange / inMinor + 1e-9); ticks = ''; labels = '';
		var lv = [[1, 18], [0.5, 14], [0.25, 11], [0.125, 8]];
		for (i = 0; i <= count; i++) {
			v = i * inMinor; x = n(x0 + v * inS); h = 6;
			for (var j = 0; j < lv.length; j++) if (Math.abs(v / lv[j][0] - Math.round(v / lv[j][0])) < 1e-6) { h = lv[j][1]; break; }
			ticks += 'M' + x + ' ' + yB + 'v-' + h;
			if (Math.abs(v / inLabel - Math.round(v / inLabel)) < 1e-6 && v * inS < usable - 40) labels += '<text x="' + x + '" y="' + (yB - 24) + '">' + esc(fractionText(BigInt(Math.round(v * 64)), 64n, '')) + '</text>';
		}
		o.push('<path class="dv-v-tick" d="' + ticks + '"/>', '<g class="dv-v-lab">' + labels + '</g>');
		o.push('<text class="dv-v-unit" x="' + n(x0 + usable + 2) + '" y="' + (yB - 24) + '" text-anchor="end">in</text>');

		if (mm > 0) {
			var dy = 24, wide = (xv - x0) > 90, tx = wide ? n((x0 + xv) / 2) : n(xv + 8), anchor = wide ? '' : ' text-anchor="start"';
			o.push('<path class="dv-v-edge" d="M' + n(xv) + ' ' + (yT - 2) + 'V' + (yB + 2) + '"/>');
			o.push('<path class="dv-v-dim" d="M' + x0 + ' ' + (dy - 6) + 'v12M' + x0 + ' ' + dy + 'H' + n(xv) + 'M' + n(xv) + ' ' + (dy - 6) + 'v12"/>');
			o.push('<text class="dv-v-top" x="' + tx + '" y="' + (dy - 10) + '"' + anchor + '>' + esc(top) + '</text>');
			o.push('<text class="dv-v-bottom" x="' + tx + '" y="' + (yB + 22) + '"' + anchor + '>' + esc(bottom) + '</text>');
		}
		return '<svg class="dv-v" viewBox="0 0 ' + W + ' 176" role="img" aria-label="' + esc('Ruler showing ' + (top + ' ' + bottom).trim()) + '">' + o.join('') + '</svg>';
	}
	function caption(mm, topUnit) {
		var s = (W - 2 * PAD) / rangeMm(Math.max(0, mm));
		return (s > 3.78 ? 'Enlarged view' : 'Scaled-down view') + ', not actual size. Top scale: ' + (topUnit === 'cm' ? 'centimeters' : 'millimeters') + '. Bottom scale: inches.';
	}

	/* ---------- wiring ---------- */
	function initTool(el) {
		var from = el.getAttribute('data-from'), to = el.getAttribute('data-to');
		if (!UNITS[from] || !UNITS[to] || (UNITS[from].dim || 'len') !== (UNITS[to].dim || 'len')) return;
		var dp = parseInt(el.getAttribute('data-dp'), 10) || 4;
		var dens = (el.getAttribute('data-fractions') || '16,32,64').split(',').map(Number).filter(Boolean);
		var input = el.querySelector('.dv-conv__input'), out = el.querySelector('.dv-conv__out');
		var label = el.querySelector('.dv-conv__label'), unit = el.querySelector('.dv-conv__unit');
		var swap = el.querySelector('.dv-conv__swap'), fig = el.querySelector('.dv-visual');
		var topUnit = fig ? fig.getAttribute('data-top') || 'mm' : 'mm';
		if (!input || !out) return;

		function update() {
			var r = parse(input.value);
			out.innerHTML = results(r, from, to, dp, dens);
			if (fig && r) {
				var rIn = convert(r, from, 'in'), b = best(rIn, [16, 32, 64]), mm = toFloat(convert(r, from, 'mm'));
				var top = valueText(convert(r, from, topUnit), topUnit);
				var bottom = (b.cmp === 0 ? '= ' : '≈ ') + (b.k > 0n ? b.text : decimal(rIn, 3).text + ' in');
				fig.innerHTML = svg(mm, top, bottom, topUnit) + '<figcaption>' + esc(caption(mm, topUnit)) + '</figcaption>';
			}
		}
		function relabel() {
			label.textContent = cap(UNITS[from].name) + ' (' + UNITS[from].short + ')';
			unit.textContent = UNITS[from].short;
			swap.querySelector('span').textContent = UNITS[to].short + ' to ' + UNITS[from].short;
		}
		var t;
		input.addEventListener('input', function () { clearTimeout(t); t = setTimeout(update, 60); });
		if (swap) {
			swap.hidden = false;
			swap.addEventListener('click', function () {
				var x = from; from = to; to = x;
				relabel(); update(); input.focus();
			});
		}
		// Script may load late (e.g. WP Rocket delay): pick up anything already typed.
		if (input.value !== input.defaultValue) update();
	}

	function initChart(el) {
		var btn = el.querySelector('.dv-chart__print');
		if (!btn) return;
		btn.hidden = false;
		btn.addEventListener('click', function () {
			document.documentElement.classList.add('dv-printing');
			el.classList.add('dv-print-me');
			var done = function () {
				document.documentElement.classList.remove('dv-printing');
				el.classList.remove('dv-print-me');
				window.removeEventListener('afterprint', done);
			};
			window.addEventListener('afterprint', done);
			window.print();
		});
	}

	function boot() {
		if (typeof BigInt === 'undefined') return; // Very old browsers keep the server-rendered result.
		Array.prototype.forEach.call(document.querySelectorAll('.dv-tool'), initTool);
		Array.prototype.forEach.call(document.querySelectorAll('.dv-chart'), initChart);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
