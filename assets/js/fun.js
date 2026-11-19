function mess(e) {
   $('.bl_mess').addClass('bl_mess_zak')
   $('.bl_mess_sam').html(e)
   setTimeout(function(){$('.bl_mess').removeClass('bl_mess_zak')},6000)
}
$('.bl_mess').on('click',function(){$(this).removeClass('bl_mess_zak')})









// 
function copytext(e) {
	var $tmp = $("<input>");
	$("body").append($tmp);
	$tmp.val(e).select();
	document.execCommand("copy");
	$tmp.remove();
   mess('Cәтті көшірілді!');
}


// 
function clcBack(e) {
	history.back();
}
function clcPP(e) {
	window.history.forward();
}
function clcP2(e) {
	history.go();
}

// Растау терезесі (браузердің confirm орнына, админдегідей): ask('Сұрақ', function(){ ... }, {ok: 'Иә', no: 'Жоқ', danger: false}) (2026-11-19)
function ask(q, yes, o) {
   o = o || {}
   if (!document.getElementById('ask_css')) {
      var s = document.createElement('style'); s.id = 'ask_css'
      s.textContent = '.ask_bg{position:fixed;inset:0;z-index:99996;background:rgba(17,24,39,.45);display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .2s}.ask_on{opacity:1}' +
         '.ask{background:#fff;border-radius:16px;padding:22px 20px 16px;max-width:380px;width:100%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.25);transform:scale(.96);transition:transform .2s}.ask_on .ask{transform:none}' +
         '.ask_i{font-size:34px;color:#16a34a;margin-bottom:8px}.ask_dg .ask_i{color:#dc2626}.ask_q{font-size:15px;line-height:1.45;color:#111827;margin-bottom:18px}' +
         '.ask_b{display:flex;gap:10px}.ask_b span{flex:1;padding:11px 10px;border-radius:10px;cursor:pointer;font-weight:600;font-size:14px;outline:none}' +
         '.ask_no{background:#f3f4f6;color:#374151}.ask_ok{background:#16a34a;color:#fff}.ask_dg .ask_ok{background:#dc2626}.ask_b span:focus{box-shadow:0 0 0 3px rgba(22,163,74,.3)}'
      document.head.appendChild(s)
   }
   $('.ask_bg').remove()
   var bg = $('<div class="ask_bg"><div class="ask"><div class="ask_i"><i class="far ' + (o.danger ? 'fa-trash-alt' : 'fa-check-circle') + '"></i></div><div class="ask_q"></div><div class="ask_b"><span class="ask_no" tabindex="0"></span><span class="ask_ok" tabindex="0"></span></div></div></div>')
   if (o.danger) bg.addClass('ask_dg')
   bg.find('.ask_q').html(q); bg.find('.ask_no').text(o.no || 'Жоқ'); bg.find('.ask_ok').text(o.ok || 'Иә')
   // o.input берілсе — мән енгізетін өріс (браузердің prompt() орнына): yes(мән) шақырылады
   var inp = null
   if (o.input !== undefined) {
      if (!document.getElementById('ask_in_css')) { var cs = document.createElement('style'); cs.id = 'ask_in_css'; cs.textContent = '.ask_in{display:block;width:100%;box-sizing:border-box;margin:-6px 0 16px;padding:11px 12px;border:1px solid #d1d5db;border-radius:10px;font-size:16px;text-align:center;outline:none;font-family:inherit}.ask_in:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,.2)}'; document.head.appendChild(cs) }
      inp = $('<input class="ask_in" autocomplete="off">').attr({ type: o.type || 'text', inputmode: o.mode || null, placeholder: o.ph || '' }).val(o.input == null ? '' : o.input)
      bg.find('.ask_q').after(inp)
   }
   $('body').append(bg)
   requestAnimationFrame(function(){ bg.addClass('ask_on') })
   function close(){ bg.removeClass('ask_on'); $(document).off('keydown.ask'); setTimeout(function(){ bg.remove() }, 200) }
   bg.on('click', function(e){ if (e.target === this) close() })
   bg.on('click', '.ask_no', close)
   bg.on('click', '.ask_ok', function(){ close(); if (yes) yes(inp ? inp.val() : undefined) })
   $(document).on('keydown.ask', function(e){ if (e.key === 'Escape') close(); if (e.key === 'Enter') { e.preventDefault(); bg.find('.ask_ok').click() } })
   setTimeout(function(){ if (inp) inp.focus().select(); else bg.find('.ask_ok').focus() }, 30)
}
// Мән сұрау (prompt() орнына): askIn('Саны', 1, function (v) { ... }, { ok: 'Сақтау', mode: 'decimal' })
function askIn(q, val, yes, o) {
   o = $.extend({ ok: 'Сақтау', no: 'Болдырмау' }, o || {}); o.input = val == null ? '' : val
   ask(q, yes, o)
}


// ===== ЖИ күту экраны (2026-11-19) =====
// ЖИ (Claude) сұрауы кеткенде бүкіл бетті жабатын «күтіңіз» терезесі шығады: басқа батырмалар басылмайды, бетті жауып/ауысып кетсе ескертеді.
// Өздігінен іске қосылады: fetch пен $.ajax адресі AI_URL-ге сәйкес келсе. Кезекпен жүретін ЖИ (бірінен соң бірі) кезінде экран жабылмай тұрады.
// Қолмен: aiWait.on('мәтін') / aiWait.off() / aiWait.msg('барысы'). Барысы беттегі прогресс жазуынан (AI_PROG) өзі алынады.
;(function () {
   var AI_URL = /\/waybill\/ai\.php(?!\?ping)|\/(set|kind)_ai\.php|\/import\/get\.php\?(read|add|fill_one|fill_set)|\/tpl\/get\.php\?(semi_ai|dish_ai|prices|all(?!&dry))|get\.php\?(ai_suggest|semi_suggest)/
   var AI_PROG = '.mi_stat, .gt_prog, .gt_sprog, .wbi_drop_wait b, .prp_st.load, .gt_mp, [data-ai-prog]'
   var n = 0, man = 0, box = null, t0 = 0, tick = 0, hideTm = 0, lastBtn = null, txt = ''
   function lbl(u) {
      u = String(u || '')
      if (/waybill/.test(u)) return 'ЖИ накладнойды оқып жатыр…'
      if (/import\/get\.php\?read/.test(u)) return 'ЖИ мәзірді оқып жатыр…'
      if (/import/.test(u)) return 'ЖИ мәзірді толтырып жатыр…'
      if (/kind_ai/.test(u)) return 'ЖИ тағам түрін анықтап жатыр…'
      if (/set_ai/.test(u)) return 'ЖИ сет құрамын жазып жатыр…'
      if (/prices/.test(u)) return 'Шикізат бағасы анықталуда…'
      return 'ЖИ граммовка құрастырып жатыр…'
   }
   function css() {
      if (document.getElementById('aiw_css')) return
      var s = document.createElement('style'); s.id = 'aiw_css'
      s.textContent = '.aiw{position:fixed;inset:0;z-index:99990;background:rgba(17,24,39,.45);display:flex;align-items:center;justify-content:center;padding:16px;cursor:progress;opacity:0;transition:opacity .2s}' +
         '.aiw_on{opacity:1}.aiw_b{background:#fff;border-radius:16px;padding:24px 22px 18px;max-width:380px;width:100%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.25);font-family:inherit;color:#111827}' +
         '.aiw_sp{width:46px;height:46px;margin:0 auto 14px;border-radius:50%;border:4px solid #e5e7eb;border-top-color:#16a34a;animation:aiw_r 0.9s linear infinite}' +
         '@keyframes aiw_r{to{transform:rotate(360deg)}}.aiw_t{font-size:16px;font-weight:600;line-height:1.35}.aiw_p{font-size:13px;color:#374151;margin-top:8px;line-height:1.4;word-break:break-word}.aiw_p:empty{display:none}' +
         '.aiw_s{font-size:13px;color:#6b7280;margin-top:10px}.aiw_h{font-size:12px;color:#9ca3af;margin-top:6px}' +
         '.tst_box{z-index:99995!important}.ask_bg{z-index:99996!important}'
      document.head.appendChild(s)
   }
   function prog() {
      var el = null
      document.querySelectorAll(AI_PROG).forEach(function (e) { if (!el && e.offsetParent !== null && e.textContent.trim()) el = e })
      var t = el ? el.textContent.trim() : ''
      if (!t && lastBtn && document.contains(lastBtn)) { t = lastBtn.textContent.trim(); if (t.length > 80) t = '' }
      return txt || t
   }
   function upd() {
      if (!box) return
      var s = Math.round((Date.now() - t0) / 1000), p = prog()
      box.querySelector('.aiw_p').textContent = p && p != box.querySelector('.aiw_t').textContent ? p : ''
      box.querySelector('.aiw_s').textContent = (s >= 60 ? Math.floor(s / 60) + ' мин ' : '') + (s % 60) + ' сек'
   }
   function show(t) {
      clearTimeout(hideTm)
      if (box) { if (t) box.querySelector('.aiw_t').textContent = t; return }
      css(); t0 = Date.now()
      if (document.activeElement && document.activeElement.blur) document.activeElement.blur()
      box = document.createElement('div'); box.className = 'aiw'
      box.innerHTML = '<div class="aiw_b"><div class="aiw_sp"></div><div class="aiw_t"></div><div class="aiw_p"></div><div class="aiw_s">0 сек</div><div class="aiw_h">Күте тұрыңыз, бетті жаппаңыз. Әдетте 10–90 сек.</div></div>'
      box.querySelector('.aiw_t').textContent = t || 'ЖИ жұмыс істеп жатыр…'
      document.body.appendChild(box)
      requestAnimationFrame(function () { box && box.classList.add('aiw_on') })
      tick = setInterval(upd, 500); upd()
   }
   function hide() {
      clearTimeout(hideTm)
      // кезекпен жүретін сұраулар арасында экран жыпылықтамасын
      hideTm = setTimeout(function () {
         if (n > 0 || man > 0 || !box) return
         clearInterval(tick); box.remove(); box = null; txt = ''
      }, 700)
   }
   function start(u) { n++; show(lbl(u)) }
   function end() { n = Math.max(0, n - 1); if (!n) hide() }
   window.aiWait = {
      on: function (t) { man++; show(t) }, off: function () { man = Math.max(0, man - 1); if (!man && !n) hide() },
      msg: function (t) { txt = t || ''; upd() }, busy: function () { return n + man > 0 }, url: AI_URL
   }
   // ЖИ жүріп жатқанда: пернетақта (Enter/Space) мен басулар өтпейді, бетті жапса ескертеді
   document.addEventListener('click', function (e) {
      if (box && !(e.target.closest && e.target.closest('.ask_bg'))) { e.preventDefault(); e.stopPropagation(); return }
      lastBtn = e.target.closest && e.target.closest('button, .btn, a, [class*="_b"], [class*="_ai"]')
   }, true)
   document.addEventListener('keydown', function (e) { if (box && e.key != 'Tab' && !document.querySelector('.ask_bg')) { e.preventDefault(); e.stopPropagation() } }, true)
   window.addEventListener('beforeunload', function (e) { if (box) { e.preventDefault(); e.returnValue = 'ЖИ әлі жұмыс істеп жатыр'; return e.returnValue } })
   // fetch
   if (window.fetch) {
      var of = window.fetch
      window.fetch = function (u) {
         var s = typeof u == 'string' ? u : (u && u.url) || ''
         if (!AI_URL.test(s)) return of.apply(this, arguments)
         start(s)
         return of.apply(this, arguments).then(function (r) { end(); return r }, function (e) { end(); throw e })
      }
   }
   // jQuery $.ajax
   if (window.jQuery) {
      jQuery(document).ajaxSend(function (e, x, o) { if (AI_URL.test(o.url || '')) { x._aiw = 1; start(o.url) } })
      jQuery(document).ajaxComplete(function (e, x) { if (x._aiw) { x._aiw = 0; end() } })
   }
})()
