// Накладнойды файлдан жүктеу.
// Excel (xlsx, xls, csv) браузерде оқылады. Фото мен PDF-ті Claude оқиды (/waybill/ai.php, API кілті серверде).
// Google Drive қосулы болса, файл алдымен Drive-қа жүктеледі (gdrive_up.js), сервер оны сонда алады;
// қосылмаса, файл тікелей ai.php-ге жіберіледі (серверде сақталмайды).
// Claude қосылмаса, мәтіні бар PDF бұрынғыдай браузерде оқылады.
// Тауарлар тексеру терезесінде көрсетіледі: артығын алып тастайсыз, қалғаны формаға қосылады. Базаға әлі ештеңе жазылмайды.
(function ($) {
	var $doc = $('.wb_doc');
	if (!$doc.length || $doc.data('posted') == 1) return;

	var LIB = {
		xlsx: '/assets/pl/xlsx/xlsx.full.min.js',
		pdf: '/assets/pl/pdfjs/pdf.min.js',
		worker: '/assets/pl/pdfjs/pdf.worker.min.js',
		gd: '/assets/js/gdrive_up.js'
	};
	var aiOn = !!window.wb_ai_on; // Claude қосулы (config/ai.php ішінде API кілті бар, item.php)
	var gdOn = !!window.wb_gd_on; // Google Drive қосулы: файл сонда да сақталады
	var wbFiles = []; // Drive-қа жүктелген файлдар: накладной сақталғанда соған байланады
	var say = function (t) { if (typeof mess == 'function') mess(t); else alert(t); };
	var h = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); };
	var r2 = function (n) { return Math.round(n * 100) / 100; };
	var r3 = function (n) { return Math.round(n * 1000) / 1000; };
	var money = function (n) {
		n = r2(n);
		var s = (Math.floor(n) == n ? n.toFixed(0) : n.toFixed(2));
		return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₸';
	};
	var raw = window.wb_raw || [];
	var CMP = $doc.data('cmp') || 0;

	// ---------- Кітапханаларды керек кезде ғана жүктеу ----------
	var loaded = {};
	function load(src) {
		if (!loaded[src]) loaded[src] = new Promise(function (ok, fail) {
			var s = document.createElement('script');
			s.src = src; s.onload = ok;
			s.onerror = function () { delete loaded[src]; fail(new Error('Кітапхана жүктелмеді: ' + src)); };
			document.head.appendChild(s);
		});
		return loaded[src];
	}

	// ---------- Көмекші функциялар ----------
	function low(s) { return String(s == null ? '' : s).toLowerCase().replace(/ё/g, 'е').replace(/\s+/g, ' ').trim(); }
	function norm(s) { return low(s).replace(/[^0-9a-zа-яәіңғүұқөһ]+/gi, ' ').replace(/\s+/g, ' ').trim(); }

	// "1 234,50" → 1234.5 ; сан болмаса null
	function toNum(v) {
		if (typeof v == 'number') return isFinite(v) ? v : null;
		var s = String(v == null ? '' : v).replace(/[\s  ]/g, '').replace(/[₸тг]+\.?$/i, '');
		if (!/^-?\d+([.,]\d+)*$/.test(s)) return null;
		var c = s.lastIndexOf(','), d = s.lastIndexOf('.');
		if (c > -1 && d > -1) s = c > d ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
		else if (c > -1) s = (s.split(',').length > 2) ? s.replace(/,/g, '') : s.replace(',', '.');
		else if (s.split('.').length > 2) s = s.replace(/\./g, '');
		var n = parseFloat(s);
		return isNaN(n) ? null : n;
	}
	function isNum(v) { return toNum(v) !== null; }

	var UNITS = [
		['кг', /^(кг|кгр|килограмм\w*|kg)$/],
		['г', /^(г|гр|грамм\w*|g)$/],
		['л', /^(л|лит|литр\w*|l|lt)$/],
		['мл', /^(мл|ml)$/],
		['шт', /^(шт|штук\w*|штк|дана|ед|единиц\w*|pcs|pc)$/],
		['қап', /^(қап|мешок|меш|мешк\w*)$/],
		['қорап', /^(қорап|коробк\w*|кор|ящик\w*|кейс)$/],
		['бума', /^(бума|уп|упак\w*|пачк\w*|пач|блок)$/]
	];
	function unitOf(s) {
		var u = low(s).replace(/\./g, '').trim();
		if (!u) return '';
		for (var i = 0; i < UNITS.length; i++) if (UNITS[i][1].test(u)) return UNITS[i][0];
		return '';
	}
	var TOTAL_RX = /^(итого|итог|всего|барлығы|жиыны|жиыны|total)/;

	// ---------- Тауар атауын шикізатпен сәйкестендіру ----------
	function bigrams(s) {
		var r = {}, t = ' ' + s + ' ';
		for (var i = 0; i < t.length - 1; i++) { var b = t.substr(i, 2); r[b] = (r[b] || 0) + 1; }
		return r;
	}
	function dice(a, b) {
		var A = bigrams(a), B = bigrams(b), n = 0, ca = 0, cb = 0, k;
		for (k in A) { ca += A[k]; if (B[k]) n += Math.min(A[k], B[k]); }
		for (k in B) cb += B[k];
		return ca + cb ? 2 * n / (ca + cb) : 0;
	}
	function score(a, b) {
		if (!a || !b) return 0;
		if (a == b) return 1;
		var s = dice(a, b);
		var ta = a.split(' ').filter(function (w) { return w.length > 1 && !/^\d+$/.test(w); });
		var tb = b.split(' ').filter(function (w) { return w.length > 1 && !/^\d+$/.test(w); });
		var sh = ta.length <= tb.length ? ta : tb, lg = ta.length <= tb.length ? tb : ta;
		if (sh.length) {
			var all = sh.every(function (w) { return lg.some(function (x) { return x == w || (w.length > 3 && x.indexOf(w) == 0) || (x.length > 3 && w.indexOf(x) == 0); }); });
			if (all) s = Math.max(s, sh.length == lg.length ? 0.95 : 0.8);
		}
		return s;
	}
	// бұрын қолмен таңдалған сәйкестіктер (осы браузерде сақталады)
	var MEM_KEY = 'wb_imp_map_' + CMP;
	function memGet() { try { return JSON.parse(localStorage.getItem(MEM_KEY) || '{}') || {}; } catch (e) { return {}; } }
	function memSet(m) { try { localStorage.setItem(MEM_KEY, JSON.stringify(m)); } catch (e) {} }
	// қазақша және орысша атауы бойынша іздейміз
	var rawNorm = [];
	raw.forEach(function (r) { rawNorm.push({ r: r, n: norm(r.n) }); if (r.r) rawNorm.push({ r: r, n: norm(r.r) }); });
	function rawById(id) { for (var i = 0; i < raw.length; i++) if (raw[i].id == id) return raw[i]; return null; }
	function rawByName(name) {
		var n = low(name);
		for (var i = 0; i < raw.length; i++) if (low(raw[i].n) == n) return raw[i];
		for (i = 0; i < raw.length; i++) if (raw[i].r && low(raw[i].r) == n) return raw[i];
		return null;
	}
	// жеткізуші тауары → шикізат, 1 шт = f (supplier_item кестесінен)
	var aliasNorm = {};
	(window.wb_alias || []).forEach(function (a) { aliasNorm[norm(a.n)] = a; });
	function aliasOf(name) { return aliasNorm[norm(name)] || null; }

	// атаудағы қаптама көлемі: "190гр" → 0.19 кг, "5 кг", "1000гр", "100 листов"
	function packFactor(name, ru) {
		var s = low(name).replace(/(\d),(\d)/g, '$1.$2'), m, w = null, v = null, c = null;
		if ((m = s.match(/(\d+(?:\.\d+)?)\s*(кг|kg)(?![а-яa-z])/))) w = +m[1];
		else if ((m = s.match(/(\d+(?:\.\d+)?)\s*(гр|г|g)(?![а-яa-z])/))) w = m[1] / 1000;
		if ((m = s.match(/(\d+(?:\.\d+)?)\s*(мл|ml)(?![а-яa-z])/))) v = m[1] / 1000;
		else if ((m = s.match(/(\d+(?:\.\d+)?)\s*(л|l|литр\S*)(?![а-яa-z])/))) v = +m[1];
		if ((m = s.match(/(\d+)\s*(листов|лист|шт|pcs|дана)/))) c = +m[1];
		var r = { 'кг': w, 'г': w && w * 1000, 'л': v, 'мл': v && v * 1000, 'шт': c }[ru];
		return r > 0 ? r3(r) : null;
	}

	function match(name) {
		var n = norm(name), mem = memGet();
		var al = aliasNorm[n];
		if (al) { var ar = rawById(al.id); if (ar) return { r: ar, s: 1, al: al }; }
		if (mem[n]) { var m = rawById(mem[n]); if (m) return { r: m, s: 1, mem: true }; }
		var best = null, bs = 0;
		for (var i = 0; i < rawNorm.length; i++) {
			var s = score(n, rawNorm[i].n);
			if (s > bs) { bs = s; best = rawNorm[i].r; }
		}
		return best && bs >= 0.55 ? { r: best, s: bs } : null;
	}

	// ---------- Кестені талдау (Excel торы немесе PDF жолдары) ----------
	var ROLE = {
		name: /(наим|товар|номенклатур|атау|продукц|описан|тауар|материал|название)/,
		unit: /(ед[^а-я]*изм|единиц|өлшем|^ед\.?$|изм\.)/,
		qty: /(кол-?\s*во|количеств|колич|саны|отпущ|qty)/,
		price: /(цена|баға|price)/,
		sum: /(сумма|сома|стоимост|amount)/
	};
	function headerText(grid, r, c) {
		var a = grid[r] ? grid[r][c] : '', b = grid[r + 1] ? grid[r + 1][c] : '';
		return low((a == null ? '' : a) + ' ' + (isNum(b) ? '' : (b == null ? '' : b)));
	}
	// бағандарды тақырып бойынша табу
	function findHeader(grid) {
		var best = null;
		var lim = Math.min(grid.length, 60);
		for (var r = 0; r < lim; r++) {
			var row = grid[r] || [], cols = {}, n = 0, w = row.length;
			if (row.filter(function (v) { return v !== '' && v != null; }).length < 2) continue;
			if (grid[r + 1] && grid[r + 1].length > w) w = grid[r + 1].length;
			var cand = { name: [], unit: [], qty: [], price: [], sum: [] };
			for (var c = 0; c < w; c++) {
				var t = headerText(grid, r, c);
				if (!t) continue;
				for (var k in cand) if (ROLE[k].test(t)) cand[k].push({ c: c, t: t });
			}
			// бір бағанға бір рөл: атауы, саны, бағасы, сомасы
			var used = {};
			var pick = function (k, pref, avoid) {
				var list = cand[k].filter(function (x) { return !used[x.c]; });
				if (!list.length) return;
				var p = pref ? list.filter(function (x) { return pref.test(x.t); }) : [];
				if (!p.length && avoid) p = list.filter(function (x) { return !avoid.test(x.t); });
				var x = (p.length ? p : list)[0];
				cols[k] = x.c; used[x.c] = 1; n++;
			};
			pick('price', /с ндс|ққс/, /без ндс|ндс[^с]*$/);
			pick('sum', /с ндс|ққс/, /^сумма ндс|ндс$|без ндс/);
			pick('qty', /отпущ|факт/, /цена|сумма/);
			pick('unit', null, null);
			pick('name', /наимен|атау|товар|тауар/, /код|номенклатурн\w* ном/);
			// саны жоқ болса, бұл баға тізімі: санын адам өзі жазады
			if (cols.name == null || (cols.qty == null && cols.price == null)) continue;
			if (!best || n > best.n) best = { r: r, cols: cols, n: n };
		}
		if (!best) return null;
		best.start = best.r + 1; // тақырыптың 2-жолы, бағана нөмірлері т.б. өзі өтіп кетеді
		best.noQty = best.cols.qty == null;
		// өлшем бағанының тақырыбы бос болса, мазмұнынан табу (кг, шт ...)
		if (best.cols.unit == null) {
			var used = {}, w = 0, k;
			for (k in best.cols) used[best.cols[k]] = 1;
			grid.slice(best.start, best.start + 60).forEach(function (rw) { if (rw.length > w) w = rw.length; });
			for (var c = 0; c < w; c++) {
				if (used[c]) continue;
				var all = 0, un = 0;
				grid.slice(best.start, best.start + 60).forEach(function (rw) { var v = rw[c]; if (v === '' || v == null) return; all++; if (unitOf(v)) un++; });
				if (all >= 2 && un / all >= 0.6) { best.cols.unit = c; break; }
			}
		}
		return best;
	}

	// бір жолды тақырып бойынша оқу
	function rowByCols(row, cols) {
		var g = function (k) { return cols[k] == null ? '' : row[cols[k]]; };
		var name = String(g('name') == null ? '' : g('name')).replace(/\s+/g, ' ').trim();
		if (!name || isNum(name)) return null;
		var qty = toNum(g('qty')), price = toNum(g('price')), sum = toNum(g('sum'));
		if (cols.qty == null) {
			if (!price || price <= 0) return null;
			return { name: name, unitTxt: String(g('unit') || ''), qty: 0, price: price, sum: 0, noQty: true };
		}
		if (!qty || qty <= 0) return null;
		if ((price == null || price === 0) && sum) price = sum / qty;
		if (price == null) price = 0;
		if (sum == null) sum = qty * price;
		return { name: name, unitTxt: String(g('unit') || ''), qty: qty, price: price, sum: sum };
	}

	// тақырып табылмаса: «саны × бағасы = сомасы» болатын үш санды іздеу
	function rowByMath(cells, emptyName) {
		cells = cells.map(function (v) { return v == null ? '' : (typeof v == 'number' ? v : String(v).replace(/\s+/g, ' ').trim()); })
			.filter(function (v) { return v !== ''; });
		if (cells.length < 4) return null;
		// жол басындағы реттік нөмір: "1 Күріш" → "1", "Күріш"
		if (typeof cells[0] == 'string') {
			var m = cells[0].match(/^(\d{1,4})[.)]?\s+(\D.*)$/);
			if (m) cells.splice(0, 1, m[1], m[2]);
		}
		var nums = [];
		cells.forEach(function (v, i) {
			var n = toNum(v);
			if (n === null) return;
			if (i == 0 && cells.length > 1 && !isNum(cells[1]) && n == Math.round(n)) return; // реттік нөмір
			if (typeof v == 'string' && /^0\d/.test(v.trim())) return; // код: 00012
			nums.push({ i: i, v: n });
		});
		if (nums.length < 3) return null;
		var hit = null;
		var ok = function (a, b, c) { return a.v > 0 && b.v > 0 && c.v > 0 && Math.abs(a.v * b.v - c.v) <= Math.max(0.06, c.v * 0.006); };
		for (var gap = 1; gap <= 2 && !hit; gap++)
			for (var x = 0; x < nums.length - 2 && !hit; x++)
				for (var y = x + 1; y < nums.length - 1 && y <= x + gap && !hit; y++)
					for (var z = y + 1; z < nums.length && z <= y + gap && !hit; z++)
						if (ok(nums[x], nums[y], nums[z])) hit = [nums[x], nums[y], nums[z]];
		if (!hit) return null;
		var qi = hit[0].i, unitTxt = '', parts = [];
		for (var i = 0; i < qi; i++) {
			var v = cells[i];
			if (typeof v == 'number' || isNum(v)) continue;
			if (unitOf(v)) { unitTxt = v; continue; }
			if (/^[A-ZА-Я0-9\-\/]{5,}$/.test(v) && /\d/.test(v)) continue; // код, артикул
			parts.push(v);
		}
		if (!unitTxt) for (var j = qi + 1; j < hit[1].i; j++) if (unitOf(cells[j])) unitTxt = cells[j];
		var name = parts.join(' ').trim();
		if (!name && !emptyName) return null;
		return { name: name, unitTxt: unitTxt, qty: hit[0].v, price: hit[1].v, sum: hit[2].v };
	}

	function isTotalRow(cells) {
		for (var i = 0; i < cells.length; i++) {
			var v = cells[i];
			if (typeof v == 'string' && TOTAL_RX.test(low(v))) return true;
		}
		return false;
	}

	// жеткізуші, нөмір, күн
	function findMeta(grid) {
		var meta = { supplier: '', docNo: '', date: '' };
		var SUP = /(поставщик|грузоотправител|отправител|продавец|жеткізуші|жеберуші|исполнитель)/;
		var NOT = /(получател|покупател|заказчик|алушы)/;
		var cut = function (s) {
			return String(s).replace(/[\s,;(]+(бин|иин|жсн|бсн)(?=[\s\/:№]|$).*$/i, '').replace(/,?\s*\d{12}.*$/, '').replace(/[,;]\s*$/, '').replace(/\s+/g, ' ').trim().substr(0, 120);
		};
		var lim = Math.min(grid.length, 40);
		for (var r = 0; r < lim; r++) {
			var row = grid[r] || [];
			for (var c = 0; c < row.length; c++) {
				var v = row[c]; if (v == null || v === '') continue;
				var t = low(v);
				if (!meta.supplier && SUP.test(t) && !NOT.test(t)) {
					var after = String(v).replace(/^.*?(поставщик|грузоотправитель|отправитель|продавец|жеткізуші|жеберуші|исполнитель)\)?\s*[:\-–]?\s*/i, '');
					var val = after && after.length > 2 && !SUP.test(low(after)) ? after : '';
					for (var cc = c + 1; !val && cc < row.length; cc++) {
						var x = row[cc];
						if (x != null && x !== '' && String(x) !== String(v) && !isNum(x) && !/^(бин|иин|жсн|бсн)/i.test(String(x)) && String(x).length > 2) val = String(x);
					}
					if (val) meta.supplier = cut(val);
				}
				if (!meta.docNo && /номер документа|құжат нөмірі/.test(t)) {
					for (var rr = r + 1; rr <= r + 2 && !meta.docNo; rr++) {
						var y = grid[rr] && grid[rr][c];
						if (y != null && y !== '' && !/\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}/.test(String(y))) meta.docNo = String(y).trim();
					}
				}
				if (!meta.docNo) {
					var m = String(v).match(/(накладн\w*|счет[\s-]*фактур\w*|документ)[^№]{0,40}№\s*([A-Za-zА-Яа-я0-9\-\/]+)/i);
					if (m) meta.docNo = m[2];
				}
				if (!meta.date) {
					var d = String(v).match(/\b(\d{1,2})[.\/](\d{1,2})[.\/](\d{4}|\d{2})\b/);
					if (d && (/дата|от\s|күні/.test(t) || (grid[r - 1] && grid[r - 1].some(function (z) { return /дата|күні/.test(low(z)); })))) {
						var Y = d[3].length == 2 ? '20' + d[3] : d[3];
						meta.date = Y + '-' + ('0' + d[2]).slice(-2) + '-' + ('0' + d[1]).slice(-2);
					}
				}
			}
		}
		return meta;
	}

	// 1 2 3 4 5 ... (бағана нөмірлері)
	function isSeqRow(row) {
		var v = row.filter(function (x) { return x !== '' && x != null; });
		if (v.length < 3) return false;
		for (var i = 0; i < v.length; i++) if (toNum(v[i]) !== i + 1) return false;
		return true;
	}

	// PDF: әр жол y бойынша. Атауы екі жолға бөлінсе, жақын тұрған мәтін жолын сандары бар жолға қосамыз
	function extractPdf(grid) {
		var lines = grid.map(function (row) {
			if (isTotalRow(row)) return { t: 'stop', row: row };
			if (isSeqRow(row)) return { t: 'stop', row: row };
			var it = rowByMath(row, true);
			if (it) { it.pre = []; it.post = []; return { t: 'item', it: it, row: row }; }
			var full = row.filter(function (v) { return v !== '' && v != null; });
			if (full.length && full.length <= 2 && full.every(function (v) { return !isNum(v); }) && full.join(' ').length < 80)
				return { t: 'text', row: row, s: full.join(' ') };
			return { t: 'stop', row: row };
		});
		lines.forEach(function (L, i) {
			if (L.t != 'text') return;
			var best = null, bd = 1e9, fh = L.row.fh || 9;
			[-1, 1].forEach(function (dir) {
				for (var j = i + dir; j >= 0 && j < lines.length && Math.abs(j - i) <= 3; j += dir) {
					var M = lines[j];
					if (M.row.pg !== L.row.pg || M.t == 'stop') break;
					if (M.t != 'item') continue;
					var d = Math.abs(M.row.y - L.row.y);
					if (d < bd) { bd = d; best = M; }
					break;
				}
			});
			if (best && bd <= fh * 1.35) (L.row.y > best.row.y ? best.it.pre : best.it.post).push(L.s);
		});
		var items = [];
		lines.forEach(function (L) {
			if (L.t != 'item') return;
			var it = L.it;
			it.name = it.pre.concat(it.name ? [it.name] : [], it.post).join(' ').replace(/\s+/g, ' ').trim();
			delete it.pre; delete it.post;
			if (it.name && !isNum(it.name)) items.push(it);
		});
		return items;
	}

	// торды тауарлар тізіміне айналдыру
	function extract(grid, hdr, mode) {
		if (mode == 'math') return extractPdf(grid);
		var items = [], start = hdr ? hdr.start : 0, last = null, pend = [];
		// PDF: атауы келесі жолға ауысса, келесі тауар не «Итого» келгенде ғана қосамыз
		var flush = function () { if (last && pend.length) last.name += ' ' + pend.join(' '); pend = []; };
		for (var r = start; r < grid.length; r++) {
			var row = grid[r] || [];
			if (!row.length) continue;
			if (isTotalRow(row)) { flush(); last = null; continue; }
			var it = hdr ? rowByCols(row, hdr.cols) : rowByMath(row);
			if (it) { flush(); items.push(it); last = it; continue; }
			if (mode == 'math' && last) {
				var full = row.filter(function (v) { return v !== '' && v != null; });
				if (full.length == 1 && !isNum(full[0]) && String(full[0]).length < 60 && pend.length < 2) pend.push(String(full[0]).trim());
				else { pend = []; last = null; }
			}
		}
		return items;
	}

	// ---------- Файл оқу ----------
	function readExcel(file) {
		return load(LIB.xlsx).then(function () { return file.arrayBuffer(); }).then(function (buf) {
			var wbk;
			if (/\.(csv|txt)$/i.test(file.name)) {
				var text;
				try { text = new TextDecoder('utf-8', { fatal: true }).decode(buf); }
				catch (e) { text = new TextDecoder('windows-1251').decode(buf); }
				wbk = XLSX.read(text.replace(/^\ufeff/, ''), { type: 'string', raw: true });
			} else {
				wbk = XLSX.read(buf, { type: 'array', cellDates: true });
			}
			var sheets = wbk.SheetNames.map(function (sn) {
				var ws = wbk.Sheets[sn];
				// біріккен ұяшықтардың мәнін бүкіл аумаққа тарату (тақырып үшін)
				(ws['!merges'] || []).forEach(function (m) {
					var top = ws[XLSX.utils.encode_cell(m.s)];
					if (!top) return;
					for (var R = m.s.r; R <= m.e.r; R++) for (var C = m.s.c; C <= m.e.c; C++) {
						var a = XLSX.utils.encode_cell({ r: R, c: C });
						if ((!ws[a] || ws[a].v == null || ws[a].v === '') && typeof top.v == 'string') ws[a] = { t: 's', v: top.v };
					}
				});
				var grid = XLSX.utils.sheet_to_json(ws, { header: 1, raw: true, defval: '', blankrows: true });
				grid = grid.map(function (row) {
					return row.map(function (v) {
						if (v instanceof Date) return ('0' + v.getDate()).slice(-2) + '.' + ('0' + (v.getMonth() + 1)).slice(-2) + '.' + v.getFullYear();
						return typeof v == 'string' ? v.replace(/\s+/g, ' ').trim() : v;
					});
				});
				return { name: sn, grid: grid };
			});
			return { type: 'excel', sheets: sheets };
		});
	}

	function readPdf(file) {
		return load(LIB.pdf).then(function () {
			window.pdfjsLib.GlobalWorkerOptions.workerSrc = LIB.worker;
			return file.arrayBuffer();
		}).then(function (buf) {
			return window.pdfjsLib.getDocument({ data: new Uint8Array(buf) }).promise;
		}).then(function (pdf) {
			var pages = [];
			for (var p = 1; p <= pdf.numPages; p++) pages.push(pdf.getPage(p).then(function (pg) { return pg.getTextContent(); }));
			return Promise.all(pages);
		}).then(function (contents) {
			var grid = [], chars = 0;
			contents.forEach(function (tc, pgi) {
				var its = tc.items.filter(function (i) { return i.str && i.str.trim(); }).map(function (i) {
					var fh = Math.abs(i.transform[3]) || i.height || 8;
					chars += i.str.trim().length;
					return { s: i.str, x: i.transform[4], y: i.transform[5], w: i.width, fh: fh };
				});
				its.sort(function (a, b) { return b.y - a.y || a.x - b.x; });
				// жолдарға бөлу (y бойынша)
				var lines = [];
				its.forEach(function (it) {
					var L = lines.length ? lines[lines.length - 1] : null;
					if (L && Math.abs(L.y - it.y) <= Math.max(2, it.fh * 0.45)) L.items.push(it);
					else lines.push({ y: it.y, items: [it] });
				});
				lines.forEach(function (L) {
					L.items.sort(function (a, b) { return a.x - b.x; });
					// жақын тұрған мәтіндерді бір ұяшыққа біріктіру
					var cells = [], cur = null;
					L.items.forEach(function (it) {
						var gap = cur ? it.x - cur.x1 : 1e9;
						if (cur && gap < it.fh * 0.6) {
							cur.s += (gap > it.fh * 0.12 && !/\s$/.test(cur.s) ? ' ' : '') + it.s;
							cur.x1 = it.x + it.w;
						} else {
							cur = { s: it.s, x1: it.x + it.w };
							cells.push(cur);
						}
					});
					var row = cells.map(function (c) { return c.s.replace(/\s+/g, ' ').trim(); }).filter(function (s) { return s; });
					row.y = L.y; row.pg = pgi; row.fh = L.items[0].fh;
					grid.push(row);
				});
			});
			return { type: 'pdf', chars: chars, sheets: [{ name: 'PDF', grid: grid }] };
		});
	}

	// ---------- Терезе ----------
	var $m = null, state = null;

	function modal() {
		if ($m) return $m;
		$m = $(
			'<div class="wbi_bg dsp_n"><div class="wbi">' +
				'<div class="wbi_h"><div>Накладнойды файлдан жүктеу</div><div class="wbi_h_btns">' +
					(aiOn ? '<div class="wb_ic wbi_tech_btn" title="Claude туралы инфо (модель, шығын)"><i class="fal fa-info-circle"></i></div>' : '') +
					'<div class="wb_x wbi_close" title="Жабу"><i class="fal fa-times"></i></div></div></div>' +
				// Claude қосылған ба: белгі + «Тексеру» (серверден кішкентай сұрау жібереді)
				'<div class="wbi_ai' + (aiOn ? ' wbi_tech dsp_n' : '') + '" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 12px;font-size:13px">' +
					(aiOn
						? '<span style="color:#1a9b4b"><i class="fas fa-circle" style="font-size:8px"></i> Claude қосулы</span>' +
						  '<span class="wbi_ai_test" style="cursor:pointer;text-decoration:underline;color:#555">Тексеру</span>' +
						  '<span class="wbi_ai_res"></span>'
						: '<span style="color:#c0392b"><i class="fas fa-circle" style="font-size:8px"></i> Claude қосылмаған: фото оқылмайды, Excel мен PDF оқылады</span>') +
				'</div>' +
				'<label class="wbi_drop">' +
					'<input type="file" class="wbi_file" multiple accept=".xlsx,.xls,.xlsm,.csv,.pdf,application/pdf,image/jpeg,image/png,image/webp">' +
					'<i class="fal fa-file-import"></i>' +
					'<b>Файлды таңдаңыз немесе осында тастаңыз</b>' +
					'<span>' + (aiOn ? 'Фото, PDF немесе Excel. Фото мен PDF-ті Claude оқиды (ұзын чек болса, бірнеше фото таңдаңыз).' : 'Excel (xlsx, xls, csv) немесе PDF. Сканерленген (сурет) PDF оқылмайды.') + '</span>' +
				'</label>' +
				'<div class="wbi_body dsp_n">' +
					'<div class="wbi_info"></div>' +
					'<div class="wbi_map dsp_n"></div>' +
					'<div class="wbi_list"></div>' +
				'</div>' +
				'<div class="wbi_foot dsp_n">' +
					'<label class="wbi_chk"><input type="checkbox" class="wbi_replace" checked> Формадағы бар жолдарды ауыстыру</label>' +
					'<div class="wbi_btns">' +
						'<div class="wb_btn wb_btn_light wbi_again"><i class="fal fa-redo"></i> Басқа файл</div>' +
						'<div class="wb_btn wbi_apply"><i class="fal fa-check"></i> Накладнойға қосу</div>' +
						'<div class="wb_btn wb_btn_green wbi_apply wbi_apply_save"><i class="fal fa-save"></i> Қосып, сақтау</div>' +
					'</div>' +
				'</div>' +
			'</div></div>'
		).appendTo('body');

		$m.on('click', '.wbi_close', close);
		$m.on('click', function (e) { if (e.target === $m[0]) close(); });
		// техникалық инфо (Claude күйі, модель, шығын) әдепкіде жасырын, бұрыштағы (i) батырмасымен ашылады
		$m.on('click', '.wbi_tech_btn', function () {
			var on = !$(this).hasClass('wbi_tech_on');
			$(this).toggleClass('wbi_tech_on', on);
			$m.find('.wbi_tech').toggleClass('dsp_n', !on);
		});
		$m.on('click', '.wbi_note', function () { $(this).toggleClass('wbi_note_open'); });
		$m.on('click', '.wbi_ai_test', function () {
			var $r = $m.find('.wbi_ai_res').css('color', '#555').text('тексеріп жатыр...');
			$.ajax({ url: '/waybill/ai.php?ping', method: 'POST', dataType: 'json', timeout: 60000 }).then(function (d) {
				if (d && d.ok) $r.css('color', '#1a9b4b').text('✓ Claude жауап берді (' + d.model + ', ' + d.ms + ' мс)');
				else $r.css('color', '#c0392b').text('✗ ' + ((d && d.msg) || 'жауап жоқ'));
			}, function (x) { $r.css('color', '#c0392b').text('✗ сервер қатесі ' + (x && x.status || '')); });
		});
		$m.on('change', '.wbi_file', function () { if (this.files && this.files[0]) open(Array.prototype.slice.call(this.files)); this.value = ''; });
		$m.on('dragover', '.wbi_drop', function (e) { e.preventDefault(); $(this).addClass('wbi_drop_on'); });
		$m.on('dragleave drop', '.wbi_drop', function () { $(this).removeClass('wbi_drop_on'); });
		$m.on('drop', '.wbi_drop', function (e) {
			e.preventDefault();
			var f = e.originalEvent.dataTransfer.files; if (f && f[0]) open(Array.prototype.slice.call(f));
		});
		$m.on('click', '.wbi_again', function () { $m.find('.wbi_file').click(); });
		$m.on('change', '.wbi_sheet', function () { state.sheet = +$(this).val(); state.hdr = null; analyze(); });
		$m.on('change', '.wbi_col', function () {
			var k = $(this).data('k'), v = $(this).val();
			if (!state.hdr) state.hdr = { r: 0, start: 0, cols: {} };
			if (v === '') delete state.hdr.cols[k]; else state.hdr.cols[k] = +v;
			state.manual = true;
			build();
		});
		$m.on('input change', '.wbi_r_name', function () { badge($(this).closest('.wbi_row')); });
		$m.on('input', '.wbi_r_qty', function () {
			var $r = $(this).closest('.wbi_row'), it = state.items[$r.data('i')], q = toNum($.trim($(this).val())) || 0;
			it.qty = q > 0 ? q : 0;
			$r.find('.wbi_r_on').prop('checked', it.qty > 0);
			$r.find('.wbi_r_sum').text(it.qty ? money(it.qty * it.price) : '—');
			convText($r);
			sumUp();
		});
		$m.on('input', '.wbi_r_f', function () {
			var $r = $(this).closest('.wbi_row'), it = state.items[$r.data('i')], f = toNum($.trim($(this).val()));
			it.fUser = f > 0 ? f : 0; it.f = it.fUser;
			convText($r);
		});
		$m.on('keydown', '.wbi_r_qty', function (e) {
			if (e.key != 'Enter') return;
			e.preventDefault();
			$(this).closest('.wbi_row').nextAll('.wbi_row').first().find('.wbi_r_qty').focus();
		});
		$m.on('change', '.wbi_r_on, .wbi_all', function () {
			if ($(this).hasClass('wbi_all')) $m.find('.wbi_r_on').prop('checked', this.checked);
			sumUp();
		});
		$m.on('click', '.wbi_apply', apply);
		$(document).on('keydown.wbi', function (e) { if (e.key == 'Escape' && !$m.hasClass('dsp_n')) close(); });
		return $m;
	}

	function show() {
		modal().removeClass('dsp_n');
		$('body').addClass('wbi_lock');
	}
	function close() { if ($m) $m.addClass('dsp_n'); $('body').removeClass('wbi_lock'); }
	function reset() {
		$m.find('.wbi_drop').removeClass('dsp_n wbi_drop_wait');
		$m.find('.wbi_body, .wbi_foot').addClass('dsp_n');
	}

	function open(files) {
		modal();
		var list = Array.isArray(files) ? files : [files], file = list[0];
		var isPdf = /\.pdf$/i.test(file.name) || file.type == 'application/pdf';
		var isXls = /\.(xlsx|xls|xlsm|csv|txt)$/i.test(file.name);
		var isImg = /^image\/(jpeg|png|webp)$/.test(file.type);
		if (!isXls && (isPdf || isImg) && aiOn) {
			list = list.filter(function (f) { return f.type == 'application/pdf' || /^image\/(jpeg|png|webp)$/.test(f.type); }).slice(0, 5);
			aiRead(list); return;
		}
		if (isImg) { say('Фотоны оқу үшін Claude қосылмаған, админге айтыңыз'); return; }
		if (!isPdf && !isXls) { say('Тек Excel (xlsx, xls, csv), PDF немесе фото'); return; }
		show();
		$m.find('.wbi_drop').addClass('wbi_drop_wait').find('b').text('Оқып жатырмыз...');
		(isPdf ? readPdf(file) : readExcel(file)).then(function (res) {
			$m.find('.wbi_drop').removeClass('wbi_drop_wait').find('b').text('Файлды таңдаңыз немесе осында тастаңыз');
			if (res.type == 'pdf' && res.chars < 30 && aiOn) { aiRead([file]); return; }
			if (res.type == 'pdf' && res.chars < 30) {
				say('Бұл PDF ішінде мәтін жоқ (сканерленген сурет). Жеткізушіден Excel нұсқасын сұраңыз немесе қолмен енгізіңіз.');
				reset(); return;
			}
			state = { file: file, res: res, sheet: 0, hdr: null, manual: false };
			// бірнеше парақ болса, тауары көп парақты таңдау
			if (res.sheets.length > 1) {
				var bi = 0, bn = -1;
				res.sheets.forEach(function (s, i) {
					var hd = findHeader(s.grid), n = extract(s.grid, hd, res.type == 'pdf' ? 'math' : '').length;
					if (n > bn) { bn = n; bi = i; }
				});
				state.sheet = bi;
			}
			analyze();
		}).catch(function (e) {
			console.error(e);
			$m.find('.wbi_drop').removeClass('wbi_drop_wait').find('b').text('Файлды таңдаңыз немесе осында тастаңыз');
			say('Файлды оқу мүмкін болмады. ' + (e && e.message ? e.message : ''));
		});
	}

	// Фото/PDF: Drive-қа тікелей жүктеп, Claude-қа оқытамыз
	function shrink(f) {
		if (!/^image\/(jpeg|png|webp)$/.test(f.type) || f.size < 1500000 || !window.createImageBitmap) return Promise.resolve(f);
		return createImageBitmap(f).then(function (bm) {
			var k = Math.min(1, 2000 / Math.max(bm.width, bm.height)), c = document.createElement('canvas');
			c.width = Math.round(bm.width * k); c.height = Math.round(bm.height * k);
			var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, c.width, c.height); x.drawImage(bm, 0, 0, c.width, c.height);
			return new Promise(function (ok) { c.toBlob(function (b) { ok(b ? new File([b], f.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : f); }, 'image/jpeg', 0.85); });
		}).then(null, function () { return f; });
	}
	function aiRead(list) {
		show();
		var $d = $m.find('.wbi_drop').removeClass('dsp_n').addClass('wbi_drop_wait'), $t = $d.find('b');
		$m.find('.wbi_body, .wbi_foot').addClass('dsp_n');
		var back = function () { $d.removeClass('wbi_drop_wait'); $t.text('Файлды таңдаңыз немесе осында тастаңыз'); };
		var ids = [], i = 0;
		// Drive қосулы болса, алдымен соған; Drive қате берсе, файлды тікелей жібереміз
		var viaDrive = !gdOn ? Promise.resolve(false) : (function () {
			$t.text('Google Drive-қа жүктеп жатырмыз...');
			return load(LIB.gd).then(function next() {
				if (i >= list.length) return true;
				var f = list[i++];
				if (list.length > 1) $t.text('Google Drive-қа жүктеп жатырмыз... ' + i + '/' + list.length);
				return window.gdUpload(f, 'waybill').then(function (r) {
					if (!r.ok) throw new Error(r.msg || 'Drive-қа жүктелмеді');
					ids.push(r.file.id);
					return next();
				});
			}).then(null, function (e) { if (window.console) console.warn('Drive:', e); ids = []; return false; });
		})();
		viaDrive.then(function (ok) {
			$t.text('Claude оқып жатыр... (әдетте 20–60 секунд)');
			var o = { url: '/waybill/ai.php', method: 'POST', dataType: 'json', timeout: 300000 };
			// телефон фотосы үлкен (5–10 МБ): жібермес бұрын 2000px JPEG-ке кішірейтеміз (кухня)
			return Promise.all(list.map(shrink)).then(function (fs) {
				var fd = new FormData();
				fs.forEach(function (f) { fd.append('f[]', f, f.name); });
				o.data = fd; o.processData = false; o.contentType = false;
				return $.ajax(o);
			});
		}).then(function (d) {
			back();
			if (d && d.ok) wbFiles = wbFiles.concat(ids);
			if (!d || !d.ok) { say((d && d.msg) || 'Claude оқи алмады'); reset(); return; }
			state = {
				file: { name: list.map(function (f) { return f.name; }).join(', '), n: list.length },
				res: { type: 'ai', sheets: [{ name: '', grid: [] }] }, sheet: 0, hdr: null, manual: true,
				meta: { supplier: d.supplier, docNo: d.doc_no, date: d.date, total: d.total, note: d.note, usage: d.usage },
				ai: d.items.map(function (x) { return { name: x.name, unitTxt: x.unit, qty: x.qty, price: x.price, sum: x.sum, rawId: x.raw_id, sure: x.sure, noQty: !(x.qty > 0) }; })
			};
			build();
		}, function (e) {
			back(); reset();
			say(e && e.message ? e.message : (e && e.statusText == 'timeout' ? 'Claude ұзақ жауап бермеді, қайталап көріңіз' : 'Қате шықты, қайталап көріңіз'));
		});
	}

	function analyze() {
		var g = state.res.sheets[state.sheet].grid;
		state.hdr = state.res.type == 'pdf' ? null : findHeader(g);
		state.meta = findMeta(g);
		build();
	}

	function colLetter(i) { var s = ''; i++; while (i > 0) { var m = (i - 1) % 26; s = String.fromCharCode(65 + m) + s; i = Math.floor((i - 1) / 26); } return s; }

	function build() {
		var res = state.res, g = res.sheets[state.sheet].grid, isAi = res.type == 'ai', isPdf = res.type == 'pdf' || isAi;
		var items = isAi ? state.ai : extract(g, state.hdr, isPdf ? 'math' : '');
		state.items = items;

		$m.find('.wbi_drop').addClass('dsp_n');
		$m.find('.wbi_body, .wbi_foot').removeClass('dsp_n');

		// жоғарғы ақпарат
		// Claude оқыса файл атаулары (ұзын uuid) жасырын, тек саны; атаулары (i) батырмасында
		var techOn = $m.find('.wbi_tech_btn').hasClass('wbi_tech_on'), tcls = 'wbi_tech' + (techOn ? '' : ' dsp_n');
		var meta = state.meta, inf = '<div class="wbi_file_n"><i class="fal ' + (isAi ? 'fa-magic' : isPdf ? 'fa-file-pdf' : 'fa-file-excel') + '"></i> ' +
			(isAi ? 'Claude оқыды' + ((state.file.n || 1) > 1 ? ': ' + state.file.n + ' файл' : '') + ' <span class="wb_small ' + tcls + '">' + h(state.file.name) + '</span>' : h(state.file.name)) + '</div>';
		if (res.sheets.length > 1) {
			inf += '<select class="wb_inp wbi_sheet">' + res.sheets.map(function (s, i) { return '<option value="' + i + '"' + (i == state.sheet ? ' selected' : '') + '>Парақ: ' + h(s.name) + '</option>'; }).join('') + '</select>';
		}
		var sup = supplierPick(meta.supplier);
		inf += '<div class="wbi_meta">' +
			'<span>Жеткізуші: <b>' + (meta.supplier ? h(meta.supplier) + (sup && sup.id ? ' <i class="wbi_ok">✓ тізімде бар</i>' : ' <i class="wbi_new">+ жаңа</i>') : '<i class="wbi_mut">табылмады</i>') + '</b></span>' +
			(meta.docNo ? '<span>№ <b>' + h(meta.docNo) + '</b></span>' : '') +
			(meta.date ? '<span>Күні: <b>' + h(meta.date.split('-').reverse().join('.')) + '</b></span>' : '') +
			'</div>';
		if (isAi) {
			var tsum = 0; items.forEach(function (x) { tsum += x.sum || 0; });
			if (meta.total > 0 && Math.abs(tsum - meta.total) > Math.max(1, meta.total * 0.005))
				inf += '<div class="wbi_hint">Құжаттағы жалпы сома ' + h(money(meta.total)) + ', жолдардың қосындысы ' + h(money(tsum)) + '. Бір жол оқылмаған не қате болуы мүмкін, тексеріңіз.</div>';
			if (meta.note) inf += '<div class="wbi_hint wbi_note" title="Толық оқу үшін басыңыз">' + h(meta.note) + '</div>';
			// шығын мен қай модель оқығаны (сервер есептейді)
			var u = meta.usage;
			if (u && u.steps) {
				var nm = function (m) { return (String(m).match(/haiku|sonnet|opus|fable/) || [m])[0].replace(/^./, function (c) { return c.toUpperCase(); }); };
				var path = u.steps.map(function (x) { return nm(x.model); }).join(' → ');
				inf += '<div class="wb_small ' + tcls + '">Claude: ' + h(path) + (u.why ? ' (' + h(u.why) + ')' : '') + ' · ' + (u.tokens || 0).toLocaleString('ru-RU') + ' токен, шамамен $' + (+u.usd).toFixed(3) + ' (~' + Math.round(u.usd * 500) + ' ₸)</div>';
			}
			inf += '<div class="wb_small wbi_tip">Артық жолдың белгісін алып тастаңыз, атауын түзетуге болады. Жетпейтін тауарды кейін формада «Тауар қосу» арқылы қосасыз.</div>';
		}
		$m.find('.wbi_info').html(inf);

		// Excel: бағандарды қолмен түзету
		if (!isPdf && !isAi) {
			var w = 0; g.forEach(function (r) { if (r.length > w) w = r.length; });
			w = Math.min(w, 40);
			var hr = state.hdr ? state.hdr.r : -1;
			var label = function (c) {
				var t = hr >= 0 ? String((g[hr] && g[hr][c]) || '') + ' ' + String((g[hr + 1] && !isNum(g[hr + 1][c]) && g[hr + 1][c]) || '') : '';
				t = t.replace(/\s+/g, ' ').trim();
				return colLetter(c) + (t ? ': ' + t.substr(0, 28) : '');
			};
			var names = { name: 'Атауы', unit: 'Өлшемі', qty: 'Саны', price: 'Бағасы', sum: 'Сомасы' };
			var cols = state.hdr ? state.hdr.cols : {};
			var html = '<div class="wb_small">Бағандар дұрыс анықталмаса, осы жерден таңдаңыз:</div><div class="wbi_cols">';
			for (var k in names) {
				html += '<label class="wb_fld"><span class="wb_lbl">' + names[k] + '</span><select class="wb_inp wbi_col" data-k="' + k + '"><option value="">—</option>';
				for (var c = 0; c < w; c++) html += '<option value="' + c + '"' + (cols[k] === c ? ' selected' : '') + '>' + h(label(c)) + '</option>';
				html += '</select></label>';
			}
			$m.find('.wbi_map').html(html + '</div>').removeClass('dsp_n');
		} else $m.find('.wbi_map').addClass('dsp_n');

		// тауарлар
		if (!items.length) {
			$m.find('.wbi_list').html('<div class="wb_empty"><i class="fal fa-search"></i><div>Файлдан тауар табылмады.' + (isPdf ? ' PDF құрылымы ерекше болуы мүмкін, Excel нұсқасын көріңіз.' : ' Жоғарыдан «Атауы» мен «Саны» бағанын таңдаңыз.') + '</div></div>');
			$m.find('.wbi_apply').addClass('wb_btn_wait');
			return;
		}
		$m.find('.wbi_apply').removeClass('wb_btn_wait');
		var list = '<div class="wb_tr wb_th wbi_tr">' +
			'<div class="wb_td wbi_td_c"><input type="checkbox" class="wbi_all" checked></div>' +
			'<div class="wb_td wb_td_name">Шикізат (біздің атау)</div>' +
			'<div class="wb_td wb_td_unit">Өлшемі</div><div class="wb_td">Саны</div><div class="wb_td">Бағасы</div><div class="wb_td">Сомасы</div></div>';
		items.forEach(function (it, i) {
			var mt = match(it.name);
			// Claude ұсынған шикізат (сіз бұрын қолмен таңдаған сәйкестік болса, сол басым)
			if (it.rawId && rawById(it.rawId) && !(mt && (mt.al || mt.mem))) mt = { r: rawById(it.rawId), s: it.sure ? 0.95 : 0.7, ai: true };
			it.m = mt;
			var unit = unitOf(it.unitTxt) || (String(it.unitTxt || '').trim().substr(0, 20)) || (mt && mt.s >= 0.8 && mt.r.u) || 'шт';
			it.unit = unit;
			var val = mt && mt.s >= 0.8 ? mt.r.n : it.name;
			list += '<div class="wb_tr wbi_tr wbi_row" data-i="' + i + '">' +
				'<div class="wb_td wbi_td_c"><input type="checkbox" class="wbi_r_on"' + (it.noQty ? '' : ' checked') + '></div>' +
				'<div class="wb_td wb_td_name">' +
					'<input type="text" class="wb_inp wbi_r_name" list="wb_raw_list" value="' + h(val) + '">' +
					'<div class="wbi_src">Файлда: ' + h(it.name) + ' <span class="wbi_bd"></span></div>' +
					'<div class="wbi_conv"></div>' +
				'</div>' +
				'<div class="wb_td wb_td_unit" data-h="Өлшемі">' + h(unit) + '</div>' +
				'<div class="wb_td" data-h="Саны">' + (it.noQty ? '<input type="text" inputmode="decimal" class="wb_inp wbi_r_qty" placeholder="0">' : h(r3(it.qty))) + '</div>' +
				'<div class="wb_td" data-h="Бағасы">' + h(money(it.price)) + '</div>' +
				'<div class="wb_td" data-h="Сомасы"><b class="wbi_r_sum">' + (it.qty ? h(money(it.qty * it.price)) : '—') + '</b></div>' +
			'</div>';
		});
		if (items.some(function (x) { return x.noQty; }))
			list = '<div class="wbi_hint">Файлда «саны» бағаны жоқ (баға тізімі сияқты). Келген тауарлардың санын жазыңыз, тек солар қосылады.</div>' + list;
		list += '<div class="wbi_total">Таңдалғаны: <b class="wbi_cnt"></b> · Барлығы: <b class="wbi_sum"></b></div>';
		$m.find('.wbi_list').html(list);
		$m.find('.wbi_row').each(function () { badge($(this)); });
		var empty = !$doc.find('.wb_row').filter(function () { return $.trim($(this).find('.wb_r_name').val()) !== ''; }).length;
		$m.find('.wbi_replace').prop('checked', true).closest('.wbi_chk').toggleClass('dsp_n', empty);
		sumUp();
	}

	// «1 шт = X кг» аударма: файлдағы өлшем шикізат өлшемінен басқа болса
	function conv($r) {
		var it = state.items[$r.data('i')], m = rawByName($r.find('.wbi_r_name').val()), $c = $r.find('.wbi_conv');
		var fu = unitOf(it.unitTxt) || String(it.unitTxt || '').trim() || 'шт';
		it.raw = m; it.cv = false; it.f = 1;
		if (!m || !m.u) { $c.empty(); return; }
		var al = aliasOf(it.name); if (al && al.id != m.id) al = null;
		var pf = packFactor(it.name, m.u);
		var need = fu != m.u || (al && al.f != 1) || (m.u == 'шт' && pf > 1);
		if (!need) { $c.empty(); return; }
		var f = it.fUser != null ? it.fUser : (al ? al.f : pf);
		it.cv = true; it.f = f > 0 ? f : 0;
		it.fu = fu;
		$c.html('1 ' + h(fu) + ' = <input type="text" inputmode="decimal" class="wb_inp wbi_r_f" value="' + (f > 0 ? r3(f) : '') + '" placeholder="?"> ' + h(m.u) + ' <span class="wbi_cv_t"></span>');
		convText($r);
	}
	function convText($r) {
		var it = state.items[$r.data('i')];
		if (!it.cv) return;
		var t = !(it.f > 0) ? 'неше ' + it.raw.u + ' екенін жазыңыз' :
			(it.qty > 0 ? '→ ' + r3(it.qty * it.f) + ' ' + it.raw.u + ', 1 ' + it.raw.u + ' = ' + money(it.price / it.f) : '1 ' + it.raw.u + ' = ' + money(it.price / it.f));
		$r.find('.wbi_cv_t').text(t);
	}

	// әр жолдың белгісі: сәйкес / тексеріңіз / жаңа шикізат
	function badge($r) {
		conv($r);
		var it = state.items[$r.data('i')], v = $r.find('.wbi_r_name').val(), m = rawByName(v), $b = $r.find('.wbi_bd');
		$r.removeClass('wbi_warn');
		if (m && it.m && it.m.r.id == m.id && it.m.s < 0.8) { $b.html('<i class="wbi_warn_t">? ұқсас, тексеріңіз</i>'); $r.addClass('wbi_warn'); }
		else if (m) $b.html('<i class="wbi_ok">✓ шикізатта бар</i>');
		else {
			var hint = it.m && it.m.s < 0.8 ? ' · мүмкін «' + h(it.m.r.n) + '»?' : '';
			$b.html('<i class="wbi_new">+ жаңа шикізат болып қосылады</i>' + hint);
		}
	}
	function sumUp() {
		var n = 0, t = 0;
		$m.find('.wbi_row').each(function () {
			if (!$(this).find('.wbi_r_on').prop('checked')) return;
			var it = state.items[$(this).data('i')]; n++; t += it.qty * it.price;
		});
		$m.find('.wbi_cnt').text(n);
		$m.find('.wbi_sum').text(money(t));
	}

	// жеткізушіні тізімнен іздеу
	function supplierPick(name) {
		if (!name) return null;
		var clean = function (s) { return norm(s).replace(/\b(тоо|ип|ао|жшс|too|llp|оао|ооо|компания|фирма)\b/g, ' ').replace(/\s+/g, ' ').trim(); };
		var n = clean(name), best = null, bs = 0;
		$doc.find('.wb_f_supplier option').each(function () {
			var id = $(this).val(); if (id == '0' || id == 'new') return;
			var s = score(n, clean($(this).text()));
			if (s > bs) { bs = s; best = { id: id, name: $(this).text() }; }
		});
		return bs >= 0.8 ? best : { id: 0, name: name };
	}

	// ---------- Формаға жазу ----------
	function apply() {
		if ($(this).hasClass('wb_btn_wait')) return;
		var saveNow = $(this).hasClass('wbi_apply_save');
		var sel = [], noq = '', nof = '';
		$m.find('.wbi_row').each(function () {
			if (!$(this).find('.wbi_r_on').prop('checked')) return;
			var it = state.items[$(this).data('i')];
			if (!(it.qty > 0)) { noq = noq || it.name; return; }
			if (it.cv && !(it.f > 0)) { nof = nof || it.name; return; }
			sel.push({ it: it, name: $.trim($(this).find('.wbi_r_name').val()) || it.name });
		});
		if (noq) { say('«' + noq + '» санын жазыңыз немесе белгіні алып тастаңыз'); return; }
		if (nof) { say('«' + nof + '»: 1 шт неше кг (л, дана) екенін жазыңыз'); return; }
		if (!sel.length) { say('Кемінде бір тауардың санын жазыңыз'); return; }

		var $rows = $doc.find('.wb_rows'), $tpl = $doc.find('.wb_row').first().clone();
		var replace = $m.find('.wbi_replace').prop('checked');
		if (replace) $doc.find('.wb_row').remove();
		else $doc.find('.wb_row').filter(function () { return $.trim($(this).find('.wb_r_name').val()) === '' && !$(this).find('.wb_r_qty').val(); }).remove();

		var mem = memGet(), save = [];
		sel.forEach(function (s) {
			var it = s.it, m = rawByName(s.name), $r = $tpl.clone();
			$r.find('input').val('');
			// шт → кг: саны көбейеді, бағасы бөлінеді, сомасы сол күйі
			var qty = it.qty, price = it.price, unit = it.unit;
			if (m && it.cv && it.f > 0) { qty = r3(it.qty * it.f); price = it.qty * it.price / qty; unit = m.u; }
			else if (m && m.u && !unitOf(it.unitTxt)) unit = m.u;
			if (m && low(it.name) != low(m.n) && low(it.name) != low(m.r || '')) {
				var f = it.cv ? it.f : 1;
				save.push({ name: it.name, raw_id: m.id, factor: f });
				aliasNorm[norm(it.name)] = { n: it.name, id: m.id, f: f };
			}
			var $u = $r.find('.wb_r_unit');
			if (!$u.find('option').filter(function () { return $(this).text() == unit; }).length) $u.append($('<option>').text(unit));
			$u.val(unit);
			$r.find('.wb_r_name').val(m ? m.n : s.name).attr('data-raw', m ? m.id : 0).data('raw', m ? m.id : 0);
			$r.find('.wb_r_qty').val(r3(qty));
			$r.find('.wb_r_price').val(r2(price));
			$r.find('.wb_r_sum').val(r2(qty * price));
			$rows.append($r);
			// келесі жолы осы атау бірден танылсын
			if (m) mem[norm(it.name)] = m.id;
		});
		memSet(mem);
		// жеткізуші атауы → шикізат байланысын базада сақтау (кесте болса)
		if (save.length) $.post('/waybill/get.php?alias_save', { items: JSON.stringify(save) }, null, 'json');
		if (!$doc.find('.wb_row').length) $rows.append($tpl);
		$doc.find('.wb_row').last().find('.wb_r_qty').trigger('input');

		// жеткізуші, нөмір, күн (бос болса ғана)
		var meta = state.meta, $sup = $doc.find('.wb_f_supplier');
		if (meta.supplier && ($sup.val() == '0' || !$sup.val())) {
			var p = supplierPick(meta.supplier);
			if (p && p.id) $sup.val(p.id).trigger('change');
			else { $sup.val('new').trigger('change'); $doc.find('.wb_f_supplier_new').val(meta.supplier); }
		}
		if (meta.docNo && !$.trim($doc.find('.wb_f_docno').val())) $doc.find('.wb_f_docno').val(meta.docNo);
		if (meta.date && !$doc.data('id')) $doc.find('.wb_f_date').val(meta.date);
		var c = $doc.find('.wb_f_comment');
		if (!$.trim(c.val())) c.val('Файлдан: ' + state.file.name);

		close();
		if (saveNow) { setTimeout(function () { $doc.find('.wb_save[data-post="0"]').first().click(); }, 50); return; }
		say(sel.length + ' тауар қосылды. Тексеріп, «Сақтау» басыңыз.');
		$('html, body').animate({ scrollTop: $doc.find('.wb_table').offset().top - 90 }, 200);
	}

	// ---------- Батырмалар ----------
	$doc.on('click', '.wb_imp_open', function () { modal(); reset(); show(); });
	// бетке файлды тастаса да болады
	$(document).on('dragover', function (e) {
		var dt = e.originalEvent.dataTransfer;
		if (dt && Array.prototype.indexOf.call(dt.types || [], 'Files') > -1) e.preventDefault();
	});
	$(document).on('drop', function (e) {
		if ($(e.target).closest('.wbi').length) return;
		var f = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
		if (f && f[0]) { e.preventDefault(); open(f[0]); }
	});
	if (/[?&]import=1/.test(location.search)) { modal(); reset(); show(); }

	// накладной сақталғанда Drive-тағы файлдарды соған байлаймыз (базада тек сілтемесі)
	$(document).ajaxSuccess(function (e, xhr, o, d) {
		if (!wbFiles.length || !/\/waybill\/get\.php\?save/.test(o.url || '')) return;
		if (typeof d == 'string') { try { d = JSON.parse(d); } catch (x) { d = null; } }
		var id = (d && d.id) || $doc.data('id');
		if (id && window.gdLink) { window.gdLink(wbFiles, 'waybill', id); wbFiles = []; }
	});

	// тест үшін
	window.wb_import = { toNum: toNum, unitOf: unitOf, findHeader: findHeader, extract: extract, findMeta: findMeta, match: match, rowByMath: rowByMath, open: open };
})(jQuery);
