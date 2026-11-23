<?php
// ЖИ мен Google Drive кілттері базада (sys_setting, dev ▸ Кілттер). Базада кілт жоқ болса — ескі config/ai.php (көшіргенге дейін).
// Кухня (ktc) өз ai.php-сы болмаса көрші админнің ai.php-сын оқиды. Тұрақты аттары бұрынғыдай: ANTHROPIC_API_KEY, CLAUDE_MODEL, GDRIVE_*, APIFY_TOKEN.
require_once __DIR__.'/sys_set.php';

if (!function_exists('sys_keys_ai')) {
	function sys_keys_ai() {
		if (defined('ANTHROPIC_API_KEY')) return;
		if (sys_set('anthropic_key') !== '') {
			define('ANTHROPIC_API_KEY', sys_set('anthropic_key'));
			define('CLAUDE_MODEL', sys_set('claude_model', 'auto'));
			define('GDRIVE_CLIENT_ID', sys_set('gdrive_client_id'));
			define('GDRIVE_CLIENT_SECRET', sys_set('gdrive_client_secret'));
			define('GDRIVE_REDIRECT', sys_set('gdrive_redirect', 'http://localhost'));
			define('GDRIVE_ROOT', sys_set('gdrive_root', 'Berik POS'));
			define('APIFY_TOKEN', sys_set('apify_token'));
		} else {
			foreach ([__DIR__.'/ai.php', dirname(__DIR__, 2).'/adm.berik.c/config/ai.php'] as $f) if (is_file($f)) { require_once $f; break; }
		}
		$def = ['ANTHROPIC_API_KEY' => '', 'CLAUDE_MODEL' => 'auto', 'GDRIVE_CLIENT_ID' => '', 'GDRIVE_CLIENT_SECRET' => '',
			'GDRIVE_REDIRECT' => 'http://localhost', 'GDRIVE_ROOT' => 'Berik POS', 'APIFY_TOKEN' => ''];
		foreach ($def as $k => $v) if (!defined($k)) define($k, $v);
	}
}
sys_keys_ai();
