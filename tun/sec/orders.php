<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_auth']) || $_SESSION['admin_auth'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../apis/bootstrap.php';

$adminRole     = $_SESSION['admin_role']     ?? '';
$adminUsername = $_SESSION['admin_username'] ?? '';
$adminFullname = $_SESSION['admin_fullname'] ?? $adminUsername;

function roleLabelArO(string $role): string {
    return match ($role) {
        'super_admin' => 'سوبر ادمن',
        'admin'       => 'مشرف',
        'support'     => 'دعم فني',
        default       => $role,
    };
}
function roleBadgeCssO(string $role): string {
    return match ($role) {
        'super_admin' => 'background:#fdf6e0;color:#735c00;border:1px solid #e8d48a',
        'admin'       => 'background:#fdecea;color:#3c0004;border:1px solid #f5b8b8',
        'support'     => 'background:#e8f0fe;color:#1a56d6;border:1px solid #b6ccf7',
        default       => 'background:#f0f0f0;color:#555',
    };
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#3c0004">
<title>الطلبات — لوحة التحكم</title>
<link rel="icon" href="cp-logo.jpg" type="image/jpeg">
<link rel="apple-touch-icon" href="cp-logo.jpg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
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
  --blue:           #1a56d6;
  --blue-bg:        #e8f0fe;
  --purple:         #6a1b9a;
  --purple-bg:      #f3e5f5;
  --shadow:         0 2px 12px rgba(60,0,4,.08);
  --shadow-lg:      0 8px 32px rgba(60,0,4,.13);
  --radius:         14px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 15px; overflow-x: hidden; }
body { font-family: 'Manrope','Amiri',sans-serif; background: var(--surface-dim); color: var(--on-surface); min-height: 100vh; }
.ms { font-family:'Material Symbols Outlined'; font-weight:normal; font-style:normal; line-height:1; letter-spacing:normal; text-transform:none; display:inline-block; white-space:nowrap; direction:ltr; font-feature-settings:'liga'; -webkit-font-feature-settings:'liga'; -webkit-font-smoothing:antialiased; user-select:none; }
.ms-fill { font-variation-settings:'FILL' 1,'wght' 400,'GRAD' 0,'opsz' 24; }

/* ── Layout ── */
.layout { display:flex; min-height:100vh; }
.sidebar { width:240px; min-height:100vh; background:var(--primary); display:flex; flex-direction:column; position:fixed; top:0; right:0; bottom:0; z-index:100; box-shadow:-4px 0 20px rgba(0,0,0,.18); }
.sidebar-logo { padding:1.5rem 1.25rem 1rem; border-bottom:1px solid rgba(255,255,255,.12); }
.sidebar-logo h1 { font-family:'Amiri',serif; font-size:1.4rem; font-weight:700; color:#fff; line-height:1.2; }
.sidebar-logo p { color:rgba(255,255,255,.5); font-size:.75rem; margin-top:.2rem; }
.sidebar-nav { flex:1; padding:1rem 0; }
.nav-item { display:flex; align-items:center; gap:.75rem; padding:.75rem 1.25rem; color:rgba(255,255,255,.75); cursor:pointer; border-right:3px solid transparent; font-size:.925rem; font-weight:500; text-decoration:none; transition:all .2s; }
.nav-item:hover { color:#fff; background:rgba(255,255,255,.08); }
.nav-item.active { color:#fff; background:rgba(255,255,255,.12); border-right-color:#a08500; }
.nav-item .ms { font-size:1.25rem; }
.sidebar-footer { padding:1rem 1.25rem; border-top:1px solid rgba(255,255,255,.12); }
.sidebar-username { font-size:.875rem; font-weight:700; color:#fff; }
.sidebar-role { display:inline-block; font-size:.72rem; font-weight:700; padding:.2rem .6rem; border-radius:999px; margin-top:.3rem; }
.logout-btn { display:flex; align-items:center; gap:.6rem; color:rgba(255,255,255,.65); font-size:.875rem; cursor:pointer; background:none; border:none; font-family:inherit; width:100%; padding:.5rem 0; margin-top:.75rem; transition:color .2s; }
.logout-btn:hover { color:#fff; }
.main { margin-right:240px; flex:1; display:flex; flex-direction:column; min-height:100vh; }
.topbar { background:var(--surface); border-bottom:1px solid var(--border); padding:1rem 1.75rem; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
.topbar-title { font-size:1.2rem; font-weight:700; color:var(--primary); display:flex; align-items:center; gap:.5rem; }
.content { padding:1.75rem; flex:1; }

/* ── Stats ── */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:1rem; margin-bottom:1.5rem; }
.stat-card { background:var(--surface); border-radius:var(--radius); padding:1.1rem 1.25rem 1rem; box-shadow:var(--shadow); border:1px solid var(--border); position:relative; overflow:hidden; }
.stat-card::before { content:''; position:absolute; top:0; right:0; width:4px; height:100%; background:var(--primary); }
.stat-card.gold::before { background:var(--gold); }
.stat-card.green::before { background:var(--green); }
.stat-card.blue::before { background:var(--blue); }
.stat-card.purple::before { background:var(--purple); }
.stat-label { font-size:.75rem; color:var(--on-surface-dim); font-weight:600; margin-bottom:.4rem; display:flex; align-items:center; gap:.3rem; }
.stat-value { font-size:1.75rem; font-weight:700; color:var(--primary); line-height:1; }
.stat-card.gold .stat-value { color:var(--gold); }
.stat-card.green .stat-value { color:var(--green); }
.stat-card.blue .stat-value { color:var(--blue); }
.stat-card.purple .stat-value { color:var(--purple); }

/* ── Toolbar ── */
.toolbar { display:flex; align-items:center; gap:.75rem; margin-bottom:1rem; flex-wrap:wrap; }
.search-wrap { display:flex; align-items:center; border:1.5px solid var(--border); border-radius:10px; background:var(--surface); overflow:hidden; flex:1; min-width:200px; max-width:320px; }
.search-wrap .ms { padding:0 .6rem; color:var(--on-surface-dim); font-size:1.15rem; flex-shrink:0; }
.search-input { border:none; background:none; padding:.6rem .25rem; font-size:.875rem; font-family:inherit; color:var(--on-surface); outline:none; flex:1; direction:rtl; text-align:right; min-width:0; }
.filter-select { border:1.5px solid var(--border); border-radius:10px; background:var(--surface); padding:.6rem .85rem; font-size:.85rem; font-family:inherit; color:var(--on-surface); outline:none; cursor:pointer; transition:border-color .2s; }
.filter-select:focus { border-color:var(--primary); }
.refresh-btn { display:flex; align-items:center; gap:.35rem; border:1.5px solid var(--border); border-radius:10px; background:var(--surface); padding:.6rem .9rem; font-size:.85rem; font-family:inherit; cursor:pointer; color:var(--on-surface); transition:all .2s; }
.refresh-btn:hover { border-color:var(--primary); color:var(--primary); }

/* ── Filter tabs ── */
.filter-tabs { display:flex; gap:.4rem; margin-bottom:1rem; flex-wrap:wrap; }
.filter-tab { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem 1rem; border-radius:999px; font-size:.8rem; font-weight:700; border:1.5px solid var(--border); background:var(--surface); color:var(--on-surface-dim); cursor:pointer; transition:all .18s; font-family:inherit; white-space:nowrap; }
.filter-tab:hover { border-color:var(--primary); color:var(--primary); }
.filter-tab.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.filter-tab .tab-count { background:rgba(255,255,255,.25); color:inherit; border-radius:999px; padding:.05rem .45rem; font-size:.72rem; }
.filter-tab:not(.active) .tab-count { background:var(--surface-dim); color:var(--on-surface-dim); }

/* ── Table ── */
.table-card { background:var(--surface); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid var(--border); overflow:hidden; }
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; min-width:820px; }
thead { background:var(--surface-dim); }
th { padding:.75rem 1rem; text-align:right; font-size:.75rem; font-weight:700; color:var(--on-surface-dim); white-space:nowrap; border-bottom:1px solid var(--border); }
td { padding:.8rem 1rem; font-size:.825rem; border-bottom:1px solid var(--border); vertical-align:middle; }
tr:last-child td { border-bottom:none; }
tr.order-row:hover td { background:rgba(60,0,4,.02); cursor:pointer; }
tr.order-row.expanded td { background:rgba(60,0,4,.03); }
tr.detail-row td { padding:0; background:#fdf9f6; border-bottom:1px solid var(--border); }
tr.detail-row.hidden { display:none; }
.detail-inner { padding:1.25rem 1.5rem; border-top:2px solid rgba(60,0,4,.07); }

/* ── Status badge ── */
.status-badge { display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .75rem; border-radius:999px; font-size:.75rem; font-weight:700; white-space:nowrap; }
.status-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.s-pending   { background:#fff8e1; color:#735c00; }
.s-pending   .status-dot { background:#fabd00; }
.s-confirmed { background:var(--green-bg); color:var(--green); }
.s-confirmed .status-dot { background:#43a047; }
.s-preparing { background:var(--blue-bg); color:var(--blue); }
.s-preparing .status-dot { background:#1e88e5; animation:blink 1.4s infinite; }
.s-shipping  { background:var(--purple-bg); color:var(--purple); }
.s-shipping  .status-dot { background:#8e24aa; animation:blink 1.4s infinite; }
.s-delivered { background:var(--green-bg); color:#1b5e20; }
.s-delivered .status-dot { background:#2e7d32; }
.s-cancelled { background:var(--red-bg); color:var(--red-text); }
.s-cancelled .status-dot { background:#e53935; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── Guest badge ── */
.guest-badge { display:inline-flex; align-items:center; gap:.25rem; background:#f0f0f0; color:#555; padding:.2rem .55rem; border-radius:6px; font-size:.72rem; font-weight:700; }

/* ── Action buttons ── */
.action-btn { display:inline-flex; align-items:center; gap:.25rem; padding:.35rem .65rem; border-radius:8px; font-size:.78rem; font-weight:600; border:1.5px solid var(--border); background:none; cursor:pointer; font-family:inherit; color:var(--on-surface); transition:all .15s; }
.action-btn:hover { border-color:var(--primary); color:var(--primary); background:rgba(60,0,4,.04); }
.action-btn .ms { font-size:1rem; }

/* ── Status select ── */
.status-select { border:1.5px solid var(--border); border-radius:8px; background:var(--surface); padding:.35rem .6rem; font-size:.8rem; font-family:inherit; color:var(--on-surface); cursor:pointer; outline:none; }
.status-select:focus { border-color:var(--primary); }

/* ── Products in detail ── */
.prod-list { display:flex; flex-direction:column; gap:.5rem; margin-bottom:1rem; }
.prod-item { display:flex; align-items:center; gap:.75rem; background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:.6rem .85rem; }
.prod-img { width:44px; height:44px; border-radius:8px; object-fit:cover; border:1px solid var(--border); background:var(--surface-dim); flex-shrink:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.prod-img .ms { font-size:20px; color:#ccc; font-variation-settings:'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 24; }
.prod-name { font-weight:700; font-size:.85rem; color:var(--on-surface); flex:1; }
.prod-meta { font-size:.75rem; color:var(--on-surface-dim); margin-top:.1rem; }
.prod-price { font-size:.85rem; font-weight:700; color:var(--primary); flex-shrink:0; }

/* ── Totals ── */
.totals-row { display:flex; justify-content:space-between; align-items:center; padding:.45rem 0; font-size:.85rem; border-bottom:1px solid var(--border); }
.totals-row:last-child { border-bottom:none; font-weight:700; font-size:.9rem; color:var(--primary); }
.totals-row span:first-child { color:var(--on-surface-dim); }

/* ── Address block ── */
.addr-block { display:flex; align-items:flex-start; gap:.5rem; background:#fdf9f0; border-radius:10px; padding:.65rem .85rem; margin-bottom:1rem; font-size:.82rem; line-height:1.55; color:var(--on-surface); }
.addr-block .ms { color:var(--primary); flex-shrink:0; margin-top:.1rem; font-size:1.1rem; }

/* ── Note block ── */
.note-block { background:#fffde7; border-radius:10px; padding:.6rem .85rem; font-size:.82rem; color:#5a4e3a; margin-bottom:1rem; border-right:3px solid #fabd00; line-height:1.5; }

/* ── Save status button ── */
.save-btn { display:inline-flex; align-items:center; gap:.35rem; background:var(--primary); color:#fff; border:none; border-radius:9px; padding:.5rem 1.1rem; font-size:.82rem; font-family:inherit; font-weight:700; cursor:pointer; transition:background .2s; }
.save-btn:hover { background:var(--primary-light); }
.save-btn:disabled { opacity:.5; cursor:not-allowed; }

/* ── Pagination ── */
.pagination { padding:.85rem 1.25rem; display:flex; align-items:center; justify-content:space-between; border-top:1px solid var(--border); gap:.5rem; flex-wrap:wrap; }
.page-info { font-size:.8rem; color:var(--on-surface-dim); }
.page-btns { display:flex; gap:.35rem; }
.page-btn { min-width:34px; height:34px; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; font-size:.825rem; font-weight:600; border:1.5px solid var(--border); background:var(--surface); color:var(--on-surface); cursor:pointer; transition:all .15s; font-family:inherit; }
.page-btn:hover:not(:disabled) { border-color:var(--primary); color:var(--primary); }
.page-btn.active { background:var(--primary); color:#fff; border-color:var(--primary); }
.page-btn:disabled { opacity:.4; cursor:not-allowed; }

/* ── Empty ── */
.empty-state { padding:4rem 2rem; text-align:center; color:var(--on-surface-dim); }
.empty-state .ms { font-size:3rem; display:block; margin-bottom:1rem; color:#ccc; font-variation-settings:'FILL' 1,'wght' 300,'GRAD' 0,'opsz' 48; }

/* ── Loading ── */
.loading-row td { text-align:center; padding:3rem; color:var(--on-surface-dim); }

/* ── Toast ── */
#toast { position:fixed; bottom:1.5rem; left:50%; transform:translateX(-50%) translateY(20px); background:#1a1006; color:#fff; padding:.65rem 1.25rem; border-radius:999px; font-size:.85rem; font-weight:600; z-index:999; opacity:0; transition:all .3s; pointer-events:none; white-space:nowrap; }
#toast.show { opacity:1; transform:translateX(-50%) translateY(0); }

/* ── Responsive sidebar toggle ── */
.sidebar-toggle { display:none; background:none; border:none; cursor:pointer; padding:.25rem; color:var(--primary); }
@media (max-width:900px) {
  .sidebar { transform:translateX(240px); transition:transform .3s; }
  .sidebar.open { transform:translateX(0); }
  .main { margin-right:0; }
  .sidebar-toggle { display:flex; align-items:center; }
  .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:99; }
  .sidebar-overlay.open { display:block; }
}
</style>
</head>
<body>

<div class="layout">

  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <h1>متجر المصطفى</h1>
      <p>لوحة التحكم</p>
    </div>
    <nav class="sidebar-nav">
      <a href="index.php" class="nav-item">
        <span class="ms">dashboard</span>الرئيسية
      </a>
      <a href="orders.php" class="nav-item active">
        <span class="ms">package_2</span>الطلبات
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-username"><?= htmlspecialchars($adminFullname) ?></div>
      <span class="sidebar-role" style="<?= roleBadgeCssO($adminRole) ?>"><?= roleLabelArO($adminRole) ?></span>
      <form method="POST">
        <button type="submit" name="logout" class="logout-btn">
          <span class="ms" style="font-size:1.1rem;">logout</span>تسجيل الخروج
        </button>
      </form>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

  <!-- ══ MAIN ══ -->
  <main class="main">

    <!-- Topbar -->
    <div class="topbar">
      <div class="topbar-title">
        <button class="sidebar-toggle" onclick="toggleSidebar()" aria-label="القائمة">
          <span class="ms" style="font-size:1.5rem;color:var(--primary)">menu</span>
        </button>
        <span class="ms ms-fill" style="color:var(--primary)">package_2</span>
        إدارة الطلبات
      </div>
      <div style="font-size:.8rem;color:var(--on-surface-dim);" id="last-refresh">—</div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- Stats -->
      <div class="stats-grid" id="stats-grid">
        <div class="stat-card">
          <div class="stat-label"><span class="ms" style="color:var(--primary)">receipt_long</span>إجمالي الطلبات</div>
          <div class="stat-value" id="stat-total">—</div>
        </div>
        <div class="stat-card" style="--accent:#fabd00">
          <div class="stat-label"><span class="ms" style="color:#735c00">schedule</span>بانتظار التأكيد</div>
          <div class="stat-value" id="stat-pending" style="color:#735c00">—</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-label"><span class="ms" style="color:var(--blue)">inventory_2</span>جاري التجهيز</div>
          <div class="stat-value" id="stat-preparing">—</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-label"><span class="ms" style="color:var(--purple)">local_shipping</span>قيد التوصيل</div>
          <div class="stat-value" id="stat-shipping">—</div>
        </div>
        <div class="stat-card green">
          <div class="stat-label"><span class="ms" style="color:var(--green)">where_to_vote</span>تم التوصيل</div>
          <div class="stat-value" id="stat-delivered">—</div>
        </div>
        <div class="stat-card gold">
          <div class="stat-label"><span class="ms" style="color:var(--gold)">payments</span>الإيرادات (موصّل)</div>
          <div class="stat-value" id="stat-revenue">—</div>
        </div>
      </div>

      <!-- Filter tabs -->
      <div class="filter-tabs" id="filter-tabs">
        <button class="filter-tab active" data-status="">
          <span class="ms" style="font-size:.95rem">apps</span>الكل
          <span class="tab-count" id="tc-all">0</span>
        </button>
        <button class="filter-tab" data-status="pending">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#fabd00;display:inline-block"></span>
          بانتظار التأكيد
          <span class="tab-count" id="tc-pending">0</span>
        </button>
        <button class="filter-tab" data-status="confirmed">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#43a047;display:inline-block"></span>
          مؤكد
          <span class="tab-count" id="tc-confirmed">0</span>
        </button>
        <button class="filter-tab" data-status="preparing">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#1e88e5;display:inline-block"></span>
          جاري التجهيز
          <span class="tab-count" id="tc-preparing">0</span>
        </button>
        <button class="filter-tab" data-status="shipping">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#8e24aa;display:inline-block"></span>
          قيد التوصيل
          <span class="tab-count" id="tc-shipping">0</span>
        </button>
        <button class="filter-tab" data-status="delivered">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#2e7d32;display:inline-block"></span>
          تم التوصيل
          <span class="tab-count" id="tc-delivered">0</span>
        </button>
        <button class="filter-tab" data-status="cancelled">
          <span class="status-dot" style="width:8px;height:8px;border-radius:50%;background:#e53935;display:inline-block"></span>
          ملغي
          <span class="tab-count" id="tc-cancelled">0</span>
        </button>
      </div>

      <!-- Toolbar -->
      <div class="toolbar">
        <div class="search-wrap">
          <span class="ms">search</span>
          <input class="search-input" id="search-input" type="text" placeholder="رقم الطلب، اسم العميل، الهاتف…"
                 oninput="debounceSearch()">
        </div>
        <button class="refresh-btn" onclick="loadOrders()">
          <span class="ms" id="refresh-icon">refresh</span>
          تحديث
        </button>
      </div>

      <!-- Table -->
      <div class="table-card">
        <div class="table-wrap">
          <table id="orders-table">
            <thead>
              <tr>
                <th>رقم الطلب</th>
                <th>العميل</th>
                <th>الهاتف</th>
                <th>المنتجات</th>
                <th>الإجمالي</th>
                <th>الحالة</th>
                <th>التاريخ</th>
                <th>إجراءات</th>
              </tr>
            </thead>
            <tbody id="orders-body">
              <tr class="loading-row"><td colspan="8">
                <span class="ms" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#ccc">hourglass_empty</span>
                جاري التحميل…
              </td></tr>
            </tbody>
          </table>
        </div>
        <div class="pagination" id="pagination" style="display:none">
          <div class="page-info" id="page-info">—</div>
          <div class="page-btns" id="page-btns"></div>
        </div>
      </div>

    </div><!-- /content -->
  </main>
</div>

<div id="toast"></div>

<script>
const API_LIST   = '../../apis/orders/admin_list.php';
const API_UPDATE = '../../apis/orders/admin_update.php';

let currentPage   = 1;
let currentStatus = '';
let currentSearch = '';
let searchTimer   = null;
let isLoading     = false;
let expandedRows  = new Set();

const STATUS_LABELS = {
  pending:   'بانتظار التأكيد',
  confirmed: 'مؤكد',
  preparing: 'جاري التجهيز',
  shipping:  'قيد التوصيل',
  delivered: 'تم التوصيل',
  cancelled: 'ملغي',
};
const STATUS_CLASS = {
  pending:'s-pending', confirmed:'s-confirmed', preparing:'s-preparing',
  shipping:'s-shipping', delivered:'s-delivered', cancelled:'s-cancelled',
};

/* ── Filter tabs ── */
document.querySelectorAll('.filter-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = btn.dataset.status;
    currentPage   = 1;
    expandedRows.clear();
    loadOrders();
  });
});

/* ── Debounced search ── */
function debounceSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    currentSearch = document.getElementById('search-input').value.trim();
    currentPage   = 1;
    expandedRows.clear();
    loadOrders();
  }, 380);
}

/* ── Load orders ── */
async function loadOrders() {
  if (isLoading) return;
  isLoading = true;

  const icon = document.getElementById('refresh-icon');
  icon.style.animation = 'spin .7s linear infinite';
  icon.style.display   = 'inline-block';

  const body = document.getElementById('orders-body');
  body.innerHTML = '<tr class="loading-row"><td colspan="8"><span class="ms" style="font-size:1.5rem;display:block;margin-bottom:.4rem;color:#ccc">hourglass_empty</span>جاري التحميل…</td></tr>';

  const params = new URLSearchParams({
    page: currentPage,
    per_page: 25,
  });
  if (currentStatus) params.set('status', currentStatus);
  if (currentSearch) params.set('search', currentSearch);

  try {
    const res  = await fetch(API_LIST + '?' + params);
    const data = await res.json();

    if (!data.ok) throw new Error(data.error || 'خطأ في الخادم');

    updateStats(data);
    renderOrders(data.orders);
    renderPagination(data);

    document.getElementById('last-refresh').textContent =
      'آخر تحديث: ' + new Date().toLocaleTimeString('ar-EG');
  } catch(e) {
    body.innerHTML = `<tr class="loading-row"><td colspan="8" style="color:#b71c1c">
      <span class="ms" style="font-size:1.5rem;display:block;margin-bottom:.4rem">error</span>
      تعذر تحميل الطلبات — ${e.message}
    </td></tr>`;
  } finally {
    isLoading = false;
    icon.style.animation = '';
  }
}

/* ── Stats ── */
function updateStats(data) {
  const c = data.counts || {};
  const total = data.total || 0;

  document.getElementById('stat-total').textContent     = total;
  document.getElementById('stat-pending').textContent   = c.pending   || 0;
  document.getElementById('stat-preparing').textContent = c.preparing || 0;
  document.getElementById('stat-shipping').textContent  = c.shipping  || 0;
  document.getElementById('stat-delivered').textContent = c.delivered || 0;
  document.getElementById('stat-revenue').textContent   = fmtMoney(data.revenue || 0);

  // Tab counts
  const allCount = Object.values(c).reduce((a,b) => a+b, 0);
  document.getElementById('tc-all').textContent       = allCount;
  document.getElementById('tc-pending').textContent   = c.pending   || 0;
  document.getElementById('tc-confirmed').textContent = c.confirmed || 0;
  document.getElementById('tc-preparing').textContent = c.preparing || 0;
  document.getElementById('tc-shipping').textContent  = c.shipping  || 0;
  document.getElementById('tc-delivered').textContent = c.delivered || 0;
  document.getElementById('tc-cancelled').textContent = c.cancelled || 0;
}

/* ── Render orders ── */
function renderOrders(orders) {
  const body = document.getElementById('orders-body');
  if (!orders || orders.length === 0) {
    body.innerHTML = `<tr><td colspan="8">
      <div class="empty-state">
        <span class="ms">package_2</span>
        <p>لا توجد طلبات تطابق البحث</p>
      </div>
    </td></tr>`;
    document.getElementById('pagination').style.display = 'none';
    return;
  }

  body.innerHTML = '';
  orders.forEach((o, idx) => {
    const rowId    = `row-${o.id}`;
    const detailId = `detail-${o.id}`;
    const isOpen   = expandedRows.has(o.id);

    const statusCls = STATUS_CLASS[o.status] || 's-pending';
    const statusLbl = STATUS_LABELS[o.status] || o.status;

    const itemCount = o.items ? o.items.length : 0;
    const itemSummary = o.items && o.items.length > 0
      ? o.items.slice(0,2).map(i => `<span style="font-size:.78rem;color:var(--on-surface-dim)">${escHtml(i.name)} ×${i.qty}</span>`).join('<br>')
        + (o.items.length > 2 ? `<br><span style="font-size:.72rem;color:#aaa">+${o.items.length-2} أخرى</span>` : '')
      : '<span style="color:#ccc;font-size:.75rem">—</span>';

    const guestBadge = o.is_guest
      ? '<span class="guest-badge"><span class="ms" style="font-size:.85rem">person_off</span>زائر</span>'
      : '';

    const dateFmt = fmtDate(o.created_at);

    const mainRow = document.createElement('tr');
    mainRow.className = 'order-row' + (isOpen ? ' expanded' : '');
    mainRow.id        = rowId;
    mainRow.dataset.id = o.id;
    mainRow.innerHTML = `
      <td>
        <div style="font-family:monospace;font-size:.8rem;font-weight:700;color:var(--primary);direction:ltr">${escHtml(o.order_number)}</div>
        ${guestBadge}
      </td>
      <td style="font-weight:600">${escHtml(o.customer_name)}</td>
      <td style="font-family:monospace;font-size:.82rem;direction:ltr">${escHtml(o.customer_phone)}</td>
      <td style="line-height:1.5">${itemSummary}</td>
      <td style="font-weight:700;color:var(--primary)">${fmtMoney(o.total)}</td>
      <td><span class="status-badge ${statusCls}"><span class="status-dot"></span>${statusLbl}</span></td>
      <td style="white-space:nowrap;color:var(--on-surface-dim);font-size:.78rem">${dateFmt}</td>
      <td>
        <button class="action-btn" onclick="toggleDetail(${o.id}, event)" title="التفاصيل">
          <span class="ms" id="expand-icon-${o.id}">${isOpen ? 'expand_less' : 'expand_more'}</span>
        </button>
      </td>
    `;
    mainRow.addEventListener('click', (e) => {
      if (!e.target.closest('button') && !e.target.closest('select')) {
        toggleDetail(o.id, e);
      }
    });

    const detailRow = document.createElement('tr');
    detailRow.className = 'detail-row' + (isOpen ? '' : ' hidden');
    detailRow.id        = detailId;
    detailRow.innerHTML = `<td colspan="8"><div class="detail-inner">${buildDetail(o)}</div></td>`;

    body.appendChild(mainRow);
    body.appendChild(detailRow);
  });
}

/* ── Build detail panel ── */
function buildDetail(o) {
  let html = `<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start">`;

  // Left: products + totals
  html += `<div>`;
  html += `<p style="font-size:.75rem;font-weight:700;color:var(--on-surface-dim);margin-bottom:.6rem;text-transform:uppercase;letter-spacing:.06em">المنتجات</p>`;
  html += `<div class="prod-list">`;
  (o.items || []).forEach(item => {
    const imgHtml = item.img
      ? `<img src="${escHtml(item.img)}" style="width:100%;height:100%;object-fit:cover;border-radius:7px">`
      : `<span class="ms ms-fill">grocery</span>`;
    html += `<div class="prod-item">
      <div class="prod-img">${imgHtml}</div>
      <div style="flex:1;min-width:0">
        <div class="prod-name">${escHtml(item.name)}</div>
        <div class="prod-meta">الكمية: ${item.qty}${item.weight ? ' · ' + escHtml(item.weight) : ''}</div>
      </div>
      <div class="prod-price">${fmtMoney(item.price * item.qty)}</div>
    </div>`;
  });
  html += `</div>`;

  // Totals
  html += `<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.75rem .85rem;">`;
  html += `<div class="totals-row"><span>المجموع الفرعي</span><span>${fmtMoney(o.subtotal)}</span></div>`;
  html += `<div class="totals-row"><span>الشحن</span><span>${fmtMoney(o.shipping)}</span></div>`;
  if (o.discount > 0) {
    html += `<div class="totals-row" style="color:var(--green)"><span>خصم المحفظة</span><span>- ${fmtMoney(o.discount)}</span></div>`;
  }
  html += `<div class="totals-row"><span>الإجمالي</span><span>${fmtMoney(o.total)}</span></div>`;
  html += `</div>`;
  html += `</div>`; // end left

  // Right: info + status update
  html += `<div>`;

  // Address
  if (o.address) {
    html += `<p style="font-size:.75rem;font-weight:700;color:var(--on-surface-dim);margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.06em">عنوان التوصيل</p>`;
    html += `<div class="addr-block"><span class="ms ms-fill">location_on</span>${escHtml(o.address)}</div>`;
  }

  // Note
  if (o.note) {
    html += `<div class="note-block"><strong>ملاحظة العميل:</strong> ${escHtml(o.note)}</div>`;
  }

  // Customer info
  html += `<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.75rem .85rem;margin-bottom:1rem;">`;
  html += `<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.5rem">
    <span class="ms" style="color:var(--primary);font-size:1.1rem">person</span>
    <span style="font-weight:700;font-size:.85rem">${escHtml(o.customer_name)}</span>
    ${o.is_guest ? '<span class="guest-badge"><span class="ms" style="font-size:.8rem">person_off</span>زائر</span>' : ''}
  </div>`;
  html += `<a href="tel:${escHtml(o.customer_phone)}" style="display:flex;align-items:center;gap:.5rem;text-decoration:none;color:var(--blue);font-size:.82rem;font-weight:600;font-family:monospace;direction:ltr">
    <span class="ms" style="font-size:1rem;color:var(--blue)">call</span>
    ${escHtml(o.customer_phone)}
  </a>`;
  html += `</div>`;

  // Status update
  html += `<div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:.85rem;">`;
  html += `<p style="font-size:.75rem;font-weight:700;color:var(--on-surface-dim);margin-bottom:.65rem;text-transform:uppercase;letter-spacing:.06em">تغيير الحالة</p>`;
  html += `<div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">`;
  html += `<select class="status-select" id="sel-${o.id}">`;
  ['pending','confirmed','preparing','shipping','delivered','cancelled'].forEach(s => {
    html += `<option value="${s}"${o.status === s ? ' selected' : ''}>${STATUS_LABELS[s]}</option>`;
  });
  html += `</select>`;
  html += `<button class="save-btn" onclick="saveStatus(${o.id})">
    <span class="ms" style="font-size:1rem">save</span>حفظ
  </button>`;
  html += `</div>`;
  html += `</div>`;

  html += `</div>`; // end right
  html += `</div>`; // end grid

  return html;
}

/* ── Toggle detail row ── */
function toggleDetail(orderId, e) {
  if (e) e.stopPropagation();
  const detailRow = document.getElementById(`detail-${orderId}`);
  const mainRow   = document.querySelector(`tr[data-id="${orderId}"]`);
  const icon      = document.getElementById(`expand-icon-${orderId}`);

  if (!detailRow) return;

  const isHidden = detailRow.classList.contains('hidden');
  if (isHidden) {
    detailRow.classList.remove('hidden');
    mainRow && mainRow.classList.add('expanded');
    if (icon) icon.textContent = 'expand_less';
    expandedRows.add(orderId);
  } else {
    detailRow.classList.add('hidden');
    mainRow && mainRow.classList.remove('expanded');
    if (icon) icon.textContent = 'expand_more';
    expandedRows.delete(orderId);
  }
}

/* ── Save status ── */
async function saveStatus(orderId) {
  const sel = document.getElementById(`sel-${orderId}`);
  if (!sel) return;

  const newStatus = sel.value;
  const btn       = sel.nextElementSibling;
  btn.disabled    = true;
  btn.textContent = 'جاري…';

  try {
    const res  = await fetch(API_UPDATE, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: orderId, status: newStatus }),
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'خطأ');

    showToast('✓ تم تحديث الحالة بنجاح');

    // Update badge in main row
    const mainRow = document.querySelector(`tr[data-id="${orderId}"]`);
    if (mainRow) {
      const badge = mainRow.querySelector('.status-badge');
      if (badge) {
        Object.keys(STATUS_CLASS).forEach(k => badge.classList.remove(STATUS_CLASS[k]));
        badge.classList.add(STATUS_CLASS[newStatus] || 's-pending');
        const lbl = badge.querySelector(':not(.status-dot)');
        if (lbl) lbl.textContent = STATUS_LABELS[newStatus] || newStatus;
      }
    }

    // Refresh stats silently
    loadOrders();
  } catch(e) {
    showToast('✗ ' + e.message, true);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<span class="ms" style="font-size:1rem">save</span>حفظ';
  }
}

/* ── Pagination ── */
function renderPagination(data) {
  const wrap = document.getElementById('pagination');
  const info = document.getElementById('page-info');
  const btns = document.getElementById('page-btns');

  if (data.pages <= 1 && data.total <= 25) {
    wrap.style.display = 'none';
    return;
  }

  wrap.style.display = 'flex';

  const from = ((data.page-1) * data.per_page) + 1;
  const to   = Math.min(data.page * data.per_page, data.total);
  info.textContent = `عرض ${from}–${to} من ${data.total} طلب`;

  btns.innerHTML = '';

  const prevBtn = document.createElement('button');
  prevBtn.className = 'page-btn';
  prevBtn.innerHTML = '<span class="ms" style="font-size:1.1rem">chevron_right</span>';
  prevBtn.disabled  = data.page <= 1;
  prevBtn.onclick   = () => { currentPage = data.page - 1; loadOrders(); };
  btns.appendChild(prevBtn);

  for (let p = 1; p <= data.pages; p++) {
    if (data.pages > 7 && Math.abs(p - data.page) > 2 && p !== 1 && p !== data.pages) {
      if (p === 2 || p === data.pages - 1) {
        const ellipsis = document.createElement('button');
        ellipsis.className = 'page-btn';
        ellipsis.textContent = '…';
        ellipsis.disabled = true;
        btns.appendChild(ellipsis);
      }
      continue;
    }
    const pb = document.createElement('button');
    pb.className   = 'page-btn' + (p === data.page ? ' active' : '');
    pb.textContent = p;
    pb.onclick     = () => { currentPage = p; loadOrders(); };
    btns.appendChild(pb);
  }

  const nextBtn = document.createElement('button');
  nextBtn.className = 'page-btn';
  nextBtn.innerHTML = '<span class="ms" style="font-size:1.1rem">chevron_left</span>';
  nextBtn.disabled  = data.page >= data.pages;
  nextBtn.onclick   = () => { currentPage = data.page + 1; loadOrders(); };
  btns.appendChild(nextBtn);
}

/* ── Helpers ── */
function fmtMoney(v) {
  return (parseFloat(v)||0).toLocaleString('ar-EG', { style:'decimal', minimumFractionDigits:0, maximumFractionDigits:2 }) + ' ر.ي';
}
function fmtDate(dt) {
  if (!dt) return '—';
  try {
    return new Date(dt).toLocaleDateString('ar-EG', { year:'numeric', month:'short', day:'numeric' });
  } catch { return dt.substring(0,10); }
}
function escHtml(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let toastTimer;
function showToast(msg, isErr) {
  clearTimeout(toastTimer);
  const t = document.getElementById('toast');
  t.textContent  = msg;
  t.style.background = isErr ? '#b71c1c' : '#1a1006';
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── Sidebar toggle (mobile) ── */
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

/* ── Spin animation ── */
const style = document.createElement('style');
style.textContent = '@keyframes spin { to { transform:rotate(360deg); } }';
document.head.appendChild(style);

/* ── Init ── */
loadOrders();

// Auto-refresh every 60 seconds
setInterval(loadOrders, 60000);
</script>
</body>
</html>
