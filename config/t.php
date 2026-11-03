<?php

// Тіл: kz (стандарт), en, uz, ky, ru — кассадағыдай. Мәтін кодта: t::w('Қазақша', 'Русский'),
// қалған тілдер config/lang.php ішінде (орысша мәтін бойынша). Аудармасы жоқ болса қазақшасы шығады.
class t {

	public static $lang = 'kz';
	public static $dict = null;

	public function __construct() { }

	public static function w($kz, $ru = null) {
		if (self::$lang == 'kz' || $ru === null) return $kz;
		if (self::$lang == 'ru') return $ru;
		if (self::$dict === null) self::$dict = require __DIR__.'/lang.php';
		$i = ['en' => 0, 'uz' => 1, 'ky' => 2][self::$lang] ?? null;
		return ($i !== null && isset(self::$dict[$ru][$i])) ? self::$dict[$ru][$i] : $kz;
	}

	// HTML атрибутына қауіпсіз мәтін
	public static function h($kz, $ru = null) {
		return htmlspecialchars(self::w($kz, $ru), ENT_QUOTES);
	}

	// word block
	public static function wb($l) {
		$sql = mysqli_fetch_array(db::query("select * from `word_block` where txt = '$l'"));
		return @$sql['txt_'.(self::$lang == 'ru' ? 'ru' : 'kz')];
	}

	// info
	public static function info($id) {
		$sql = mysqli_fetch_array(db::query("select * from `info` where id = '$id'"));
		return @$sql['name_'.(self::$lang == 'ru' ? 'ru' : 'kz')];
	}

}
