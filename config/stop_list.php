<?php

// Стоп-лист: уақытша жоқ тағамдар (company_stop_list). Кесте алғаш керек болғанда өзі құрылады.
// branch_id = 0 — бүкіл компания (әзірге осылай). Стоптағы тағам кірген сет те стопта саналады.
// mode: 1 = қолмен стоп, 2 = «Сатуға рұқсат» (шикізаты бітсе де автомат стопқа түспейді).
// Автомат стоп (company_stop_setting.auto = 1): граммовкадағы шикізат қалдығы <= 0 болса, тағам өзі стопта.
// Бұл файлдың көшірмесі әр сайтта бөлек тұр (adm, pos, ktc); my.berik.c-да PDO нұсқасы. Басқару — админ /products/stop/.
class stopl {

	public static $ok = null;

	public static function install() {
		if (self::$ok !== null) return self::$ok;
		db::query("CREATE TABLE IF NOT EXISTS `company_stop_list` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`company_id` int(11) NOT NULL,
			`branch_id` int(11) NOT NULL DEFAULT 0,
			`product_id` int(11) NOT NULL,
			`mode` tinyint(1) NOT NULL DEFAULT 1,
			`note` varchar(255) DEFAULT NULL,
			`user_id` int(11) DEFAULT NULL,
			`ins_dt` datetime NOT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `cmp_br_pr` (`company_id`, `branch_id`, `product_id`),
			KEY `product_id` (`product_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		db::query("CREATE TABLE IF NOT EXISTS `company_stop_setting` (
			`company_id` int(11) NOT NULL,
			`auto` tinyint(1) NOT NULL DEFAULT 0,
			`upd_dt` datetime DEFAULT NULL,
			PRIMARY KEY (`company_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
		$q = db::query("SHOW TABLES LIKE 'company_stop_list'");
		return self::$ok = ($q && mysqli_num_rows($q) > 0);
	}

	// Автомат стоп қосулы ма
	public static function auto($cmp) {
		$cmp = (int)$cmp;
		if (!$cmp || !self::install()) return false;
		$r = mysqli_fetch_assoc(db::query("select auto from company_stop_setting where company_id = '$cmp'"));
		return $r && (int)$r['auto'] == 1;
	}
	public static function auto_set($cmp, $on) {
		$cmp = (int)$cmp;
		if (!$cmp || !self::install()) return false;
		unset(self::$cache[$cmp]);
		$now = date('Y-m-d H:i:s'); $on = $on ? 1 : 0;
		return db::query("insert into company_stop_setting (company_id, auto, upd_dt) values ('$cmp', '$on', '$now')
			on duplicate key update auto = values(auto), upd_dt = values(upd_dt)");
	}

	// Өзі стопқа қойылған тағамдар: [product_id => жол]
	public static function own($cmp) {
		$cmp = (int)$cmp; $out = [];
		if (!$cmp || !self::install()) return $out;
		$q = db::query("select * from company_stop_list where company_id = '$cmp' order by ins_dt desc");
		while ($q && $r = mysqli_fetch_assoc($q)) $out[(int)$r['product_id']] = $r;
		return $out;
	}

	// Сатуға болмайтындар: [product_id => 1 қолмен стоп, 2 ішіндегі тағам стопта (сет), 3 шикізаты бітті (автомат)]
	public static $cache = [];
	public static function ids($cmp) {
		$cmp = (int)$cmp;
		if (isset(self::$cache[$cmp])) return self::$cache[$cmp];
		$out = []; $allow = [];
		foreach (self::own($cmp) as $pid => $r) { if ((int)$r['mode'] == 2) $allow[$pid] = 1; else $out[$pid] = 1; }
		if (self::auto($cmp)) foreach (self::raw_out_cached($cmp) as $pid => $m) if (!isset($out[$pid]) && !isset($allow[$pid])) $out[$pid] = 3;
		if ($out) {
			// сеттер: ішінде стоптағы тағам бар болса (2 деңгейге дейін)
			for ($lvl = 0; $lvl < 2; $lvl++) {
				$ids = implode(',', array_map('intval', array_keys($out)));
				$q = @db::query("select distinct set_id from product_set_item where product_id in ($ids)");
				$add = 0;
				while ($q && $r = mysqli_fetch_assoc($q)) if (!isset($out[(int)$r['set_id']]) && !isset($allow[(int)$r['set_id']])) { $out[(int)$r['set_id']] = 2; $add++; }
				if (!$add) break;
			}
		}
		return self::$cache[$cmp] = $out;
	}

	public static function is($cmp, $pid) { $s = self::ids($cmp); return $s[(int)$pid] ?? 0; }

	// mode 1 — стопқа қою, 2 — «Сатуға рұқсат», 0 — екеуін де алу
	public static function set($cmp, $pid, $mode, $user_id = 0, $note = '') {
		$cmp = (int)$cmp; $pid = (int)$pid; $mode = (int)$mode;
		if (!$cmp || !$pid || !self::install()) return false;
		unset(self::$cache[$cmp]);
		if (!in_array($mode, [1, 2])) return db::query("delete from company_stop_list where company_id = '$cmp' and branch_id = 0 and product_id = '$pid'");
		// тек өз компаниясының тағамы
		$ok = mysqli_num_rows(db::query("select p.id from product p left join product_catalog c on c.id = p.catalog_id
			where p.id = '$pid' and (p.company_id = '$cmp' or c.company_id = '$cmp') limit 1"));
		if (!$ok) return false;
		$n = mysqli_real_escape_string(db::$connect, mb_substr(trim(strip_tags((string)$note)), 0, 255));
		$now = date('Y-m-d H:i:s');
		return db::query("insert into company_stop_list (company_id, branch_id, product_id, mode, note, user_id, ins_dt)
			values ('$cmp', 0, '$pid', '$mode', '$n', '".(int)$user_id."', '$now')
			on duplicate key update mode = values(mode), note = values(note), user_id = values(user_id), ins_dt = values(ins_dt)");
	}

	// raw_out бір минут сессияда сақталады (касса әр санат ашқанда қайта есептемейді)
	public static function raw_out_cached($cmp) {
		$k = 'stopl_raw_'.(int)$cmp;
		if (session_status() == PHP_SESSION_ACTIVE && isset($_SESSION[$k]) && time() - (int)$_SESSION[$k]['t'] < 60) return $_SESSION[$k]['d'];
		$d = self::raw_out($cmp);
		if (session_status() == PHP_SESSION_ACTIVE) $_SESSION[$k] = ['t' => time(), 'd' => $d];
		return $d;
	}

	// Шикізаты біткен тағамдар (ескерту ғана, стопқа өзі қоймайды): [product_id => [raw аттары]]
	// Граммовка: product_item → product_comp → product_comp_part → product_raw; сет → ішіндегі тағамдар.
	public static function raw_out($cmp) {
		$cmp = (int)$cmp; $res = [];
		$t = db::query("SHOW TABLES LIKE 'product_raw_stock'");
		if (!$t || !mysqli_num_rows($t)) return $res;
		// қалдық: барлық филиал бойынша қосынды; накладной арқылы бір рет те келмеген шикізат есепке алынбайды
		$out = [];
		$q = db::query("select r.id, COALESCE(NULLIF(r.name_kz, ''), r.name_ru) name, SUM(st.quantity) qty from product_raw r
			join product_raw_stock st on st.raw_id = r.id and st.company_id = '$cmp'
			where r.company_id = '$cmp' group by r.id having qty <= 0");
		while ($q && $r = mysqli_fetch_assoc($q)) $out[(int)$r['id']] = $r['name'];
		if (!$out) return $res;
		$comps = [];
		$q = db::query("select id, raw_id from product_comp where company_id = '$cmp'");
		while ($q && $r = mysqli_fetch_assoc($q)) $comps[(int)$r['id']] = ['raw' => (int)$r['raw_id'], 'parts' => []];
		if ($comps) {
			$q = db::query("select comp_id, raw_id, sub_comp_id from product_comp_part where comp_id in (".implode(',', array_keys($comps)).")");
			while ($q && $r = mysqli_fetch_assoc($q)) $comps[(int)$r['comp_id']]['parts'][] = $r;
		}
		$comp_raws = function ($cid, $d = 0) use (&$comp_raws, $comps) {
			$c = $comps[$cid] ?? null; $o = [];
			if (!$c || $d > 8) return $o;
			if ($c['parts']) foreach ($c['parts'] as $p) {
				if ($p['raw_id']) $o[(int)$p['raw_id']] = 1;
				elseif ($p['sub_comp_id']) $o += $comp_raws((int)$p['sub_comp_id'], $d + 1);
			} elseif ($c['raw']) $o[$c['raw']] = 1;
			return $o;
		};
		$dish = [];
		$q = db::query("select pi.product_id, pi.comp_id from product_item pi join product p on p.id = pi.product_id where p.company_id = '$cmp' and pi.comp_id > 0");
		while ($q && $r = mysqli_fetch_assoc($q)) $dish[(int)$r['product_id']] = ($dish[(int)$r['product_id']] ?? []) + $comp_raws((int)$r['comp_id']);
		$q = db::query("select si.set_id, si.product_id, si.comp_id from product_set_item si join product s on s.id = si.set_id where s.company_id = '$cmp'");
		$sets = [];
		while ($q && $r = mysqli_fetch_assoc($q)) $sets[(int)$r['set_id']][] = $r;
		foreach ($sets as $sid => $rows) if (!isset($dish[$sid])) {
			$o = [];
			foreach ($rows as $s) {
				if ($s['product_id']) $o += $dish[(int)$s['product_id']] ?? [];
				elseif ($s['comp_id']) $o += $comp_raws((int)$s['comp_id']);
			}
			$dish[$sid] = $o;
		}
		foreach ($dish as $pid => $raws) {
			$miss = array_values(array_intersect_key($out, $raws));
			if ($miss) $res[$pid] = $miss;
		}
		return $res;
	}

}
