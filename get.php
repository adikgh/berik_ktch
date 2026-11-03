<? include "config/core.php";

	// sign in code
	// if(isset($_GET['code'])) {
	// 	$code = strip_tags($_POST['code']);
	// 	$user_mn = db::query("SELECT * FROM user_management WHERE code = '$code'");
	// 	if (mysqli_num_rows($user_mn)) {
	// 		$user_mnd = mysqli_fetch_assoc($user_mn);
	// 		$id = $user_mnd['user_id'];
	// 		$user = db::query("SELECT * FROM user WHERE id = '$id'");
	// 		$user_d = mysqli_fetch_assoc($user);
	// 		$_SESSION['uph'] = $user_d['phone'];
	// 		$_SESSION['ups'] = $user_d['password'];
	// 		echo 'yes';
	// 	} else echo 'none';
	// 	exit();
	// }


	// Кіру: пароль → WhatsApp коды, парольді ұмыттым (config/auth.php)
	auth_routes([1, 2, 3, 9], 'ktch');











	// ubd user
	if(isset($_GET['ubd_acc'])) {
		if (!$user_id) exit('none');
		$n_name = strip_tags($_POST['n_name']);
		$surname = strip_tags($_POST['surname']);
		$sex = strip_tags($_POST['sex']);
		$age = strip_tags($_POST['age']);
		$mail = strip_tags($_POST['mail']);
		$phone = substr(preg_replace('/\D/', '', (string)@$_POST['phone']), -10);
		$password = trim((string)@$_POST['password']);
		
		// SQL-ге экрандау; телефон тек 10 таңба болса және басқа адамда болмаса ауысады
		$e = function ($v) { return mysqli_real_escape_string(db::$connect, trim((string)$v)); };
		$set_ph = '';
		if (strlen($phone) == 10 && !mysqli_num_rows(db::query("SELECT id FROM `user` WHERE `phone` = '$phone' AND id <> '".(int)$user_id."' LIMIT 1"))) $set_ph = ", `phone`='$phone'";
		$upd = db::query("UPDATE `user` SET `name`='".$e($n_name)."', `surname`='".$e($surname)."', `sex`='".$e($sex)."', `age`='".$e($age)."', `mail`='".$e($mail)."'$set_ph, `upd_dt`='$datetime' WHERE id = '".(int)$user_id."'");
		if ($password !== '') db::query("UPDATE `user` SET `password` = '".pass_sql($password)."' WHERE id = '$user_id'");

		auth_relogin($user_id); // cookie жаңа телефон/парольмен

		echo "yes";
		exit();
	}



