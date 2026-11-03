<? include "../config/core.php";

	// Өз құпиясөзін өзгерту (тек кірген қолданушы)
	if (isset($_GET['pass_ubd'])) {
		if (!$user_id) exit('none');
		$pass = trim(strip_tags((string)@$_POST['pass']));
		if (mb_strlen($pass) < 6) exit('short');
		if (db::query("UPDATE `user` SET `password` = '".pass_sql($pass)."', `upd_dt` = '$datetime' WHERE id = '".(int)$user_id."'")) {
			auth_relogin($user_id);
			echo 'yes';
		}
		exit();
	}
