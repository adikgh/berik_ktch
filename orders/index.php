<? include "../config/core.php";

	// Кухня: тек аспаздар мен басшылар
	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], [1, 2, 3, 9])) { $core->user_unset(); header('location: /'); exit(); }

	$cmp = fun::company($company);
	if ($cmp['ins_dt'] != null && $cmp['end_dt'] != null) {
		$result = intval((strtotime($cmp['end_dt']) - strtotime(date("d.m.Y"))) / (60*60*24));
		if ($result <= 0) { header('location: http://kassa.abdis.kz/pay.php'); exit(); }
	}

	// Кезекте: Жаңа (1) + Дайындалуда (8). Дайын: бүгін дайын болғандар (2 және одан әрі).
	$sort = @$_GET['sort'] == 'ready' ? 'ready' : 'queue';
	$cid = (int)$company;
	$types = "1, 2, 4"; // Жеткізу, Собой, Кафе (Сервис кухняға келмейді)
	if ($sort == 'queue') {
		$orders = db::query("select * from retail_orders where ins_dt BETWEEN '$start_cdate' and '$end_cdate' and `paid` = 1 and company_id = '$cid' and `order_type` in ($types) and `order_status` in (1, 8) order by ins_dt asc, number asc");
	} else {
		$orders = db::query("select o.* from retail_orders o
			join (select order_id, max(ins_dt) dt from retail_orders_log where field = 'status' and new_value = '2' group by order_id) l on l.order_id = o.id
			where o.ins_dt BETWEEN '$start_cdate' and '$end_cdate' and o.`paid` = 1 and o.company_id = '$cid' and o.`order_type` in ($types) and o.`order_status` not in (1, 8, 5, 6, 10)
			order by l.dt desc limit 60");
	}

	// Дайын болған уақыт (Дайын табы үшін)
	$ready_dt = [];
	if ($sort == 'ready') {
		$rq = db::query("select l.order_id, max(l.ins_dt) dt from retail_orders_log l join retail_orders o on o.id = l.order_id where o.company_id = '$cid' and o.ins_dt BETWEEN '$start_cdate' and '$end_cdate' and l.field = 'status' and l.new_value = '2' group by l.order_id");
		while ($r = mysqli_fetch_assoc($rq)) $ready_dt[(int)$r['order_id']] = $r['dt'];
	}

	$menu_name = 'kitchen';
	$css = ['orders'];
	$js = ['orders'];
	$o_cnt = $orders ? mysqli_num_rows($orders) : 0;
	$tp_nm = [1 => t::w('Жеткізу', 'Доставка'), 2 => t::w('Өзімен', 'Собой'), 4 => t::w('Кафе', 'Кафе')];
	$tp_ic = [1 => 'fa-car', 2 => 'fa-walking', 4 => 'fa-utensils'];
	$st_nm = [1 => t::w('Жаңа', 'Новый'), 8 => t::w('Дайындалуда', 'Готовится'), 2 => t::w('Дайын', 'Готов'), 11 => t::w('Курьер тағайындалды', 'Курьер назначен'), 3 => t::w('Жолда', 'В пути'), 9 => t::w('Столда', 'На столе'), 4 => t::w('Аяқталды', 'Завершён')];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page kt_page" data-sort="<?=$sort?>">

		<div class="bp_head">
			<div class="bl_c">
				<div class="bp_head_t">
					<div><h1 class="bp_head_h"><?=($sort == 'queue' ? t::w('Кезекте', 'В очереди') : t::w('Бүгін дайын', 'Готовые сегодня'))?></h1></div>
					<div class="kt_head_r">
						<div class="kt_snd on_snd" title="<?=t::h('Жаңа тапсырыс дыбысы', 'Звук нового заказа')?>"><i class="far fa-volume-mute"></i></div>
						<div class="bp_cnt">
							<span><?=t::w('Тапсырыс', 'Заказы')?></span>
							<b class="pp_number"><?=$o_cnt?></b>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="bl_c">
			<div class="uc_u kt_list">
				<? if ($o_cnt): ?>
					<? while ($o = mysqli_fetch_assoc($orders)): ?>
						<? $branch_d = $o['branch_id'] ? fun::branch($o['branch_id']) : 0;
							$ot = (int)$o['order_type']; $st = (int)$o['order_status'];
							$nm = (string)@$branch_d['code'].$o['number'];
							$pre = trim((string)$o['preorder_dt']);
							$note = trim(strip_tags((string)$o['additional'])); $cmt = trim(strip_tags((string)$o['comment'])); ?>
						<div class="uc_ui kt_card kt_st<?=$st?>" data-id="<?=(int)$o['id']?>">
							<div class="uc_uil2">
								<div class="kt_top">
									<div class="kt_nmb"><?=htmlspecialchars($nm)?></div>
									<div class="kt_info">
										<div class="kt_tags">
											<span class="kt_tp kt_tp<?=$ot?>"><i class="far <?=@$tp_ic[$ot]?>"></i> <?=@$tp_nm[$ot]?></span>
											<? if (isset($st_nm[$st])): ?><span class="bp_st bp_st<?=$st?>"><?=$st_nm[$st]?></span><? endif ?>
										</div>
										<div class="bp_tm">
											<b class="bp_time"><?=date("H:i", strtotime($o['ins_dt']))?></b>
											<? if ($sort == 'queue'): ?><span class="kt_wait" data-t="<?=strtotime($o['ins_dt'])?>"></span><? endif ?>
											<? if ($sort == 'ready' && isset($ready_dt[(int)$o['id']])): ?><span class="kt_done"><i class="far fa-check"></i> <?=date("H:i", strtotime($ready_dt[(int)$o['id']]))?> · <?=max(0, (int)round((strtotime($ready_dt[(int)$o['id']]) - strtotime($o['ins_dt'])) / 60))?> <?=t::w('мин', 'мин')?></span><? endif ?>
											<i class="far fa-history bp_hist_ic on_hist" data-id="<?=(int)$o['id']?>" data-nm="<?=htmlspecialchars($nm)?>" title="<?=t::h('Статус тарихы', 'История статусов')?>"></i>
										</div>
									</div>
								</div>
								<? if ($pre !== ''): ?><div class="kt_pre"><i class="far fa-bell"></i> <?=t::w('Предзаказ', 'Предзаказ')?>: <b><?=htmlspecialchars($pre)?></b></div><? endif ?>

								<div class="kt_items">
									<? $ps = db::query("select * from retail_orders_products where order_id = '".(int)$o['id']."' order by ins_dt asc"); ?>
									<? while ($p = mysqli_fetch_assoc($ps)): $pd = product::product($p['product_id']); ?>
										<div class="kt_it">
											<b class="kt_q"><?=(float)$p['quantity'] + 0?></b>
											<span class="kt_n"><?=htmlspecialchars((string)@$pd['name_ru'])?></span>
										</div>
									<? endwhile ?>
								</div>

								<? if ($note !== '' || $cmt !== ''): ?>
									<div class="kt_note"><i class="far fa-comment-alt-lines"></i> <span><?=htmlspecialchars(trim($note.($note !== '' && $cmt !== '' ? ' · ' : '').$cmt))?></span></div>
								<? endif ?>

								<div class="kt_act">
									<? if ($st == 1): ?>
										<div class="btn kt_btn kt_btn_go on_kst" data-id="<?=(int)$o['id']?>" data-st="8"><i class="far fa-fire"></i> <?=t::w('Дайындау', 'Готовить')?></div>
									<? endif ?>
									<? if ($st == 1 || $st == 8): ?>
										<div class="btn kt_btn kt_btn_ok on_kst" data-id="<?=(int)$o['id']?>" data-st="2"><i class="far fa-check"></i> <?=t::w('Дайын', 'Готово')?></div>
									<? elseif ($st == 2): ?>
										<div class="btn kt_btn kt_btn_back on_kst" data-id="<?=(int)$o['id']?>" data-st="8" data-ask="<?=t::h('Тапсырысты қайта «Дайындалуда» күйіне қайтару керек пе?', 'Вернуть заказ в «Готовится»?')?>"><i class="far fa-undo"></i> <?=t::w('Қайтару', 'Вернуть')?></div>
									<? endif ?>
								</div>
							</div>
						</div>
					<? endwhile ?>
				<? else: ?>
					<div class="ds_nr"><i class="fal fa-inbox"></i><p><?=($sort == 'queue' ? t::w('Кезекте тапсырыс жоқ', 'Очередь пуста') : t::w('Тапсырыс жоқ', 'Заказов нет'))?></p></div>
				<? endif ?>
			</div>
		</div>

	</div>

	<div class="bp_dr bp_hist_sh">
		<div class="bp_dr_bg"></div>
		<div class="bp_dr_c">
			<div class="bp_dr_grab"></div>
			<div class="bp_sh_h"><span><?=t::w('Тарих', 'История')?> <b class="bp_hist_nm"></b></span> <span class="bp_dr_x"><i class="far fa-times"></i></span></div>
			<div class="oh_list"></div>
		</div>
	</div>

	<script>
		window.KT = { min: <?=json_encode(t::w('мин', 'мин'))?>, err: <?=json_encode(t::w('Статус өзгерді, бет жаңартылды', 'Статус изменился, страница обновлена'))?> };
	</script>

<? include "../block/footer.php"; ?>
