<? include "../config/core.php";

	// Қолданушыны тексеру
	if (!$user_id) { header('location: /'); exit(); }

	// Сайттың баптаулары
	$menu_name = 'acc';
	$site_set['utop_nm'] = t::w('Жеке деректер', 'Личные данные');
	$css = ['acc'];
	$js = ['admin'];
	$pos_nm = [1 => t::w('Директор', 'Директор'), 2 => t::w('Директор', 'Директор'), 3 => t::w('Басқарушы', 'Управляющий'), 9 => t::w('Аспазшы', 'Повар')];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page bp_acc">
		<div class="bl_c">

			<div class="bp_prof">
				<div class="bp_prof_av lazy_img" <?=(@$user['img'] ? 'data-src="/assets/uploads/users/'.htmlspecialchars($user['img']).'"' : '')?>><? if (!@$user['img']): ?><?=htmlspecialchars(mb_strtoupper(mb_substr(trim((string)$user['name']), 0, 1)))?><? endif ?></div>
				<div class="bp_prof_t">
					<div class="bp_prof_n"><?=htmlspecialchars(trim($user['name'].' '.$user['surname']))?></div>
					<div class="bp_prof_p fr_phone"><?=htmlspecialchars((string)$user['phone'])?></div>
					<div class="bp_prof_r">
						<? if (isset($pos_nm[(int)@$user_right['positions_id']])): ?><span><?=$pos_nm[(int)$user_right['positions_id']]?></span><? endif ?>
						<? if (@$company_d['name']): ?><span><?=htmlspecialchars($company_d['name'])?></span><? endif ?>
					</div>
				</div>
			</div>

			<div class="bp_box">
				<div class="bp_box_h"><i class="far fa-globe"></i> <?=t::w('Тіл', 'Язык')?></div>
				<div class="bp_lang">
					<? foreach ($langs_short as $lk => $lv): ?>
						<a class="bp_lang_i <?=($lang == $lk ? 'bp_lang_act' : '')?>" href="?lang=<?=$lk?>"><?=$lv?></a>
					<? endforeach ?>
				</div>
			</div>

			<div class="bp_box bp_list">
				<a class="bp_li" href="/orders/?sort=ready">
					<span class="bp_li_ic"><i class="far fa-check-circle"></i></span>
					<span class="bp_li_t"><?=t::w('Бүгін дайын болғандар', 'Готовые за сегодня')?></span>
					<i class="far fa-chevron-right bp_li_ar"></i>
				</a>
				<div class="bp_li pass_ubd_btn">
					<span class="bp_li_ic"><i class="far fa-key"></i></span>
					<span class="bp_li_t"><?=t::w('Құпиясөзді өзгерту', 'Сменить пароль')?></span>
					<i class="far fa-chevron-right bp_li_ar"></i>
				</div>
				<? if (@$site['whatsapp']): ?>
					<a class="bp_li" href="https://wa.me/<?=htmlspecialchars($site['whatsapp'])?>" target="_blank" rel="noopener">
						<span class="bp_li_ic"><i class="fab fa-whatsapp"></i></span>
						<span class="bp_li_t"><?=t::w('Көмек (WhatsApp)', 'Помощь (WhatsApp)')?></span>
						<i class="far fa-chevron-right bp_li_ar"></i>
					</a>
				<? endif ?>
			</div>

			<a class="bp_exit" href="/exit.php">
				<i class="far fa-sign-out"></i>
				<span><?=t::w('Шығу', 'Выйти')?></span>
			</a>

			<div class="up_cg">
				<a href="https://gprog.kz" target="_blank" rel="noopener" class="gprog_bl">
					<span>#gprog-та</span>
					<div class="gprog_heart"><i class="fas fa-heart"></i></div>
					<span><?=t::w('жасалған', 'сделано')?></span>
				</a>
				<div class="up_brand">Berik POS</div>
			</div>

		</div>
	</div>

<? include "../block/footer.php"; ?>

	<!-- Құпиясөзді өзгерту -->
	<div class="pop_bl pop_bl2 pass_ubd">
		<div class="pop_bl_a pass_ubd_back"></div>
		<div class="pop_bl_c">
			<div class="head_c">
				<h4><?=t::w('Құпиясөзді өзгерту', 'Сменить пароль')?></h4>
				<div class="btn btn_dd pass_ubd_back"><i class="fal fa-times"></i></div>
			</div>
			<div class="pop_bl_cl">
				<div class="form_c">
					<div class="form_im ">
						<div class="form_span"><?=t::w('Құпиясөз', 'Пароль')?>:</div>
						<input type="text" class="form_txt pass" placeholder="<?=t::h('Жаңа құпиясөз', 'Новый пароль')?>" data-lenght="6">
						<i class="fal fa-key form_icon"></i>
					</div>
					<div class="form_im form_im_bn">
						<div class="btn pass_ubd_send" data-id="<?=$user_id?>"><?=t::w('Сақтау', 'Сохранить')?></div>
					</div>
				</div>
			</div>
		</div>
	</div>
