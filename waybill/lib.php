<?
	// Кухня: накладной тіркеу. Кестелер adm.berik.c/waybill-пен ортақ (supplier, waybill, waybill_item, product_raw).
	// Кухня тек жоба (status 0) сақтайды, қалдыққа қосуды (қабылдау) админ жасайды.

	class kwb {
		public static $units = ['кг', 'г', 'л', 'мл', 'шт', 'қап', 'қорап', 'бума'];

		public static function esc($v) { return mysqli_real_escape_string(db::$connect, trim(strip_tags((string)$v))); }
		public static function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES); }
		public static function num($v) { return (float)str_replace([' ', ','], ['', '.'], (string)$v); }
		public static function sum($v) { return number_format(round((float)$v), 0, '.', ' '); }
		public static function qty($v) { return rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.'); }

		public static function unit_norm($u) {
			$k = mb_strtolower(trim(str_replace('.', '', (string)$u)));
			if ($k === '') return '';
			$map = ['шт' => ['шт', 'штук', 'штука', 'штуки', 'дана', 'ед'], 'кг' => ['кг', 'kg', 'килограмм'], 'г' => ['г', 'гр', 'грамм'], 'л' => ['л', 'литр'], 'мл' => ['мл', 'ml']];
			foreach ($map as $n => $list) if (in_array($k, $list, true)) return $n;
			return mb_substr($k, 0, 20);
		}

		public static function get($id, $cmp) {
			$q = db::query("select w.*, s.name supplier_name from waybill w left join supplier s on s.id = w.supplier_id where w.id = '".(int)$id."' and w.company_id = '".(int)$cmp."' limit 1");
			return $q && mysqli_num_rows($q) ? mysqli_fetch_assoc($q) : null;
		}
		public static function items($id) {
			$r = []; $q = db::query("select * from waybill_item where waybill_id = '".(int)$id."' order by id");
			while ($q && $i = mysqli_fetch_assoc($q)) $r[] = $i;
			return $r;
		}
		// Кухня өзгерте алады: өзі жасаған, әлі қабылданбаған
		public static function can_edit($w, $user_id) { return $w && (int)$w['status'] == 0 && (int)$w['user_id'] == (int)$user_id; }

		public static function csrf() {
			if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
			return $_SESSION['csrf'];
		}
	}
