<? if ($site_set['menu']): ?>
	<? $km = $menu_name == 'kitchen' ? (@$_GET['sort'] == 'ready' ? 'ready' : 'queue') : $menu_name; ?>
	<div class="pmenu">
		<div class="pmenu_c">
			<span class="pmenu_sl"></span>
			<a class="pmenu_i <?=($km == 'queue' ? 'pmenu_i_act' : '')?>" href="/orders/">
				<i class="far fa-fire"></i>
				<span><?=t::w('Кезекте', 'В очереди')?></span>
			</a>
			<a class="pmenu_i <?=($km == 'ready' ? 'pmenu_i_act' : '')?>" href="/orders/?sort=ready">
				<i class="far fa-check-circle"></i>
				<span><?=t::w('Дайын', 'Готовые')?></span>
			</a>
			<a class="pmenu_i <?=($km == 'waybill' ? 'pmenu_i_act' : '')?>" href="/waybill/">
				<i class="far fa-file-invoice"></i>
				<span><?=t::w('Накладной', 'Накладные')?></span>
			</a>
			<a class="pmenu_i <?=($km == 'revision' ? 'pmenu_i_act' : '')?>" href="/revision/">
				<i class="far fa-clipboard-list-check"></i>
				<span><?=t::w('Ревизия', 'Ревизия')?></span>
			</a>
			<a class="pmenu_i <?=($km == 'acc' ? 'pmenu_i_act' : '')?>" href="/acc/">
				<i class="far fa-user"></i>
				<span><?=t::w('Аккаунт', 'Аккаунт')?></span>
			</a>
		</div>
	</div>
<? endif ?>
