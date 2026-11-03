<? include "../config/core.php";
	include "lib.php";

	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], [1, 2, 3, 9])) { $core->user_unset(); header('location: /'); exit(); }

	$cmp = (int)$company;
	$from = date('Y-m-d', strtotime('-60 days'));
	$q = db::query("select w.*, s.name supplier_name, (select count(*) from waybill_item i where i.waybill_id = w.id) cnt
		from waybill w left join supplier s on s.id = w.supplier_id
		where w.company_id = '$cmp' and w.doc_date >= '$from' order by w.doc_date desc, w.id desc limit 100");

	$menu_name = 'waybill';
	$css = ['orders'];
	$js = [];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page">
		<div class="bp_head">
			<div class="bl_c">
				<div class="bp_head_t">
					<div><h1 class="bp_head_h"><?=t::w('Накладной', 'Накладные')?></h1></div>
					<a class="btn kt_btn kt_btn_ok kw_new" href="/waybill/form.php"><i class="far fa-plus"></i> <?=t::w('Жаңа', 'Новая')?></a>
				</div>
			</div>
		</div>

		<div class="bl_c">
			<div class="kw_list">
				<? if ($q && mysqli_num_rows($q)): ?>
					<? while ($w = mysqli_fetch_assoc($q)): $mine = (int)$w['user_id'] == (int)$user_id; ?>
						<a class="kw_i" href="/waybill/form.php?id=<?=(int)$w['id']?>">
							<span class="kw_n">№<?=(int)$w['number']?></span>
							<span class="kw_t">
								<b><?=kwb::h($w['supplier_name'] ?: t::w('Жеткізуші жоқ', 'Без поставщика'))?></b>
								<small><?=date('d.m.Y', strtotime($w['doc_date']))?> · <?=(int)$w['cnt']?> <?=t::w('тауар', 'тов.')?><?=($w['doc_no'] !== '' && $w['doc_no'] !== null ? ' · '.kwb::h($w['doc_no']) : '')?><?=(!$mine ? ' · '.kwb::h((string)@fun::user($w['user_id'])['name']) : '')?></small>
							</span>
							<span class="kw_r">
								<b class="fr_price"><?=round((float)$w['total'])?></b>
								<? if ((int)$w['status'] == 1): ?><span class="bp_st bp_st4"><?=t::w('Қабылданды', 'Принята')?></span>
								<? else: ?><span class="bp_st bp_st8"><?=t::w('Тексеруде', 'На проверке')?></span><? endif ?>
							</span>
						</a>
					<? endwhile ?>
				<? else: ?>
					<div class="ds_nr"><i class="fal fa-file-invoice"></i><p><?=t::w('Накладной жоқ', 'Накладных нет')?></p></div>
				<? endif ?>
			</div>
		</div>
	</div>

<? include "../block/footer.php"; ?>
