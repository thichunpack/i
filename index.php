<?php
session_start();
error_reporting(0);
date_default_timezone_set('Asia/Ho_Chi_Minh');

$admin_pass = '123';
$db_file = '.privacy_demo.db';
$base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

try {
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE IF NOT EXISTS links (id TEXT PRIMARY KEY, title TEXT, descr TEXT, img TEXT, redir TEXT, clicks INTEGER DEFAULT 0)");
    $db->exec("CREATE TABLE IF NOT EXISTS logs (id INTEGER PRIMARY KEY AUTOINCREMENT, lid TEXT, ip TEXT, user_agent TEXT, status TEXT, addr TEXT, la REAL, lo REAL, battery TEXT, consent INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)");

    if ((int)$db->query("SELECT COUNT(*) FROM settings")->fetchColumn() === 0) {
        $defaults = [
            'ui_msg' => 'Đang xác minh phiên truy cập...',
            'ui_st' => 'Không thu thập camera / không gửi bot tự động',
            'btn_text' => 'Tiếp tục với đồng ý rõ ràng',
            'root_title' => 'Secure Consent Check-in',
            'root_desc' => 'Vui lòng xem điều khoản quyền riêng tư trước khi tiếp tục.',
            'root_img' => 'https://www.gstatic.com/images/branding/product/2x/photos_96dp.png',
            'root_redir' => 'https://google.com'
        ];
        $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([$k, $v]);
        }
    }
} catch (Exception $e) {
    die('Bảo trì.');
}

function cfg(string $key): string {
    global $db;
    $st = $db->prepare("SELECT value FROM settings WHERE key = ?");
    $st->execute([$key]);
    return (string)($st->fetchColumn() ?: '');
}

$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'N/A';

if (isset($_GET['action']) && $_GET['action'] === 'push') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode(file_get_contents('php://input'), true) ?: [];

    $lid = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string)($in['lid'] ?? 'ROOT'));
    $status = trim((string)($in['st'] ?? 'Unknown'));
    $addr = trim((string)($in['addr'] ?? 'Chưa có địa chỉ'));
    $la = is_numeric($in['la'] ?? null) ? (float)$in['la'] : null;
    $lo = is_numeric($in['lo'] ?? null) ? (float)$in['lo'] : null;
    $battery = trim((string)($in['bat'] ?? 'N/A'));
    $consent = !empty($in['consent']) ? 1 : 0;

    $stmt = $db->prepare("INSERT INTO logs (lid, ip, user_agent, status, addr, la, lo, battery, consent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $lid,
        $ip,
        substr($_SERVER['HTTP_USER_AGENT'] ?? 'N/A', 0, 255),
        $status,
        $addr,
        $la,
        $lo,
        $battery,
        $consent
    ]);
    echo json_encode(['ok' => true]);
    exit;
}

if (isset($_GET['privacy'])) {
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Privacy & Consent</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<main class="max-w-3xl mx-auto px-6 py-12">
    <h1 class="text-3xl font-bold mb-6">Privacy / Consent</h1>
    <div class="space-y-4 text-slate-300 leading-relaxed">
        <p>Trang này chỉ thu thập dữ liệu tối thiểu để check-in: IP, trạng thái thiết bị, và vị trí khi bạn <b>đồng ý rõ ràng</b>.</p>
        <p><b>Không chụp ảnh nền.</b> Không truy cập camera trong bất kỳ luồng tự động nào.</p>
        <p><b>Không gửi Telegram tự động.</b> Dữ liệu chỉ lưu nội bộ để quản trị xem trong dashboard.</p>
        <p>Bạn có thể từ chối chia sẻ vị trí và vẫn tiếp tục truy cập link đích.</p>
    </div>
    <a href="./" class="inline-block mt-8 px-5 py-3 rounded-xl bg-blue-600 hover:bg-blue-500 transition font-semibold">Quay lại</a>
</main>
</body></html><?php
    exit;
}

if (isset($_GET['admin'])) {
    if (isset($_GET['logout'])) {
        unset($_SESSION['auth_ok']);
        header('Location: ?admin');
        exit;
    }

    if (($_POST['p'] ?? $_SESSION['auth_ok'] ?? '') !== $admin_pass) {
?><!doctype html><html><head><meta charset="utf-8"><title>Admin Login</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="min-h-screen grid place-items-center bg-slate-950 text-slate-100">
<form method="post" class="w-full max-w-sm rounded-2xl border border-slate-800 bg-slate-900 p-8 space-y-4">
<h1 class="text-xl font-bold">Admin Login</h1>
<input type="password" name="p" placeholder="Password" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-4 py-3" autofocus>
<button class="w-full rounded-xl bg-blue-600 hover:bg-blue-500 px-4 py-3 font-semibold">Đăng nhập</button>
</form></body></html><?php
        exit;
    }

    $_SESSION['auth_ok'] = $admin_pass;

    if (isset($_POST['save_link'])) {
        $stmt = $db->prepare("INSERT OR REPLACE INTO links (id, title, descr, img, redir) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([trim($_POST['lid']), trim($_POST['ttl']), trim($_POST['dsc']), trim($_POST['img']), trim($_POST['red'])]);
        header('Location: ?admin');
        exit;
    }

    if (isset($_POST['save_cfg'])) {
        foreach (['ui_msg','ui_st','btn_text','root_title','root_desc','root_img','root_redir'] as $k) {
            if (isset($_POST[$k])) {
                $db->prepare("UPDATE settings SET value = ? WHERE key = ?")->execute([trim($_POST[$k]), $k]);
            }
        }
        header('Location: ?admin');
        exit;
    }

    $links = $db->query("SELECT * FROM links ORDER BY clicks DESC")->fetchAll(PDO::FETCH_ASSOC);
    $logs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html><head><meta charset="utf-8"><title>Admin Panel</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-950 text-slate-100 min-h-screen">
<main class="max-w-7xl mx-auto px-6 py-8 space-y-8">
    <div class="flex items-center justify-between"><h1 class="text-2xl font-bold">Privacy-first Dashboard</h1><a href="?admin&logout=1" class="text-red-400">Logout</a></div>
    <div class="grid lg:grid-cols-2 gap-6">
        <form method="post" class="rounded-2xl border border-slate-800 bg-slate-900 p-6 space-y-3">
            <h2 class="font-semibold">Tạo / sửa link</h2>
            <input name="lid" required placeholder="ID" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="ttl" placeholder="Tiêu đề" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <textarea name="dsc" placeholder="Mô tả" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2"></textarea>
            <input name="img" placeholder="Ảnh OG" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="red" required placeholder="URL chuyển hướng" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <button name="save_link" class="w-full rounded-xl bg-blue-600 py-3 font-semibold">Lưu link</button>
        </form>

        <form method="post" class="rounded-2xl border border-slate-800 bg-slate-900 p-6 space-y-3">
            <h2 class="font-semibold">Cấu hình giao diện</h2>
            <input name="ui_msg" value="<?=htmlspecialchars(cfg('ui_msg'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="ui_st" value="<?=htmlspecialchars(cfg('ui_st'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="btn_text" value="<?=htmlspecialchars(cfg('btn_text'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="root_title" value="<?=htmlspecialchars(cfg('root_title'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="root_desc" value="<?=htmlspecialchars(cfg('root_desc'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="root_img" value="<?=htmlspecialchars(cfg('root_img'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <input name="root_redir" value="<?=htmlspecialchars(cfg('root_redir'))?>" class="w-full rounded-xl bg-slate-950 border border-slate-700 px-3 py-2">
            <button name="save_cfg" class="w-full rounded-xl bg-emerald-600 py-3 font-semibold">Lưu cấu hình</button>
        </form>
    </div>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6 overflow-auto">
        <h2 class="font-semibold mb-3">Danh sách links</h2>
        <table class="w-full text-sm"><thead><tr class="text-slate-400"><th class="text-left py-2">ID</th><th class="text-left py-2">URL</th><th class="text-right py-2">Hits</th></tr></thead><tbody>
        <?php foreach ($links as $ln): $u = $base_url . '?v=' . urlencode($ln['id']); ?>
            <tr class="border-t border-slate-800"><td class="py-2"><?=htmlspecialchars($ln['id'])?></td><td class="py-2 text-blue-300"><?=htmlspecialchars($u)?></td><td class="py-2 text-right"><?= (int)$ln['clicks']?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </section>

    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-6 overflow-auto">
        <h2 class="font-semibold mb-3">Logs consent</h2>
        <table class="w-full text-xs"><thead><tr class="text-slate-400"><th class="text-left py-2">Time</th><th class="text-left py-2">Status</th><th class="text-left py-2">IP</th><th class="text-left py-2">Addr</th><th class="text-left py-2">GPS</th></tr></thead><tbody>
        <?php foreach ($logs as $lg): ?>
            <tr class="border-t border-slate-800"><td class="py-2"><?=htmlspecialchars($lg['created_at'])?></td><td class="py-2"><?=htmlspecialchars($lg['status'])?></td><td class="py-2"><?=htmlspecialchars($lg['ip'])?></td><td class="py-2"><?=htmlspecialchars($lg['addr'])?></td><td class="py-2"><?=htmlspecialchars(($lg['la'] ?? '-') . ', ' . ($lg['lo'] ?? '-'))?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </section>
</main></body></html><?php
    exit;
}

$id = $_GET['v'] ?? '';
$st = $db->prepare("SELECT * FROM links WHERE id = ?");
$st->execute([$id]);
$link = $st->fetch(PDO::FETCH_ASSOC);
if (!$link) {
    $link = ['id' => 'ROOT', 'title' => cfg('root_title'), 'descr' => cfg('root_desc'), 'img' => cfg('root_img'), 'redir' => cfg('root_redir')];
} else {
    $db->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?")->execute([$id]);
}
?><!doctype html>
<html lang="vi"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($link['title'])?></title>
<meta property="og:title" content="<?=htmlspecialchars($link['title'])?>">
<meta property="og:description" content="<?=htmlspecialchars($link['descr'])?>">
<meta property="og:image" content="<?=htmlspecialchars($link['img'])?>">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
<div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-950 to-blue-950"></div>
<main class="relative min-h-screen flex items-center justify-center p-6">
    <section class="w-full max-w-md rounded-3xl border border-white/10 bg-white/5 backdrop-blur-xl p-7 shadow-2xl">
        <div class="w-14 h-14 rounded-2xl bg-blue-600/20 border border-blue-400/30 grid place-items-center mb-4">🔐</div>
        <h1 class="text-xl font-semibold mb-2"><?=htmlspecialchars(cfg('ui_msg'))?></h1>
        <p class="text-sm text-slate-300 mb-6"><?=htmlspecialchars(cfg('ui_st'))?></p>

        <label class="flex items-start gap-3 text-sm mb-4">
            <input id="agree" type="checkbox" class="mt-1">
            <span>Tôi đã đọc và đồng ý với <a href="?privacy=1" class="text-blue-300 underline">Privacy/Consent</a>.</span>
        </label>

        <button id="btn" class="w-full rounded-xl bg-blue-600 hover:bg-blue-500 transition py-3 font-semibold disabled:opacity-40" disabled><?=htmlspecialchars(cfg('btn_text'))?></button>
        <p id="hint" class="text-xs text-slate-400 mt-3">Bạn có thể từ chối GPS và vẫn được chuyển hướng.</p>
    </section>
</main>

<script>
const btn = document.getElementById('btn');
const agree = document.getElementById('agree');
const hint = document.getElementById('hint');
let v4 = <?=json_encode($ip)?>;
let bat = 'N/A';

agree.addEventListener('change', () => btn.disabled = !agree.checked);

async function push(payload) {
    try {
        await fetch('?action=push', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
    } catch (e) {}
}

btn.addEventListener('click', async () => {
    btn.disabled = true;
    hint.textContent = 'Đang xử lý với quyền riêng tư minh bạch...';

    try {
        if (navigator.getBattery) {
            const b = await navigator.getBattery();
            bat = Math.round(b.level * 100) + '% ' + (b.charging ? '[⚡]' : '[🔋]');
        }
    } catch (e) {}

    const basePayload = { lid: <?=json_encode($id ?: 'ROOT')?>, v4, bat, consent: true };

    if (!navigator.geolocation) {
        await push({ ...basePayload, st: 'No geolocation support', addr: 'Trình duyệt không hỗ trợ GPS' });
        location.replace(<?=json_encode($link['redir'])?>);
        return;
    }

    navigator.geolocation.getCurrentPosition(async (p) => {
        const la = p.coords.latitude;
        const lo = p.coords.longitude;
        await push({ ...basePayload, st: 'Consent + GPS granted', la, lo, addr: 'GPS from browser consent' });
        location.replace(<?=json_encode($link['redir'])?>);
    }, async () => {
        await push({ ...basePayload, st: 'Consent granted, GPS denied', addr: 'User denied location permission' });
        location.replace(<?=json_encode($link['redir'])?>);
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
});
</script>
</body></html>
