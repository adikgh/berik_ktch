// Кіру: телефон + пароль → (керек болса) WhatsApp коды. «Забыл пароль?» → нөмірге код → жаңа пароль.
// Сервер жағы: config/auth.php → auth_routes(). Форма: block/sign_form.php
$(function () {
	function say(t, k) { if (typeof window.mess == 'function') window.mess(t, k); else alert(t) }
	var $b = $('.sg_box')
	if (!$b.length) return
	var home = $b.attr('data-home') || '/orders/'
	var msg = {
		none: 'Телефон немесе пароль қате',
		wait: 'Кодты 1 минуттан кейін қайта сұраңыз',
		limit: 'Тым көп әрекет. 15 минуттан кейін көріңіз',
		fail: 'Кодты WhatsApp-қа жіберу мүмкін болмады. Әкімшіге хабарласыңыз',
		wrong: 'Код қате',
		expired: 'Кодтың уақыты өтті, жаңасын сұраңыз',
		off: 'Парольді қалпына келтіру үшін әкімшіге хабарласыңыз',
		phone: 'Телефонды толық жазыңыз',
		short: 'Пароль кемінде 6 таңба болсын'
	}

	function digits(s) { return String(s || '').replace(/\D/g, '') }
	function phone10(s) { return digits(s).slice(-10) }
	function step(n) {
		$b.find('.sg_step').hide()
		var $s = $b.find('.sg_' + n).show()
		setTimeout(function () { $s.find('input:visible').first().focus() }, 50)
	}
	function post(act, data, $btn, done) {
		if ($btn) $btn.addClass('sg_wait')
		$.ajax({
			url: '/get.php?' + act, type: 'POST', data: data, dataType: 'text',
			success: function (r) {
				r = String(r).trim()
				var j = null
				if (r.charAt(0) == '{') try { j = JSON.parse(r) } catch (e) {}
				done(r, j)
			},
			error: function () { say('Байланыс қатесі', 'err') },
			complete: function () { if ($btn) $btn.removeClass('sg_wait') }
		})
	}
	function err(r) {
		say(msg[r] || 'Қате', 'err')
		if (r == 'expired' || r == 'limit') { if ($b.find('.sg_rcode:visible, .sg_rpass:visible').length) step('reset'); else step('login') }
	}
	function showCode(n, j) {
		var $s = $b.find('.sg_' + n)
		$s.find('.sg_ph').text(j.phone || '')
		if (j.days) $s.find('.sg_days').text(j.days)
		var $t = $s.find('.sg_test')
		if (j.test) $t.show().find('b').text(j.test); else $t.hide()
		$s.find('.sg_otp').val('')
		step(n)
	}

	// 1. Кіру
	$b.on('click', '.sg_go', function () {
		var ph = phone10($b.find('.sg_phone').val()), ps = $b.find('.sg_pass').val()
		if (ph.length < 10 || !ps) return say('Телефон мен парольді жазыңыз')
		post('sign', { phone: ph, password: ps }, $(this), function (r, j) {
			if (r == 'yes') location.href = home
			else if (j && j.st == 'code') showCode('code', j)
			else err(r)
		})
	})

	// 2. Кіру коды
	$b.on('click', '.sg_code_go', function () {
		var c = digits($b.find('.sg_code_in').val())
		if (c.length != 4) return say('4 таңбалы кодты жазыңыз')
		post('sign_code', { code: c, trust: $b.find('.sg_trust_in').is(':checked') ? 1 : 0 }, $(this), function (r) {
			if (r == 'yes') location.href = home; else err(r)
		})
	})

	// Кодты қайта жіберу
	$b.on('click', '.sg_resend', function () {
		var key = $(this).attr('data-key')
		post('sign_resend=' + key, {}, null, function (r, j) {
			if (j && j.st == 'code') { showCode(key == 'reset' ? 'rcode' : 'code', j); say('Код қайта жіберілді', 'ok') }
			else err(r)
		})
	})

	// 3. Парольді ұмыттым
	$b.on('click', '.sg_forgot', function () {
		var ph = $b.find('.sg_phone').val()
		if (ph) $b.find('.sg_rphone').val(ph)
		step('reset')
	})
	$b.on('click', '.sg_reset_go', function () {
		var ph = phone10($b.find('.sg_rphone').val())
		if (ph.length < 10) return say(msg.phone)
		post('reset_send', { phone: ph }, $(this), function (r, j) {
			if (j && j.st == 'code') showCode('rcode', j); else err(r)
		})
	})

	// 4. Код
	$b.on('click', '.sg_rcode_go', function () {
		var c = digits($b.find('.sg_rcode_in').val())
		if (c.length != 4) return say('4 таңбалы кодты жазыңыз')
		post('reset_code', { code: c }, $(this), function (r) {
			if (r == 'yes') step('rpass'); else err(r)
		})
	})

	// 5. Жаңа пароль
	$b.on('click', '.sg_rpass_go', function () {
		var p1 = $b.find('.sg_np1').val(), p2 = $b.find('.sg_np2').val()
		if (p1.length < 6) return say(msg.short)
		if (p1 !== p2) return say('Парольдер сәйкес емес')
		post('reset_pass', { pass: p1 }, $(this), function (r) {
			if (r == 'yes') { say('Жаңа пароль сақталды', 'ok'); setTimeout(function () { location.href = home }, 600) }
			else err(r)
		})
	})

	$b.on('click', '.sg_back', function () { step('login') })

	// Enter басылса, сол қадамның батырмасы
	$b.on('keydown', 'input', function (e) {
		if (e.key == 'Enter') { e.preventDefault(); $(this).closest('.sg_step').find('.sg_btn').first().trigger('click') }
	})
	// 4 таңба жазылса, өзі жібереді
	$b.on('input', '.sg_otp', function () {
		var v = digits(this.value).slice(0, 4)
		if (this.value !== v) this.value = v
		if (v.length == 4) $(this).closest('.sg_step').find('.sg_btn').first().trigger('click')
	})
})
