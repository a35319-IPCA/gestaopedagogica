<?php
// gestor_nav.php — include em todas as páginas do gestor
// Requer: $active_page (string) e $email (string) já definidos

$nav_items = [
    ['href' => 'planoestudos.php',       'icon' => 'fa-inbox',        'label' => 'Candidaturas', 'id' => 'candidaturas'],
    ['href' => 'gestor_fichas.php',      'icon' => 'fa-id-card',      'label' => 'Fichas',       'id' => 'fichas'],
    ['href' => 'gestor_cursos.php',      'icon' => 'fa-book',         'label' => 'Cursos',       'id' => 'cursos'],
    ['href' => 'gestor_disciplinas.php', 'icon' => 'fa-chalkboard',   'label' => 'Disciplinas',  'id' => 'disciplinas'],
    ['href' => 'gestor_alunos.php',      'icon' => 'fa-users',        'label' => 'Alunos',       'id' => 'alunos'],
];
?>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}
.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar-left{display:flex;align-items:center;gap:10px;}
.topbar-left i.logo{color:#3b82f6;font-size:18px;}
.topbar-left span{font-size:15px;font-weight:500;color:#fff;}
.topbar-right{display:flex;align-items:center;gap:12px;}
.user-label{font-size:13px;color:#6b7280;}
.btn-logout{background:#ef444420;color:#ef4444;border:1px solid #ef444440;text-decoration:none;padding:6px 12px;border-radius:6px;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.btn-logout:hover{background:#ef444440;}
.subnav{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;display:flex;gap:2px;}
.subnav a{color:#6b7280;text-decoration:none;padding:12px 14px;font-size:14px;display:flex;align-items:center;gap:7px;border-bottom:2px solid transparent;transition:.2s;}
.subnav a:hover{color:#e4e6eb;}
.subnav a.active{color:#3b82f6;border-bottom-color:#3b82f6;}
.page{max-width:1000px;margin:0 auto;padding:2rem;}
/* Cards */
.card{background:#1a1d24;border-radius:12px;padding:1.5rem;border:1px solid #1f2937;margin-bottom:1.5rem;}
.card-title{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.25rem;}
/* Formulários */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.form-group{margin-bottom:1rem;}
.form-group label{display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
input[type=text],input[type=email],input[type=date],select{width:100%;padding:9px 11px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;transition:.2s;font-family:inherit;}
input:focus,select:focus{outline:none;border-color:#3b82f6;background:#2f3540;}
select option{background:#2a2f38;}
/* Botões */
.btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-secondary{background:#2a2f38;color:#e4e6eb;}
.btn-secondary:hover{background:#374151;}
.btn-sm{padding:6px 12px;font-size:12px;}
.btn-danger-sm{background:none;border:none;color:#6b7280;cursor:pointer;padding:5px 7px;border-radius:6px;transition:.2s;}
.btn-danger-sm:hover{background:#7f1d1d30;color:#f87171;}
.btn-edit-sm{background:none;border:none;color:#6b7280;cursor:pointer;padding:5px 7px;border-radius:6px;transition:.2s;text-decoration:none;display:inline-flex;align-items:center;}
.btn-edit-sm:hover{background:#1e3a5f30;color:#60a5fa;}
/* Tabelas */
table{width:100%;border-collapse:collapse;font-size:14px;}
th{text-align:left;padding:9px 12px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;border-bottom:1px solid #1f2937;}
td{padding:10px 12px;border-bottom:1px solid #111318;color:#9ca3af;vertical-align:middle;}
td.td-main{color:#e4e6eb;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#111318;}
.badge-id{background:#2a2f38;color:#6b7280;padding:2px 7px;border-radius:5px;font-size:12px;}
/* Edit section */
.edit-box{background:#2a2f38;border-radius:10px;padding:1.25rem;margin-top:1rem;}
.edit-box h3{font-size:14px;color:#e4e6eb;margin-bottom:1rem;}
/* Alerts */
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
hr{border:none;border-top:1px solid #1f2937;margin:1.25rem 0;}
@media(max-width:600px){.form-row{grid-template-columns:1fr;}}
</style>

<div class="topbar">
    <div class="topbar-left">
        <i class="fas fa-chalkboard-teacher logo"></i>
        <span>Painel do Gestor</span>
    </div>
    <div class="topbar-right">
        <span class="user-label"><?= htmlspecialchars($_SESSION['user'] ?? '') ?></span>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<div class="subnav">
    <?php foreach ($nav_items as $item): ?>
    <a href="<?= $item['href'] ?>" class="<?= $active_page === $item['id'] ? 'active' : '' ?>">
        <i class="fas <?= $item['icon'] ?>"></i>
        <?= $item['label'] ?>
        <?php if ($item['id'] === 'candidaturas' && isset($n_pendentes) && $n_pendentes > 0): ?>
        <span style="background:#ef4444;color:#fff;padding:1px 6px;border-radius:10px;font-size:11px;font-weight:600"><?= $n_pendentes ?></span>
        <?php endif; ?>
        <?php if ($item['id'] === 'fichas' && isset($n_submetidas) && $n_submetidas > 0): ?>
        <span style="background:#ef4444;color:#fff;padding:1px 6px;border-radius:10px;font-size:11px;font-weight:600"><?= $n_submetidas ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>