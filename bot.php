<?php
/**
 * Telegram AI Photo Generator Bot (single-file)
 * Endpoint: https://api.fast-creat.ir/gpt/photo?apikey=API_KEY&text=TEXT
 * Requires: PHP 7.2+ with cURL, MBString
 */

// ====== CONFIG ======
const BOT_TOKEN = ''; // Telegram bot token
const ADMIN_ID  = ; // Admin Telegram user ID
const FAST_CREAT_PHOTO_APIKEY = '7135477742:nGVhoj8p624zBHf@Api_ManagerRoBot'; // Fast-Creat API key for photo
const FAST_CREAT_QUALITY_APIKEY = '7135477742:9rRYVLb7DewS02f@Api_ManagerRoBot'; // Fast-Creat API key for quality
const FAST_CREAT_LOGO_APIKEY = '7135477742:TehUQYoquRyasbA@Api_ManagerRoBot'; // Fast-Creat API key for logo/effect
const FAST_CREAT_GPT_APIKEY = '7135477742:xpbZ0YO92loHaRu@Api_ManagerRoBot'; // Fast-Creat API key for chat GPT (gpt4)
const FAST_CREAT_GPT_CHAT_APIKEY = '7135477742:ESoWxUwzvteaQ7N@Api_ManagerRoBot'; // Fast-Creat API key for chat (chat)
const FAST_CREAT_GHIBLI_APIKEY = '7135477742:nakueQ3Hv08NKZs@Api_ManagerRoBot'; // Fast-Creat API key for anime (ghibli)

// ====== CONSTANTS ======
const TG_API = 'https://api.telegram.org/bot';
const DATA_DIR = __DIR__ . '/data';
const USERS_FILE = DATA_DIR . '/users.json';
const USERS_DB_FILE = DATA_DIR . '/users_db.json';
const SETTINGS_FILE = DATA_DIR . '/settings.json';
const STATE_FILE = DATA_DIR . '/state.json';
const TMP_DIR = DATA_DIR . '/tmp';

// Ensure dirs
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0777, true);
if (!is_dir(TMP_DIR)) @mkdir(TMP_DIR, 0777, true);

// ====== Storage ======
function loadJsonFile(string $path): array {
	if (!file_exists($path)) return [];
	$raw = @file_get_contents($path);
	if ($raw === false || $raw === '') return [];
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : [];
}

function saveJsonFile(string $path, array $data): void {
	@file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function registerUser(int $userId): void {
	$users = loadJsonFile(USERS_FILE);
	if (!in_array($userId, $users, true)) {
		$users[] = $userId;
		saveJsonFile(USERS_FILE, $users);
	}
    // ensure detailed record exists/update last seen
    $u = getUserRecord($userId);
    if ($u === null) {
        $settings = loadSettings();
        $now = time();
        $today = date('Y-m-d');
        $record = [
            'id' => $userId,
            'joined_at' => $now,
            'last_seen' => $now,
            'total_requests' => 0,
            'daily_date' => $today,
            'daily_count' => 0,
            'points' => (int)($settings['initial_points'] ?? 20),
        ];
        saveUserRecord($record);
    } else {
        $u['last_seen'] = time();
        saveUserRecord($u);
    }
}

function getUserState(int $userId): ?string {
	$state = loadJsonFile(STATE_FILE);
	return $state[(string)$userId] ?? null;
}

function setUserState(int $userId, ?string $value): void {
	$state = loadJsonFile(STATE_FILE);
	if ($value === null) {
		unset($state[(string)$userId]);
	} else {
		$state[(string)$userId] = $value;
	}
	saveJsonFile(STATE_FILE, $state);
}

// ====== Settings & Users DB ======
function loadSettings(): array {
    $defaults = [
        'daily_limit' => 20,
        'request_cost_points' => 1,
        'initial_points' => 20,
    ];
    $cfg = loadJsonFile(SETTINGS_FILE);
    return array_merge($defaults, $cfg);
}

function saveSettings(array $cfg): void {
    $current = loadSettings();
    $new = array_merge($current, $cfg);
    saveJsonFile(SETTINGS_FILE, $new);
}

function getUserRecord(int $userId): ?array {
    $db = loadJsonFile(USERS_DB_FILE);
    $key = (string)$userId;
    return isset($db[$key]) && is_array($db[$key]) ? $db[$key] : null;
}

function saveUserRecord(array $record): void {
    if (!isset($record['id'])) return;
    $db = loadJsonFile(USERS_DB_FILE);
    $db[(string)$record['id']] = $record;
    saveJsonFile(USERS_DB_FILE, $db);
}

function resetDailyIfNeeded(array $user): array {
    $today = date('Y-m-d');
    if (($user['daily_date'] ?? '') !== $today) {
        $user['daily_date'] = $today;
        $user['daily_count'] = 0;
    }
    return $user;
}

function canUserRequest(int $userId, ?string &$reason = null): bool {
    $settings = loadSettings();
    $user = getUserRecord($userId);
    if ($user === null) { $reason = 'حساب کاربری یافت نشد.'; return false; }
    $user = resetDailyIfNeeded($user);
    $limit = (int)($settings['daily_limit'] ?? 20);
    $cost = (int)($settings['request_cost_points'] ?? 1);
    if ($user['daily_count'] >= $limit) { $reason = 'به محدودیت روزانه رسیدی.'; return false; }
    if ($user['points'] < $cost) { $reason = 'امتیاز کافی نداری.'; return false; }
    return true;
}

function chargeUserForRequest(int $userId): void {
    $settings = loadSettings();
    $cost = (int)($settings['request_cost_points'] ?? 1);
    $user = getUserRecord($userId);
    if ($user === null) return;
    $user = resetDailyIfNeeded($user);
    $user['daily_count'] = (int)$user['daily_count'] + 1;
    $user['total_requests'] = (int)$user['total_requests'] + 1;
    $user['points'] = max(0, (int)$user['points'] - $cost);
    $user['last_seen'] = time();
    saveUserRecord($user);
}

function addUserPoints(int $userId, int $amount): void {
    $user = getUserRecord($userId);
    if ($user === null) return;
    $user['points'] = (int)$user['points'] + max(0, $amount);
    saveUserRecord($user);
}

// ====== Telegram API ======
function tgApi(string $method, array $params = []): array {
	$url = TG_API . BOT_TOKEN . '/' . $method;
	$isMultipart = false;
	foreach ($params as $v) {
		if ($v instanceof CURLFile) { $isMultipart = true; break; }
	}
	$ch = curl_init($url);
	if ($isMultipart) {
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_POSTFIELDS => $params,
		]);
	} else {
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE)
		]);
	}
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($res === false) return ['ok' => false, 'description' => $err ?: 'curl error'];
	$decoded = json_decode($res, true);
	return is_array($decoded) ? $decoded : ['ok' => false, 'description' => 'bad json'];
}

function sendMessage(int $chatId, string $text, array $options = []): array {
	$params = array_merge([
		'chat_id' => $chatId,
		'text' => $text,
		'parse_mode' => 'HTML',
	], $options);
	return tgApi('sendMessage', $params);
}

function buildInlineKeyboard(array $rows): array {
	return ['reply_markup' => json_encode(['inline_keyboard' => $rows], JSON_UNESCAPED_UNICODE)];
}

function sendPhotoUrl(int $chatId, string $url, string $caption = ''): array {
	return tgApi('sendPhoto', [
		'chat_id' => $chatId,
		'photo' => $url,
		'caption' => $caption,
		'parse_mode' => 'HTML',
	]);
}

function sendPhotoFile(int $chatId, string $filePath, string $caption = ''): array {
	$file = new CURLFile($filePath);
	return tgApi('sendPhoto', [
		'chat_id' => $chatId,
		'photo' => $file,
		'caption' => $caption,
		'parse_mode' => 'HTML',
	]);
}

function sendChatAction(int $chatId, string $action): void {
	tgApi('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
}

// ====== UI ======
function mainMenuKeyboard(): array {
	return buildInlineKeyboard([
		[
            ['text' => '🖼 ساخت عکس با هوش مصنوعی', 'callback_data' => 'gen_photo'],
            ['text' => '👤 حساب من', 'callback_data' => 'account'],
		],
        [
            ['text' => '🔼 افزایش کیفیت عکس', 'callback_data' => 'enhance_quality'],
            ['text' => '🎨 لوگوساز', 'callback_data' => 'logo_maker'],
        ],
        [
            ['text' => '🤖 چت با هوش مصنوعی', 'callback_data' => 'ai_chat'],
            ['text' => '🧩 تبدیل به انیمه', 'callback_data' => 'to_anime'],
        ],
        [
            ['text' => 'ℹ️ راهنما', 'callback_data' => 'help'],
            ['text' => '🛠 پنل ادمین', 'callback_data' => 'admin'],
        ],
	]);
}

function sendWelcome(int $chatId): void {
    $txt = "سلام! 👋\nبا من می‌تونی با متن دلخواه، عکس‌های AI بسازی.\nاز منوی شیشه‌ای استفاده کن.";
	sendMessage($chatId, $txt, mainMenuKeyboard());
}

function adminMenuKeyboard(): array {
    return buildInlineKeyboard([
        [
            ['text' => '📊 آمار', 'callback_data' => 'admin_stats'],
            ['text' => '⏳ محدودیت روزانه', 'callback_data' => 'admin_set_daily_limit'],
        ],
        [
            ['text' => '💰 هزینه هر درخواست', 'callback_data' => 'admin_set_cost'],
            ['text' => '➕ اعطای امتیاز', 'callback_data' => 'admin_add_points'],
        ],
        [
            ['text' => '📣 ارسال همگانی (کپی)', 'callback_data' => 'admin_broadcast_copy'],
            ['text' => '🔁 فوروارد همگانی', 'callback_data' => 'admin_broadcast_forward'],
        ],
        [
            ['text' => '⬅️ بازگشت', 'callback_data' => 'back_to_menu'],
        ],
    ]);
}

function buildAdminStatsText(): string {
    $users = loadJsonFile(USERS_FILE);
    $db = loadJsonFile(USERS_DB_FILE);
    $totalUsers = count($users);
    $today = date('Y-m-d');
    $activeToday = 0;
    $requestsToday = 0;
    $weekAgoTs = time() - 7 * 86400;
    $active7d = 0;
    $totalRequests = 0;
    foreach ($db as $uid => $u) {
        if (!is_array($u)) continue;
        $totalRequests += (int)($u['total_requests'] ?? 0);
        if (($u['daily_date'] ?? '') === $today) {
            $requestsToday += (int)($u['daily_count'] ?? 0);
        }
        $last = (int)($u['last_seen'] ?? 0);
        if ($last > 0 && $last >= strtotime($today)) $activeToday++;
        if ($last > 0 && $last >= $weekAgoTs) $active7d++;
    }
    $settings = loadSettings();
    $lines = [];
    $lines[] = '📊 <b>آمار کلی</b>';
    $lines[] = '👥 کاربران: ' . $totalUsers;
    $lines[] = '🗓 فعال امروز: ' . $activeToday . ' | 7 روز اخیر: ' . $active7d;
    $lines[] = '📈 کل درخواست‌ها: ' . $totalRequests;
    $lines[] = '📅 درخواست‌های امروز: ' . $requestsToday;
    $lines[] = '';
    $lines[] = '⚙️ تنظیمات:';
    $lines[] = '⏳ محدودیت روزانه: ' . (int)$settings['daily_limit'];
    $lines[] = '💰 هزینه هر درخواست: ' . (int)$settings['request_cost_points'] . ' امتیاز';
    $lines[] = '🎁 امتیاز اولیه: ' . (int)$settings['initial_points'];
    return implode("\n", $lines);
}

// ====== Fast-Creat Photo API ======
function photoApiRequest(string $text): array {
	$base = 'https://api.fast-creat.ir/gpt/photo';
	$q = [
		'apikey' => FAST_CREAT_PHOTO_APIKEY,
		'text' => $text,
	];
	$qs = [];
	foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
	$url = $base . '?' . implode('&', $qs);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_TIMEOUT => 120,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
	]);
	$res = curl_exec($ch);
	$err = curl_error($ch);
	curl_close($ch);
	if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
	$decoded = json_decode($res, true);
	if (!is_array($decoded)) return ['ok' => true, 'data' => $res]; // sometimes API returns raw URL
	return ['ok' => true, 'data' => $decoded];
}

// ====== Fast-Creat Enhance Quality API ======
function qualityApiRequest(string $imageUrl): array {
    $base = 'https://api.fast-creat.ir/photo-quality';
    $q = [
        'apikey' => FAST_CREAT_QUALITY_APIKEY,
        'url' => $imageUrl,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res]; // sometimes returns direct url
    return ['ok' => true, 'data' => $decoded];
}

// ====== Fast-Creat Logo/Effect API ======
function logoApiRequest(int $id, string $text): array {
    $base = 'https://api.fast-creat.ir/logo';
    $q = [
        'apikey' => FAST_CREAT_LOGO_APIKEY,
        'type' => 'logo',
        'id' => $id,
        'text' => $text,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res];
    return ['ok' => true, 'data' => $decoded];
}

// ====== Fast-Creat GPT Chat API ======
function gptChatApiRequest(string $text): array {
    $base = 'https://api.fast-creat.ir/gpt/gpt4';
    $q = [
        'apikey' => FAST_CREAT_GPT_APIKEY,
        'text' => $text,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res];
    return ['ok' => true, 'data' => $decoded];
}

// ====== Fast-Creat Ghibli (Anime) API ======
function ghibliApiRequest(string $imageUrl): array {
    $base = 'https://api.fast-creat.ir/ghibli';
    $q = [
        'apikey' => FAST_CREAT_GHIBLI_APIKEY,
        'url' => $imageUrl,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res];
    return ['ok' => true, 'data' => $decoded];
}

function gptChatSimpleApiRequest(string $text): array {
    $base = 'https://api.fast-creat.ir/gpt/chat';
    $q = [
        'apikey' => FAST_CREAT_GPT_CHAT_APIKEY,
        'text' => $text,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res];
    return ['ok' => true, 'data' => $decoded];
}

function effectApiRequest(int $id, string $imageUrl): array {
    $base = 'https://api.fast-creat.ir/logo';
    $q = [
        'apikey' => FAST_CREAT_LOGO_APIKEY,
        'type' => 'effect',
        'id' => $id,
        'url' => $imageUrl,
    ];
    $qs = [];
    foreach ($q as $k => $v) $qs[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    $url = $base . '?' . implode('&', $qs);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'error' => $err ?: 'curl error'];
    $decoded = json_decode($res, true);
    if (!is_array($decoded)) return ['ok' => true, 'data' => $res];
    return ['ok' => true, 'data' => $decoded];
}

function extractImagesFromResponse($data): array {
	$results = [];
	if (is_string($data)) {
		$u = trim($data);
		if (strpos($u, 'http') === 0) {
			$results[] = ['kind' => 'url', 'value' => $u];
			return $results;
		}
	}
	if (isset($data['result']) && is_array($data['result'])) $data = $data['result'];
	$walker = function ($node, array $path) use (&$walker, &$results) {
		if (is_array($node)) {
			foreach ($node as $k => $v) $walker($v, array_merge($path, [(string)$k]));
			return;
		}
		if (!is_string($node)) return;
		$val = trim($node);
		if (strpos($val, 'http') === 0) {
			$pth = strtolower(implode('.', $path));
			$pathOk = (strpos($pth, 'url') !== false || strpos($pth, 'image') !== false || strpos($pth, 'photo') !== false);
			$ext = strtolower(parse_url($val, PHP_URL_PATH) ?? '');
			if ($pathOk || preg_match('~\.(png|jpe?g|webp)(?:$|\?)~i', $ext)) {
				$results[] = ['kind' => 'url', 'value' => $val];
			}
			return;
		}
		if (strpos($val, 'data:image/') === 0 && strpos($val, ';base64,') !== false) {
			$results[] = ['kind' => 'datauri', 'value' => $val];
			return;
		}
		// Optional: raw base64 image without data URI
		if (strlen($val) > 800 && preg_match('~^[A-Za-z0-9+/=\r\n]+$~', $val)) {
			$results[] = ['kind' => 'base64', 'mime' => 'image/png', 'value' => $val];
		}
	};
	$walker($data, []);
	// Dedup
	$seen = [];
	$out = [];
	foreach ($results as $r) {
		$key = $r['kind'] . '|' . substr($r['value'], 0, 128);
		if (isset($seen[$key])) continue;
		$seen[$key] = true;
		$out[] = $r;
	}
	return $out;
}

// ====== Normalizers ======
function normalizeIncomingText(string $text): string {
    $t = trim($text);
    // If text is a JSON-like structure with title/text keys, try to extract pure text content
    if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $decoded = json_decode($t, true);
        if (is_array($decoded)) {
            $gather = function ($lines): string {
                $out = [];
                foreach ($lines as $line) {
                    if (!is_string($line)) continue;
                    $line = trim($line);
                    // remove markdown bold/italic/backticks
                    $line = str_replace(['**', '__', '~~', '`'], '', $line);
                    // remove labels like **Prompt 1:**
                    $line = preg_replace('~^\s*(\*{1,3}[^*]+\*{1,3}|[^:]{1,30}:)\s*~u', '', $line);
                    // remove leading bullets/markers
                    $line = preg_replace('~^\s*[-•\d]+[\.)\-\s]*~u', '', $line);
                    // drop parenthetical translations or notes
                    $line = preg_replace('~\([^\)]*\)~u', '', $line);
                    // extract quoted segments if present, else use cleaned line
                    if (preg_match_all('~"([^"\\]*(?:\\.[^"\\]*)*)"~u', $line, $mm) && !empty($mm[1])) {
                        foreach ($mm[1] as $q) {
                            $q = trim($q);
                            if ($q !== '') $out[] = $q;
                        }
                        continue;
                    }
                    // remove wrapping quotes if any remain
                    $line = preg_replace('~^\"|\"$~u', '', $line);
                    $line = trim($line);
                    if ($line !== '') $out[] = $line;
                }
                return trim(implode("\n", $out));
            };

            if (isset($decoded['text'])) {
                if (is_array($decoded['text'])) {
                    return $gather($decoded['text']);
                }
                if (is_string($decoded['text'])) {
                    return $gather([$decoded['text']]);
                }
            }
            // fallback: gather all string leaves
            $collector = [];
            $walker = function ($node) use (&$walker, &$collector) {
                if (is_array($node)) { foreach ($node as $v) $walker($v); return; }
                if (is_string($node)) $collector[] = $node;
            };
            $walker($decoded);
            if ($collector) return $gather($collector);
        }
    }
    // If wrapped in quotes like "text", unwrap
    if (preg_match('~^\s*\"(.+)\"\s*$~us', $t, $m)) {
        return trim($m[1]);
    }
    // remove stray braces/brackets if user pasted raw JSON-ish without being valid JSON
    $t = trim($t, "{}[]");
    return trim($t);
}

function normalizeOutgoingText(string $text): string {
    $t = trim($text);
    // Try JSON decode first
    if ($t !== '' && ($t[0] === '{' || $t[0] === '[')) {
        $decoded = json_decode($t, true);
        if (is_array($decoded)) {
            $extract = function ($node) use (&$extract) {
                if (is_string($node)) return trim($node);
                if (is_array($node)) {
                    if (isset($node['result']) && is_string($node['result'])) return trim($node['result']);
                    if (isset($node['message']) && is_string($node['message'])) return trim($node['message']);
                    if (isset($node['text']) && is_string($node['text'])) return trim($node['text']);
                    foreach ($node as $v) {
                        $r = $extract($v);
                        if ($r !== '') return $r;
                    }
                }
                return '';
            };
            $t = $extract($decoded);
        }
    }
    // Unwrap full-line quotes
    if (preg_match('~^\s*\"(.+)\"\s*$~us', $t, $m)) {
        $t = trim($m[1]);
    }
    // Strip markdown and labels
    $t = str_replace(['**', '__', '~~', '`'], '', $t);
    $t = preg_replace('~^\s*(Answer:|Translation:|Result:|Output:)\s*~iu', '', $t);
    // Remove outer braces/brackets if user/API wrapped
    $t = trim($t, "{}[]");
    return trim($t);
}

function saveDataUriToFile(string $dataUri): ?string {
	if (!preg_match('~^data:(image\/(?:png|jpeg|jpg|webp));base64,(.+)$~i', $dataUri, $m)) return null;
	$mime = strtolower($m[1]);
	$payload = $m[2];
	$ext = $mime === 'image/jpeg' || $mime === 'image/jpg' ? 'jpg' : ($mime === 'image/webp' ? 'webp' : 'png');
	$bin = base64_decode($payload);
	if ($bin === false) return null;
	$path = TMP_DIR . '/' . uniqid('img_', true) . '.' . $ext;
	if (@file_put_contents($path, $bin) === false) return null;
	return $path;
}

function saveBase64ToFile(string $base64, string $ext = 'png'): ?string {
	$bin = base64_decode($base64);
	if ($bin === false) return null;
	$path = TMP_DIR . '/' . uniqid('img_', true) . '.' . $ext;
	if (@file_put_contents($path, $bin) === false) return null;
	return $path;
}

// ====== Handlers ======
function handleGenPhoto(int $chatId, int $userId, string $text): void {
	$prompt = normalizeIncomingText($text);
	if ($prompt === '') {
		sendMessage($chatId, 'لطفاً متن ساخت تصویر را ارسال کن.');
		return;
	}
    $reason = null;
    if (!canUserRequest($userId, $reason)) {
        sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.') . "\nاز دکمه 'حساب من' برای مشاهده محدودیت استفاده کن.");
        return;
    }
	sendChatAction($chatId, 'upload_photo');
	$api = photoApiRequest($prompt);
	if (!$api['ok']) {
		sendMessage($chatId, 'خطا در ارتباط با سرویس.');
		return;
	}
	$data = $api['data'];
	$images = extractImagesFromResponse($data);
	if (!$images) {
		sendMessage($chatId, 'چیزی برای ارسال پیدا نشد. لطفاً متن دیگری امتحان کن.');
		return;
	}
	$sent = 0;
	foreach ($images as $img) {
		if ($sent >= 5) break; // limit
		if ($img['kind'] === 'url') {
			sendPhotoUrl($chatId, $img['value']);
			$sent++;
			usleep(200000);
		} elseif ($img['kind'] === 'datauri') {
			$path = saveDataUriToFile($img['value']);
			if ($path) { sendPhotoFile($chatId, $path); @unlink($path); $sent++; usleep(200000); }
		} elseif ($img['kind'] === 'base64') {
			$path = saveBase64ToFile($img['value']);
			if ($path) { sendPhotoFile($chatId, $path); @unlink($path); $sent++; usleep(200000); }
		}
	}
    chargeUserForRequest($userId);
	setUserState($userId, null);
}

function handleEnhanceQuality(int $chatId, int $userId, string $text): void {
    if (!preg_match('~https?://[^\s]+~u', $text, $m)) {
        sendMessage($chatId, 'لطفاً لینک معتبر عکس را ارسال کن.');
        return;
    }
    $reason = null;
    if (!canUserRequest($userId, $reason)) {
        sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.') . "\nاز دکمه 'حساب من' برای مشاهده محدودیت استفاده کن.");
        return;
    }
    $url = trim($m[0], "<>()[]{}\t\n\r ");
    sendChatAction($chatId, 'upload_photo');
    $api = qualityApiRequest($url);
    if (!$api['ok']) { sendMessage($chatId, 'خطا در ارتباط با سرویس.'); return; }
    $data = $api['data'];
    $images = extractImagesFromResponse($data);
    if (!$images) { sendMessage($chatId, 'چیزی برای ارسال پیدا نشد.'); return; }
    $sent = 0;
    foreach ($images as $img) {
        if ($sent >= 3) break;
        if ($img['kind'] === 'url') {
            sendPhotoUrl($chatId, $img['value'], 'نتیجه افزایش کیفیت');
            $sent++;
            usleep(200000);
        } elseif ($img['kind'] === 'datauri') {
            $path = saveDataUriToFile($img['value']);
            if ($path) { sendPhotoFile($chatId, $path, 'نتیجه افزایش کیفیت'); @unlink($path); $sent++; usleep(200000); }
        } elseif ($img['kind'] === 'base64') {
            $path = saveBase64ToFile($img['value']);
            if ($path) { sendPhotoFile($chatId, $path, 'نتیجه افزایش کیفیت'); @unlink($path); $sent++; usleep(200000); }
        }
    }
    chargeUserForRequest($userId);
    setUserState($userId, null);
}

function handleLogoMake(int $chatId, int $userId, string $text): void {
    // Expect: id text...  (id between 1..140)
    $parts = preg_split('~\s+~u', trim($text), 2);
    if (count($parts) < 2) { sendMessage($chatId, 'فرمت: <b>id text</b>\nمثال: 12 Fast Creat'); return; }
    $id = (int)$parts[0];
    $name = trim($parts[1]);
    if ($id < 1 || $id > 140 || $name === '') { sendMessage($chatId, 'شناسه باید بین 1 تا 140 و متن نباید خالی باشد.'); return; }
    $reason = null;
    if (!canUserRequest($userId, $reason)) { sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.')); return; }
    sendChatAction($chatId, 'upload_photo');
    $api = logoApiRequest($id, $name);
    if (!$api['ok']) { sendMessage($chatId, 'خطا در ارتباط با سرویس.'); return; }
    $data = $api['data'];
    $images = extractImagesFromResponse($data);
    if (!$images) { sendMessage($chatId, 'چیزی برای ارسال پیدا نشد.'); return; }
    $sent = 0;
    foreach ($images as $img) {
        if ($sent >= 3) break;
        if ($img['kind'] === 'url') { sendPhotoUrl($chatId, $img['value'], 'لوگوی ساخته‌شده'); $sent++; usleep(200000); }
        elseif ($img['kind'] === 'datauri') { $p = saveDataUriToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'لوگوی ساخته‌شده'); @unlink($p); $sent++; usleep(200000);} }
        elseif ($img['kind'] === 'base64') { $p = saveBase64ToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'لوگوی ساخته‌شده'); @unlink($p); $sent++; usleep(200000);} }
    }
    chargeUserForRequest($userId);
    setUserState($userId, null);
}

function handleEffectMake(int $chatId, int $userId, string $text): void {
    // Expect: id url
    if (!preg_match('~^(\d{1,3})\s+(https?://\S+)~u', trim($text), $m)) { sendMessage($chatId, 'فرمت: <b>id url</b>\nمثال: 5 https://site/image.jpg'); return; }
    $id = (int)$m[1];
    $url = trim($m[2]);
    if ($id < 1 || $id > 80) { sendMessage($chatId, 'شناسه افکت باید بین 1 تا 80 باشد.'); return; }
    $reason = null;
    if (!canUserRequest($userId, $reason)) { sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.')); return; }
    sendChatAction($chatId, 'upload_photo');
    $api = effectApiRequest($id, $url);
    if (!$api['ok']) { sendMessage($chatId, 'خطا در ارتباط با سرویس.'); return; }
    $data = $api['data'];
    $images = extractImagesFromResponse($data);
    if (!$images) { sendMessage($chatId, 'چیزی برای ارسال پیدا نشد.'); return; }
    $sent = 0;
    foreach ($images as $img) {
        if ($sent >= 3) break;
        if ($img['kind'] === 'url') { sendPhotoUrl($chatId, $img['value'], 'نتیجه افکت'); $sent++; usleep(200000); }
        elseif ($img['kind'] === 'datauri') { $p = saveDataUriToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'نتیجه افکت'); @unlink($p); $sent++; usleep(200000);} }
        elseif ($img['kind'] === 'base64') { $p = saveBase64ToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'نتیجه افکت'); @unlink($p); $sent++; usleep(200000);} }
    }
    chargeUserForRequest($userId);
    setUserState($userId, null);
}

function handleAiChat(int $chatId, int $userId, string $text): void {
	$prompt = normalizeIncomingText($text);
    if ($prompt === '') { sendMessage($chatId, 'پیامت را بفرست تا پاسخ بدهم.'); return; }
    $reason = null;
    if (!canUserRequest($userId, $reason)) { sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.')); return; }
    sendChatAction($chatId, 'typing');
    // try simple chat first, then fallback to gpt4 endpoint
    $api = gptChatSimpleApiRequest($prompt);
    if (!$api['ok']) { $api = gptChatApiRequest($prompt); }
    if (!$api['ok']) { sendMessage($chatId, 'خطا در ارتباط با سرویس.'); return; }
    $data = $api['data'];
    $reply = null;
    if (is_string($data)) { $reply = $data; }
    elseif (isset($data['result'])) { $reply = is_string($data['result']) ? $data['result'] : json_encode($data['result'], JSON_UNESCAPED_UNICODE); }
    elseif (isset($data['message'])) { $reply = is_string($data['message']) ? $data['message'] : json_encode($data['message'], JSON_UNESCAPED_UNICODE); }
    if (!$reply) { $reply = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); }
    sendMessage($chatId, normalizeOutgoingText((string)$reply));
    chargeUserForRequest($userId);
    setUserState($userId, 'await_ai_chat');
}

function handleToAnime(int $chatId, int $userId, string $text): void {
    if (!preg_match('~https?://[^\s]+~u', $text, $m)) { sendMessage($chatId, 'لطفاً لینک معتبر عکس را ارسال کن.'); return; }
    $reason = null;
    if (!canUserRequest($userId, $reason)) { sendMessage($chatId, '⛔️ ' . ($reason ?? 'محدودیت اعمال شده است.')); return; }
    $url = trim($m[0], "<>()[]{}\t\n\r ");
    sendChatAction($chatId, 'upload_photo');
    $api = ghibliApiRequest($url);
    if (!$api['ok']) { sendMessage($chatId, 'خطا در ارتباط با سرویس.'); return; }
    $data = $api['data'];
    $images = extractImagesFromResponse($data);
    if (!$images) { sendMessage($chatId, 'چیزی برای ارسال پیدا نشد.'); return; }
    $sent = 0;
    foreach ($images as $img) {
        if ($sent >= 3) break;
        if ($img['kind'] === 'url') { sendPhotoUrl($chatId, $img['value'], 'نسخه انیمه'); $sent++; usleep(200000); }
        elseif ($img['kind'] === 'datauri') { $p = saveDataUriToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'نسخه انیمه'); @unlink($p); $sent++; usleep(200000);} }
        elseif ($img['kind'] === 'base64') { $p = saveBase64ToFile($img['value']); if ($p) { sendPhotoFile($chatId, $p, 'نسخه انیمه'); @unlink($p); $sent++; usleep(200000);} }
    }
    chargeUserForRequest($userId);
    setUserState($userId, null);
}

// ====== Router ======
$input = file_get_contents('php://input');
if ($input === false || $input === '') exit('OK');
$update = json_decode($input, true);
if (!is_array($update)) exit('OK');

if (isset($update['message'])) {
	$msg = $update['message'];
	$chatId = (int)($msg['chat']['id'] ?? 0);
	$fromId = (int)($msg['from']['id'] ?? 0);
	$text = isset($msg['text']) ? trim($msg['text']) : '';
	// Normalize any JSON-like text inputs globally to plain text
	if ($text !== '') { $text = normalizeIncomingText($text); }
	registerUser($fromId);

	if ($text === '/start') {
		setUserState($fromId, null);
		sendWelcome($chatId);
		exit('OK');
	}
    if ($text === '/help') {
		sendMessage($chatId, "متن دلخواهت رو بفرست تا عکس برات بسازم. همچنین می‌تونی از منوی شیشه‌ای استفاده کنی.", mainMenuKeyboard());
		exit('OK');
	}
    if ($text === '/admin') {
        if ($fromId !== ADMIN_ID) { sendMessage($chatId, 'دسترسی غیرمجاز.'); exit('OK'); }
        sendMessage($chatId, '🛠 پنل ادمین:', adminMenuKeyboard());
        exit('OK');
    }
    if ($text === '/logo') {
        setUserState($fromId, 'await_logo');
        sendMessage($chatId, "لوگوساز: به صورت 'id text' ارسال کن. (id بین 1 تا 140)");
        exit('OK');
    }
    if ($text === '/effect') {
        setUserState($fromId, 'await_effect');
        sendMessage($chatId, "افکت: به صورت 'id url' ارسال کن. (id بین 1 تا 80)");
        exit('OK');
    }
    if ($text === '/anime') {
        setUserState($fromId, 'await_to_anime');
        sendMessage($chatId, 'لینک عکس را برای تبدیل به انیمه بفرست.');
        exit('OK');
    }
    if ($text === '/chat') {
        setUserState($fromId, 'await_ai_chat');
        sendMessage($chatId, 'پیامت را بفرست تا پاسخ بدهم.');
        exit('OK');
    }
    if ($text === '/enhance') {
        setUserState($fromId, 'await_quality_url');
        sendMessage($chatId, 'لینک عکس را برای افزایش کیفیت بفرست.');
        exit('OK');
    }

	$state = getUserState($fromId);
    if ($state === 'await_photo_text') {
		handleGenPhoto($chatId, $fromId, $text);
		exit('OK');
	}
    if ($state === 'await_logo') {
        handleLogoMake($chatId, $fromId, $text);
        exit('OK');
    }
    if ($state === 'await_effect') {
        handleEffectMake($chatId, $fromId, $text);
        exit('OK');
    }
    if ($state === 'await_to_anime') {
        handleToAnime($chatId, $fromId, $text);
        exit('OK');
    }
    if ($state === 'await_ai_chat') {
        handleAiChat($chatId, $fromId, $text);
        exit('OK');
    }
    if ($state === 'await_quality_url') {
        handleEnhanceQuality($chatId, $fromId, $text);
        exit('OK');
    }
    if ($fromId === ADMIN_ID && $state === 'await_set_daily_limit') {
        $val = (int)filter_var($text, FILTER_SANITIZE_NUMBER_INT);
        if ($val <= 0) { sendMessage($chatId, 'عدد معتبر ارسال کن.'); exit('OK'); }
        saveSettings(['daily_limit' => $val]);
        sendMessage($chatId, '✅ محدودیت روزانه روی ' . $val . ' تنظیم شد.', adminMenuKeyboard());
        setUserState($fromId, null);
        exit('OK');
    }
    if ($fromId === ADMIN_ID && $state === 'await_set_cost') {
        $val = (int)filter_var($text, FILTER_SANITIZE_NUMBER_INT);
        if ($val < 0) { sendMessage($chatId, 'عدد معتبر ارسال کن.'); exit('OK'); }
        saveSettings(['request_cost_points' => $val]);
        sendMessage($chatId, '✅ هزینه هر درخواست: ' . $val . ' امتیاز.', adminMenuKeyboard());
        setUserState($fromId, null);
        exit('OK');
    }
    if ($fromId === ADMIN_ID && $state === 'await_add_points') {
        // format: "userId amount"
        $parts = preg_split('~\s+~u', trim($text));
        if (count($parts) < 2) { sendMessage($chatId, 'فرمت: user_id amount'); exit('OK'); }
        $uid = (int)$parts[0];
        $amount = (int)$parts[1];
        if ($uid <= 0 || $amount === 0) { sendMessage($chatId, 'ورودی نامعتبر.'); exit('OK'); }
        addUserPoints($uid, $amount);
        sendMessage($chatId, '✅ به کاربر ' . $uid . ' مقدار ' . $amount . ' امتیاز اضافه شد.', adminMenuKeyboard());
        setUserState($fromId, null);
        exit('OK');
    }

	// Default: show menu
	sendWelcome($chatId);
	exit('OK');
}

if (isset($update['callback_query'])) {
	$cb = $update['callback_query'];
	$data = (string)($cb['data'] ?? '');
	$fromId = (int)($cb['from']['id'] ?? 0);
	$chatId = (int)($cb['message']['chat']['id'] ?? 0);
	$messageId = (int)($cb['message']['message_id'] ?? 0);
	$cbId = (string)($cb['id'] ?? '');

	if ($data === 'gen_photo') {
		setUserState($fromId, 'await_photo_text');
		tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'متن ساخت تصویر را بفرستید']);
		tgApi('editMessageText', [
			'chat_id' => $chatId,
			'message_id' => $messageId,
			'text' => 'لطفاً متن موردنظر برای ساخت تصویر را بفرستید:',
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
			'reply_markup' => mainMenuKeyboard()['reply_markup'],
		]);
		exit('OK');
	}
    if ($data === 'account') {
        $user = getUserRecord($fromId);
        if ($user !== null) $user = resetDailyIfNeeded($user);
        $settings = loadSettings();
        $limit = (int)$settings['daily_limit'];
        $cost = (int)$settings['request_cost_points'];
        $remaining = $limit - (int)($user['daily_count'] ?? 0);
        $remaining = max(0, $remaining);
        $txt = "👤 حساب شما\n" .
            '💰 امتیاز: ' . (int)($user['points'] ?? 0) . "\n" .
            '⏳ باقیمانده امروز: ' . $remaining . ' از ' . $limit . "\n" .
            '💳 هزینه هر درخواست: ' . $cost . ' امتیاز';
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $txt,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => mainMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'ai_chat') {
        setUserState($fromId, 'await_ai_chat');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'پیامت را بفرست تا پاسخ بدهم.',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => mainMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'to_anime') {
        setUserState($fromId, 'await_to_anime');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'لینک عکس را بفرستید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'لطفاً لینک عکس موردنظر را برای تبدیل به انیمه ارسال کنید:',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => mainMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'logo_maker') {
        setUserState($fromId, 'await_logo');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => "فرمت: id text (id: 1..140)"]);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => "لوگوساز: به صورت 'id text' ارسال کن. (id بین 1 تا 140)",
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => mainMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'enhance_quality') {
        setUserState($fromId, 'await_quality_url');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'لینک عکس را بفرستید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'لطفاً لینک عکس موردنظر برای افزایش کیفیت را ارسال کنید:',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => mainMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
	if ($data === 'help') {
		tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
		tgApi('editMessageText', [
			'chat_id' => $chatId,
			'message_id' => $messageId,
			'text' => "راهنما:\n- دکمه 'ساخت عکس' را بزنید و متن خود را ارسال کنید.\n- تا ۵ عکس برای شما ارسال می‌شود.",
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
			'reply_markup' => mainMenuKeyboard()['reply_markup'],
		]);
		exit('OK');
	}
    if ($data === 'admin') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'دسترسی غیرمجاز', 'show_alert' => true]); exit('OK'); }
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => '🛠 پنل ادمین:',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_stats') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        $txt = buildAdminStatsText();
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $txt,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_set_daily_limit') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        setUserState($fromId, 'await_set_daily_limit');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'عدد محدودیت روزانه را بفرستید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'محدودیت روزانه چند تا باشد؟ (فقط عدد)',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_set_cost') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        setUserState($fromId, 'await_set_cost');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'هزینه هر درخواست (امتیاز) را بفرستید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'هزینه هر درخواست چند امتیاز باشد؟ (فقط عدد)',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_add_points') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        setUserState($fromId, 'await_add_points');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'فرمت: user_id amount']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'برای اعطای امتیاز، به صورت "user_id amount" ارسال کنید.',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_broadcast_copy') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        setUserState($fromId, 'await_broadcast_copy');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'پیام را ارسال کنید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'هر پیامی بفرستید تا به صورت کپی برای همه ارسال شود.',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
    if ($data === 'admin_broadcast_forward') {
        if ($fromId !== ADMIN_ID) { tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'No access', 'show_alert' => true]); exit('OK'); }
        setUserState($fromId, 'await_broadcast_forward');
        tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => 'پیام را ارسال کنید']);
        tgApi('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => 'هر پیامی بفرستید تا به صورت فوروارد برای همه ارسال شود.',
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup' => adminMenuKeyboard()['reply_markup'],
        ]);
        exit('OK');
    }
	tgApi('answerCallbackQuery', ['callback_query_id' => $cbId]);
	exit('OK');
}

exit('OK');

?>


