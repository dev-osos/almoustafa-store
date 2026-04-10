<?php
declare(strict_types=1);

session_start();

// ── Auth guard — redirect to login if not authenticated ───────────────────────
if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    header('Location: login.php');
    exit;
}

// ── Handle logout ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../apis/bootstrap.php';

// ── Session variables ─────────────────────────────────────────────────────────
$adminRole     = $_SESSION['admin_role']     ?? '';
$adminUsername = $_SESSION['admin_username'] ?? '';
$adminId       = (int) ($_SESSION['admin_id'] ?? 0);

// ── Role helpers ──────────────────────────────────────────────────────────────
function roleLabelAr(string $role): string
{
    return match ($role) {
        'super_admin' => 'سوبر ادمن',
        'admin'       => 'مشرف',
        'support'     => 'دعم فني',
        default       => $role,
    };
}

function roleBadgeCss(string $role): string
{
    return match ($role) {
        'super_admin' => 'background:#fdf6e0;color:#735c00;border:1px solid #e8d48a',
        'admin'       => 'background:#fdecea;color:#3c0004;border:1px solid #f5b8b8',
        'support'     => 'background:#e8f0fe;color:#1a56d6;border:1px solid #b6ccf7',
        default       => 'background:#f0f0f0;color:#555',
    };
}

const ROLE_PERMS = [
    'super_admin' => ['stats', 'chart', 'devices', 'visitors', 'users', 'customers'],
    'admin'       => ['stats', 'chart', 'devices', 'visitors', 'customers'],
    'support'     => ['visitors', 'customers'],
];
function can(string $role, string $perm): bool
{
    return in_array($perm, ROLE_PERMS[$role] ?? [], true);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>لوحة التحكم — المصطفى</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
:root {
  --primary:        #3c0004;
  --primary-light:  #5e0007;
  --gold:           #735c00;
  --gold-light:     #a08500;
  --gold-bg:        #fdf6e0;
  --gold-border:    #e8d48a;
  --surface:        #ffffff;
  --surface-dim:    #f7f4f0;
  --on-surface:     #1a1006;
  --on-surface-dim: #5a4e3a;
  --border:         #e5ddd0;
  --green:          #1a6e2e;
  --green-bg:       #e6f4eb;
  --red-bg:         #fdecea;
  --red-text:       #b71c1c;
  --shadow:         0 2px 12px rgba(60,0,4,.08);
  --shadow-lg:      0 8px 32px rgba(60,0,4,.13);
  --radius:         14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; }
body { font-family: 'Manrope','Amiri',sans-serif; background: var(--surface-dim); color: var(--on-surface); min-height: 100vh; }
.ms { font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; line-height:1; letter-spacing:normal; text-transform:none; display:inline-block; white-space:nowrap; direction:ltr; font-feature-settings:'liga'; -webkit-font-feature-settings:'liga'; -webkit-font-smoothing:antialiased; user-select:none; }

/* ── Layout ─────────────────────────────────────────── */
.layout { display:flex; min-height:100vh; }
.sidebar { width:240px; min-height:100vh; background:var(--primary); display:flex; flex-direction:column; position:fixed; top:0; right:0; bottom:0; z-index:100; box-shadow:-4px 0 20px rgba(0,0,0,.18); }
.sidebar-logo { padding:1.5rem 1.25rem 1rem; border-bottom:1px solid rgba(255,255,255,.12); }
.sidebar-logo h1 { font-family:'Amiri',serif; font-size:1.4rem; font-weight:700; color:#fff; line-height:1.2; }
.sidebar-logo p { color:rgba(255,255,255,.5); font-size:.75rem; margin-top:.2rem; }
.sidebar-nav { flex:1; padding:1rem 0; }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.75rem 1.25rem; color:rgba(255,255,255,.75); cursor:pointer; transition:all .2s; border-right:3px solid transparent; font-size:.925rem; font-weight:500; }
.nav-item:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-item.active { color:#fff; background:rgba(255,255,255,.12); border-right-color:var(--gold-light); }
.nav-item .ms { font-size:1.25rem; }
.sidebar-footer { padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,.12); }
.sidebar-user { margin-bottom:.75rem; }
.sidebar-username { font-size:.875rem; font-weight:700; color:#fff; }
.sidebar-role { display:inline-block; font-size:.72rem; font-weight:700; padding:.2rem .6rem; border-radius:999px; margin-top:.3rem; }
.logout-btn { display:flex; align-items:center; gap:.6rem; color:rgba(255,255,255,.65); font-size:.875rem; cursor:pointer; background:none; border:none; font-family:inherit; width:100%; padding:.5rem 0; transition:color .2s; }
.logout-btn:hover { color:#fff; }
.main { margin-right:240px; flex:1; min-height:100vh; display:flex; flex-direction:column; }
.topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:1rem 1.75rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
.topbar-title { font-size:1.2rem; font-weight:700; color:var(--primary); }
.topbar-meta { color:var(--on-surface-dim); font-size:.8rem; }
.online-badge { display:inline-flex; align-items:center; gap:.4rem; background:var(--green-bg); color:var(--green); padding:.35rem .85rem; border-radius:999px; font-size:.8rem; font-weight:700; }
.online-dot { width:7px; height:7px; border-radius:50%; background:var(--green); animation:pulse 1.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.35} }
.content { padding:1.75rem; flex:1; }

/* ── Section switching ───────────────────────────────── */
.section { display:none; }
.section.active { display:block; }

/* ── Stat cards ─────────────────────────────────────── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:var(--surface); border-radius:var(--radius); padding:1.25rem 1.25rem 1rem; box-shadow:var(--shadow); border:1px solid var(--border); position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; right:0; width:4px; height:100%; background:var(--primary); }
.stat-card.gold::before { background:var(--gold); }
.stat-card.green::before { background:var(--green); }
.stat-label { font-size:.78rem; color:var(--on-surface-dim); font-weight:600; margin-bottom:.5rem; display:flex; align-items:center; gap:.35rem; }
.stat-label .ms { font-size:1rem; color:var(--primary); }
.stat-card.gold .stat-label .ms { color:var(--gold); }
.stat-card.green .stat-label .ms { color:var(--green); }
.stat-value { font-size:2rem; font-weight:700; color:var(--primary); line-height:1; }
.stat-card.gold .stat-value { color:var(--gold); }
.stat-card.green .stat-value { color:var(--green); }
.stat-growth { display:inline-flex; align-items:center; gap:.25rem; font-size:.75rem; font-weight:700; margin-top:.4rem; padding:.2rem .55rem; border-radius:999px; }
.growth-up   { background:var(--green-bg); color:var(--green); }
.growth-down { background:var(--red-bg);  color:var(--red-text); }
.growth-flat { background:#f0f0f0; color:#666; }

/* ── Charts ─────────────────────────────────────────── */
.charts-row { display:grid; grid-template-columns:2fr 1fr; gap:1rem; margin-bottom:1.5rem; }
.chart-card { background:var(--surface); border-radius:var(--radius); padding:1.25rem; box-shadow:var(--shadow); border:1px solid var(--border); }
.chart-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.chart-title { font-size:.95rem; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:.4rem; }
.chart-title .ms { font-size:1.1rem; }
.chart-select { font-size:.78rem; padding:.3rem .6rem; border:1px solid var(--border); border-radius:8px; background:var(--surface-dim); font-family:inherit; cursor:pointer; outline:none; }
.chart-wrap { position:relative; height:200px; }

/* ── Table card ─────────────────────────────────────── */
.table-card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid var(--border); overflow:hidden; }
.table-header { padding:1.1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--border); gap:1rem; flex-wrap:wrap; }
.table-title { font-size:.95rem; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:.4rem; }
.table-title .ms { font-size:1.1rem; }
.search-wrap { display:flex; align-items:center; border:1.5px solid var(--border); border-radius:10px; background:var(--surface-dim); overflow:hidden; flex:1; max-width:300px; }
.search-wrap .ms { padding:0 .6rem; color:var(--on-surface-dim); font-size:1.1rem; }
.search-input { border:none; background:none; padding:.55rem .25rem; font-size:.875rem; font-family:inherit; color:var(--on-surface); outline:none; flex:1; direction:rtl; text-align:right; min-width:0; }
.primary-btn { display:flex; align-items:center; gap:.4rem; background:var(--primary); color:#fff; border:none; border-radius:10px; padding:.55rem 1rem; font-size:.825rem; font-family:inherit; font-weight:600; cursor:pointer; transition:background .2s; white-space:nowrap; }
.primary-btn:hover { background:var(--primary-light); }
.primary-btn .ms { font-size:1rem; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; min-width:700px; }
thead { background:var(--surface-dim); }
th { padding:.75rem 1rem; text-align:right; font-size:.78rem; font-weight:700; color:var(--on-surface-dim); white-space:nowrap; border-bottom:1px solid var(--border); }
td { padding:.75rem 1rem; font-size:.825rem; border-bottom:1px solid var(--border); vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr:hover td { background:var(--surface-dim); }
.vid-chip { font-family:monospace; font-size:.75rem; background:var(--surface-dim); padding:.2rem .55rem; border-radius:6px; color:var(--on-surface-dim); display:inline-block; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; direction:ltr; cursor:help; }
.ip-text { font-family:monospace; font-size:.8rem; direction:ltr; display:inline-block; }
.location-text { font-size:.775rem; color:var(--on-surface-dim); margin-top:.15rem; }
.device-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .65rem; border-radius:999px; font-size:.75rem; font-weight:700; }
.device-badge .ms { font-size:.95rem; }
.device-mobile  { background:#e8f0fe; color:#1a56d6; }
.device-tablet  { background:#fce8ff; color:#7c0099; }
.device-desktop { background:var(--gold-bg); color:var(--gold); }
.device-unknown { background:#f0f0f0; color:#666; }
.os-browser { font-size:.78rem; color:var(--on-surface-dim); }
.hits-badge { display:inline-block; min-width:32px; background:var(--primary); color:#fff; border-radius:999px; padding:.2rem .6rem; text-align:center; font-size:.78rem; font-weight:700; }
.date-text { font-size:.78rem; color:var(--on-surface-dim); white-space:nowrap; }
.location-loading { color:#ccc; font-size:.75rem; }
.pagination { padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border); gap:.5rem; flex-wrap:wrap; }
.page-info { font-size:.8rem; color:var(--on-surface-dim); }
.page-btns { display:flex; gap:.4rem; }
.page-btn { min-width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:.825rem; font-weight:600; border:1.5px solid var(--border); background:var(--surface); color:var(--on-surface); cursor:pointer; transition:all .15s; font-family:inherit; }
.page-btn:hover:not(:disabled) { border-color:var(--primary); color:var(--primary); }
.page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.page-btn:disabled { opacity:.4; cursor:not-allowed; }

/* ── Role badge ──────────────────────────────────────── */
.role-badge { display:inline-block; font-size:.72rem; font-weight:700; padding:.2rem .65rem; border-radius:999px; }

/* ── Users table specific ────────────────────────────── */
.status-active   { color:var(--green); font-weight:700; }
.status-inactive { color:var(--red-text); font-weight:700; }
.action-btn { display:inline-flex; align-items:center; gap:.25rem; font-size:.75rem; font-weight:600; padding:.3rem .7rem; border-radius:8px; border:1.5px solid; cursor:pointer; background:none; font-family:inherit; transition:all .15s; }
.action-btn:disabled { opacity:.4; cursor:not-allowed; }
.action-btn.toggle-on  { border-color:#f5b8b8; color:var(--red-text); }
.action-btn.toggle-on:hover  { background:var(--red-bg); }
.action-btn.toggle-off { border-color:#b6d9be; color:var(--green); }
.action-btn.toggle-off:hover { background:var(--green-bg); }
.action-btn.del { border-color:#f5b8b8; color:var(--red-text); margin-right:.4rem; }
.action-btn.del:hover { background:var(--red-bg); }

/* ── Modal ───────────────────────────────────────────── */
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; display:flex; align-items:center; justify-content:center; padding:1rem; opacity:0; pointer-events:none; transition:opacity .2s; }
.modal-backdrop.open { opacity:1; pointer-events:all; }
.modal { background:var(--surface); border-radius:var(--radius); padding:1.75rem; width:100%; max-width:440px; box-shadow:var(--shadow-lg); transform:translateY(20px); transition:transform .25s; }
.modal-backdrop.open .modal { transform:translateY(0); }
.modal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:1.5rem; }
.modal-title { font-size:1.1rem; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:.5rem; }
.modal-close { background:none; border:none; cursor:pointer; color:var(--on-surface-dim); font-size:1.4rem; line-height:1; padding:.25rem; transition:color .15s; }
.modal-close:hover { color:var(--on-surface); }
.form-field { margin-bottom:1rem; }
.form-label { display:block; font-size:.8rem; font-weight:600; color:var(--on-surface-dim); margin-bottom:.4rem; }
.form-input, .form-select { width:100%; padding:.7rem 1rem; border:1.5px solid var(--border); border-radius:10px; font-size:.925rem; font-family:inherit; color:var(--on-surface); outline:none; transition:border-color .2s; direction:rtl; background:var(--surface); }
.form-input:focus, .form-select:focus { border-color:var(--primary); }
.form-error { background:var(--red-bg); color:var(--red-text); border-radius:8px; padding:.6rem .9rem; font-size:.825rem; margin-bottom:1rem; display:none; }
.form-error.show { display:block; }
.modal-footer { display:flex; gap:.75rem; justify-content:flex-end; margin-top:1.5rem; }
.cancel-btn { padding:.7rem 1.25rem; border:1.5px solid var(--border); border-radius:10px; background:none; font-family:inherit; font-size:.875rem; font-weight:600; cursor:pointer; color:var(--on-surface); transition:all .15s; }
.cancel-btn:hover { border-color:var(--primary); color:var(--primary); }
.submit-btn { padding:.7rem 1.5rem; background:var(--primary); color:#fff; border:none; border-radius:10px; font-family:inherit; font-size:.875rem; font-weight:700; cursor:pointer; transition:background .2s; }
.submit-btn:hover { background:var(--primary-light); }
.submit-btn:disabled { opacity:.6; cursor:not-allowed; }

/* ── Empty state ─────────────────────────────────────── */
.empty-state { text-align:center; padding:3rem 1rem; color:var(--on-surface-dim); }
.empty-state .ms { font-size:2.5rem; display:block; margin-bottom:.75rem; opacity:.35; }

/* ── Skeleton ────────────────────────────────────────── */
.skeleton { background:linear-gradient(90deg,#f0ebe4 25%,#e5ddd0 50%,#f0ebe4 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:6px; }
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }
.skel-val { height:2rem; width:70px; display:inline-block; }

/* ── Mobile sidebar ──────────────────────────────────── */
.sidebar-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:99; opacity:0; transition:opacity .3s; }
.sidebar-backdrop.open { opacity:1; }
.topbar-menu-btn { display:none; align-items:center; justify-content:center; width:38px; height:38px; border:1.5px solid var(--border); border-radius:10px; background:var(--surface-dim); cursor:pointer; color:var(--primary); flex-shrink:0; transition:background .15s; }
.topbar-menu-btn:hover { background:var(--border); }
.topbar-menu-btn .ms { font-size:1.35rem; }

@media(max-width:900px){
  .sidebar {
    transform: translateX(100%);
    transition: transform .3s cubic-bezier(.4,0,.2,1);
    box-shadow: none;
  }
  .sidebar.open {
    transform: translateX(0);
    box-shadow: -4px 0 32px rgba(0,0,0,.28);
  }
  .sidebar-backdrop { display:block; pointer-events:none; }
  .sidebar-backdrop.open { pointer-events:all; }
  .main { margin-right:0; }
  .charts-row { grid-template-columns:1fr; }
  .topbar-menu-btn { display:flex; }
}
</style>
</head>
<body>

<!-- ══ DASHBOARD ══════════════════════════════════════════════════════════════ -->
<script>
const ADMIN = {
  id:       <?= json_encode($adminId) ?>,
  username: <?= json_encode($adminUsername) ?>,
  role:     <?= json_encode($adminRole) ?>,
  canUsers: <?= json_encode(can($adminRole, 'users')) ?>,
  canStats: <?= json_encode(can($adminRole, 'stats')) ?>,
};
</script>

<!-- Modal: create user -->
<div class="modal-backdrop" id="userModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><span class="ms">person_add</span> إضافة مستخدم جديد</div>
      <button class="modal-close" onclick="closeModal()"><span class="ms">close</span></button>
    </div>
    <div class="form-error" id="modalErr"></div>
    <form id="createUserForm" onsubmit="submitCreateUser(event)">
      <div class="form-field">
        <label class="form-label">اسم المستخدم</label>
        <input class="form-input" type="text" name="username" placeholder="username" required autocomplete="off">
      </div>
      <div class="form-field">
        <label class="form-label">كلمة المرور</label>
        <input class="form-input" type="password" name="password" placeholder="••••••••" required autocomplete="new-password">
      </div>
      <div class="form-field">
        <label class="form-label">الدور</label>
        <select class="form-select" name="role">
          <option value="support">دعم فني</option>
          <option value="admin">مشرف</option>
          <option value="super_admin">سوبر ادمن</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="cancel-btn" onclick="closeModal()">إلغاء</button>
        <button type="submit" class="submit-btn" id="createSubmitBtn">إنشاء الحساب</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: invitation users -->
<div class="modal-backdrop" id="invitationUsersModal">
  <div class="modal" style="max-width:600px;">
    <div class="modal-header">
      <div class="modal-title"><span class="ms">group</span> مستخدمو الكود: <span id="modalInvCode"></span></div>
      <button class="modal-close" onclick="closeInvitationModal()"><span class="ms">close</span></button>
    </div>
    <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>الاسم</th>
            <th>رقم الهاتف</th>
            <th>تاريخ التسجيل</th>
          </tr>
        </thead>
        <tbody id="modalInvUsersBody">
          <tr><td colspan="4" style="text-align:center;padding:2rem">جارٍ التحميل...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="modal-footer">
      <button type="button" class="cancel-btn" onclick="closeInvitationModal()">إغلاق</button>
    </div>
  </div>
</div>

<!-- Sidebar backdrop (mobile) -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
        <img src="../../logo.png" alt="المصطفى" style="width:64px;height:64px;border-radius:50%;object-fit:contain;border:2px solid rgba(254,214,91,0.3);">
        <h1 style="margin:0;">لوحة التحكم</h1>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-item active" id="nav-visitors" onclick="showSection('visitors')">
        <span class="ms">group</span> إحصائيات الزوار
      </div>
      <div class="nav-item" id="nav-customers" onclick="showSection('customers')">
        <span class="ms">storefront</span> عملاؤنا
      </div>
      <?php if (in_array($adminRole, ['super_admin', 'admin'])): ?>
      <div class="nav-item" id="nav-reviews" onclick="showSection('reviews')">
        <span class="ms">star</span> إدارة الآراء
      </div>
      <?php endif; ?>
      <?php if (can($adminRole, 'users')): ?>
      <div class="nav-item" id="nav-users" onclick="showSection('users')">
        <span class="ms">manage_accounts</span> إدارة المستخدمين
      </div>
      <?php endif; ?>
      <?php if (in_array($adminRole, ['super_admin', 'admin'])): ?>
      <div class="nav-item" id="nav-invitations" onclick="showSection('invitations')">
        <span class="ms">card_giftcard</span> الدعوات
      </div>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-username"><?= htmlspecialchars($adminUsername) ?></div>
        <span class="sidebar-role" style="<?= roleBadgeCss($adminRole) ?>"><?= roleLabelAr($adminRole) ?></span>
      </div>
      <form method="post">
        <button class="logout-btn" name="logout" value="1">
          <span class="ms">logout</span> تسجيل الخروج
        </button>
      </form>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <button class="topbar-menu-btn" id="btnMenuToggle" onclick="toggleSidebar()" aria-label="القائمة">
          <span class="ms">menu</span>
        </button>
        <div>
          <div class="topbar-title" id="topbarTitle">إحصائيات الزوار</div>
          <div class="topbar-meta" id="lastUpdate">جارٍ التحميل...</div>
        </div>
      </div>
      <?php if (can($adminRole, 'stats')): ?>
      <div class="online-badge">
        <span class="online-dot"></span>
        <span id="onlineCount">—</span> متصل الآن
      </div>
      <?php endif; ?>
    </div>

    <div class="content">

      <!-- ── VISITORS SECTION ───────────────────────────────── -->
      <div class="section active" id="visitorsSection">

        <?php if (can($adminRole, 'stats')): ?>
        <!-- Stats -->
        <div class="stats-grid">
          <?php
          $cards = [
            ['icon'=>'groups',        'label'=>'إجمالي الزوار',   'id'=>'sTotal', 'cls'=>''],
            ['icon'=>'today',         'label'=>'زوار اليوم',      'id'=>'sToday', 'cls'=>'',      'g'=>'gToday'],
            ['icon'=>'date_range',    'label'=>'هذا الأسبوع',     'id'=>'sWeek',  'cls'=>'gold',  'g'=>'gWeek'],
            ['icon'=>'calendar_month','label'=>'هذا الشهر',       'id'=>'sMonth', 'cls'=>'gold'],
            ['icon'=>'ads_click',     'label'=>'إجمالي الزيارات', 'id'=>'sHits',  'cls'=>'green'],
            ['icon'=>'trending_up',   'label'=>'متوسط الزيارات',  'id'=>'sAvg',   'cls'=>'green'],
          ];
          foreach ($cards as $c): ?>
          <div class="stat-card <?= $c['cls'] ?>">
            <div class="stat-label"><span class="ms"><?= $c['icon'] ?></span><?= $c['label'] ?></div>
            <div class="stat-value skeleton skel-val" id="<?= $c['id'] ?>">—</div>
            <?php if (!empty($c['g'])): ?>
            <div id="<?= $c['g'] ?>" style="margin-top:.4rem;display:none"></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Charts -->
        <div class="charts-row">
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title"><span class="ms">show_chart</span> الزوار اليومية</div>
              <select class="chart-select" id="chartDays" onchange="loadChart()">
                <option value="7">7 أيام</option>
                <option value="30" selected>30 يوم</option>
                <option value="60">60 يوم</option>
                <option value="90">90 يوم</option>
              </select>
            </div>
            <div class="chart-wrap"><canvas id="lineChart"></canvas></div>
          </div>
          <div class="chart-card">
            <div class="chart-header">
              <div class="chart-title"><span class="ms">devices</span> أنواع الأجهزة</div>
            </div>
            <div class="chart-wrap"><canvas id="donutChart"></canvas></div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Visitors Table -->
        <div class="table-card">
          <div class="table-header">
            <div class="table-title"><span class="ms">table_rows</span> سجل الزوار</div>
            <div class="search-wrap">
              <span class="ms">search</span>
              <input class="search-input" id="searchInput" placeholder="بحث بـ IP أو معرّف..." type="search">
            </div>
            <button class="primary-btn" onclick="loadVisitors(1)">
              <span class="ms">refresh</span> تحديث
            </button>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>المعرّف</th>
                  <th>عنوان IP والموقع</th>
                  <th>الجهاز</th>
                  <th>المتصفح / النظام</th>
                  <th>أول زيارة</th>
                  <th>آخر زيارة</th>
                  <th>الزيارات</th>
                </tr>
              </thead>
              <tbody id="visitorsBody">
                <tr><td colspan="8" style="text-align:center;padding:2rem"><div class="skeleton" style="height:14px;width:60%;margin:auto"></div></td></tr>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <div class="page-info" id="pageInfo">—</div>
            <div class="page-btns" id="pageBtns"></div>
          </div>
        </div>

      </div><!-- /visitorsSection -->

      <!-- ── USERS SECTION (super_admin only) ──────────────── -->
      <?php if (can($adminRole, 'users')): ?>
      <div class="section" id="usersSection">
        <div class="table-card">
          <div class="table-header">
            <div class="table-title"><span class="ms">manage_accounts</span> مستخدمو لوحة التحكم</div>
            <button class="primary-btn" onclick="openModal()">
              <span class="ms">person_add</span> إضافة مستخدم
            </button>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>اسم المستخدم</th>
                  <th>الدور</th>
                  <th>الحالة</th>
                  <th>تاريخ الإنشاء</th>
                  <th>أُنشئ بواسطة</th>
                  <th>إجراءات</th>
                </tr>
              </thead>
              <tbody id="usersBody">
                <tr><td colspan="7" style="text-align:center;padding:2rem"><div class="skeleton" style="height:14px;width:60%;margin:auto"></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── CUSTOMERS SECTION ─────────────────────────── -->
      <div class="section" id="customersSection">

        <!-- Customer Stats -->
        <div class="stats-grid" id="custStatsGrid">
          <?php
          $custCards = [
            ['icon'=>'people_alt',   'label'=>'إجمالي العملاء',       'id'=>'csTotal',     'cls'=>''],
            ['icon'=>'person_add',   'label'=>'مسجّلون اليوم',         'id'=>'csToday',     'cls'=>'',     'g'=>'cgToday'],
            ['icon'=>'date_range',   'label'=>'هذا الأسبوع',          'id'=>'csWeek',      'cls'=>'gold', 'g'=>'cgWeek'],
            ['icon'=>'task_alt',     'label'=>'ملفات مكتملة',          'id'=>'csComplete',  'cls'=>'green'],
            ['icon'=>'person_off',   'label'=>'ملفات غير مكتملة',      'id'=>'csInc',       'cls'=>''],
            ['icon'=>'storefront',   'label'=>'عملاء الجملة',          'id'=>'csWholesale', 'cls'=>'gold'],
          ];
          foreach ($custCards as $c): ?>
          <div class="stat-card <?= $c['cls'] ?>">
            <div class="stat-label"><span class="ms"><?= $c['icon'] ?></span><?= $c['label'] ?></div>
            <div class="stat-value skeleton skel-val" id="<?= $c['id'] ?>">—</div>
            <?php if (!empty($c['g'])): ?>
            <div id="<?= $c['g'] ?>" style="margin-top:.4rem;display:none"></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Customers Table -->
        <div class="table-card">
          <div class="table-header">
            <div class="table-title"><span class="ms">people_alt</span> سجل العملاء</div>
            <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;flex:1;justify-content:flex-end;">
              <select class="chart-select" id="custSegmentFilter" onchange="loadCustomers(1)">
                <option value="">كل الشرائح</option>
                <option value="consumer">مستهلك</option>
                <option value="wholesale">جملة</option>
                <option value="corporate">جملة الجملة</option>
              </select>
              <select class="chart-select" id="custProfileFilter" onchange="loadCustomers(1)">
                <option value="">كل الملفات</option>
                <option value="1">مكتمل</option>
                <option value="0">غير مكتمل</option>
              </select>
              <div class="search-wrap" style="max-width:240px">
                <span class="ms">search</span>
                <input class="search-input" id="custSearch" placeholder="بحث باسم أو هاتف..." type="search">
              </div>
              <button class="primary-btn" onclick="loadCustomers(1)">
                <span class="ms">refresh</span> تحديث
              </button>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>الاسم</th>
                  <th>رقم الهاتف</th>
                  <th>الشريحة</th>
                  <th>المحافظة / المدينة</th>
                  <th>الملف</th>
                  <th>رصيد المحفظة</th>
                  <th>تاريخ التسجيل</th>
                </tr>
              </thead>
              <tbody id="custBody">
                <tr><td colspan="8" style="text-align:center;padding:2rem"><div class="skeleton" style="height:14px;width:60%;margin:auto"></div></td></tr>
              </tbody>
            </table>
          </div>
          <div class="pagination">
            <div class="page-info" id="custPageInfo">—</div>
            <div class="page-btns" id="custPageBtns"></div>
          </div>
        </div>

      </div><!-- /customersSection -->

      <!-- ── REVIEWS SECTION ───────────────────────────── -->
      <?php if (in_array($adminRole, ['super_admin', 'admin'])): ?>
      <div class="section" id="reviewsSection">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
          <h2 style="font-family:'Amiri',serif;font-size:1.4rem;color:var(--primary);margin:0;">آراء العملاء</h2>
          <button onclick="loadReviews()" style="padding:.5rem 1.2rem;background:var(--primary);color:#fff;border:none;border-radius:8px;cursor:pointer;font-family:'Amiri',serif;font-size:.9rem;">تحديث</button>
        </div>
        <div id="reviews-admin-list" style="display:flex;flex-direction:column;gap:1rem;">
          <div style="text-align:center;padding:3rem;color:var(--on-surface-dim);">جارٍ التحميل...</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- ── INVITATIONS SECTION ─────────────────────────── -->
      <?php if (in_array($adminRole, ['super_admin', 'admin'])): ?>
      <div class="section" id="invitationsSection">

        <!-- Invitation Stats -->
        <div class="stats-grid" id="invStatsGrid">
          <?php
          $invCards = array(
            array('icon'=>'card_giftcard',   'label'=>'إجمالي الأكواد',       'id'=>'invTotal',     'cls'=>''),
            array('icon'=>'check_circle',    'label'=>'أكواد نشطة',           'id'=>'invActive',     'cls'=>'green'),
            array('icon'=>'group_add',       'label'=>'إجمالي الاستخدامات',    'id'=>'invUsage',     'cls'=>'gold'),
            array('icon'=>'person',          'label'=>'المستخدمون الفريدون',   'id'=>'invCustomers', 'cls'=>'')
          );
          foreach ($invCards as $c): ?>
          <div class="stat-card <?= $c['cls'] ?>">
            <div class="stat-label"><span class="ms"><?= $c['icon'] ?></span><?= $c['label'] ?></div>
            <div class="stat-value skeleton skel-val" id="<?= $c['id'] ?>">—</div>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Invitations Table -->
        <div class="table-card">
          <div class="table-header">
            <div class="table-title"><span class="ms">card_giftcard</span> سجل الأكواد</div>
            <button class="primary-btn" onclick="loadInvitations()">
              <span class="ms">refresh</span> تحديث
            </button>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>الكود</th>
                  <th>صاحب الكود</th>
                  <th>رقم الهاتف</th>
                  <th>مرات الاستخدام</th>
                  <th>تاريخ التسجيل</th>
                  <th>المستخدمون</th>
                </tr>
              </thead>
              <tbody id="invBody">
                <tr><td colspan="9" style="text-align:center;padding:2rem"><div class="skeleton" style="height:14px;width:60%;margin:auto"></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>

      </div><!-- /invitationsSection -->
      <?php endif; ?>

    </div><!-- /content -->
  </main>
</div>

<script>
// ── Helpers ───────────────────────────────────────────────────────────────────
const $id = id => document.getElementById(id);

function fmt(n) {
  return (n === null || n === undefined) ? '—' : Number(n).toLocaleString('ar-EG');
}

function fmtDate(s) {
  if (!s) return '—';
  const d = new Date(s.replace(' ', 'T') + 'Z');
  return d.toLocaleString('ar-SA', {
    year:'numeric', month:'short', day:'numeric',
    hour:'2-digit', minute:'2-digit', hour12:false, timeZone:'Asia/Riyadh'
  });
}

function growthBadge(pct) {
  if (pct === null || pct === undefined) return '';
  const cls   = pct > 0 ? 'growth-up' : pct < 0 ? 'growth-down' : 'growth-flat';
  const arrow = pct > 0 ? '▲' : pct < 0 ? '▼' : '=';
  return `<span class="stat-growth ${cls}">${arrow} ${Math.abs(pct)}%</span>`;
}

function deviceBadge(device) {
  const map = {
    mobile:  ['smartphone','هاتف',       'device-mobile'],
    tablet:  ['tablet',    'تابلت',      'device-tablet'],
    desktop: ['computer',  'كمبيوتر', 'device-desktop'],
  };
  const [icon, label, cls] = map[device] ?? ['device_unknown','—','device-unknown'];
  return `<span class="device-badge ${cls}"><span class="ms">${icon}</span>${label}</span>`;
}

function roleBadgeHtml(role, label) {
  const styles = {
    super_admin: 'background:#fdf6e0;color:#735c00;border:1px solid #e8d48a',
    admin:       'background:#fdecea;color:#3c0004;border:1px solid #f5b8b8',
    support:     'background:#e8f0fe;color:#1a56d6;border:1px solid #b6ccf7',
  };
  return `<span class="role-badge" style="${styles[role] ?? ''}">${label}</span>`;
}

// ── Sidebar toggle (mobile) ───────────────────────────────────────────────────
const sidebar         = $id('sidebar');
const sidebarBackdrop = $id('sidebarBackdrop');

function openSidebar() {
  sidebar.classList.add('open');
  sidebarBackdrop.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  sidebar.classList.remove('open');
  sidebarBackdrop.classList.remove('open');
  document.body.style.overflow = '';
}
function toggleSidebar() {
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
}

// Close sidebar when a nav item is tapped on mobile
document.querySelectorAll('.nav-item').forEach(item => {
  item.addEventListener('click', () => {
    if (window.innerWidth <= 900) closeSidebar();
  });
});

// ── Section switching ─────────────────────────────────────────────────────────
const SECTION_TITLES = { visitors: 'إحصائيات الزوار', customers: 'عملاؤنا', users: 'إدارة المستخدمين', reviews: 'إدارة الآراء', invitations: 'الدعوات' };

function showSection(name) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
  const sec = $id(name + 'Section');
  if (sec) sec.classList.add('active');
  const nav = $id('nav-' + name);
  if (nav) nav.classList.add('active');
  $id('topbarTitle').textContent = SECTION_TITLES[name] ?? '';
  // Persist current section
  try { localStorage.setItem('alm_admin_section', name); } catch {}
  if (name === 'users')      loadUsers();
  if (name === 'customers')  { loadCustomerStats(); loadCustomers(1); }
  if (name === 'reviews')    loadReviews();
  if (name === 'invitations' && ADMIN.canUsers) loadInvitations();
}

// ── Restore last section on load — called at end of script after all vars ─────
function restoreSection() {
  const valid = ['visitors', 'customers', 'reviews', 'users', 'invitations'];
  let saved;
  try { saved = localStorage.getItem('alm_admin_section'); } catch {}
  const section = valid.includes(saved) ? saved : 'visitors';
  // Ensure the section exists in the DOM (role-gated sections may be absent)
  if (!$id(section + 'Section')) { showSection('visitors'); return; }
  showSection(section);
}

// ── Stats ─────────────────────────────────────────────────────────────────────
async function loadStats() {
  if (!ADMIN.canStats) return;
  const res = await fetch('data.php?type=stats');
  if (!res.ok) return;
  const d = await res.json();

  ['sTotal','sToday','sWeek','sMonth','sHits','sAvg'].forEach(id => {
    const el = $id(id);
    if (el) el.classList.remove('skeleton','skel-val');
  });

  $id('sTotal').textContent = fmt(d.total);
  $id('sToday').textContent = fmt(d.today);
  $id('sWeek').textContent  = fmt(d.thisWeek);
  $id('sMonth').textContent = fmt(d.thisMonth);
  $id('sHits').textContent  = fmt(d.totalHits);
  $id('sAvg').textContent   = d.avgHits;
  if ($id('onlineCount')) $id('onlineCount').textContent = fmt(d.online);

  const gToday = $id('gToday');
  if (gToday && d.growthToday !== null) {
    gToday.style.display = '';
    gToday.innerHTML = growthBadge(d.growthToday) + ' <span style="font-size:.72rem;color:var(--on-surface-dim)">مقارنة بالأمس</span>';
  }
  const gWeek = $id('gWeek');
  if (gWeek && d.growthWeek !== null) {
    gWeek.style.display = '';
    gWeek.innerHTML = growthBadge(d.growthWeek) + ' <span style="font-size:.72rem;color:var(--on-surface-dim)">مقارنة بالأسبوع السابق</span>';
  }
  $id('lastUpdate').textContent = 'آخر تحديث: ' + new Date().toLocaleTimeString('ar-SA',{hour:'2-digit',minute:'2-digit'});
}

// ── Charts ────────────────────────────────────────────────────────────────────
let lineChart, donutChart;

async function loadChart() {
  if (!ADMIN.canStats) return;
  const days = $id('chartDays').value;
  const res  = await fetch(`data.php?type=chart&days=${days}`);
  if (!res.ok) return;
  const rows = await res.json();

  const labels = rows.map(r => new Date(r.date).toLocaleDateString('ar-SA',{month:'short',day:'numeric'}));
  const data   = rows.map(r => r.count);

  if (lineChart) lineChart.destroy();
  lineChart = new Chart($id('lineChart'), {
    type: 'line',
    data: { labels, datasets:[{ label:'زوار جدد', data, borderColor:'#3c0004', backgroundColor:'rgba(60,0,4,.08)', borderWidth:2.5, pointBackgroundColor:'#3c0004', pointRadius:3, tension:.35, fill:true }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{ x:{grid:{display:false},ticks:{font:{family:'Manrope',size:11},maxRotation:45}}, y:{beginAtZero:true,grid:{color:'rgba(0,0,0,.06)'},ticks:{font:{family:'Manrope',size:11},precision:0}} } }
  });
}

async function loadDonut() {
  if (!ADMIN.canStats) return;
  const res = await fetch('data.php?type=devices');
  if (!res.ok) return;
  const d   = await res.json();
  if (donutChart) donutChart.destroy();
  donutChart = new Chart($id('donutChart'), {
    type: 'doughnut',
    data: { labels:['هاتف','تابلت','كمبيوتر'], datasets:[{ data:[d.mobile,d.tablet,d.desktop], backgroundColor:['#1a56d6','#7c0099','#735c00'], borderWidth:2, borderColor:'#fff' }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom', labels:{font:{family:'Manrope',size:12},padding:14,usePointStyle:true} } }, cutout:'62%' }
  });
}

// ── Geo cache — routed through PHP proxy to avoid browser CORS block ──────────
const geoCache = {};
async function geoLookup(ips) {
  const missing = ips.filter(ip => ip && !geoCache[ip] && !ip.startsWith('127') && !ip.startsWith('::1'));
  if (!missing.length) return;
  try {
    const res = await fetch('data.php?type=geo&ips=' + encodeURIComponent(missing.slice(0, 50).join(',')));
    const map = await res.json();
    Object.assign(geoCache, map);
  } catch {}
}
function locationHtml(ip) {
  if (!ip) return '<span class="location-text">—</span>';
  const geo = geoCache[ip];
  if (geo === undefined) return `<span class="location-loading">جارٍ تحديد الموقع...</span>`;
  if (!geo) return '<span class="location-text">غير معروف</span>';
  return `<span class="location-text">${[geo.city, geo.country].filter(Boolean).join('، ')}</span>`;
}

// ── Visitors table ────────────────────────────────────────────────────────────
let currentPage = 1;

async function loadVisitors(page = 1) {
  currentPage = page;
  const search = $id('searchInput').value.trim();
  const url    = `data.php?type=visitors&page=${page}&limit=25${search ? '&search='+encodeURIComponent(search) : ''}`;
  const res    = await fetch(url);
  if (!res.ok) return;
  const data   = await res.json();

  await geoLookup(data.visitors.map(v => v.ip_address).filter(Boolean));

  const tbody  = $id('visitorsBody');
  tbody.innerHTML = '';
  const offset = (page - 1) * 25;

  if (!data.visitors.length) {
    tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><span class="ms">search_off</span>لا توجد نتائج</div></td></tr>';
  } else {
    data.visitors.forEach((v, i) => {
      const p  = v.parsed || {};
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td style="color:var(--on-surface-dim);font-size:.78rem">${offset+i+1}</td>
        <td><span class="vid-chip" title="${v.v_id}">${v.v_id}</span></td>
        <td>
          <span class="ip-text">${v.ip_address || '—'}</span><br>
          ${locationHtml(v.ip_address)}
        </td>
        <td>${deviceBadge(p.device || 'unknown')}</td>
        <td>
          <div class="os-browser">${p.browser || '—'}</div>
          <div class="os-browser">${p.os || '—'}</div>
        </td>
        <td><div class="date-text">${fmtDate(v.first_seen)}</div></td>
        <td><div class="date-text">${fmtDate(v.last_seen)}</div></td>
        <td><span class="hits-badge">${v.hit_count}</span></td>`;
      tbody.appendChild(tr);
    });
  }

  const start = (page-1)*25 + 1;
  const end   = Math.min(page*25, data.total);
  $id('pageInfo').textContent = `عرض ${fmt(start)}–${fmt(end)} من ${fmt(data.total)} زائر`;
  renderPagination(page, data.pages);
}

function renderPagination(current, total) {
  const wrap = $id('pageBtns');
  wrap.innerHTML = '';
  const btn = (label, page, disabled=false, active=false) => {
    const b = document.createElement('button');
    b.className = 'page-btn' + (active?' active':'');
    b.innerHTML = label; b.disabled = disabled;
    if (!disabled) b.onclick = () => loadVisitors(page);
    wrap.appendChild(b);
  };
  btn('<span class="ms" style="font-size:.9rem">chevron_right</span>', current-1, current<=1);
  const pages = [];
  if (total<=7) { for(let i=1;i<=total;i++) pages.push(i); }
  else {
    pages.push(1);
    if(current>3) pages.push('…');
    for(let i=Math.max(2,current-1);i<=Math.min(total-1,current+1);i++) pages.push(i);
    if(current<total-2) pages.push('…');
    pages.push(total);
  }
  pages.forEach(p => {
    if(p==='…'){const s=document.createElement('span');s.textContent='…';s.style.cssText='padding:0 .4rem;line-height:34px;color:var(--on-surface-dim)';wrap.appendChild(s);}
    else btn(p,p,false,p===current);
  });
  btn('<span class="ms" style="font-size:.9rem">chevron_left</span>', current+1, current>=total);
}

// ── Users table ───────────────────────────────────────────────────────────────
async function loadUsers() {
  const res  = await fetch('data.php?type=users');
  if (!res.ok) return;
  const rows = await res.json();
  const tbody = $id('usersBody');
  tbody.innerHTML = '';

  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><span class="ms">person_off</span>لا يوجد مستخدمون</div></td></tr>';
    return;
  }

  rows.forEach((u, i) => {
    const isSelf    = u.is_self;
    const isActive  = u.is_active == 1;
    const toggleCls = isActive ? 'toggle-on' : 'toggle-off';
    const toggleLbl = isActive ? 'تعطيل' : 'تفعيل';
    const toggleIco = isActive ? 'block' : 'check_circle';

    const tr = document.createElement('tr');
    tr.id = `urow-${u.id}`;
    tr.innerHTML = `
      <td style="color:var(--on-surface-dim);font-size:.78rem">${i+1}</td>
      <td style="font-weight:600">${escHtml(u.username)}${isSelf ? ' <span style="font-size:.72rem;color:var(--on-surface-dim)">(أنت)</span>' : ''}</td>
      <td>${roleBadgeHtml(u.role, u.role_label)}</td>
      <td><span class="${isActive ? 'status-active' : 'status-inactive'}">${isActive ? 'نشط' : 'معطّل'}</span></td>
      <td><div class="date-text">${fmtDate(u.created_at)}</div></td>
      <td style="font-size:.78rem;color:var(--on-surface-dim)">${u.created_by_name ? escHtml(u.created_by_name) : '—'}</td>
      <td>
        ${!isSelf ? `
          <button class="action-btn ${toggleCls}" onclick="toggleUser(${u.id}, ${JSON.stringify(u.username)})">
            <span class="ms">${toggleIco}</span>${toggleLbl}
          </button>
          <button class="action-btn del" onclick="deleteUser(${u.id}, ${JSON.stringify(u.username)})">
            <span class="ms">delete</span>
          </button>
        ` : '<span style="font-size:.75rem;color:var(--on-surface-dim)">—</span>'}
      </td>`;
    tbody.appendChild(tr);
  });
}

async function toggleUser(id, username) {
  if (!confirm(`هل تريد تغيير حالة المستخدم "${username}"؟`)) return;
  const res = await fetch('data.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle_user', id})
  });
  const d = await res.json();
  if (d.ok) loadUsers();
  else alert(d.error || 'حدث خطأ');
}

async function deleteUser(id, username) {
  if (!confirm(`هل تريد حذف المستخدم "${username}" نهائياً؟`)) return;
  const res = await fetch('data.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete_user', id})
  });
  const d = await res.json();
  if (d.ok) loadUsers();
  else alert(d.error || 'حدث خطأ');
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openModal() {
  const form = $id('createUserForm');
  const err = $id('modalErr');
  const modal = $id('userModal');
  if (form) form.reset();
  if (err) err.classList.remove('show');
  if (modal) modal.classList.add('open');
}
function closeModal() { 
  const modal = $id('userModal');
  if (modal) modal.classList.remove('open'); 
}
$id('userModal').addEventListener('click', e => { if (e.target === $id('userModal')) closeModal(); });

async function submitCreateUser(e) {
  e.preventDefault();
  const form = e.target;
  const btn  = $id('createSubmitBtn');
  const err  = $id('modalErr');
  if (err) err.classList.remove('show');
  if (btn) btn.disabled = true;
  btn.textContent = 'جارٍ الإنشاء...';

  const data = Object.fromEntries(new FormData(form));
  const res  = await fetch('data.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({action:'create_user', ...data})
  });
  const d = await res.json();

  btn.disabled = false;
  btn.textContent = 'إنشاء الحساب';

  if (d.ok) {
    closeModal();
    loadUsers();
  } else {
    err.textContent = d.error || 'حدث خطأ أثناء الإنشاء';
    err.classList.add('show');
  }
}

// ── XSS helper ────────────────────────────────────────────────────────────────
function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Search debounce ───────────────────────────────────────────────────────────
let searchTimer;
$id('searchInput').addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => loadVisitors(1), 400);
});

// ── Init ──────────────────────────────────────────────────────────────────────
(async () => {
  await Promise.all([
    loadStats(),
    loadChart(),
    loadDonut(),
    loadVisitors(1),
  ]);
  setInterval(loadStats, 60_000);
})();

// ── Customers ─────────────────────────────────────────────────────────────────
const SEGMENT_MAP = {
  consumer:  { label:'مستهلك',       style:'background:#e8f0fe;color:#1a56d6;border:1px solid #b6ccf7' },
  wholesale: { label:'جملة',         style:'background:#fdf6e0;color:#735c00;border:1px solid #e8d48a' },
  corporate: { label:'جملة الجملة',  style:'background:#fdecea;color:#3c0004;border:1px solid #f5b8b8' },
};

async function loadCustomerStats() {
  const res = await fetch('data.php?type=customer_stats');
  if (!res.ok) return;
  const d = await res.json();

  ['csTotal','csToday','csWeek','csComplete','csInc','csWholesale'].forEach(id => {
    const el = $id(id);
    if (el) { el.classList.remove('skeleton','skel-val'); }
  });

  $id('csTotal').textContent    = fmt(d.total);
  $id('csToday').textContent    = fmt(d.today);
  $id('csWeek').textContent     = fmt(d.this_week);
  $id('csComplete').textContent = fmt(d.complete);
  $id('csInc').textContent      = fmt(d.incomplete);
  $id('csWholesale').textContent= fmt(d.wholesale);

  const cgToday = $id('cgToday');
  if (cgToday && d.growth_today !== null) {
    cgToday.style.display = '';
    cgToday.innerHTML = growthBadge(d.growth_today) + ' <span style="font-size:.72rem;color:var(--on-surface-dim)">مقارنة بالأمس</span>';
  }
  const cgWeek = $id('cgWeek');
  if (cgWeek && d.growth_week !== null) {
    cgWeek.style.display = '';
    cgWeek.innerHTML = growthBadge(d.growth_week) + ' <span style="font-size:.72rem;color:var(--on-surface-dim)">مقارنة بالأسبوع السابق</span>';
  }
}

let custCurrentPage = 1;

async function loadCustomers(page = 1) {
  custCurrentPage = page;
  const search  = $id('custSearch').value.trim();
  const segment = $id('custSegmentFilter').value;
  const profile = $id('custProfileFilter').value;

  const params = new URLSearchParams({ type:'customers', page, limit:25 });
  if (search)  params.set('search', search);
  if (segment) params.set('segment', segment);
  if (profile !== '') params.set('profile', profile);

  const res  = await fetch('data.php?' + params);
  if (!res.ok) return;
  const data = await res.json();

  const tbody = $id('custBody');
  tbody.innerHTML = '';
  const offset = (page - 1) * 25;

  if (!data.customers.length) {
    tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><span class="ms">person_search</span>لا توجد نتائج</div></td></tr>';
  } else {
    data.customers.forEach((c, i) => {
      const seg     = SEGMENT_MAP[c.segment] ?? { label: c.segment, style: '' };
      const loc     = [c.governorate, c.city].filter(Boolean).join(' / ') || '—';
      const done    = c.profile_complete == 1;
      const balance = parseFloat(c.wallet_balance ?? 0);
      const balFmt  = balance.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const balStyle = balance > 0
        ? 'background:var(--green-bg);color:var(--green)'
        : 'background:#f0f0f0;color:#888';
      const tr    = document.createElement('tr');
      tr.innerHTML = `
        <td style="color:var(--on-surface-dim);font-size:.78rem">${offset + i + 1}</td>
        <td style="font-weight:600">${escHtml(c.name || '—')}</td>
        <td><span class="ip-text" style="font-size:.82rem">${escHtml(c.phone)}</span></td>
        <td><span class="role-badge" style="${seg.style}">${seg.label}</span></td>
        <td style="font-size:.8rem;color:var(--on-surface-dim)">${escHtml(loc)}</td>
        <td>
          <span style="font-size:.75rem;font-weight:700;padding:.2rem .6rem;border-radius:999px;
            ${done ? 'background:var(--green-bg);color:var(--green)' : 'background:#f0f0f0;color:#888'}">
            ${done ? 'مكتمل' : 'غير مكتمل'}
          </span>
        </td>
        <td>
          <span style="font-size:.78rem;font-weight:700;padding:.2rem .6rem;border-radius:999px;${balStyle}">
            ${balFmt} ر.س
          </span>
        </td>
        <td><div class="date-text">${fmtDate(c.created_at)}</div></td>`;
      tbody.appendChild(tr);
    });
  }

  const start = (page - 1) * 25 + 1;
  const end   = Math.min(page * 25, data.total);
  $id('custPageInfo').textContent = `عرض ${fmt(start)}–${fmt(end)} من ${fmt(data.total)} عميل`;
  renderCustPagination(page, data.pages);
}

function renderCustPagination(current, total) {
  const wrap = $id('custPageBtns');
  wrap.innerHTML = '';
  const btn = (label, page, disabled=false, active=false) => {
    const b = document.createElement('button');
    b.className = 'page-btn' + (active?' active':'');
    b.innerHTML = label; b.disabled = disabled;
    if (!disabled) b.onclick = () => loadCustomers(page);
    wrap.appendChild(b);
  };
  btn('<span class="ms" style="font-size:.9rem">chevron_right</span>', current-1, current<=1);
  const pages = [];
  if (total<=7) { for(let i=1;i<=total;i++) pages.push(i); }
  else {
    pages.push(1);
    if(current>3) pages.push('…');
    for(let i=Math.max(2,current-1);i<=Math.min(total-1,current+1);i++) pages.push(i);
    if(current<total-2) pages.push('…');
    pages.push(total);
  }
  pages.forEach(p => {
    if(p==='…'){const s=document.createElement('span');s.textContent='…';s.style.cssText='padding:0 .4rem;line-height:34px;color:var(--on-surface-dim)';wrap.appendChild(s);}
    else btn(p,p,false,p===current);
  });
  btn('<span class="ms" style="font-size:.9rem">chevron_left</span>', current+1, current>=total);
}

let custSearchTimer;
$id('custSearch').addEventListener('input', () => {
  clearTimeout(custSearchTimer);
  custSearchTimer = setTimeout(() => loadCustomers(1), 400);
});

// ── Reviews ───────────────────────────────────────────────────────────────────
let reviewsData = [];
let reviewsDragSrc = null;

async function loadReviews() {
  const list = $id('reviews-admin-list');
  if (!list) return;
  list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--on-surface-dim);">جارٍ التحميل...</div>';
  try {
    const res  = await fetch('data.php?type=reviews');
    const data = await res.json();
    reviewsData = data.reviews ?? [];
    renderReviewsList();
  } catch(e) {
    list.innerHTML = '<div style="text-align:center;padding:2rem;color:#c00;">فشل التحميل</div>';
  }
}

function renderReviewsList() {
  const list = $id('reviews-admin-list');
  if (!list) return;
  if (reviewsData.length === 0) {
    list.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--on-surface-dim);">لا توجد آراء بعد</div>';
    return;
  }
  list.innerHTML = '';
  reviewsData.forEach((r, idx) => {
    const card = document.createElement('div');
    card.dataset.id  = r.id;
    card.dataset.idx = idx;
    card.draggable   = true;
    card.style.cssText = `
      background:var(--surface);border:1px solid var(--border);border-radius:14px;
      padding:1.1rem 1.4rem;display:flex;align-items:flex-start;gap:1rem;
      opacity:${r.visible == 1 ? 1 : 0.45};cursor:grab;
      transition:box-shadow .2s,transform .2s;
    `;
    // Stars
    let stars = '';
    for (let i = 1; i <= 5; i++) {
      stars += `<span class="ms" style="font-size:1rem;color:${i <= r.rating ? '#b88a00' : '#ddd'};font-variation-settings:'FILL' ${i<=r.rating?1:0},'wght' 300,'GRAD' 0,'opsz' 24;">star</span>`;
    }
    const date = new Date(r.created_at.replace(' ','T')+'Z').toLocaleDateString('ar-EG',{year:'numeric',month:'short',day:'numeric'});
    card.innerHTML = `
      <span class="ms" style="font-size:1.4rem;color:var(--on-surface-dim);cursor:grab;flex-shrink:0;margin-top:.2rem;">drag_indicator</span>
      <div style="flex:1;min-width:0;">
        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:.4rem;">
          <strong style="font-family:'Amiri',serif;font-size:1rem;color:var(--primary);">${r.name}</strong>
          ${r.product ? `<span style="font-size:.75rem;background:var(--gold-bg);color:var(--gold);border:1px solid var(--gold-border);padding:.15rem .6rem;border-radius:20px;">${r.product}</span>` : ''}
          <span style="display:flex;gap:1px;">${stars}</span>
          <span style="font-size:.72rem;color:var(--on-surface-dim);margin-right:auto;">${date}</span>
        </div>
        <p style="font-size:.88rem;color:var(--on-surface-dim);margin:0;white-space:pre-wrap;word-break:break-word;">${r.content}</p>
      </div>
      <div style="display:flex;flex-direction:column;gap:.5rem;flex-shrink:0;">
        <button onclick="reviewToggle(${r.id}, this)" title="${r.visible==1 ? 'إخفاء' : 'إظهار'}"
          style="padding:.4rem .8rem;border:1px solid ${r.visible==1?'var(--gold-border)':'#ccc'};border-radius:8px;background:${r.visible==1?'var(--gold-bg)':'#f5f5f5'};cursor:pointer;font-size:.78rem;font-family:'Amiri',serif;white-space:nowrap;">
          <span class="ms" style="font-size:.95rem;vertical-align:middle;">${r.visible==1?'visibility':'visibility_off'}</span>
          ${r.visible==1?'ظاهر':'مخفي'}
        </button>
        <button onclick="reviewDelete(${r.id}, this)" title="حذف"
          style="padding:.4rem .8rem;border:1px solid #f5b8b8;border-radius:8px;background:#fdecea;cursor:pointer;font-size:.78rem;font-family:'Amiri',serif;color:var(--primary);">
          <span class="ms" style="font-size:.95rem;vertical-align:middle;">delete</span>
          حذف
        </button>
      </div>`;

    // Drag events
    card.addEventListener('dragstart', e => {
      reviewsDragSrc = card;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(() => card.style.opacity = '0.3', 0);
    });
    card.addEventListener('dragend', () => {
      reviewsDragSrc = null;
      card.style.opacity = reviewsData[idx]?.visible == 1 ? '1' : '0.45';
      list.querySelectorAll('[data-id]').forEach(c => c.style.background = '');
    });
    card.addEventListener('dragover', e => {
      e.preventDefault();
      if (card !== reviewsDragSrc) card.style.background = '#f0eaff';
    });
    card.addEventListener('dragleave', () => { card.style.background = ''; });
    card.addEventListener('drop', async e => {
      e.preventDefault();
      card.style.background = '';
      if (!reviewsDragSrc || reviewsDragSrc === card) return;
      // Reorder in DOM
      const allCards = [...list.querySelectorAll('[data-id]')];
      const fromIdx  = allCards.indexOf(reviewsDragSrc);
      const toIdx    = allCards.indexOf(card);
      if (fromIdx < toIdx) list.insertBefore(reviewsDragSrc, card.nextSibling);
      else                  list.insertBefore(reviewsDragSrc, card);
      // Persist order
      const ids = [...list.querySelectorAll('[data-id]')].map(c => parseInt(c.dataset.id));
      await fetch('data.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'review_reorder', ids}) });
    });

    list.appendChild(card);
  });
}

async function reviewToggle(id, btn) {
  btn.disabled = true;
  const res  = await fetch('data.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'review_toggle',id}) });
  const data = await res.json();
  if (data.ok) {
    const r = reviewsData.find(x => x.id == id);
    if (r) r.visible = data.visible ? 1 : 0;
    renderReviewsList();
  }
  btn.disabled = false;
}

async function reviewDelete(id, btn) {
  if (!confirm('هل أنت متأكد من حذف هذا الرأي؟')) return;
  btn.disabled = true;
  const res  = await fetch('data.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({action:'review_delete',id}) });
  const data = await res.json();
  if (data.ok) {
    reviewsData = reviewsData.filter(x => x.id != id);
    renderReviewsList();
  }
  btn.disabled = false;
}

// ── Invitations ───────────────────────────────────────────────────────────────────
let invitationsData = [];

async function loadInvitations() {
  // Check if invitations section exists in DOM
  if (!$id('invitationsSection')) return;
  
  try {
    const res = await fetch('../../apis/invitations/stats.php');
    if (!res.ok) throw new Error('Failed to load');
    const data = await res.json();
    
    // Update stats
    ['invTotal','invActive','invUsage','invCustomers'].forEach(id => {
      const el = $id(id);
      if (el) {
        el.classList.remove('skeleton','skel-val');
        switch(id) {
          case 'invTotal': el.textContent = fmt(data.stats.total_codes); break;
          case 'invActive': el.textContent = fmt(data.stats.active_codes); break;
          case 'invUsage': el.textContent = fmt(data.stats.total_usage); break;
          case 'invCustomers': el.textContent = fmt(data.stats.unique_customers); break;
        }
      }
    });
    
    // Update table
    invitationsData = data.codes || [];
    renderInvitationsTable();
  } catch(e) {
    console.error('Failed to load invitations:', e);
    const tbody = $id('invBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:2rem;color:#c00;">فشل التحميل</td></tr>';
  }
}

function renderInvitationsTable() {
  const tbody = $id('invBody');
  if (!tbody) return;
  
  if (invitationsData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--on-surface-dim);">لا توجد أكواد دعوة بعد</td></tr>';
    return;
  }
  
  tbody.innerHTML = '';
  invitationsData.forEach((inv, idx) => {
    const tr = document.createElement('tr');
    const statusBadge = inv.is_active 
      ? '<span style="color:var(--green);font-weight:700;">نشط</span>'
      : '<span style="color:var(--red-text);font-weight:700;">غير نشط</span>';
    
    const expiresDate = inv.expires_at ? fmtDate(inv.expires_at) : '—';
    
    tr.innerHTML = `
      <td>${idx + 1}</td>
      <td><code style="background:var(--surface-dim);padding:.2rem .5rem;border-radius:4px;font-family:monospace;letter-spacing:.12em;">${inv.code}</code></td>
      <td>${inv.owner_name || '—'}</td>
      <td style="direction:ltr;text-align:right;font-family:monospace;font-size:.8rem;">${inv.owner_phone || '—'}</td>
      <td><span style="background:var(--green-bg);color:var(--green);font-weight:700;padding:.2rem .7rem;border-radius:999px;">${fmt(inv.usage_count)}</span></td>
      <td>${fmtDate(inv.created_at)}</td>
      <td>
        <button onclick="showInvitationUsers('${inv.code}')" style="background:var(--primary);color:#fff;border:none;border-radius:6px;padding:.3rem .75rem;cursor:pointer;font-size:.78rem;font-family:inherit;">
          <span class="ms" style="font-size:.95rem;vertical-align:middle;">group</span> ${fmt(inv.customer_count)} مستخدم
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function showInvitationUsers(code) {
  const modal = $id('invitationUsersModal');
  const codeSpan = $id('modalInvCode');
  const tbody = $id('modalInvUsersBody');
  
  if (!modal || !codeSpan || !tbody) return;
  
  codeSpan.textContent = code;
  modal.classList.add('open');
  
  // Find invitation data
  const invitation = invitationsData.find(inv => inv.code === code);
  if (!invitation || !invitation.customers || invitation.customers.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:2rem;color:var(--on-surface-dim);">لا يوجد مستخدمون لهذا الكود</td></tr>';
    return;
  }
  
  tbody.innerHTML = '';
  invitation.customers.forEach((customer, idx) => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${idx + 1}</td>
      <td>${customer.name}</td>
      <td><code style="background:var(--surface-dim);padding:.2rem .4rem;border-radius:3px;font-family:monospace;font-size:.8rem;">${customer.phone}</code></td>
      <td>${fmtDate(customer.created_at)}</td>
    `;
    tbody.appendChild(tr);
  });
}

function closeInvitationModal() {
  const modal = $id('invitationUsersModal');
  if (modal) modal.classList.remove('open');
}

// ── Run after all declarations ────────────────────────────────────────────────
restoreSection();
</script>
</body>
</html>
