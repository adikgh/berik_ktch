<? include "../config/core.php";
	include "lib.php";
	include "../config/claude.php";

	// Кухня: Claude накладнойды/чекті оқиды (админдегі waybill/ai.php көшірмесі).
	// Файл осы сұраумен тікелей келеді (f[]), серверде сақталмайды.
	// Жауап: тауарлар тізімі. Базаға ештеңе жазылмайды, оны адам тексеріп «Сақтау» басады.
	header('Content-Type: application/json; charset=utf-8');
	$out = function ($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit(); };

	if (!$user_id || !in_array((int)@$user_right['positions_id'], kwb::$rights)) $out(['ok' => 0, 'msg' => t::w('Кіру рұқсаты жоқ', 'Нет доступа')]);
	if (!kwb::csrf_ok()) $out(['ok' => 0, 'msg' => t::w('Бетті жаңартып, қайта көріңіз', 'Обновите страницу и попробуйте снова')]);
	if (!claude::ready()) $out(['ok' => 0, 'msg' => t::w('Claude қосылмаған, админге айтыңыз', 'Claude не подключён, сообщите админу')]);
	claude::$feature = 'waybill_ktc';
	@set_time_limit(300);

	// ?ping — Claude-пен байланысты тексеру (өте қысқа сұрау, 1 теңгеге жетпейді)
	if (isset($_GET['ping'])) {
		$t = microtime(true);
		$pm = claude::model() == 'auto' ? claude::CHEAP : claude::model();
		$d = claude::json('Байланысты тексеру. ok өрісіне "ok" деп жаз.', [['type' => 'text', 'text' => 'ping']],
			['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'string']]], 'low', $pm);
		if (!$d) $out(['ok' => 0, 'msg' => claude::$err]);
		$out(['ok' => 1, 'model' => (claude::model() == 'auto' ? 'авто: ' : '').$pm, 'ms' => (int)round((microtime(true) - $t) * 1000)]);
	}
	$cmp = (int)$company;

	// файлдар (5-ке дейін: ұзын чек бірнеше фото болуы мүмкін)
	$content = []; $names = [];
	$add = function ($b, $mime, $name) use (&$content, &$names, $out) {
		if ($mime == 'application/pdf') $content[] = claude::pdf_block($b);
		elseif (preg_match('~^image/(jpeg|png|webp|gif)$~', (string)$mime)) $content[] = claude::image_block($b, $mime);
		else $out(['ok' => 0, 'msg' => 'Claude тек фото немесе PDF оқиды']);
		$names[] = $name;
	};
	if (!empty($_FILES['f']['tmp_name'])) {
		$F = $_FILES['f'];
		foreach (array_slice((array)$F['tmp_name'], 0, 5, true) as $k => $tmp) {
			if ((int)((array)$F['error'])[$k] !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) $out(['ok' => 0, 'msg' => 'Файл жүктелмеді (өлшемі тым үлкен болуы мүмкін)']);
			if (filesize($tmp) > 25 * 1024 * 1024) $out(['ok' => 0, 'msg' => 'Файл 25 МБ-тан үлкен']);
			$b = file_get_contents($tmp);
			$mime = function_exists('mime_content_type') ? mime_content_type($tmp) : ((array)$F['type'])[$k];
			$add($b, $mime, basename((string)((array)$F['name'])[$k]));
		}
	}
	if (!$content) $out(['ok' => 0, 'msg' => 'Файл таңдалмады']);

	// біздің шикізат пен жеткізушілер: Claude әр жолды осыдан табуға тырысады
	$raw = []; $raw_ids = [];
	$has_u = kwb::has_col('product_raw', 'unit');
	$rq = db::query("select id, name_kz, name_ru".($has_u ? ', unit' : '')." from product_raw where company_id = '$cmp' order by id");
	while ($rq && $r = mysqli_fetch_assoc($rq)) {
		$n = trim($r['name_kz'] ?: $r['name_ru']); if ($n === '') continue;
		$ru = trim((string)$r['name_ru']);
		$raw[] = $r['id'].' | '.$n.($ru !== '' && $ru !== $n ? ' / '.$ru : '').($has_u && $r['unit'] ? ' | '.kwb::unit_norm($r['unit']) : '');
		$raw_ids[(int)$r['id']] = 1;
	}
	// бұрын адам түзеткен сәйкестіктер (supplier_item кестесі болса): Claude соны басым көреді
	$mem = [];
	if (kwb::has_col('supplier_item', 'factor')) {
		$aq = db::query("select name, raw_id from supplier_item where company_id = '$cmp' order by upd_dt desc limit 400");
		while ($aq && $a = mysqli_fetch_assoc($aq)) if (isset($raw_ids[(int)$a['raw_id']])) $mem[] = $a['name'].' → '.$a['raw_id'];
	}
	$sup = [];
	$sq = db::query("select name from supplier where company_id = '$cmp' and archive = 0");
	while ($sq && $s = mysqli_fetch_assoc($sq)) $sup[] = $s['name'];

	$system = "Сен мейрамхананың закуп бөлімінде жеткізушінің накладнойын, чегін немесе шот-фактурасын оқисың. "
		."Құжаттағы әр тауар жолын жеке item етіп қайтар: name — құжатта жазылғандай (қысқартпа), unit — кг, г, л, мл, шт, пачка, қорап т.б., "
		."qty — саны, price — бір бірліктің бағасы (теңге), sum — жол сомасы. Бағасы не сомасы жоқ болса, басқасынан есепте. "
		."«Итого», «Барлығы», ҚҚС, жеңілдік жолдарын тауар деп алма; жеткізу ақысы болса бөлек жол қыл. "
		."raw_id — төмендегі біздің шикізат тізімінен дәл сол тауар болса оның id-сы, күмәнді болса немесе жоқ болса 0. "
		."sure — оқылуы мен сәйкестігіне сенімді болсаң true, бұлыңғыр/қолжазба/болжам болса false. "
		."supplier — жеткізушінің аты (біздің тізімде болса, сол жазылуымен), doc_no — құжат нөмірі, date — күні YYYY-MM-DD (жоқ болса бос), "
		."total — құжаттағы жалпы сома (жоқ болса 0), note — тексеру керек нәрсе болса қысқа қазақша ескерту, болмаса бос. "
		."quality — құжаттың оқылуы: good (анық басылған, бәрі оқылады), fair (кей жері бұлыңғыр/қисық), poor (бұлыңғыр, қараңғы, кесілген, көп жері оқылмайды); "
		."handwritten — тауарлары не сандары қолмен жазылған болса true. Өзіңді шын бағала. Ойдан ештеңе қоспа.\n\n"
		."Біздің шикізат (id | атауы | өлшемі):\n".implode("\n", $raw)
		.($mem ? "\n\nБұрын адам бекіткен сәйкестіктер (құжаттағы атау → raw_id), осындай атау кездессе дәл соны қолдан:\n".implode("\n", $mem) : '')
		."\n\nБіздің жеткізушілер: ".implode('; ', $sup);
	$content[] = ['type' => 'text', 'text' => 'Осы құжатты (файл: '.implode(', ', $names).') оқып, тауарларын қайтар.'];

	$str = ['type' => 'string']; $num = ['type' => 'number'];
	$schema = [
		'type' => 'object', 'additionalProperties' => false,
		'required' => ['quality', 'handwritten', 'supplier', 'doc_no', 'date', 'total', 'note', 'items'],
		'properties' => [
			'quality' => ['type' => 'string', 'enum' => ['good', 'fair', 'poor']], 'handwritten' => ['type' => 'boolean'],
			'supplier' => $str, 'doc_no' => $str, 'date' => $str, 'total' => $num, 'note' => $str,
			'items' => ['type' => 'array', 'items' => [
				'type' => 'object', 'additionalProperties' => false,
				'required' => ['name', 'unit', 'qty', 'price', 'sum', 'raw_id', 'sure'],
				'properties' => ['name' => $str, 'unit' => $str, 'qty' => $num, 'price' => $num, 'sum' => $num, 'raw_id' => ['type' => 'integer'], 'sure' => ['type' => 'boolean']],
			]],
		],
	];

	// Модель таңдау. auto: арзан модель оқиды және құжаттың сапасын бағалайды.
	// Сапасы жақсы, сенімсіз жол аз, сомасы сәйкес келсе — сол жауап алынады (~1 ₸).
	// Әйтпесе: қолжазба/нашар фото → Opus, қалғаны → Sonnet, сол модель қайта оқиды.
	$steps = []; $usd = 0; $tok = 0;
	$run = function ($model, $effort) use ($system, $content, $schema, &$steps, &$usd, &$tok, $out) {
		$d = claude::json($system, $content, $schema, $effort, $model);
		$u = claude::$usage;
		if ($u) {
			$m = $u['model'] ?? $model; $c = claude::usd($m, $u);
			$t = ($u['input_tokens'] ?? 0) + ($u['cache_creation_input_tokens'] ?? 0) + ($u['cache_read_input_tokens'] ?? 0) + ($u['output_tokens'] ?? 0);
			$steps[] = ['model' => $m, 'tokens' => $t, 'usd' => round($c, 4)]; $usd += $c; $tok += $t;
		}
		return $d;
	};
	// оқу сапасын тексеру: себебін қайтарады, бәрі жақсы болса бос.
	// $self = true: модельдің өз бағасын (қолжазба/бұлыңғыр) да ескереміз (тек бірінші, арзан қадамда)
	$weak = function ($d, $self = true) {
		if (!$d) return 'жауап жоқ';
		if ($self && $d['handwritten']) return 'қолжазба';
		if ($self && $d['quality'] != 'good') return $d['quality'] == 'poor' ? 'фото нашар' : 'кей жері бұлыңғыр';
		$n = count($d['items']); if (!$n) return 'тауар табылмады';
		$uns = 0; $sum = 0;
		foreach ($d['items'] as $i) { if (!$i['sure']) $uns++; $sum += $i['sum'] ?: $i['qty'] * $i['price']; }
		if ($uns / $n > 0.15) return 'сенімсіз жолдар көп';
		if ($d['total'] > 0 && abs($sum - $d['total']) > max(1, $d['total'] * 0.01)) return 'сомасы сәйкес емес';
		return '';
	};

	$why = '';
	if (claude::model() != 'auto') $d = $run(claude::model(), 'medium');
	else {
		$d = $run(claude::CHEAP, 'medium');
		$why = $weak($d);
		if ($why !== '') {
			// қолжазба, нашар фото, ештеңе оқылмаса — бірден Opus; әйтпесе Sonnet, ол да сенімсіз болса Opus
			$big = !$d || $d['handwritten'] || $d['quality'] == 'poor' || !count($d['items']);
			$d2 = $run($big ? claude::TOP : claude::MID, $big ? 'high' : 'medium');
			if (!$big && $weak($d2, false) !== '') { $d3 = $run(claude::TOP, 'high'); if ($d3) $d2 = $d3; }
			if ($d2) $d = $d2;
		}
	}
	if (!$d) $out(['ok' => 0, 'msg' => claude::$err]);

	$items = [];
	foreach ($d['items'] as $i) {
		$name = trim((string)$i['name']); if ($name === '') continue;
		$qty = max(0, (float)$i['qty']); $price = max(0, (float)$i['price']); $sum = max(0, (float)$i['sum']);
		if (!$price && $sum && $qty) $price = $sum / $qty;
		if (!$sum) $sum = $qty * $price;
		$rid = (int)$i['raw_id'];
		$items[] = ['name' => $name, 'unit' => trim((string)$i['unit']), 'qty' => $qty, 'price' => $price, 'sum' => $sum, 'raw_id' => isset($raw_ids[$rid]) ? $rid : 0, 'sure' => (bool)$i['sure']];
	}
	$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['date']) ? $d['date'] : '';
	$out(['ok' => 1, 'supplier' => trim($d['supplier']), 'doc_no' => trim($d['doc_no']), 'date' => $date, 'total' => (float)$d['total'], 'note' => trim($d['note']), 'items' => $items,
		'usage' => ['tokens' => $tok, 'usd' => round($usd, 4), 'steps' => $steps, 'why' => $why]]);
