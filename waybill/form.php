<? include "../config/core.php";
	include "lib.php";

	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], [1, 2, 3, 9])) { $core->user_unset(); header('location: /'); exit(); }

	$cmp = (int)$company;
	$id = (int)@$_GET['id'];
	$w = $id ? kwb::get($id, $cmp) : null;
	if ($id && !$w) { header('location: /waybill/'); exit(); }
	$edit = !$w || kwb::can_edit($w, $user_id);
	$items = $w ? kwb::items($id) : [];
	if (!$items) $items = [['name' => '', 'quantity' => '', 'unit' => 'кг', 'price' => '']];

	// Тізімдер: шикізат (атауы, өлшемі, соңғы бағасы) және жеткізушілер
	$raws = [];
	$rq = db::query("select name_kz, name_ru, unit, price from product_raw where company_id = '$cmp' order by name_ru");
	while ($rq && $r = mysqli_fetch_assoc($rq)) { $nm = trim($r['name_ru'] ?: $r['name_kz']); if ($nm !== '') $raws[$nm] = [kwb::unit_norm($r['unit']) ?: 'кг', (int)$r['price']]; }
	$sups = [];
	$sq = db::query("select name from supplier where company_id = '$cmp' and archive = 0 order by name");
	while ($sq && $s = mysqli_fetch_assoc($sq)) $sups[] = $s['name'];

	$menu_name = 'waybill';
	$css = ['orders'];
	$js = ['waybill'];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page">
		<div class="bl_c ord_page">
			<a class="ord_back" href="/waybill/"><i class="far fa-long-arrow-left"></i> <?=t::w('Накладной', 'Накладные')?></a>
		</div>

		<div class="bl_c">
			<form class="kw_form" data-id="<?=$id?>" data-csrf="<?=kwb::h(kwb::csrf())?>" onsubmit="return false">
				<div class="kw_box">
					<div class="kw_h">
						<?=($w ? t::w('Накладной', 'Накладная').' №'.(int)$w['number'] : t::w('Жаңа накладной', 'Новая накладная'))?>
						<? if ($w): ?><?=((int)$w['status'] == 1 ? '<span class="bp_st bp_st4">'.t::w('Қабылданды', 'Принята').'</span>' : '<span class="bp_st bp_st8">'.t::w('Тексеруде', 'На проверке').'</span>')?><? endif ?>
					</div>
					<label class="kw_f"><span><?=t::w('Жеткізуші', 'Поставщик')?></span>
						<input class="form_txt" name="supplier" list="kw_sups" value="<?=kwb::h(@$w['supplier_name'])?>" placeholder="<?=t::h('Атын жазыңыз немесе таңдаңыз', 'Введите или выберите')?>" required <?=$edit ? '' : 'disabled'?>>
					</label>
					<div class="kw_2">
						<label class="kw_f"><span><?=t::w('Күні', 'Дата')?></span>
							<input class="form_txt" type="date" name="doc_date" value="<?=kwb::h(@$w['doc_date'] ?: date('Y-m-d'))?>" <?=$edit ? '' : 'disabled'?>>
						</label>
						<label class="kw_f"><span><?=t::w('Құжат №', '№ документа')?></span>
							<input class="form_txt" name="doc_no" value="<?=kwb::h(@$w['doc_no'])?>" <?=$edit ? '' : 'disabled'?>>
						</label>
					</div>
				</div>

				<div class="kw_box">
					<div class="kw_h"><?=t::w('Тауарлар', 'Товары')?></div>
					<div class="kw_rows">
						<? foreach ($items as $i): ?>
							<div class="kw_row">
								<input class="form_txt kw_nm" list="kw_raws" value="<?=kwb::h($i['name'])?>" placeholder="<?=t::h('Атауы', 'Название')?>" <?=$edit ? '' : 'disabled'?>>
								<input class="form_txt kw_q" inputmode="decimal" value="<?=kwb::h($i['quantity'] !== '' ? kwb::qty($i['quantity']) : '')?>" placeholder="<?=t::h('Саны', 'Кол-во')?>" <?=$edit ? '' : 'disabled'?>>
								<select class="form_txt kw_u" <?=$edit ? '' : 'disabled'?>>
									<? foreach (kwb::$units as $u): ?><option <?=(kwb::unit_norm($i['unit']) == $u ? 'selected' : '')?>><?=$u?></option><? endforeach ?>
								</select>
								<input class="form_txt kw_p" inputmode="decimal" value="<?=kwb::h($i['price'] !== '' ? round((float)$i['price'], 2) + 0 : '')?>" placeholder="<?=t::h('Бағасы', 'Цена')?>" <?=$edit ? '' : 'disabled'?>>
								<b class="kw_s">0</b>
								<? if ($edit): ?><i class="far fa-times kw_del" title="<?=t::h('Жолды өшіру', 'Удалить строку')?>"></i><? endif ?>
							</div>
						<? endforeach ?>
					</div>
					<? if ($edit): ?><div class="btn kw_add"><i class="far fa-plus"></i> <?=t::w('Тауар қосу', 'Добавить товар')?></div><? endif ?>
					<div class="kw_tot"><span><?=t::w('Жалпы', 'Итого')?></span><b class="kw_total">0</b></div>
				</div>

				<div class="kw_box">
					<div class="kw_2">
						<label class="kw_f"><span><?=t::w('Төленді', 'Оплачено')?></span>
							<input class="form_txt" name="paid" inputmode="decimal" value="<?=kwb::h($w ? round((float)$w['paid']) : '')?>" placeholder="0" <?=$edit ? '' : 'disabled'?>>
						</label>
					</div>
					<label class="kw_f"><span><?=t::w('Ескертпе', 'Примечание')?></span>
						<textarea class="form_txt" name="comment" rows="2" <?=$edit ? '' : 'disabled'?>><?=kwb::h(@$w['comment'])?></textarea>
					</label>
				</div>

				<? if ($edit): ?>
					<div class="kt_act">
						<? if ($w): ?><div class="btn kt_btn kt_btn_back kw_rm" data-ask="<?=t::h('Накладнойды өшіру керек пе?', 'Удалить накладную?')?>"><i class="far fa-trash-alt"></i> <?=t::w('Өшіру', 'Удалить')?></div><? endif ?>
						<button type="button" class="btn kt_btn kt_btn_ok kw_save"><i class="far fa-check"></i> <?=t::w('Сақтау', 'Сохранить')?></button>
					</div>
					<p class="kw_hint"><?=t::w('Сақталған накладнойды админ тексеріп, қоймаға қабылдайды. Қабылданғанша өзгертуге болады.', 'Сохранённую накладную админ проверит и примет на склад. До этого её можно изменить.')?></p>
				<? elseif ((int)$w['status'] == 0): ?>
					<p class="kw_hint"><?=t::w('Бұл накладнойды басқа қызметкер жасаған, тек көруге болады.', 'Накладную создал другой сотрудник, только просмотр.')?></p>
				<? endif ?>
			</form>
		</div>
	</div>

	<datalist id="kw_sups"><? foreach ($sups as $s): ?><option value="<?=kwb::h($s)?>"><? endforeach ?></datalist>
	<datalist id="kw_raws"><? foreach ($raws as $nm => $r): ?><option value="<?=kwb::h($nm)?>"><? endforeach ?></datalist>
	<script>window.KW_RAW = <?=json_encode($raws, JSON_UNESCAPED_UNICODE)?>;</script>

<? include "../block/footer.php"; ?>
