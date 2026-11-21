<? if ($site_set['menu']): ?>
	<? // Төменгі мәзір: ең көбі 4 батырма — 3 негізгі + «Тағы». «Тағы» = /acc/ беті: аккаунт + $pm_more (Накладной, Ревизия, Оплата).
		$km = $menu_name == 'kitchen' ? (@$_GET['sort'] == 'ready' ? 'ready' : 'queue') : $menu_name;
		$pm_main = [
			['queue', '/orders/', 'fa-fire', t::w('Кезекте', 'В очереди')],
			['ready', '/orders/?sort=ready', 'fa-check-circle', t::w('Дайын', 'Готовые')],
			['stop', '/stop/', 'fa-ban', t::w('Стоп-лист', 'Стоп-лист')],
		];
		$pm_more = [
			['waybill', '/waybill/', 'fa-file-invoice', t::w('Накладной', 'Накладные')],
			['revision', '/revision/', 'fa-clipboard-list-check', t::w('Ревизия', 'Ревизия')],
			['pay', '/pay/', 'fa-wallet', t::w('Оплата', 'Оплата')],
			['acc', '/acc/', 'fa-user', t::w('Аккаунт', 'Аккаунт')],
		];
		$pm_in_more = in_array($km, array_column($pm_more, 0), true); ?>
	<div class="pmenu">
		<div class="pmenu_c">
			<span class="pmenu_sl"></span>
			<? foreach ($pm_main as $p): ?>
				<a class="pmenu_i <?=($km == $p[0] ? 'pmenu_i_act' : '')?>" href="<?=$p[1]?>">
					<i class="far <?=$p[2]?>"></i>
					<span><?=$p[3]?></span>
				</a>
			<? endforeach ?>
			<a class="pmenu_i <?=($pm_in_more ? 'pmenu_i_act' : '')?>" href="/acc/">
				<i class="far fa-th-large"></i>
				<span><?=t::w('Тағы', 'Ещё')?></span>
			</a>
		</div>
	</div>
<? endif ?>
