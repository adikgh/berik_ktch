<? include "../config/core.php";
	include "lib.php";
	include "../config/claude.php";

	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], kwb::$rights)) { $core->user_unset(); header('location: /'); exit(); }

	// Админдегі накладнойдай: фото/PDF-ті Claude оқиды, Excel/PDF браузерде оқылады (assets/js/waybill_import.js),
	// атауы шикізатпен сәйкестендіріледі, жеткізуші тізімнен табылады. Кухня тек жоба сақтайды, қабылдауды админ жасайды.
	$cmp = (int)$company;
	$id = (int)@$_GET['id'];
	$w = $id ? kwb::get($id, $cmp) : null;
	if ($id && !$w) { header('location: /waybill/'); exit(); }
	$edit = !$w || kwb::can_edit($w, $user_id);
	$items = $w ? kwb::items($id) : [];
	if (!$items && $edit) $items = [['raw_id' => 0, 'name' => '', 'quantity' => '', 'unit' => 'кг', 'price' => '', 'sum' => '']];

	// шикізат тізімі: атауы, өлшемі, соңғы бағасы (атау жазғанда ұсыну және файлдағы атауды сәйкестендіру үшін)
	$raw = []; $raw_n = [];
	$has_u = kwb::has_col('product_raw', 'unit');
	$rq = db::query("select id, name_kz, name_ru".($has_u ? ', unit' : '').", price from product_raw where company_id = '$cmp' order by name_kz");
	while ($rq && $r = mysqli_fetch_assoc($rq)) {
		$n = trim($r['name_kz'] ?: $r['name_ru']); if ($n === '') continue;
		$ru = trim((string)$r['name_ru']);
		$raw_n[(int)$r['id']] = $n;
		$raw[] = ['id' => (int)$r['id'], 'n' => $n, 'u' => kwb::unit_norm(@$r['unit']), 'p' => (float)$r['price']] + ($ru !== '' && $ru !== $n ? ['r' => $ru] : []);
	}
	// жеткізуші тауары → шикізат (1 шт = factor), supplier_item кестесі болса
	$alias = [];
	if (kwb::has_col('supplier_item', 'factor')) {
		$aq = db::query("select name, raw_id, factor from supplier_item where company_id = '$cmp'");
		while ($aq && $a = mysqli_fetch_assoc($aq)) $alias[] = ['n' => $a['name'], 'id' => (int)$a['raw_id'], 'f' => (float)$a['factor']];
	}
	$sup_id = (int)@$w['supplier_id'];
	$suppliers = [];
	$sq = db::query("select id, name from supplier where company_id = '$cmp' and (archive = 0 or id = '$sup_id') order by name");
	while ($sq && $s = mysqli_fetch_assoc($sq)) $suppliers[] = $s;

	$ai_on = claude::ready();
	$debt = $w ? max(0, (float)$w['total'] - (float)$w['paid']) : 0;

	$menu_name = 'waybill';
	$css = ['orders', 'waybill'];
	$js = $edit ? ['waybill', 'waybill_import'] : [];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page">
		<div class="bl_c wb_page kw_page">
			<a class="ord_back" href="/waybill/"><i class="far fa-long-arrow-left"></i> <?=t::w('Накладной', 'Накладные')?></a>

			<div class="wb_doc" data-id="<?=$id?>" data-posted="<?=($edit ? 0 : 1)?>" data-cmp="<?=$cmp?>" data-csrf="<?=kwb::h(kwb::csrf())?>">

				<? if ($edit): ?>
				<!-- Чекті суретке түсіру: Claude оқып, тауарларды өзі толтырады -->
				<div class="wb_box kw_scan">
					<div class="kw_scan_t">
						<b><?=t::w('Чекті суретке түсіріңіз', 'Сфотографируйте накладную')?></b>
						<span><?=($ai_on ? t::w('Тауарларды, санын, бағасын өзі толтырады. Ұзын чек болса, бірнеше фото таңдаңыз.', 'Товары, количество и цены заполнятся сами. Длинный чек — выберите несколько фото.') : t::w('Excel мен PDF оқылады. Фото оқу үшін админ Claude-ты қосуы керек.', 'Читаются Excel и PDF. Для фото админ должен подключить Claude.'))?></span>
					</div>
					<div class="kw_scan_b">
						<? if ($ai_on): ?>
							<label class="wb_btn wb_btn_green kw_cam"><i class="far fa-camera"></i> <?=t::w('Фото түсіру', 'Сфотографировать')?>
								<input type="file" accept="image/*" capture="environment" class="kw_cam_in">
							</label>
						<? endif ?>
						<div class="wb_btn wb_btn_light wb_imp_open"><i class="far fa-file-import"></i> <?=t::w('Файлдан', 'Из файла')?></div>
					</div>
				</div>
				<? endif ?>

				<!-- Негізгі деректер -->
				<div class="wb_box">
					<div class="wb_box_h">
						<div><?=($w ? t::w('Накладной', 'Накладная').' №'.(int)$w['number'] : t::w('Жаңа накладной', 'Новая накладная'))?></div>
						<? if ($w): ?><?=((int)$w['status'] == 1 ? '<span class="wb_st wb_st_ok">'.t::w('Қабылданды', 'Принята').'</span>' : '<span class="wb_st wb_st_draft">'.t::w('Тексеруде', 'На проверке').'</span>')?><? endif ?>
					</div>
					<div class="wb_grid">
						<label class="wb_fld wb_fld_2">
							<span class="wb_lbl"><?=t::w('Жеткізуші', 'Поставщик')?></span>
							<select class="wb_inp wb_f_supplier" <?=$edit ? '' : 'disabled'?>>
								<option value="0"><?=t::w('Таңдаңыз', 'Выберите')?></option>
								<? foreach ($suppliers as $s): ?><option value="<?=(int)$s['id']?>" <?=($sup_id == $s['id'] ? 'selected' : '')?>><?=kwb::h($s['name'])?></option><? endforeach ?>
								<? if ($edit): ?><option value="new">+ <?=t::w('Жаңа жеткізуші', 'Новый поставщик')?></option><? endif ?>
							</select>
							<input type="text" class="wb_inp wb_f_supplier_new dsp_n" placeholder="<?=t::h('Жаңа жеткізушінің аты', 'Имя нового поставщика')?>">
						</label>
						<label class="wb_fld">
							<span class="wb_lbl"><?=t::w('Күні', 'Дата')?></span>
							<input type="date" class="wb_inp wb_f_date" value="<?=kwb::h(@$w['doc_date'] ?: date('Y-m-d'))?>" <?=$edit ? '' : 'disabled'?>>
						</label>
						<label class="wb_fld">
							<span class="wb_lbl"><?=t::w('Құжат №', '№ документа')?></span>
							<input type="text" class="wb_inp wb_f_docno" value="<?=kwb::h(@$w['doc_no'])?>" placeholder="<?=t::h('Міндетті емес', 'Необязательно')?>" <?=$edit ? '' : 'disabled'?>>
						</label>
					</div>
				</div>

				<!-- Тауарлар -->
				<div class="wb_box wb_table">
					<div class="wb_box_h"><div><?=t::w('Тауарлар', 'Товары')?></div></div>
					<div class="wb_tr wb_th">
						<div class="wb_td wb_td_n">#</div>
						<div class="wb_td wb_td_name"><?=t::w('Атауы', 'Название')?></div>
						<div class="wb_td wb_td_unit"><?=t::w('Өлшемі', 'Ед.')?></div>
						<div class="wb_td"><?=t::w('Саны', 'Кол-во')?></div>
						<div class="wb_td"><?=t::w('Бағасы', 'Цена')?></div>
						<div class="wb_td"><?=t::w('Сомасы', 'Сумма')?></div>
						<? if ($edit): ?><div class="wb_td wb_td_x"></div><? endif ?>
					</div>
					<div class="wb_rows">
						<? foreach ($items as $k => $i): $iu = kwb::unit_norm($i['unit']); $rid = (int)$i['raw_id']; ?>
							<? if (!$edit): ?>
								<div class="wb_tr">
									<div class="wb_td wb_td_n"><?=$k + 1?></div>
									<div class="wb_td wb_td_name"><div class="wb_name"><?=kwb::h($i['name'])?></div></div>
									<div class="wb_td wb_td_unit" data-h="<?=t::h('Өлшемі', 'Ед.')?>"><?=kwb::h($iu)?></div>
									<div class="wb_td" data-h="<?=t::h('Саны', 'Кол-во')?>"><?=kwb::qty($i['quantity'])?></div>
									<div class="wb_td" data-h="<?=t::h('Бағасы', 'Цена')?>"><?=kwb::sum($i['price'])?></div>
									<div class="wb_td" data-h="<?=t::h('Сомасы', 'Сумма')?>"><b><?=kwb::sum($i['sum'])?></b></div>
								</div>
							<? else: ?>
								<div class="wb_tr wb_row">
									<div class="wb_td wb_td_n wb_row_n"><?=$k + 1?></div>
									<div class="wb_td wb_td_name">
										<input type="text" class="wb_inp wb_r_name" list="wb_raw_list" value="<?=kwb::h($i['name'])?>" data-raw="<?=$rid?>" placeholder="<?=t::h('Мысалы: Күріш', 'Например: Рис')?>">
									</div>
									<div class="wb_td wb_td_unit" data-h="<?=t::h('Өлшемі', 'Ед.')?>">
										<select class="wb_inp wb_r_unit">
											<? foreach (kwb::$units as $u): ?><option <?=($iu == $u ? 'selected' : '')?>><?=$u?></option><? endforeach ?>
											<? if ($iu && !in_array($iu, kwb::$units)): ?><option selected><?=kwb::h($iu)?></option><? endif ?>
										</select>
									</div>
									<div class="wb_td" data-h="<?=t::h('Саны', 'Кол-во')?>"><input type="text" inputmode="decimal" class="wb_inp wb_r_qty" value="<?=($i['quantity'] !== '' ? kwb::qty($i['quantity']) : '')?>" placeholder="0"></div>
									<div class="wb_td" data-h="<?=t::h('Бағасы', 'Цена')?>"><input type="text" inputmode="decimal" class="wb_inp wb_r_price" value="<?=($i['price'] !== '' ? round((float)$i['price'], 2) + 0 : '')?>" placeholder="0"></div>
									<div class="wb_td" data-h="<?=t::h('Сомасы', 'Сумма')?>"><input type="text" inputmode="decimal" class="wb_inp wb_r_sum" value="<?=($i['sum'] !== '' ? round((float)$i['sum'], 2) + 0 : '')?>" placeholder="0"></div>
									<div class="wb_td wb_td_x"><div class="wb_x" title="<?=t::h('Жолды өшіру', 'Удалить строку')?>"><i class="far fa-times"></i></div></div>
								</div>
							<? endif ?>
						<? endforeach ?>
					</div>
					<div class="wb_foot">
						<? if ($edit): ?><div class="wb_btn wb_btn_light wb_add_row"><i class="far fa-plus"></i> <?=t::w('Тауар қосу', 'Добавить товар')?></div><? else: ?><div></div><? endif ?>
						<div class="wb_total"><?=t::w('Барлығы', 'Итого')?>: <b class="wb_total_v"><?=kwb::sum(@$w['total'])?></b></div>
					</div>
				</div>

				<!-- Төлем және ескертпе -->
				<div class="wb_box">
					<div class="wb_pay">
						<label class="wb_fld">
							<span class="wb_lbl"><?=t::w('Төленді, ₸', 'Оплачено, ₸')?></span>
							<input type="text" inputmode="decimal" class="wb_inp wb_f_paid" value="<?=($w && (float)$w['paid'] ? round((float)$w['paid'], 2) + 0 : '')?>" placeholder="0" <?=$edit ? '' : 'disabled'?>>
						</label>
						<? if ($edit): ?><div class="wb_btn wb_btn_light wb_pay_full"><?=t::w('Толық төленді', 'Оплачено полностью')?></div><? endif ?>
						<div class="wb_pay_debt"><?=t::w('Қарыз', 'Долг')?>: <b class="wb_debt_v wb_c_red"><?=kwb::sum($debt)?></b></div>
					</div>
					<label class="wb_fld kw_cmt">
						<span class="wb_lbl"><?=t::w('Ескертпе', 'Примечание')?></span>
						<input type="text" class="wb_inp wb_f_comment" value="<?=kwb::h(@$w['comment'])?>" placeholder="<?=t::h('Міндетті емес', 'Необязательно')?>" <?=$edit ? '' : 'disabled'?>>
					</label>
				</div>

				<? if ($edit): ?>
					<div class="wb_actions">
						<div class="wb_btn wb_btn_green wb_save" data-post="0"><i class="far fa-check"></i> <?=t::w('Сақтау', 'Сохранить')?></div>
						<? if ($w): ?><div class="wb_btn wb_btn_red wb_delete" data-ask="<?=t::h('Накладнойды өшіру керек пе?', 'Удалить накладную?')?>"><i class="far fa-trash-alt"></i> <?=t::w('Өшіру', 'Удалить')?></div><? endif ?>
					</div>
					<p class="kw_hint"><?=t::w('Сақталған накладнойды админ тексеріп, қоймаға қабылдайды. Қабылданғанша өзгертуге болады.', 'Сохранённую накладную админ проверит и примет на склад. До этого её можно изменить.')?></p>
				<? elseif ((int)$w['status'] == 0): ?>
					<p class="kw_hint"><?=t::w('Бұл накладнойды басқа қызметкер жасаған, тек көруге болады.', 'Накладную создал другой сотрудник, только просмотр.')?></p>
				<? endif ?>
			</div>
		</div>
	</div>

	<? if ($edit): ?>
		<datalist id="wb_raw_list"><? foreach ($raw as $r): ?><option value="<?=kwb::h($r['n'])?>"></option><? endforeach ?></datalist>
		<script>window.wb_raw = <?=json_encode($raw, JSON_UNESCAPED_UNICODE)?>; window.wb_alias = <?=json_encode($alias, JSON_UNESCAPED_UNICODE)?>; window.wb_ai_on = <?=($ai_on ? 'true' : 'false')?>; window.wb_gd_on = false;</script>
	<? endif ?>

<? include "../block/footer.php"; ?>
