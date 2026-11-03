<? include "../config/core.php";
	include "../waybill/lib.php";

	// Кухня: ревизия (шикізатты санау). Кесте админмен ортақ (revision, revision_item).
	// Аспаз санап, сақтайды. Қалдыққа теңестіруді («Аяқтау») админ жасайды.
	if (!$user_id) { header('location: /'); exit(); }
	if (!in_array((int)@$user_right['positions_id'], [1, 2, 3, 9])) { $core->user_unset(); header('location: /'); exit(); }

	$cmp = (int)$company;
	$now = date('Y-m-d H:i:s');
	// филиал: компанияныкі болмаса — біріншісі (админдегідей)
	$br_ids = []; $bq = db::query("select id from company_branch where company_id = '$cmp' order by number, id");
	while ($bq && $b = mysqli_fetch_assoc($bq)) $br_ids[] = (int)$b['id'];
	$br = ($br_ids && !in_array((int)$branch, $br_ids)) ? $br_ids[0] : (int)$branch;

	$csrf_ok = isset($_POST['csrf']) && hash_equals(kwb::csrf(), (string)$_POST['csrf']);
	$rev_get = function ($id) use ($cmp) {
		$q = db::query("select * from revision where id = '".(int)$id."' and company_id = '$cmp' limit 1");
		return $q && mysqli_num_rows($q) ? mysqli_fetch_assoc($q) : null;
	};

	// Жаңа ревизия: ашық ревизия болса, соны жалғастырамыз
	if (isset($_POST['create']) && $csrf_ok) {
		$op = mysqli_fetch_assoc(db::query("select id from revision where company_id = '$cmp' and branch_id = '$br' and status = 0 order by id desc limit 1"));
		if ($op) { header('location: /revision/?id='.(int)$op['id']); exit(); }
		$num = (int)mysqli_fetch_assoc(db::query("select IFNULL(MAX(number), 0) + 1 n from revision where company_id = '$cmp'"))['n'];
		db::query("insert into revision (company_id, branch_id, number, dt, status, user_id) values ('$cmp', '$br', '$num', '$now', 0, '".(int)$user_id."')");
		$id = (int)mysqli_insert_id(db::$connect);
		if ($id) db::query("insert into revision_item (revision_id, raw_id, expected, price)
			select '$id', r.id, IFNULL(st.quantity, 0), IFNULL(r.price, 0) from product_raw r
			left join product_raw_stock st on st.raw_id = r.id and st.company_id = '$cmp' and st.branch_id = '$br'
			where r.company_id = '$cmp'");
		header('location: /revision/?id='.$id); exit();
	}

	// Санағанын сақтау (тек ашық ревизия)
	if (isset($_POST['id']) && $csrf_ok) {
		$rv = $rev_get($_POST['id']);
		if ($rv && (int)$rv['status'] == 0) {
			$id = (int)$rv['id'];
			foreach ((array)@$_POST['c'] as $rid => $v) {
				$v = trim((string)$v);
				$val = $v === '' ? 'NULL' : "'".round(max(0, kwb::num($v)), 3)."'";
				db::query("update revision_item set counted = $val where revision_id = '$id' and raw_id = '".(int)$rid."'");
			}
			db::query("update revision set comment = '".kwb::esc(mb_substr((string)@$_POST['comment'], 0, 1000))."' where id = '$id' and status = 0");
		}
		header('location: /revision/?id='.(int)@$_POST['id'].'&saved=1'); exit();
	}

	$id = (int)@$_GET['id'];
	$rv = $id ? $rev_get($id) : null;
	$items = [];
	if ($rv) {
		// аспазға күтілетін қалдық көрсетілмейді (санау әділ болу үшін)
		$q = db::query("select i.raw_id, i.counted, r.name_kz, r.name_ru, r.unit, r.grp from revision_item i join product_raw r on r.id = i.raw_id
			where i.revision_id = '".(int)$rv['id']."' order by field(r.grp, 'raw', 'pack', 'hoz'), r.name_ru, r.name_kz");
		while ($q && $i = mysqli_fetch_assoc($q)) $items[] = $i;
	} else {
		$list = db::query("select v.*, u.name u_name,
				(select count(*) from revision_item i where i.revision_id = v.id and i.counted is not null) counted_n,
				(select count(*) from revision_item i where i.revision_id = v.id) all_n
			from revision v left join user u on u.id = v.user_id
			where v.company_id = '$cmp' order by v.dt desc, v.id desc limit 30");
	}
	$open = $rv && (int)$rv['status'] == 0;
	$grp_nm = ['raw' => t::w('Шикізат', 'Сырьё'), 'pack' => t::w('Қаптама', 'Упаковка'), 'hoz' => t::w('Шаруашылық', 'Хозтовары')];

	$menu_name = 'revision';
	$css = ['orders'];
	$js = ['revision'];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page">
	<? if (!$rv): ?>
		<div class="bp_head">
			<div class="bl_c">
				<div class="bp_head_t">
					<div><h1 class="bp_head_h"><?=t::w('Ревизия', 'Ревизия')?></h1></div>
					<form method="post">
						<input type="hidden" name="csrf" value="<?=kwb::h(kwb::csrf())?>">
						<button class="btn kt_btn kt_btn_ok kw_new" name="create" value="1"><i class="far fa-clipboard-list-check"></i> <?=t::w('Санау', 'Пересчёт')?></button>
					</form>
				</div>
			</div>
		</div>
		<div class="bl_c">
			<p class="kw_hint"><?=t::w('Әр шикізатты санап, нақты қалдығын жазасыз. Админ тексеріп аяқтағанда жүйедегі қалдық соған теңеледі.', 'Пересчитайте каждое сырьё и запишите фактический остаток. После проверки админом остаток в системе выровняется.')?></p>
			<div class="kw_list">
				<? if ($list && mysqli_num_rows($list)): ?>
					<? while ($v = mysqli_fetch_assoc($list)): ?>
						<a class="kw_i" href="/revision/?id=<?=(int)$v['id']?>">
							<span class="kw_n">№<?=(int)$v['number']?></span>
							<span class="kw_t">
								<b><?=date('d.m.Y H:i', strtotime($v['dt']))?></b>
								<small><?=kwb::h((string)$v['u_name'])?> · <?=t::w('саналды', 'посчитано')?> <?=(int)$v['counted_n']?> / <?=(int)$v['all_n']?></small>
							</span>
							<span class="kw_r">
								<? if ((int)$v['status'] == 1): ?><span class="bp_st bp_st4"><?=t::w('Аяқталды', 'Завершена')?></span>
								<? else: ?><span class="bp_st bp_st8"><?=t::w('Ашық', 'Открыта')?></span><? endif ?>
							</span>
						</a>
					<? endwhile ?>
				<? else: ?>
					<div class="ds_nr"><i class="fal fa-clipboard-list"></i><p><?=t::w('Ревизия жоқ', 'Ревизий нет')?></p></div>
				<? endif ?>
			</div>
		</div>
	<? else: ?>
		<div class="bl_c ord_page">
			<a class="ord_back" href="/revision/"><i class="far fa-long-arrow-left"></i> <?=t::w('Ревизия', 'Ревизия')?></a>
		</div>
		<div class="bl_c">
			<form class="kw_form kr_form" method="post">
				<input type="hidden" name="csrf" value="<?=kwb::h(kwb::csrf())?>">
				<input type="hidden" name="id" value="<?=(int)$rv['id']?>">
				<div class="kw_box">
					<div class="kw_h">
						<?=t::w('Ревизия', 'Ревизия')?> №<?=(int)$rv['number']?>
						<?=($open ? '<span class="bp_st bp_st8">'.t::w('Ашық', 'Открыта').'</span>' : '<span class="bp_st bp_st4">'.t::w('Аяқталды', 'Завершена').'</span>')?>
					</div>
					<? if (@$_GET['saved']): ?><p class="kw_hint kr_ok"><i class="far fa-check"></i> <?=t::w('Сақталды', 'Сохранено')?></p><? endif ?>
					<input class="form_txt kr_find" type="search" placeholder="<?=t::h('Іздеу', 'Поиск')?>">
					<div class="kr_prog"><?=t::w('Саналды', 'Посчитано')?>: <b class="kr_n">0</b> / <?=count($items)?></div>
				</div>

				<div class="kw_box">
					<? $g = null; foreach ($items as $i): $ig = (string)$i['grp']; if ($ig !== $g): $g = $ig; ?>
						<div class="kr_g"><?=@$grp_nm[$ig] ?: t::w('Басқа', 'Прочее')?></div>
					<? endif ?>
						<label class="kr_row" data-n="<?=kwb::h(mb_strtolower(($i['name_ru'] ?: '').' '.($i['name_kz'] ?: '')))?>">
							<span class="kr_nm"><?=kwb::h($i['name_ru'] ?: $i['name_kz'])?></span>
							<input class="form_txt kr_in" name="c[<?=(int)$i['raw_id']?>]" inputmode="decimal" value="<?=($i['counted'] !== null ? kwb::qty($i['counted']) : '')?>" placeholder="—" <?=$open ? '' : 'disabled'?>>
							<span class="kr_u"><?=kwb::h(kwb::unit_norm($i['unit']) ?: 'кг')?></span>
						</label>
					<? endforeach ?>
					<? if (!$items): ?><div class="ds_nr"><i class="fal fa-box-open"></i><p><?=t::w('Шикізат тізімі бос', 'Список сырья пуст')?></p></div><? endif ?>
				</div>

				<div class="kw_box">
					<label class="kw_f"><span><?=t::w('Ескертпе', 'Примечание')?></span>
						<textarea class="form_txt" name="comment" rows="2" <?=$open ? '' : 'disabled'?>><?=kwb::h((string)$rv['comment'])?></textarea>
					</label>
				</div>

				<? if ($open): ?>
					<div class="kt_act kr_bar"><button class="btn kt_btn kt_btn_ok"><i class="far fa-check"></i> <?=t::w('Сақтау', 'Сохранить')?></button></div>
					<p class="kw_hint"><?=t::w('Бос қалған жол саналмаған болып есептеледі. Санап болған соң админ ревизияны аяқтайды.', 'Пустая строка считается непосчитанной. После пересчёта админ завершит ревизию.')?></p>
				<? endif ?>
			</form>
		</div>
	<? endif ?>
	</div>

<? include "../block/footer.php"; ?>
