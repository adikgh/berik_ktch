<? include "../config/core.php";
	include "lib.php";

	header('Content-Type: application/json; charset=utf-8');
	function out($ok, $data = []) { exit(json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE)); }

	if (!$user_id || !in_array((int)@$user_right['positions_id'], kwb::$rights)) out(false, ['msg' => t::w('Кіру рұқсаты жоқ', 'Нет доступа')]);
	if (!kwb::csrf_ok()) out(false, ['msg' => t::w('Бетті жаңартып, қайта көріңіз', 'Обновите страницу и попробуйте снова')]);
	$cmp = (int)$company;
	$now = date('Y-m-d H:i:s');

	// Накладнойды жоба ретінде сақтау (жаңа немесе өзінің жобасы)
	if (isset($_GET['save'])) {
		$id = (int)@$_POST['id'];
		if ($id && !kwb::can_edit(kwb::get($id, $cmp), $user_id)) out(false, ['msg' => t::w('Бұл накладнойды өзгертуге болмайды', 'Эту накладную нельзя изменить')]);

		// жеткізуші: тізімнен (supplier_id) немесе жаңа атау (supplier_id = new + supplier_new)
		$sup = (string)@$_POST['supplier_id'];
		if ($sup === 'new') {
			$sup_name = kwb::esc(mb_substr((string)@$_POST['supplier_new'], 0, 255));
			$sup = 0;
			if ($sup_name !== '') {
				$ex = db::query("select id from supplier where company_id = '$cmp' and name = '$sup_name' limit 1");
				if ($ex && mysqli_num_rows($ex)) $sup = (int)mysqli_fetch_assoc($ex)['id'];
				else { db::query("insert into supplier (company_id, name) values ('$cmp', '$sup_name')"); $sup = (int)mysqli_insert_id(db::$connect); }
			}
		} else {
			$sup = (int)$sup;
			if ($sup && !mysqli_num_rows(db::query("select id from supplier where id = '$sup' and company_id = '$cmp'"))) $sup = 0;
		}
		if (!$sup) out(false, ['msg' => t::w('Жеткізушіні таңдаңыз немесе жаңасын жазыңыз', 'Выберите или впишите поставщика')]);

		$date = @$_POST['doc_date'] && strtotime($_POST['doc_date']) ? date('Y-m-d', strtotime($_POST['doc_date'])) : date('Y-m-d');
		$doc_no = kwb::esc(mb_substr((string)@$_POST['doc_no'], 0, 64));
		$comment = kwb::esc(mb_substr((string)@$_POST['comment'], 0, 1000));
		$paid = max(0, round(kwb::num(@$_POST['paid']), 2));

		// жолдар: сома серверде қайта есептеледі
		$rows = json_decode((string)@$_POST['items'], true);
		$items = []; $total = 0;
		if (is_array($rows)) foreach (array_slice($rows, 0, 200) as $r) {
			$name = trim(strip_tags((string)@$r['name']));
			$qty = round(kwb::num(@$r['qty']), 3);
			$price = round(kwb::num(@$r['price']), 2);
			if ($name === '' && !$qty && !$price) continue;
			if ($name === '') out(false, ['msg' => t::w('Тауардың атауын жазыңыз', 'Укажите название товара')]);
			if ($qty <= 0) out(false, ['msg' => '«'.$name.'»: '.t::w('саны 0-ден көп болуы керек', 'количество должно быть больше 0')]);
			if ($price < 0) out(false, ['msg' => '«'.$name.'»: '.t::w('бағасы дұрыс емес', 'неверная цена')]);
			$unit = kwb::unit_norm(@$r['unit']) ?: 'кг';
			$name = mb_substr($name, 0, 255);
			// шикізат: формада таңдалғаны (осы компанияныкі болса), әйтпесе атауы бойынша (болмаса админ қабылдағанда өзі жасалады)
			$raw_id = (int)@$r['raw_id'];
			if ($raw_id && !mysqli_num_rows(db::query("select id from product_raw where id = '$raw_id' and company_id = '$cmp'"))) $raw_id = 0;
			if (!$raw_id) {
				$n = kwb::esc($name);
				$rq = db::query("select id from product_raw where company_id = '$cmp' and (name_kz = '$n' or name_ru = '$n') limit 1");
				$raw_id = $rq && mysqli_num_rows($rq) ? (int)mysqli_fetch_assoc($rq)['id'] : 0;
			}
			$sum = round($qty * $price, 2);
			$total += $sum;
			$items[] = ['raw_id' => $raw_id, 'name' => $name, 'unit' => $unit, 'qty' => $qty, 'price' => $price, 'sum' => $sum];
		}
		if (!$items) out(false, ['msg' => t::w('Кемінде бір тауар қосыңыз', 'Добавьте хотя бы один товар')]);
		$total = round($total, 2);

		mysqli_begin_transaction(db::$connect);
		if ($id) {
			$ok = db::query("update waybill set supplier_id = '$sup', doc_no = '$doc_no', doc_date = '$date', total = '$total', paid = '$paid', comment = '$comment', upd_dt = '$now'
				where id = '$id' and company_id = '$cmp' and status = 0 and user_id = '".(int)$user_id."'");
			$ok = $ok && db::query("delete from waybill_item where waybill_id = '$id'");
		} else {
			$num = (int)mysqli_fetch_assoc(db::query("select IFNULL(MAX(number), 0) + 1 n from waybill where company_id = '$cmp'"))['n'];
			$br = (int)$branch ?: 1;
			$ok = db::query("insert into waybill (company_id, branch_id, number, supplier_id, doc_no, doc_date, total, paid, comment, user_id, ins_dt)
				values ('$cmp', '$br', '$num', '$sup', '$doc_no', '$date', '$total', '$paid', '$comment', '".(int)$user_id."', '$now')");
			$id = (int)mysqli_insert_id(db::$connect);
		}
		if ($ok && $id) {
			$vals = [];
			foreach ($items as $i) $vals[] = "('$id', ".($i['raw_id'] ? "'{$i['raw_id']}'" : 'NULL').", '".kwb::esc($i['name'])."', '".kwb::esc($i['unit'])."', '{$i['qty']}', '{$i['price']}', '{$i['sum']}')";
			$ok = db::query("insert into waybill_item (waybill_id, raw_id, name, unit, quantity, price, sum) values ".implode(',', $vals));
		}
		if (!$ok || !$id) { mysqli_rollback(db::$connect); out(false, ['msg' => t::w('Сақтау кезінде қате шықты', 'Ошибка при сохранении')]); }
		mysqli_commit(db::$connect);
		out(true, ['id' => $id, 'msg' => t::w('Сақталды. Админ тексеріп, қабылдайды', 'Сохранено. Админ проверит и примет')]);
	}

	// Өзінің жобасын өшіру
	if (isset($_GET['delete'])) {
		$id = (int)@$_POST['id'];
		if (!kwb::can_edit(kwb::get($id, $cmp), $user_id)) out(false, ['msg' => t::w('Өшіруге болмайды', 'Нельзя удалить')]);
		db::query("delete from waybill_item where waybill_id = '$id'");
		db::query("delete from waybill where id = '$id' and company_id = '$cmp' and status = 0 and user_id = '".(int)$user_id."'");
		out(true, ['msg' => t::w('Өшірілді', 'Удалено')]);
	}

	// Жеткізуші атауы → біздің шикізат (1 шт = factor), импорт кезінде адам бекіткен сәйкестік. Админдегідей, supplier_item кестесі болса ғана.
	if (isset($_GET['alias_save'])) {
		if (!kwb::has_col('supplier_item', 'factor')) out(false, ['msg' => 'supplier_item кестесі әлі жоқ']);
		$rows = json_decode((string)@$_POST['items'], true); $n = 0;
		if (is_array($rows)) foreach (array_slice($rows, 0, 300) as $r) {
			$name = kwb::esc(mb_substr(trim((string)@$r['name']), 0, 255));
			$raw = (int)@$r['raw_id'];
			$f = round(kwb::num(@$r['factor']), 4);
			if ($name === '' || !$raw || $f <= 0) continue;
			$ok = db::query("select id from product_raw where id = '$raw' and company_id = '$cmp'");
			if (!$ok || !mysqli_num_rows($ok)) continue;
			if (db::query("insert into supplier_item (company_id, name, raw_id, factor) values ('$cmp', '$name', '$raw', '$f')
				on duplicate key update raw_id = values(raw_id), factor = values(factor), upd_dt = '$now'")) $n++;
		}
		out(true, ['n' => $n]);
	}

	out(false, ['msg' => 'Белгісіз сұрау']);
