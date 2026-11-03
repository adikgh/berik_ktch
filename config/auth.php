<?php
// Парольдер, кіру cookie-і және WhatsApp кодымен кіру. Бұл файл admin, delivery, dist, ktch сайттарында бірдей.
// - Пароль базада bcrypt (password_hash) болып сақталады. Ескі ашық пароль адам кірген кезде өзі шифрланады.
// - Cookie-де пароль емес, қолтаңба (ups) сақталады. Пароль ауысса, ескі cookie өзі жарамсыз болады.

require_once __DIR__ . '/wa_lib.php';

if (!function_exists('pass_ok')) {

// ---------- пароль ----------
function pass_is_hash($s) {
	return (string)$s !== '' && (bool)password_get_info((string)$s)['algo'];
}
function pass_ok($input, $stored) {
	$input = (string)$input; $stored = (string)$stored;
	if ($input === '' || $stored === '') return false;
	if (pass_is_hash($stored)) return password_verify($input, $stored);
	return hash_equals($stored, $input);
}
function pass_make($p) {
	return password_hash((string)$p, PASSWORD_DEFAULT);
}
// SQL-ге дайын (экрандалған) хэш
function pass_sql($p) {
	return mysqli_real_escape_string(db::$connect, pass_make($p));
}
// Ескі ашық парольді шифрлап қояды, жаңа сақталған мәнді қайтарады
function pass_upgrade($uid, $input, $stored) {
	if (pass_is_hash($stored) && !password_needs_rehash($stored, PASSWORD_DEFAULT)) return $stored;
	$h = pass_make($input);
	db::query("UPDATE `user` SET `password` = '" . mysqli_real_escape_string(db::$connect, $h) . "' WHERE id = '" . (int)$uid . "'");
	return $h;
}

// ---------- cookie қолтаңбасы ----------
function auth_key() {
	static $k = null;
	if ($k !== null) return $k;
	$f = __DIR__ . '/auth_key.php';
	$k = is_file($f) ? (string)include $f : '';
	if (strlen($k) < 32) {
		$new = bin2hex(random_bytes(32));
		if (@file_put_contents($f, "<?php\nreturn '" . $new . "';\n", LOCK_EX)) $k = $new;
		else $k = hash('sha256', 'berik_auth|' . db::$name . '|' . db::$user . '|' . db::$pass . '|' . __DIR__);
	}
	return $k;
}
function auth_sig($uid, $stored, $what = 'login') {
	return hash_hmac('sha256', $what . '|' . (int)$uid . '|' . (string)$stored, auth_key());
}
function auth_cookie($name, $val, $exp) {
	$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
	setcookie($name, $val, ['expires' => $exp, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
	$_COOKIE[$name] = $val;
}
// Кіргізу: $u - user жолы (id, phone, password)
function auth_login($u) {
	$exp = time() + 3600 * 24 * 400; // браузер 400 күннен ұзақ сақтамайды
	auth_cookie('uph', $u['phone'], $exp);
	auth_cookie('ups', auth_sig($u['id'], $u['password']), $exp);
	if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); $_SESSION['uph'] = $u['phone']; unset($_SESSION['ups']); }
}
// Пароль ауысқанда өз cookie-ін жаңарту
function auth_relogin($uid) {
	$u = mysqli_fetch_assoc(db::query("SELECT id, phone, password FROM `user` WHERE id = '" . (int)$uid . "' limit 1"));
	if ($u) auth_login($u);
}
// core.php тексереді. Ескі cookie (ашық пароль) бір рет қабылданып, жаңасына ауыстырылады.
function auth_check($u, $cookie) {
	$cookie = (string)$cookie;
	if ($cookie === '') return false;
	if (hash_equals(auth_sig($u['id'], $u['password']), $cookie)) return true;
	if (!pass_is_hash($u['password']) && hash_equals((string)$u['password'], $cookie)) { auth_login($u); return true; }
	return false;
}

// ---------- сенімді құрылғы (код қайта сұралмайды) ----------
function trust_set($u) {
	$days = max(1, (int)wa_conf()['trust_days']);
	$exp = time() + 86400 * $days;
	auth_cookie('utd', (int)$u['id'] . '.' . $exp . '.' . auth_sig($u['id'], $u['password'] . '|' . $exp, 'trust'), $exp);
}
function trust_ok($u) {
	$p = explode('.', (string)@$_COOKIE['utd']);
	if (count($p) != 3 || (int)$p[0] != (int)$u['id'] || (int)$p[1] < time()) return false;
	return hash_equals(auth_sig($u['id'], $u['password'] . '|' . (int)$p[1], 'trust'), $p[2]);
}

// ---------- кіру беті (get.php) ----------
function auth_json($a) {
	echo json_encode($a, JSON_UNESCAPED_UNICODE);
	exit();
}
function auth_user_by_phone($phone) {
	if (strlen($phone) != 10) return null;
	return mysqli_fetch_assoc(db::query("SELECT * FROM `user` WHERE `phone` = '" . mysqli_real_escape_string(db::$connect, $phone) . "' and `right` = 1 limit 1")) ?: null;
}
function auth_user_by_id($id) {
	return mysqli_fetch_assoc(db::query("SELECT * FROM `user` WHERE id = '" . (int)$id . "' and `right` = 1 limit 1")) ?: null;
}
function auth_staff_ok($uid, $pos) {
	$s = fun::user_staffw($uid);
	return $s && in_array((int)$s['positions_id'], $pos, true);
}

// Кіру, код, парольді қалпына келтіру. $pos - осы сайтқа кіре алатын лауазымдар, $site - admin/delivery/dist/ktch
// Жауаптар: 'yes', {st:'code', phone, test}, 'none', 'wait', 'limit', 'fail', 'wrong', 'expired', 'off', 'short'
function auth_routes($pos, $site) {

	// 1-қадам: телефон + пароль
	if (isset($_GET['sign'])) {
		$phone = substr(preg_replace('/\D/', '', (string)@$_POST['phone']), -10);
		$pass = (string)@$_POST['password'];
		if (rl_over('pass|' . $site . '|' . $phone, 10, 900)) exit('limit');
		$u = auth_user_by_phone($phone);
		if (!$u || !pass_ok($pass, $u['password']) || !auth_staff_ok($u['id'], $pos)) { rl_hit('pass|' . $site . '|' . $phone); exit('none'); }
		rl_clear('pass|' . $site . '|' . $phone);
		$u['password'] = pass_upgrade($u['id'], $pass, $u['password']);
		if (wa_login_on($site) && !trust_ok($u)) {
			$r = otp_start('login', $u['id'], $u['phone']);
			if ($r == 'wait' && @$_SESSION['otp']['login']['uid'] == $u['id']) $r = 'sent'; // код бұрын жіберілген
			if ($r != 'sent') exit($r);
			auth_json(['st' => 'code', 'phone' => wa_mask($u['phone']), 'test' => otp_test_code(), 'days' => (int)wa_conf()['trust_days']]);
		}
		auth_login($u);
		exit('yes');
	}

	// 2-қадам: WhatsApp коды
	if (isset($_GET['sign_code'])) {
		$r = otp_check('login', @$_POST['code']);
		if (!is_int($r)) exit($r);
		$u = auth_user_by_id($r);
		if (!$u || !auth_staff_ok($u['id'], $pos)) exit('none');
		auth_login($u);
		if (!empty($_POST['trust'])) trust_set($u);
		exit('yes');
	}

	// Кодты қайта жіберу (кіру не қалпына келтіру)
	if (isset($_GET['sign_resend'])) {
		$key = @$_GET['sign_resend'] === 'reset' ? 'reset' : 'login';
		$o = @$_SESSION['otp'][$key];
		if (!$o) exit('expired');
		$r = otp_start($key, $o['uid'], $o['phone']);
		if ($r != 'sent') exit($r);
		auth_json(['st' => 'code', 'phone' => wa_mask($o['phone']), 'test' => otp_test_code()]);
	}

	// Парольді ұмыттым: 1) нөмірге код
	if (isset($_GET['reset_send'])) {
		if (!wa_on()) exit('off');
		$phone = substr(preg_replace('/\D/', '', (string)@$_POST['phone']), -10);
		if (strlen($phone) != 10) exit('phone');
		$u = auth_user_by_phone($phone);
		// Нөмір базада жоқ болса да «жіберілді» деп жауап береміз (кімнің тіркелгенін білдірмеу үшін)
		if (!$u || !auth_staff_ok($u['id'], $pos)) { unset($_SESSION['otp']['reset']); auth_json(['st' => 'code', 'phone' => wa_mask($phone), 'test' => '']); }
		$r = otp_start('reset', $u['id'], $u['phone']);
		if ($r == 'wait') $r = 'sent';
		if ($r != 'sent') exit($r);
		auth_json(['st' => 'code', 'phone' => wa_mask($u['phone']), 'test' => otp_test_code()]);
	}

	// 2) кодты тексеру
	if (isset($_GET['reset_code'])) {
		$r = otp_check('reset', @$_POST['code']);
		if (!is_int($r)) exit($r == 'none' ? 'wrong' : $r);
		$_SESSION['reset_ok'] = ['uid' => $r, 'exp' => time() + 600];
		exit('yes');
	}

	// 3) жаңа пароль
	if (isset($_GET['reset_pass'])) {
		$ok = @$_SESSION['reset_ok'];
		if (!$ok || $ok['exp'] < time()) exit('expired');
		$pass = trim((string)@$_POST['pass']);
		if (mb_strlen($pass) < 6) exit('short');
		$u = auth_user_by_id($ok['uid']);
		if (!$u || !auth_staff_ok($u['id'], $pos)) exit('none');
		db::query("UPDATE `user` SET `password` = '" . pass_sql($pass) . "', `upd_dt` = '" . date('Y-m-d H:i:s') . "' WHERE id = '" . (int)$u['id'] . "'");
		unset($_SESSION['reset_ok']);
		$u = auth_user_by_id($u['id']);
		auth_login($u);
		trust_set($u); // нөмірін растады
		exit('yes');
	}
}

}
