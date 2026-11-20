<? include "../config/core.php";

	if (!$user_id) { header('location: /'); exit; }

	// Оплата: компанияның тарифі, мерзімі, төлем QR/сілтемесі, чекті WhatsApp-қа жіберу (config/pay.php).
	// Тарифтерді толық салыстыру — тек админде (adm /pay/).
	function pw($key, $kz) { return t::w($kz); } // ktc-да t::w($kz, $ru)
	$pay_qr = $pay_link = $pay_wa = '';
	@include "../config/pay.php";
	$cmp = (int)$company;
	$c = mysqli_fetch_assoc(db::query("select id, name from company where id = '$cmp'"));

	$st = plan::state();
	$sub = plan::$sub;
	$cur_name = (string)(plan::$tariff['name'] ?? '');
	if (plan::$trial) {
		$cur_name = pw('Plan free', 'Free') . ' · ' . sprintf(pw('Plan free days', '%d күн тегін'), 14);
	}
	$days = plan::$days;
	$st_txt = ['active' => pw('Active', 'Белсенді'), 'warn' => pw('Ends soon', 'Жақында бітеді'), 'grace' => pw('Expired', 'Мерзімі өтті'),
		'expired' => pw('Expired', 'Мерзімі өтті'), 'none' => pw('Not connected', 'Қосылмаған')][$st];
	if ($days === null) $days_txt = '—';
	elseif ($days > 0) $days_txt = sprintf(pw('%d days left', '%d күн қалды'), $days);
	elseif ($days == 0) $days_txt = pw('Last day today', 'Бүгін соңғы күн');
	else $days_txt = sprintf(pw('%d days overdue', '%d күн өтті'), -$days);

	$qr_src = '';
	if ($pay_qr !== '' && (preg_match('~^https?://~i', $pay_qr) || is_file($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pay_qr, '/')))) $qr_src = $pay_qr;
	$link_ok = $pay_link !== '' && preg_match('~^https?://~i', $pay_link);
	$wa = preg_replace('/\D/', '', (string)$pay_wa);
	if (strlen($wa) == 11 && $wa[0] == '8') $wa = '7' . substr($wa, 1);
	$wa_show = strlen($wa) == 11 ? '8 (' . substr($wa, 1, 3) . ') ' . substr($wa, 4, 3) . '-' . substr($wa, 7, 2) . '-' . substr($wa, 9, 2) : $wa;
	$wa_url = $wa ? 'https://wa.me/' . $wa . '?text=' . rawurlencode('Сәлеметсіз бе, Berik POS төлемінің чегін жіберемін. Компания: ' . (string)@$c['name']) : '';
	$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

	// site setting
	$menu_name = 'pay';
	$page_name = pw('Plan payment', 'Оплата');
	$css = ['pay'];
	$js = [];
?>
<? include "../block/header.php"; ?>

	<div class="bp_page">
		<div class="bl_c">
			<div class="pay_page pay_st_<?=$st?>">
				<h1 class="pay_title"><?=$h($page_name)?></h1>

				<div class="pay_box">
					<div class="pay_box_h"><span><?=pw('Your plan', 'Сіздің тарифіңіз')?></span><span class="pay_badge"><?=$h($st_txt)?></span></div>
					<? if (!$sub): ?>
						<p class="pay_mut"><?=pw('The subscription is not connected yet. Pay and send the receipt, we will connect it.', 'Жазылым әлі қосылмаған. Төлеп, чекті жіберіңіз, біз қосып береміз.')?></p>
					<? else: ?>
						<div class="pay_kpi">
							<div><small><?=pw('Tariff', 'Тариф')?></small><b><?=$h($cur_name ?: '—')?></b></div>
							<div><small><?=pw('Paid until', 'Төленген мерзім')?></small><b><?=date('d.m.Y', strtotime($sub['paid_until']))?></b></div>
							<div><small><?=pw('Days left', 'Қалған күн')?></small><b><?=$h($days_txt)?></b></div>
						</div>
					<? endif ?>
				</div>

				<div class="pay_box">
					<div class="pay_box_h"><span><?=pw('Payment', 'Төлем')?></span></div>
					<div class="pay_grid">
						<div class="pay_qr">
							<? if ($qr_src): ?><img src="<?=$h($qr_src)?>" alt="QR" loading="lazy">
							<? else: ?><div class="pay_ph"><i class="fal fa-qrcode"></i><span><?=pw('QR code will be added soon', 'QR код жақында қосылады')?></span></div><? endif ?>
						</div>
						<div class="pay_info">
							<p><?=pw('Scan the QR code or open the payment link. Specify your company name in the comment.', 'QR кодты сканерлеңіз не төлем сілтемесін ашыңыз. Түсініктемеге компания атауын жазыңыз.')?></p>
							<? if ($link_ok): ?><a class="pay_btn pay_kaspi" href="<?=$h($pay_link)?>" target="_blank" rel="noopener"><i class="fal fa-external-link"></i> <?=pw('Pay via Kaspi', 'Kaspi арқылы төлеу')?></a>
							<? else: ?><span class="pay_btn pay_off"><i class="fal fa-link"></i> <?=pw('Payment link will be added soon', 'Төлем сілтемесі жақында қосылады')?></span><? endif ?>
							<? if ($wa_url): ?>
								<div class="pay_wa">
									<p><b><?=pw('After paying, send the receipt to WhatsApp', 'Төлегеннен кейін чекті WhatsApp-қа жіберіңіз')?>:</b> <?=$h($wa_show)?>. <?=pw('We will extend the subscription after checking it.', 'Тексерген соң жазылымды ұзартамыз.')?></p>
									<a class="pay_btn pay_wa_btn" href="<?=$h($wa_url)?>" target="_blank" rel="noopener"><i class="fab fa-whatsapp"></i> <?=pw('Send receipt on WhatsApp', 'Чекті WhatsApp-қа жіберу')?></a>
								</div>
							<? endif ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

<? include "../block/footer.php"; ?>
