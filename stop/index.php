<? include "../config/core.php";
	include "../waybill/lib.php";
	include "../config/stop_list.php";

	// Кухня: стоп-лист. Аспаз тағамды стопқа қояды / алады. «Сатуға рұқсат» пен автомат стоп баптауы — тек админде.
	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], [1, 2, 3, 9])) { $core->user_unset(); header('location: /'); exit(); }

	$cmp = (int)$company;
	$csrf_ok = isset($_POST['csrf']) && hash_equals(kwb::csrf(), (string)$_POST['csrf']);
	if (isset($_POST['pid']) && $csrf_ok) {
		$mode = (int)@$_POST['mode'] == 1 ? 1 : 0;
		stopl::set($cmp, (int)$_POST['pid'], $mode, $user_id, t::w('Кухня', 'Кухня'));
		header('location: /stop/?ok='.$mode.'#p'.(int)$_POST['pid']); exit();
	}

	$own = stopl::own($cmp);
	$ids = stopl::ids($cmp);
	$raw_out = stopl::raw_out($cmp);
	$_SESSION['stopl_raw_'.$cmp] = ['t' => time(), 'd' => $raw_out];
	$all = []; $cats = [];
	$q = db::query("select p.id, p.name_kz, p.name_ru, c.id cid, c.name_kz cname from product p join product_catalog c on c.id = p.catalog_id
		where c.company_id = '$cmp' and (c.arh is null or c.arh = 0) and p.arh = 0 order by c.number, c.id, p.name_kz");
	while ($q && $r = mysqli_fetch_assoc($q)) {
		$r['nm'] = trim(strip_tags($r['name_kz'] ?: $r['name_ru']));
		$all[(int)$r['id']] = $r;
		$cats[(int)$r['cid']]['name'] = strip_tags($r['cname']);
		$cats[(int)$r['cid']]['items'][] = (int)$r['id'];
	}
	$stopped = array_filter($ids, function ($v, $pid) use ($all) { return isset($all[$pid]); }, ARRAY_FILTER_USE_BOTH);
	$warn = array_filter($raw_out, function ($m, $pid) use ($all, $ids, $own) { return isset($all[$pid]) && empty($ids[$pid]) && !isset($own[$pid]); }, ARRAY_FILTER_USE_BOTH);
	$csrf = kwb::h(kwb::csrf());
	$miss_txt = function ($m) { $m = (array)$m; $n = count($m); return kwb::h(implode(', ', array_slice($m, 0, 4))).($n > 4 ? ' +'.($n - 4) : ''); };
	$btn = function ($pid, $mode, $txt, $cls) use ($csrf) {
		return '<form method="post" class="ks_f"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="pid" value="'.(int)$pid.'"><input type="hidden" name="mode" value="'.(int)$mode.'"><button class="btn kt_btn '.$cls.'">'.$txt.'</button></form>';
	};

	$menu_name = 'stop';
	$css = ['orders'];
	$js = [];
?>
<? include "../block/header.php"; ?>
<style>
	.ks_box{background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:12px}
	.ks_h{font-weight:800;font-size:16px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
	.ks_warn{background:#fffaf0;border:1px solid #f5c26b}
	.ks_row{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f3f6}
	.ks_row:last-child{border-bottom:none}
	.ks_n{flex:1;min-width:0}
	.ks_n small{display:block;color:#8a94a6;font-size:12px}
	.ks_stop .ks_n b{color:#c92a2a}
	.ks_f{margin:0}
	.ks_f .btn{padding:8px 12px;font-size:13px;white-space:nowrap}
	.ks_red{background:#c92a2a !important;color:#fff !important}
	.ks_tag{display:inline-block;padding:1px 7px;border-radius:7px;font-size:11px;font-weight:700;background:#fdecec;color:#c92a2a}
	.ks_tag_o{background:#fff0d6;color:#b86e00}
	.ks_tag_g{background:#eef0f4;color:#5b6475}
	.ks_cat{font-size:12px;font-weight:800;color:#8a94a6;text-transform:uppercase;padding:10px 0 2px}
	.ks_cnt{min-width:26px;height:26px;border-radius:13px;background:#fdecec;color:#c92a2a;display:flex;align-items:center;justify-content:center;font-size:13px;padding:0 7px}
</style>

	<div class="bp_page">
		<div class="bp_head">
			<div class="bl_c">
				<div class="bp_head_t"><div><h1 class="bp_head_h"><?=t::w('Стоп-лист', 'Стоп-лист')?></h1></div></div>
			</div>
		</div>
		<div class="bl_c">
			<p class="kw_hint"><?=t::w('Стоптағы тағамды касса сата алмайды, QR мәзірде «Қазір жоқ» деп тұрады.', 'Блюдо в стопе нельзя продать на кассе, в QR меню оно «Нет в наличии».')?></p>
			<? if (isset($_GET['ok'])): ?><p class="kw_hint kr_ok"><i class="far fa-check"></i> <?=($_GET['ok'] ? t::w('Стопқа қойылды', 'Поставлено в стоп') : t::w('Стоптан алынды', 'Снято со стопа'))?></p><? endif ?>

			<div class="ks_box">
				<div class="ks_h"><span><?=t::w('Қазір стопта', 'Сейчас в стопе')?></span><span class="ks_cnt"><?=count($stopped)?></span></div>
				<? foreach ($stopped as $pid => $k): $p = $all[$pid]; ?>
					<div class="ks_row ks_stop">
						<div class="ks_n"><b><?=kwb::h($p['nm'])?></b>
							<small><? if ($k == 1): ?><span class="ks_tag"><?=t::w('Қолмен', 'Вручную')?></span><? elseif ($k == 3): ?><span class="ks_tag ks_tag_o"><?=t::w('Автомат', 'Авто')?></span> <?=t::w('Бітті', 'Закончилось')?>: <?=$miss_txt($raw_out[$pid] ?? [])?><? else: ?><span class="ks_tag ks_tag_g"><?=t::w('Сет', 'Сет')?></span> <?=t::w('ішіндегі тағам стопта', 'блюдо внутри в стопе')?><? endif ?></small>
						</div>
						<? if ($k == 1): ?><?=$btn($pid, 0, t::w('Стоптан алу', 'Снять'), 'kt_btn_ok')?><? endif ?>
					</div>
				<? endforeach ?>
				<? if (!$stopped): ?><p class="kw_hint"><?=t::w('Стопта ештеңе жоқ', 'В стопе ничего нет')?></p><? endif ?>
			</div>

			<? if ($warn): ?>
				<div class="ks_box ks_warn">
					<div class="ks_h"><span><i class="far fa-exclamation-triangle"></i> <?=t::w('Шикізаты бітті', 'Закончилось сырьё')?></span></div>
					<? foreach ($warn as $pid => $miss): ?>
						<div class="ks_row">
							<div class="ks_n"><b><?=kwb::h($all[$pid]['nm'])?></b><small><?=t::w('Бітті', 'Закончилось')?>: <?=$miss_txt($miss)?></small></div>
							<?=$btn($pid, 1, t::w('Стопқа', 'В стоп'), 'ks_red')?>
						</div>
					<? endforeach ?>
				</div>
			<? endif ?>

			<div class="ks_box">
				<div class="ks_h"><span><?=t::w('Барлық тағам', 'Все блюда')?></span></div>
				<input class="form_txt ks_find" type="search" placeholder="<?=t::h('Іздеу', 'Поиск')?>">
				<? foreach ($cats as $c): ?>
					<div class="ks_c">
						<div class="ks_cat"><?=kwb::h($c['name'])?></div>
						<? foreach ($c['items'] as $pid): $p = $all[$pid]; $k = $ids[$pid] ?? 0; ?>
							<div class="ks_row<?=($k ? ' ks_stop' : '')?>" id="p<?=$pid?>" data-n="<?=kwb::h(mb_strtolower($p['nm'].' '.strip_tags($p['name_ru'])))?>">
								<div class="ks_n"><b><?=kwb::h($p['nm'])?></b></div>
								<? if ($k == 1): ?><?=$btn($pid, 0, t::w('Стоптан алу', 'Снять'), 'kt_btn_ok')?>
								<? elseif (!$k): ?><?=$btn($pid, 1, t::w('Стопқа', 'В стоп'), 'ks_red')?>
								<? else: ?><span class="ks_tag<?=($k == 3 ? ' ks_tag_o' : ' ks_tag_g')?>"><?=t::w('Стопта', 'В стопе')?></span><? endif ?>
							</div>
						<? endforeach ?>
					</div>
				<? endforeach ?>
			</div>
		</div>
	</div>

	<script>
		document.addEventListener('input', function (e) {
			if (!e.target.classList.contains('ks_find')) return;
			var s = e.target.value.trim().toLowerCase();
			document.querySelectorAll('.ks_c').forEach(function (c) {
				var v = 0;
				c.querySelectorAll('.ks_row').forEach(function (r) { var ok = !s || r.dataset.n.indexOf(s) >= 0; r.style.display = ok ? '' : 'none'; if (ok) v++; });
				c.style.display = v ? '' : 'none';
			});
		});
	</script>

<? include "../block/footer.php"; ?>
