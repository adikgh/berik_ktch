// Кухня: накладной формасы (админдегі waybill.js-тің кухня нұсқасы: тек жоба сақтайды, қабылдауды админ жасайды).
// Файлдан/фотодан толтыру: waybill_import.js (админнен көшірме).
(function ($) {
	var $doc = $('.wb_doc');
	if (!$doc.length || $doc.data('posted') == 1) return;

	// get.php мен ai.php CSRF токенін X-CSRF тақырыбынан да қабылдайды (импорт скриптінің сұраулары үшін)
	$.ajaxSetup({ headers: { 'X-CSRF': $doc.data('csrf') } });

	var say = function (t) { if (typeof mess == 'function') mess(t); else alert(t); };
	var num = function (v) { v = String(v || '').replace(/\s+/g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : n; };
	var r2 = function (n) { return Math.round(n * 100) / 100; };
	var money = function (n) { n = r2(n); var s = (Math.floor(n) == n ? n.toFixed(0) : n.toFixed(2)); return s.replace(/\B(?=(\d{3})+(?!\d))/g, ' '); };
	var raw = window.wb_raw || [];
	var findRaw = function (name) {
		name = $.trim(name).toLowerCase(); if (!name) return null;
		for (var i = 0; i < raw.length; i++) if (raw[i].n.toLowerCase() == name || (raw[i].r && raw[i].r.toLowerCase() == name)) return raw[i];
		return null;
	};

	// ---- Жолдар ----
	function total() {
		var t = 0;
		$doc.find('.wb_row').each(function (k) {
			$(this).find('.wb_row_n').text(k + 1);
			t += num($(this).find('.wb_r_sum').val());
		});
		$doc.find('.wb_total_v').text(money(t));
		$doc.find('.wb_debt_v').text(money(Math.max(0, t - num($doc.find('.wb_f_paid').val()))));
		return t;
	}

	function newRow() {
		var $last = $doc.find('.wb_row').last(), $r = $last.clone();
		$r.find('input').val('');
		$r.find('.wb_r_name').attr('data-raw', 0).data('raw', 0);
		$r.find('.wb_r_unit').val($last.find('.wb_r_unit').val());
		$doc.find('.wb_rows').append($r);
		total();
		$r.find('.wb_r_name').focus();
	}

	$doc.on('click', '.wb_add_row', newRow);
	$doc.on('click', '.wb_x', function () {
		if ($doc.find('.wb_row').length > 1) $(this).closest('.wb_row').remove();
		else $(this).closest('.wb_row').find('input').val('');
		total();
	});

	// атауы шикізатқа сәйкес келсе: өлшемі мен соңғы бағасы өзі қойылады
	$doc.on('change', '.wb_r_name', function () {
		var $r = $(this).closest('.wb_row'), m = findRaw($(this).val());
		$(this).data('raw', m ? m.id : 0);
		if (!m) return;
		if (m.u) {
			var $u = $r.find('.wb_r_unit');
			if (!$u.find('option').filter(function () { return $(this).text() == m.u; }).length) $u.append($('<option>').text(m.u));
			$u.val(m.u);
		}
		if (m.p && !num($r.find('.wb_r_price').val())) $r.find('.wb_r_price').val(m.p).trigger('input');
	});
	$doc.on('input', '.wb_r_qty, .wb_r_price', function () {
		var $r = $(this).closest('.wb_row'), q = num($r.find('.wb_r_qty').val()), p = num($r.find('.wb_r_price').val());
		$r.find('.wb_r_sum').val(q && p ? r2(q * p) : '');
		total();
	});
	// сомасын жазса, бағасы өзі есептеледі
	$doc.on('input', '.wb_r_sum', function () {
		var $r = $(this).closest('.wb_row'), q = num($r.find('.wb_r_qty').val()), s = num($(this).val());
		if (q) $r.find('.wb_r_price').val(r2(s / q));
		total();
	});
	// Enter: келесі жол (соңғысында жаңа жол)
	$doc.on('keydown', '.wb_row input', function (e) {
		if (e.key != 'Enter') return;
		e.preventDefault();
		var $r = $(this).closest('.wb_row');
		if ($r.is($doc.find('.wb_row').last())) newRow(); else $r.next().find('.wb_r_name').focus();
	});

	$doc.on('input', '.wb_f_paid', total);
	$doc.on('click', '.wb_pay_full', function () { $doc.find('.wb_f_paid').val(r2(total())).trigger('input'); });
	$doc.on('change', '.wb_f_supplier', function () {
		$doc.find('.wb_f_supplier_new').toggleClass('dsp_n', $(this).val() != 'new');
		if ($(this).val() == 'new') $doc.find('.wb_f_supplier_new').focus();
	});

	// камерамен түсірілген фото бірден Claude-қа (waybill_import.js)
	$doc.on('change', '.kw_cam_in', function () {
		var f = this.files ? Array.prototype.slice.call(this.files) : [];
		this.value = '';
		if (f.length && window.wb_import) window.wb_import.open(f);
	});

	// ---- Сақтау / өшіру ----
	var busy = false;
	$doc.on('click', '.wb_save', function () {
		if (busy) return;
		var items = [];
		$doc.find('.wb_row').each(function () {
			var $r = $(this);
			items.push({ raw_id: $r.find('.wb_r_name').data('raw') || 0, name: $r.find('.wb_r_name').val(), unit: $r.find('.wb_r_unit').val(), qty: $r.find('.wb_r_qty').val(), price: $r.find('.wb_r_price').val() });
		});
		busy = true; $('.wb_save').addClass('wb_btn_wait');
		$.post('/waybill/get.php?save', {
			csrf: $doc.data('csrf'), id: $doc.data('id'),
			supplier_id: $doc.find('.wb_f_supplier').val(), supplier_new: $doc.find('.wb_f_supplier_new').val(),
			doc_date: $doc.find('.wb_f_date').val(), doc_no: $doc.find('.wb_f_docno').val(),
			comment: $doc.find('.wb_f_comment').val(), paid: $doc.find('.wb_f_paid').val(),
			items: JSON.stringify(items)
		}, function (r) {
			busy = false; $('.wb_save').removeClass('wb_btn_wait');
			say(r.msg || (r.ok ? 'OK' : 'Қате / Ошибка'));
			if (r.ok) setTimeout(function () { location.href = '/waybill/'; }, 700);
		}, 'json').fail(function () { busy = false; $('.wb_save').removeClass('wb_btn_wait'); say('Байланыс қатесі / Ошибка связи'); });
	});

	$doc.on('click', '.wb_delete', function () {
		ask($(this).data('ask'), function () {
		$.post('/waybill/get.php?delete', { csrf: $doc.data('csrf'), id: $doc.data('id') }, function (r) {
			say(r.msg);
			if (r.ok) setTimeout(function () { location.href = '/waybill/'; }, 600);
		}, 'json');
		}, { ok: 'Өшіру', danger: true });
	});

	total();
})(jQuery);
