<?php
// WhatsApp арқылы растау коды. Бұл файл admin, delivery, dist, ktch сайттарында бірдей (my.berik.c да осыны қолданады).
// Баптаулар бір жерде: adm.berik.c/config/wa_conf.php (админде «WhatsApp» бетінен өзгертіледі).
// Арналар: off - өшірулі, test - ештеңе жіберілмейді, код экранда шығады (тек локалда),
//          green - Green-API (кәдімгі WhatsApp нөмірі), meta - Meta WhatsApp Cloud API (ресми, шаблонмен).

if (!function_exists('wa_conf')) {

function wa_conf_path() {
	$main = __DIR__ . '/../../adm.berik.c/config/wa_conf.php';
	return is_file($main) ? $main : __DIR__ . '/wa_conf.php';
}

function wa_conf_default() {
	return [
		'channel' => 'off',
		'green_url' => 'https://api.green-api.com',
		'green_instance' => '',
		'green_token' => '',
		'meta_token' => '',
		'meta_phone_id' => '',
		'meta_template' => '',
		'meta_lang' => 'ru',
		'meta_button' => 1,
		'login' => ['admin' => 1, 'delivery' => 0, 'dist' => 0, 'ktch' => 0],
		'trust_days' => 365,
		'text' => 'BERIK POS: растау коды %s. Ешкімге айтпаңыз.',
		'owners' => [], // баптау бетін көретін әзірлеуші(лер), user id
	];
}

function wa_conf() {
	static $c = null;
	if ($c === null) {
		$c = wa_conf_default();
		$f = wa_conf_path();
		if (is_file($f)) {
			$x = include $f;
			if (is_array($x)) {
				$login = array_merge($c['login'], (array)@$x['login']);
				$c = array_merge($c, $x);
				$c['login'] = $login;
			}
		}
	}
	return $c;
}

// Баптауды файлға жазу (тек админде)
function wa_conf_write($c) {
	$f = __DIR__ . '/wa_conf.php';
	$php = "<?php\n// WhatsApp растау коды. Админдегі «WhatsApp» бетінен өзгертіледі. Git-ке кірмейді (құпия).\nreturn " . var_export($c, true) . ";\n";
	if (@file_put_contents($f, $php, LOCK_EX) === false) return false;
	if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
	return true;
}

// Баптау бетін тек әзірлеуші көреді. Иесі әлі жазылмаса, бетті бірінші ашқан директор (1) иесі болып жазылады.
function wa_owner_ok($uid, $pos) {
	$c = wa_conf();
	$owners = array_map('intval', (array)$c['owners']);
	if ($owners) return in_array((int)$uid, $owners, true);
	return (int)$pos === 1;
}
function wa_owner_claim($uid) {
	$c = wa_conf();
	if (!empty($c['owners'])) return;
	$c['owners'] = [(int)$uid];
	wa_conf_write($c);
}

function wa_on() {
	return in_array(wa_conf()['channel'], ['test', 'green', 'meta'], true);
}

// Осы сайтта кіргенде код сұрала ма
function wa_login_on($site) {
	return wa_on() && !empty(wa_conf()['login'][$site]);
}

// +7 707 *** ** 34
function wa_mask($phone10) {
	$p = (string)$phone10;
	return strlen($p) == 10 ? '+7 ' . substr($p, 0, 3) . ' *** ** ' . substr($p, 8, 2) : $p;
}

function wa_http($url, $data, $headers = []) {
	if (!function_exists('curl_init')) return null;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_POST => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 15,
		CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
		CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
	]);
	$body = curl_exec($ch);
	$GLOBALS['wa_last_http'] = [(int)curl_getinfo($ch, CURLINFO_HTTP_CODE), is_string($body) ? mb_substr($body, 0, 500) : curl_error($ch)];
	curl_close($ch);
	$res = is_string($body) ? json_decode($body, true) : null;
	return is_array($res) ? $res : null;
}

// Соңғы қатенің мәтіні (баптау бетіндегі тексеру үшін)
function wa_last_error() {
	$h = @$GLOBALS['wa_last_http'];
	return $h ? 'HTTP ' . $h[0] . ': ' . $h[1] : '';
}

// Кодты 10 таңбалы нөмірге жібереді. true - жіберілді.
function wa_send_code($phone10, $code, $conf = null) {
	$c = $conf ?: wa_conf();
	$to = '7' . $phone10;
	$GLOBALS['wa_last_http'] = null;
	switch ($c['channel']) {
		case 'test':
			if (session_status() === PHP_SESSION_ACTIVE) $_SESSION['wa_test_code'] = $code;
			return true;

		case 'green':
			if ($c['green_instance'] === '' || $c['green_token'] === '') return false;
			$url = rtrim($c['green_url'], '/') . '/waInstance' . rawurlencode($c['green_instance']) . '/sendMessage/' . rawurlencode($c['green_token']);
			$res = wa_http($url, ['chatId' => $to . '@c.us', 'message' => sprintf($c['text'], $code)]);
			return $res !== null && !empty($res['idMessage']);

		case 'meta':
			if ($c['meta_token'] === '' || $c['meta_phone_id'] === '' || $c['meta_template'] === '') return false;
			$cmp = [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => (string)$code]]]];
			// Authentication шаблонының «Кодты көшіру» батырмасы
			if (!empty($c['meta_button'])) $cmp[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => (string)$code]]];
			$res = wa_http('https://graph.facebook.com/v21.0/' . rawurlencode($c['meta_phone_id']) . '/messages', [
				'messaging_product' => 'whatsapp',
				'to' => $to,
				'type' => 'template',
				'template' => ['name' => $c['meta_template'], 'language' => ['code' => $c['meta_lang'] ?: 'ru'], 'components' => $cmp],
			], ['Authorization: Bearer ' . $c['meta_token']]);
			return $res !== null && !empty($res['messages'][0]['id']);
	}
	return false;
}

// ---------- әрекет санауыш (файлда, сессияға тәуелсіз) ----------
function rl_file($key) {
	return rtrim(sys_get_temp_dir(), '/\\') . '/berik_rl_' . md5(__DIR__ . '|' . $key) . '.json';
}
function rl_list($key, $sec) {
	$a = @json_decode((string)@file_get_contents(rl_file($key)), true);
	$now = time();
	return array_values(array_filter(is_array($a) ? $a : [], function ($t) use ($now, $sec) { return $t > $now - $sec; }));
}
function rl_over($key, $n, $sec) {
	return count(rl_list($key, $sec)) >= $n;
}
function rl_hit($key) {
	$a = rl_list($key, 86400);
	$a[] = time();
	@file_put_contents(rl_file($key), json_encode($a), LOCK_EX);
}
function rl_clear($key) {
	@unlink(rl_file($key));
}

// ---------- бір реттік код ----------
// $key: 'login' / 'reset'. Нәтиже: 'sent', 'wait' (1 минут өтпеді), 'limit', 'fail'
function otp_start($key, $uid, $phone10) {
	$o = @$_SESSION['otp'][$key];
	if ($o && $o['uid'] == $uid && $o['sent'] > time() - 60) return 'wait';
	if (rl_over('send|' . $phone10, 3, 900) || rl_over('send|' . $phone10, 10, 86400)) return 'limit';
	$code = (string)random_int(1000, 9999);
	rl_hit('send|' . $phone10);
	unset($_SESSION['wa_test_code']);
	if (!wa_send_code($phone10, $code)) return 'fail';
	$_SESSION['otp'][$key] = [
		'uid' => (int)$uid,
		'phone' => $phone10,
		'hash' => password_hash($code, PASSWORD_DEFAULT),
		'exp' => time() + 300,
		'sent' => time(),
		'tries' => 0,
	];
	return 'sent';
}

// Нәтиже: user id (int) немесе 'none', 'expired', 'limit', 'wrong'
function otp_check($key, $code) {
	$o = @$_SESSION['otp'][$key];
	if (!$o) return 'none';
	if ($o['exp'] < time()) { unset($_SESSION['otp'][$key]); return 'expired'; }
	if ($o['tries'] >= 5) { unset($_SESSION['otp'][$key]); return 'limit'; }
	if (!password_verify(preg_replace('/\D/', '', (string)$code), $o['hash'])) { $_SESSION['otp'][$key]['tries']++; return 'wrong'; }
	unset($_SESSION['otp'][$key], $_SESSION['wa_test_code']);
	return (int)$o['uid'];
}

// Тест режимінде экранға шығатын код
function otp_test_code() {
	return wa_conf()['channel'] === 'test' ? (string)@$_SESSION['wa_test_code'] : '';
}

}
