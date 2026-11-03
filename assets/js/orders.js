// Кухня: Дайындау / Дайын / Қайтару
document.addEventListener('click', function (e) {
	var b = e.target.closest('.on_kst'); if (!b || b.classList.contains('kt_busy')) return
	if (b.dataset.ask && !confirm(b.dataset.ask)) return
	b.classList.add('kt_busy')
	$.ajax({ url: '/orders/get.php?kitchen_status', type: 'POST', dataType: 'html', data: { id: b.dataset.id, st: b.dataset.st },
		success: function (d) {
			if (d != 'yes') { mess(KT.err); setTimeout(function () { location.reload() }, 700); return }
			var card = b.closest('.kt_card')
			if (b.dataset.st == '2' || document.querySelector('.kt_page').dataset.sort == 'ready') { card.classList.add('kt_out'); setTimeout(function () { location.reload() }, 250) }
			else location.reload()
		},
		error: function () { b.classList.remove('kt_busy') } })
})

// Күту уақыты: тапсырыс түскеннен бері (15 мин сары, 25 мин қызыл)
;(function () {
	var els = document.querySelectorAll('.kt_wait'); if (!els.length) return
	var tick = function () {
		var now = Date.now() / 1000
		els.forEach(function (el) {
			var m = Math.max(0, Math.floor((now - el.dataset.t) / 60))
			el.textContent = m + ' ' + KT.min
			el.classList.toggle('kt_w1', m >= 15 && m < 25)
			el.classList.toggle('kt_w2', m >= 25)
		})
	}
	tick(); setInterval(tick, 20000)
})()

// Автожаңарту: 15 секунд сайын тексереді, өзгеріс болса бетті жаңартады. Жаңа тапсырыс келсе — дыбыс.
;(function () {
	var page = document.querySelector('.kt_page'); if (!page) return
	var known = []
	var sig = null, snd = false, ctx = null
	try { snd = localStorage.getItem('kt_snd') == '1' } catch (e) {}
	var btn = document.querySelector('.on_snd')
	var paint = function () { if (btn) { btn.classList.toggle('kt_snd_on', snd); btn.querySelector('i').className = 'far ' + (snd ? 'fa-volume-up' : 'fa-volume-mute') } }
	var beep = function () {
		if (!snd) return
		try {
			ctx = ctx || new (window.AudioContext || window.webkitAudioContext)()
			;[0, 0.25, 0.5].forEach(function (t) {
				var o = ctx.createOscillator(), g = ctx.createGain()
				o.frequency.value = 880; o.connect(g); g.connect(ctx.destination)
				g.gain.setValueAtTime(0.25, ctx.currentTime + t); g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.2)
				o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.2)
			})
		} catch (e) {}
	}
	paint()
	if (btn) btn.addEventListener('click', function () {
		snd = !snd; paint()
		try { localStorage.setItem('kt_snd', snd ? '1' : '0') } catch (e) {}
		if (snd) { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.resume) ctx.resume(); beep() }
	})
	// Жаңа тапсырыспен ашылған бетте бір рет дыбыс (бет жаңарғаннан кейін)
	try { if (sessionStorage.getItem('kt_new') == '1') { sessionStorage.removeItem('kt_new'); setTimeout(beep, 300) } } catch (e) {}

	var check = function () {
		if (document.querySelector('.bp_dr_on')) return // попап ашық тұрғанда жаңартпайды
		$.ajax({ url: '/orders/get.php?queue', dataType: 'json', cache: false, success: function (d) {
			if (sig === null) { sig = d.sig; known = d.ids.map(String); return }
			if (d.sig == sig) return
			var fresh = d.ids.some(function (id) { return known.indexOf(String(id)) < 0 })
			if (fresh) { try { sessionStorage.setItem('kt_new', '1') } catch (e) {} }
			location.reload()
		} })
	}
	check(); setInterval(check, 15000)
})()

// Статус тарихы: астынан шығатын попап
document.addEventListener('click', function (e) {
	var b = e.target.closest('.on_hist'); if (!b) return
	var sh = document.querySelector('.bp_hist_sh'); if (!sh) return
	sh.querySelector('.bp_hist_nm').textContent = '№' + b.dataset.nm
	sh.querySelector('.oh_list').innerHTML = '<div class="oh_none">…</div>'
	bpSheet(sh, true)
	$.ajax({ url: '/orders/get.php?history', type: 'POST', dataType: 'html', data: { id: b.dataset.id },
		success: function (data) { sh.querySelector('.oh_list').innerHTML = data } })
})

// Тарих: «Статус» / «Өзгерістер» табтары
document.addEventListener('click', function (e) {
	var t = e.target.closest('.oh_tab'); if (!t) return
	var box = t.parentNode.parentNode
	box.querySelectorAll('.oh_tab').forEach(function (x) { x.classList.toggle('oh_tab_act', x === t) })
	box.querySelectorAll('.oh_pane').forEach(function (p) { p.classList.toggle('dsp_n', p.dataset.p != t.dataset.t) })
})
