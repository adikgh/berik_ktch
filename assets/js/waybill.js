// Кухня: накладной формасы
;(function () {
	var form = document.querySelector('.kw_form'); if (!form) return
	var rows = form.querySelector('.kw_rows')
	var num = function (v) { return parseFloat(String(v).replace(/\s/g, '').replace(',', '.')) || 0 }
	var fmt = function (v) { return Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') }

	var calc = function () {
		var t = 0
		rows.querySelectorAll('.kw_row').forEach(function (r) {
			var s = num(r.querySelector('.kw_q').value) * num(r.querySelector('.kw_p').value)
			r.querySelector('.kw_s').textContent = fmt(s); t += s
		})
		form.querySelector('.kw_total').textContent = fmt(t)
	}

	form.addEventListener('input', function (e) {
		if (e.target.classList.contains('kw_nm')) {
			// шикізат тізімінен таңдаса: өлшемі мен соңғы бағасы өзі қойылады
			var r = (window.KW_RAW || {})[e.target.value.trim()], row = e.target.closest('.kw_row')
			if (r) { row.querySelector('.kw_u').value = r[0]; var p = row.querySelector('.kw_p'); if (!p.value && r[1]) p.value = r[1] }
		}
		calc()
	})

	form.addEventListener('click', function (e) {
		if (e.target.closest('.kw_add')) {
			var last = rows.querySelector('.kw_row:last-child'), n = last.cloneNode(true)
			n.querySelectorAll('input').forEach(function (i) { i.value = '' }); n.querySelector('.kw_u').value = 'кг'
			rows.appendChild(n); n.querySelector('.kw_nm').focus(); calc(); return
		}
		var d = e.target.closest('.kw_del')
		if (d) { var row = d.closest('.kw_row'); if (rows.children.length > 1) row.remove(); else row.querySelectorAll('input').forEach(function (i) { i.value = '' }); calc(); return }
		if (e.target.closest('.kw_save')) save(e.target.closest('.kw_save'))
		var rm = e.target.closest('.kw_rm')
		if (rm && confirm(rm.dataset.ask)) send('delete', { id: form.dataset.id }, rm, function () { location.href = '/waybill/' })
	})

	var send = function (act, data, btn, done) {
		if (btn.classList.contains('kt_busy')) return
		btn.classList.add('kt_busy'); data.csrf = form.dataset.csrf
		$.ajax({ url: '/waybill/get.php?' + act, type: 'POST', dataType: 'json', data: data,
			success: function (d) { btn.classList.remove('kt_busy'); mess(d.msg || ''); if (d.ok) done(d) },
			error: function () { btn.classList.remove('kt_busy'); mess('Қате / Ошибка') } })
	}

	var save = function (btn) {
		var items = []
		rows.querySelectorAll('.kw_row').forEach(function (r) {
			items.push({ name: r.querySelector('.kw_nm').value, qty: r.querySelector('.kw_q').value, unit: r.querySelector('.kw_u').value, price: r.querySelector('.kw_p').value })
		})
		send('save', { id: form.dataset.id, supplier: form.supplier.value, doc_date: form.doc_date.value, doc_no: form.doc_no.value,
			paid: form.paid.value, comment: form.comment.value, items: JSON.stringify(items) }, btn,
			function (d) { setTimeout(function () { location.href = '/waybill/' }, 600) })
	}

	calc()
})()
