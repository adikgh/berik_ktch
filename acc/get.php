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

	// Профиль: CSRF (acc/index.php $_SESSION['csrf'])
	if ((isset($_GET['prof_save']) || isset($_GET['prof_img'])) && (!$user_id || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)@$_POST['csrf']))) exit($user_id ? 'csrf' : 'none');

	// Профиль: өз аты мен тегін сақтау
	if (isset($_GET['prof_save'])) {
		$name = trim(strip_tags((string)@$_POST['name']));
		$surname = trim(strip_tags((string)@$_POST['surname']));
		if ($name === '' || mb_strlen($name) > 60 || mb_strlen($surname) > 60) exit('name');
		$st = mysqli_prepare(db::$connect, "UPDATE user SET name = ?, surname = ?, upd_dt = ? WHERE id = ?");
		mysqli_stmt_bind_param($st, 'sssi', $name, $surname, $datetime, $user_id); mysqli_stmt_execute($st);
		exit('yes');
	}

	// Профиль: фото (JPG/PNG/WEBP, 8 МБ дейін → 400x400 JPG)
	if (isset($_GET['prof_img'])) {
		$f = @$_FILES['img'];
		if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) exit('file');
		if ($f['size'] > 8 * 1024 * 1024) exit('size');
		$info = @getimagesize($f['tmp_name']);
		$types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
		if (!$info || !isset($types[$info[2]]) || $info[0] < 50 || $info[1] < 50 || $info[0] > 8000 || $info[1] > 8000) exit('type');
		$dir = $_SERVER['DOCUMENT_ROOT'].'/assets/uploads/users/';
		$name = 'u'.(int)$user_id.'_'.bin2hex(random_bytes(6));
		if (function_exists('imagecreatetruecolor')) {
			$src = $info[2] == IMAGETYPE_JPEG ? @imagecreatefromjpeg($f['tmp_name']) : ($info[2] == IMAGETYPE_PNG ? @imagecreatefrompng($f['tmp_name']) : @imagecreatefromwebp($f['tmp_name']));
			if (!$src) exit('type');
			if ($info[2] == IMAGETYPE_JPEG && function_exists('exif_read_data')) {
				$o = (int)@exif_read_data($f['tmp_name'])['Orientation'];
				if ($o == 3) $src = imagerotate($src, 180, 0); elseif ($o == 6) $src = imagerotate($src, -90, 0); elseif ($o == 8) $src = imagerotate($src, 90, 0);
			}
			$w = imagesx($src); $h = imagesy($src); $side = min($w, $h);
			$dst = imagecreatetruecolor(400, 400);
			imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
			imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($h - $side) / 2), 400, 400, $side, $side);
			$name .= '.jpg';
			if (!imagejpeg($dst, $dir.$name, 85)) exit('save');
		} else {
			$name .= '.'.$types[$info[2]];
			if (!move_uploaded_file($f['tmp_name'], $dir.$name)) exit('save');
		}
		$old = (string)$user['img'];
		$st = mysqli_prepare(db::$connect, "UPDATE user SET img = ?, upd_dt = ? WHERE id = ?");
		mysqli_stmt_bind_param($st, 'ssi', $name, $datetime, $user_id); mysqli_stmt_execute($st);
		if ($old !== '' && preg_match('/^u'.(int)$user_id.'_[a-f0-9]{12}\.(jpg|png|webp)$/', $old) && is_file($dir.$old)) @unlink($dir.$old);
		echo json_encode(['ok' => 1, 'img' => '/assets/uploads/users/'.$name]);
		exit();
	}
