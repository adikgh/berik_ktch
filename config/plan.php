<?
// Тариф бойынша рұқсат (2026-11-18). Бұл файл pos, adm, dlv, ktc сайттарында бірдей, тек PLAN_SITE өзгеше (dlv-да core.php хостқа қарай dlv/dst қояды).
// plan::load($company) — company_sub + тариф + модульдер. plan::state(): active / warn (≤5 күн) / grace (өткеніне ≤3 күн) / expired / none.
// plan::can('stock') — модуль тарифте бар ма. plan::lim('dish') — лимит, 0 = шексіз.
// PLAN_ENFORCE = false: ештеңе жабылмайды, тек жоғарыда жолақ шығады (plan::banner()).

if (!class_exists('plan', false)) {

if (!defined('PLAN_SITE')) define('PLAN_SITE', 'ktc');       // осы сайттың модулі
if (!defined('PLAN_ENFORCE')) define('PLAN_ENFORCE', false);  // true болса — бітсе не модуль жоқ болса жабылады (әзірге қолданылмайды)
if (!defined('PLAN_PAY_URL')) define('PLAN_PAY_URL', '/pay/'); // төлем беті: әр сайттың өз /pay/ беті (adm, pos, dist, ktc)

class plan {
	public static $cid = 0;
	public static $sub = null;     // company_sub жолы
	public static $tariff = null;  // company_tariff жолы
	public static $trial = false;  // соңғы төлем — тегін сынақ
	public static $mods = [];      // [code => 1]
	public static $lims = ['kassa' => 0, 'branch' => 0, 'dish' => 0, 'order_month' => 0];
	public static $days = null;    // paid_until-ге дейін қалған күн (бүгін соңғы күн болса 0)

	private static function row($sql) {
		$q = @db::query($sql);
		return $q && $q !== true ? mysqli_fetch_assoc($q) : null;
	}

	public static function load($cid) {
		self::$cid = (int)$cid; self::$sub = self::$tariff = null; self::$trial = false; self::$mods = []; self::$days = null;
		foreach (self::$lims as $k => $v) self::$lims[$k] = 0;
		try {
			$s = self::row("select * from company_sub where company_id = '" . self::$cid . "' limit 1");
			if (!$s || empty($s['paid_until'])) return;
			self::$sub = $s;
			self::$days = (int)floor((strtotime($s['paid_until']) - strtotime(date('Y-m-d'))) / 86400);
			$p = self::row("select method from company_payment where company_id = '" . self::$cid . "' and arh = 0 order by id desc limit 1");
			self::$trial = $p && $p['method'] === 'trial';
			$tid = self::$trial ? 0 : (int)$s['tariff_id'];
			if ($tid) {
				self::$tariff = self::row("select * from company_tariff where id = '$tid'");
				foreach (self::$lims as $k => $v) self::$lims[$k] = (int)@self::$tariff['lim_' . $k];
			} else {
				$q = @db::query("select k, v from company_plan_conf where k like 'trial_lim_%'");
				if ($q && $q !== true) while ($r = mysqli_fetch_assoc($q)) {
					$k = substr($r['k'], 10);
					if (isset(self::$lims[$k])) self::$lims[$k] = (int)$r['v'];
				}
			}
			$q = @db::query("select module_code from company_tariff_module where tariff_id = '$tid'");
			if ($q && $q !== true) while ($r = mysqli_fetch_row($q)) self::$mods[$r[0]] = 1;
		} catch (Throwable $e) { self::$sub = null; } // кестелер әлі жоқ базада — 'none'
	}

	public static function state() {
		if (!self::$sub) return 'none';
		$d = self::$days;
		if ($d > 5) return 'active';
		if ($d >= 0) return 'warn';
		if ($d >= -3) return 'grace';
		return 'expired';
	}

	// Тариф белгісіз ('none') болса, ескерту режимінде бәріне рұқсат
	public static function can($m) {
		if (!self::$sub) return !PLAN_ENFORCE;
		return isset(self::$mods[$m]);
	}

	// plan::lim('dish') не plan::lim('lim_dish'); 0 = шексіз
	public static function lim($k) {
		$k = preg_replace('/^lim_/', '', (string)$k);
		return (int)(self::$lims[$k] ?? 0);
	}

	// Болашақта жабу үшін: PLAN_ENFORCE қосулы және мерзімі өтсе не модуль жоқ болса
	// Курьер (dlv) әрқашан кіріп жұмыс істейді: касса төленбесе тапсырыс оған бәрібір келмейді. Жолақ та, жабу да жоқ.
	public static $free = ['dlv'];
	public static function is_free() { return in_array(PLAN_SITE, self::$free, true); }

	public static function blocked($m = PLAN_SITE) {
		if (self::is_free()) return false;
		return PLAN_ENFORCE && (self::state() === 'expired' || !self::can($m));
	}

	public static function pay_url() { return PLAN_PAY_URL !== '' ? PLAN_PAY_URL : '/pay/'; }

	// ktc-дағы t::w($kz, $ru) басқаша: бірінші аргументі қазақша мәтін, сондықтан кілтсіз қазақшасы қайтады
	private static function w($k, $kz) { return $kz; }

	// Жоғарыдағы жіңішке жолақ. Ештеңені жаппайды.
	public static function banner() {
		if (self::is_free()) return '';
		$st = self::state();
		$msg = '';
		if (self::$sub && !self::can(PLAN_SITE)) $msg = self::w('Plan module missing', 'Бұл модуль тарифіңізге кірмейді');
		if ($st === 'warn') $msg = (self::$days ? sprintf(self::w('Plan ends in %d days', 'Жазылым %d күнде бітеді'), self::$days) : self::w('Plan ends today', 'Жазылым бүгін бітеді')) . ($msg ? ' · ' . $msg : '');
		elseif ($st === 'grace' || $st === 'expired') $msg = self::w('Plan expired', 'Жазылым мерзімі өтті') . ($msg ? ' · ' . $msg : '');
		if ($msg === '') return '';
		$bg = $st === 'warn' ? '#fff4d6' : ($st === 'active' ? '#e8f1fb' : '#fde4e4');
		$cl = $st === 'warn' ? '#7a5400' : ($st === 'active' ? '#1f4f7a' : '#8a1f1f');
		// Жолақ экранның үстінде бекітулі (30px). Бет пен fixed мәзірлер (.aheader, .pmenu) сонша төмен түседі.
		return '<style>body{padding-top:30px}.aheader,.pmenu{margin-top:30px}'
			. '.plan_bar{position:fixed;top:0;left:0;right:0;z-index:99990;height:30px;box-sizing:border-box;display:flex;gap:12px;align-items:center;justify-content:center;padding:0 12px;background:' . $bg . ';color:' . $cl . ';font:600 13px/30px system-ui,sans-serif;white-space:nowrap}'
			. '.plan_bar span{overflow:hidden;text-overflow:ellipsis}.plan_bar a{color:inherit;text-decoration:underline;flex:none}'
			. '@media(max-width:600px){.plan_bar{font-size:12px;justify-content:space-between}}</style>'
			. '<div class="plan_bar"><span>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</span>'
			. '<a href="' . htmlspecialchars(self::pay_url(), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(self::w('Pay', 'Төлеу'), ENT_QUOTES, 'UTF-8') . '</a></div>';
	}
}

}
