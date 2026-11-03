// Кухня: ревизия — іздеу және саналғанын санау
;(function () {
	var form = document.querySelector('.kr_form'); if (!form) return
	var ins = form.querySelectorAll('.kr_in'), n = form.querySelector('.kr_n')
	var count = function () { var c = 0; ins.forEach(function (i) { if (i.value.trim() !== '') c++ }); n.textContent = c }
	form.addEventListener('input', function (e) {
		if (e.target.classList.contains('kr_in')) return count()
		if (!e.target.classList.contains('kr_find')) return
		var q = e.target.value.trim().toLowerCase()
		form.querySelectorAll('.kr_row').forEach(function (r) { r.style.display = !q || r.dataset.n.indexOf(q) >= 0 ? '' : 'none' })
		form.querySelectorAll('.kr_g').forEach(function (g) { g.style.display = q ? 'none' : '' })
	})
	// Enter — келесі жолға өту (форма жіберілмейді)
	form.addEventListener('keydown', function (e) {
		if (e.key != 'Enter' || !e.target.classList.contains('kr_in')) return
		e.preventDefault()
		var a = Array.prototype.filter.call(ins, function (i) { return i.offsetParent !== null }), k = a.indexOf(e.target)
		if (a[k + 1]) a[k + 1].focus()
	})
	count()
})()
