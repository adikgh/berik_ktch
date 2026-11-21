// Профиль: өз атын, тегін, фотосын өзгерту (Раздача, Кухня). Сервер жолдары мен мәтіндер .prof_ed data-* ішінде.
document.addEventListener("DOMContentLoaded", function () { // попап беттің соңында, footer-ден кейін
	var r = document.querySelector('.prof_ed'); if (!r) return
	var d = r.dataset, sh = document.querySelector('.prof_sh'), f = sh.querySelector('form'), msg = f.querySelector('.prof_msg')
	var av = r.querySelector('.bp_prof_av'), inp = r.querySelector('.prof_img')
	function post(url, fd) { fd.append('csrf', d.csrf); return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (x) { return x.text() }) }

	r.querySelector('.prof_ed_btn').addEventListener('click', function () { msg.textContent = ''; bpSheet(sh, true); setTimeout(function () { f.elements.name.focus() }, 250) })

	f.addEventListener('submit', function (e) {
		e.preventDefault()
		var b = f.querySelector('button[type=submit]'); if (b.disabled) return
		b.disabled = true; msg.textContent = ''
		post(d.save, new FormData(f)).then(function (t) {
			t = t.trim()
			if (t != 'yes') { msg.textContent = t == 'name' ? d.eName : d.eSave; return }
			var n = (f.elements.name.value + ' ' + f.elements.surname.value).trim()
			r.querySelector('.bp_prof_n').textContent = n
			if (!av.style.backgroundImage) av.textContent = n.charAt(0).toUpperCase()
			bpSheet(sh, false)
		}).catch(function () { msg.textContent = d.eNet }).finally(function () { b.disabled = false })
	})

	inp.addEventListener('change', function () {
		var fl = inp.files && inp.files[0]; if (!fl) return
		if (!/^image\/(jpeg|png|webp)$/.test(fl.type)) { alert(d.eType); inp.value = ''; return }
		if (fl.size > 8 * 1024 * 1024) { alert(d.eSize); inp.value = ''; return }
		var fd = new FormData(); fd.append('img', fl)
		av.classList.add('prof_wait')
		post(d.img, fd).then(function (t) {
			var j = null; try { j = JSON.parse(t) } catch (e) {}
			if (j && j.ok) { av.style.backgroundImage = 'url(' + j.img + ')'; av.textContent = '' } else alert(d.eSave)
		}).catch(function () { alert(d.eNet) }).finally(function () { av.classList.remove('prof_wait'); inp.value = '' })
	})
})
