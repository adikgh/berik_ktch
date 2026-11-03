<?php
// Тапсырыстың статус тарихы — кассадағыдай уақыт сызығы. get.php?history мен item.php қолданады.
function dist_hist_html($o) {
	$oid = (int)$o['id'];
	$last = [];
	$lg = db::query("select new_value sid, max(ins_dt) dt from retail_orders_log where order_id = '$oid' and field = 'status' group by new_value");
	while ($l = mysqli_fetch_assoc($lg)) $last[(int)$l['sid']] = $l['dt'];
	$nm_kz = [1 => t::w('Жаңа', 'Новый'), 8 => t::w('Дайындалуда', 'Готовится'), 2 => t::w('Дайын', 'Готов'), 11 => t::w('Курьер тағайындалды', 'Курьер назначен'), 3 => t::w('Жолда', 'В пути'), 9 => t::w('Столда', 'На столе'), 4 => t::w('Аяқталды', 'Завершён'), 5 => t::w('Бас тарту', 'Отказ'), 6 => t::w('Алғанда бас тарту', 'Отказ при получении'), 10 => t::w('Сыртта бас тарту', 'Отказ вне')];
	$cur = (int)$o['order_status'];
	$rows = []; $first = true;
	$sts = db::query("select * from retail_orders_status order by number asc");
	while ($s = mysqli_fetch_assoc($sts)) {
		$sid = (int)$s['id'];
		$dt = @$last[$sid];
		if ($first) $dt = $o['ins_dt']; // Жаңа: тапсырыс жасалған уақыт
		$first = false;
		if ($sid == 7 && !$dt && $cur != $sid) continue; // Вопрос
		if (!in_array((string)$o['order_type'], explode(',', (string)@$s['types'])) && !$dt && $cur != $sid) continue; // бұл түрге жатпайды
		$rows[] = ['nm' => @$nm_kz[$sid] ?: trim($s['name_ru']), 'clr' => $s['clr'] ?: '#9aa0a6', 'dt' => $dt, 'on' => ($dt || $cur == $sid), 'cur' => $cur == $sid];
	}
	$ci = -1; foreach ($rows as $k => $r) if ($r['cur']) $ci = $k;
	if ($ci >= 0) foreach ($rows as $k => $r) if ($k > $ci) { $rows[$k]['on'] = false; $rows[$k]['dt'] = null; }
	$last_on = -1; foreach ($rows as $k => $r) if ($r['on']) $last_on = $k;
	$final = in_array($cur, [4, 5, 6, 10]);
	foreach ($rows as $k => $r) $rows[$k]['skip'] = !$r['on'] && ($k < $last_on || $final);

	$ch = dist_changes_html($oid);
	$h = '<div class="oh_tabs"><span class="oh_tab oh_tab_act" data-t="st">'.t::w('Статус', 'Статус').'</span><span class="oh_tab" data-t="ch">'.t::w('Өзгерістер', 'Изменения').($ch[1] ? ' <i>'.$ch[1].'</i>' : '').'</span></div>';
	$h .= '<div class="oh_pane" data-p="st"><div class="oh_list">';
	foreach ($rows as $i => $r) {
		$nx = @$rows[$i + 1];
		$link = $r['on'] && $nx && $nx['on'] && !$r['cur'];
		$dash = $nx && (!empty($r['skip']) || !empty($nx['skip']));
		$h .= '<div class="oh_row'.($r['cur'] ? ' oh_cur' : '').($r['on'] ? ' oh_on' : '').($link ? ' oh_link' : '').($r['skip'] ? ' oh_skip' : '').($dash ? ' oh_dash' : '').'" style="--c:'.htmlspecialchars($r['clr']).';--n:'.htmlspecialchars($nx ? $nx['clr'] : $r['clr']).';--i:'.$i.'"><em class="oh_dot"></em><span class="oh_nm">'.htmlspecialchars($r['nm']).'</span>'
			.($r['dt'] ? '<span class="oh_dt"><b>'.date('H:i', strtotime($r['dt'])).'</b><small>'.date('d.m.Y', strtotime($r['dt'])).'</small></span>' : '').'</div>';
	}
	$h .= '</div><div class="oh_foot">';
	if (@$o['upd_dt']) $h .= '<div class="oh_row"><span class="oh_nm">'.t::w('Соңғы өзгеріс', 'Последнее изменение').'</span><span class="oh_dt"><b>'.date('H:i', strtotime($o['upd_dt'])).'</b><small>'.date('d.m.Y', strtotime($o['upd_dt'])).'</small></span></div>';
	$au = $o['user_id'] ? fun::user($o['user_id']) : null;
	$h .= '<div class="oh_row"><span class="oh_nm">'.t::w('Тапсырыс авторы', 'Автор заказа').'</span><span class="oh_dt"><b>'.htmlspecialchars((string)@$au['name']).'</b></span></div>';
	$h .= '</div></div>';
	$h .= '<div class="oh_pane dsp_n" data-p="ch">'.($ch[0] !== '' ? $ch[0] : '<div class="oh_empty">'.t::w('Әзірге өзгеріс жоқ', 'Изменений пока нет').'</div>').'</div>';
	return $h;
}


// Барлық өзгерістер (кассадағыдай): кім, қашан, не өзгертті. [html, саны]
function dist_changes_html($oid) {
	$oid = (int)$oid;
	$lbl = ['created' => t::w('Жасалды', 'Создан'), 'order_type' => t::w('Түрі', 'Тип'), 'courier' => t::w('Курьер', 'Курьер'), 'branch_id' => t::w('Точка', 'Точка'), 'phone' => t::w('Нөмір', 'Номер'), 'address' => t::w('Мекенжай', 'Адрес'), 'floor_apt' => t::w('Қабат, пәтер', 'Этаж, кв'), 'additional' => t::w('Қосымша', 'Примечание'), 'preorder_dt' => t::w('Предзаказ', 'Предзаказ'), 'total' => t::w('Жалпы', 'Итого'), 'pay_delivery' => t::w('Жеткізу', 'Доставка'), 'pay_qr' => t::w('Алдын ала төлем', 'Предоплата'), 'pay_cash' => t::w('Курьерге (нал)', 'Курьеру (нал)'), 'pay_prepay' => t::w('Алдын ала төлем', 'Предоплата'), 'prepay_method' => t::w('Төлем', 'Оплата'), 'payment_method' => t::w('Төлем', 'Оплата'), 'pay_transfer' => t::w('Аударым', 'Перевод'), 'pay_red' => 'RED', 'service' => t::w('Сервис', 'Сервис'), 'items' => t::w('Тағамдар', 'Блюда'), 'refuse_reason' => t::w('Себебі', 'Причина'), 'kept_sum' => t::w('Бізде қалды', 'Осталось у нас')];
	$pm = ['transfer' => t::w('Аударым', 'Перевод'), 'qr' => 'Kaspi QR', 'cash' => t::w('Қолма-қол', 'Наличные'), 'mixed' => t::w('Аралас', 'Смешанная'), 'mix' => t::w('Аралас', 'Смешанная'), 'unpaid' => t::w('Төленбеген', 'Не оплачен'), 'red' => 'RED'];
	$types = [1 => t::w('Жеткізу', 'Доставка'), 2 => t::w('Өзімен', 'Собой'), 3 => t::w('Сервис', 'Сервис'), 4 => t::w('Кафе', 'Кафе')];
	$hv = function ($f, $v) use ($pm, $types) {
		if ($v === null || $v === '') return '—';
		$v = trim(html_entity_decode(strip_tags((string)$v)));
		if ($f == 'order_type') return @$types[(int)$v] ?: $v;
		if ($f == 'courier') { $u = fun::user((int)$v); return @$u['name'] ?: $v; }
		if ($f == 'branch_id') { $b = fun::branch((int)$v); return @$b['name'] ?: $v; }
		if ($f == 'payment_method' || $f == 'prepay_method') return @$pm[$v] ?: $v;
		if (in_array($f, ['total','pay_delivery','pay_qr','pay_cash','pay_prepay','pay_transfer','pay_red','kept_sum'])) return number_format((float)$v, 0, '', ' ').' тг';
		return $v;
	};
	// Тағамдар: тек айырмасы — «+Кола», «−Мия», «Ролл 1→2»
	$parse = function ($v) { $m = []; foreach (array_filter(array_map('trim', explode(';', trim(html_entity_decode(strip_tags((string)$v)))))) as $it) { if (preg_match('/^(.*?)\s*×(\d+)$/u', $it, $x)) $m[trim($x[1])] = (int)$x[2]; else $m[$it] = 1; } return $m; };
	$idiff = function ($old, $new) use ($parse) {
		$a = $parse($old); $b = $parse($new); $out = [];
		foreach ($b as $n => $q) if (!isset($a[$n])) $out[] = ['add', '+'.$n.($q > 1 ? ' ×'.$q : '')]; elseif ($a[$n] != $q) $out[] = ['qn', $n.' '.$a[$n].'→'.$q];
		foreach ($a as $n => $q) if (!isset($b[$n])) $out[] = ['del', '−'.$n];
		return $out;
	};
	$all = []; $q = db::query("select * from retail_orders_log where order_id = '$oid' and field <> 'status' order by id desc limit 80");
	while ($c = mysqli_fetch_assoc($q)) $all[] = $c;
	$it_at = []; foreach ($all as $c) if ($c['field'] == 'items') $it_at[$c['ins_dt']] = true;
	$sum_at = []; foreach ($all as $c) if ($c['field'] == 'total' && isset($it_at[$c['ins_dt']])) $sum_at[$c['ins_dt']] = $c;
	$h = ''; $n = 0;
	foreach ($all as $c) {
		$f = $c['field']; $u = $c['user_id'] ? fun::user($c['user_id']) : null;
		if (($f == 'total' || $f == 'pay_cash') && isset($it_at[$c['ins_dt']])) continue; // тағам өзгергенде сома өзі есептеледі
		if ($f == 'created') $val = '<span class="oh_new">'.htmlspecialchars((string)$c['new_value']).'</span>';
		elseif ($f == 'items') {
			$val = ''; foreach ($idiff($c['old_value'], $c['new_value']) as $d) $val .= '<span class="oh_it oh_it_'.$d[0].'">'.htmlspecialchars($d[1]).'</span>';
			if (isset($sum_at[$c['ins_dt']])) { $t = $sum_at[$c['ins_dt']]; $val .= '<span class="oh_sum">'.t::w('Жалпы', 'Итого').': '.((string)$t['old_value'] !== '' ? '<s>'.htmlspecialchars($hv('total', $t['old_value'])).'</s> → ' : '').'<b>'.htmlspecialchars($hv('total', $t['new_value'])).'</b></span>'; }
			if ($val === '') continue;
		}
		else $val = ((string)$c['old_value'] === '' ? '' : '<s>'.htmlspecialchars($hv($f, $c['old_value'])).'</s> → ').'<b>'.htmlspecialchars($hv($f, $c['new_value'])).'</b>';
		$h .= '<div class="oh_cl"><div class="oh_cl_t"><span class="oh_cl_f">'.htmlspecialchars(@$lbl[$f] ?: $f).'</span><small>'.date('H:i d.m', strtotime($c['ins_dt'])).($u ? ' · '.htmlspecialchars((string)@$u['name']) : '').'</small></div><div class="oh_cl_v">'.$val.'</div></div>';
		$n++;
	}
	return [$h, $n];
}
