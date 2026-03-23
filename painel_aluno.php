<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 2) { header("Location: planoestudos.php"); exit; }

// ── Ficha ────────────────────────────────────────────────────
$fs = $conn->prepare("SELECT f.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla FROM ficha_aluno f LEFT JOIN cursos c ON f.curso_pretendido = c.Id_cursos WHERE f.aluno_email = ?");
$fs->bind_param("s", $email); $fs->execute();
$ficha = $fs->get_result()->fetch_assoc();

// ── Matrícula ────────────────────────────────────────────────
$ms = $conn->prepare("SELECT m.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla FROM pedido_matricula m LEFT JOIN cursos c ON m.curso_id = c.Id_cursos WHERE m.aluno_email = ? ORDER BY m.created_at DESC LIMIT 1");
$ms->bind_param("s", $email); $ms->execute();
$matricula = $ms->get_result()->fetch_assoc();

// ── Curso do aluno (só se matrícula aprovada) ────────────────
$matricula_aprovada = $matricula && $matricula['estado'] === 'aprovado';
$curso_id = $curso_nome = $curso_sigla = null;
if ($matricula_aprovada) {
    $curso_id = $matricula['curso_id']; $curso_nome = $matricula['curso_nome']; $curso_sigla = $matricula['curso_sigla'];
}

// ── Disciplinas + notas (só com matrícula aprovada) ──────────
$disciplinas_data = [];
if ($matricula_aprovada && $curso_id) {
    $ds = $conn->prepare("SELECT d.Id_disciplina, d.nome_disciplina FROM plano_estudos p JOIN disciplinas d ON p.disciplinas = d.Id_disciplina WHERE p.cursos = ? ORDER BY d.nome_disciplina");
    $ds->bind_param("i", $curso_id); $ds->execute();
    $disc_list = $ds->get_result();
    while ($d = $disc_list->fetch_assoc()) {
        $ns = $conn->prepare("SELECT a.nota, p.epoca, p.ano_letivo FROM avaliacoes a JOIN pautas p ON a.pauta_id = p.id WHERE p.disciplina_id = ? AND a.aluno_email = ? ORDER BY p.ano_letivo DESC, FIELD(p.epoca,'Normal','Recurso','Especial')");
        $ns->bind_param("is", $d['Id_disciplina'], $email); $ns->execute();
        $notas_res = $ns->get_result();
        $notas = []; $melhor = null; $aprovado = false;
        while ($n = $notas_res->fetch_assoc()) {
            $notas[] = $n;
            if ($n['nota'] !== null) {
                if ($melhor === null || $n['nota'] > $melhor) $melhor = $n['nota'];
                if ($n['nota'] >= 10) $aprovado = true;
            }
        }
        $estado = count($notas) > 0 ? ($aprovado ? 'aprovado' : 'reprovado') : 'sem_nota';
        $disciplinas_data[] = ['id'=>$d['Id_disciplina'],'nome'=>$d['nome_disciplina'],'notas'=>$notas,'melhor'=>$melhor,'aprovado'=>$aprovado,'estado'=>$estado];
    }
}

// ── Estatísticas ─────────────────────────────────────────────
$total      = count($disciplinas_data);
$aprovadas  = count(array_filter($disciplinas_data, fn($d) => $d['estado'] === 'aprovado'));
$reprovadas = count(array_filter($disciplinas_data, fn($d) => $d['estado'] === 'reprovado'));
$sem_nota   = $total - $aprovadas - $reprovadas;
$notas_val  = array_filter(array_column($disciplinas_data, 'melhor'), fn($n) => $n !== null);
$media      = count($notas_val) > 0 ? round(array_sum($notas_val) / count($notas_val), 1) : null;

// ── POST guardar ficha (só se rascunho ou rejeitada) ─────────
$erro = ''; $sucesso = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_ficha'])) {
    $estado_ficha = $ficha['estado'] ?? 'rascunho';
    if ($ficha && !in_array($estado_ficha, ['rascunho','rejeitada'])) {
        $erro = "Não podes editar a ficha no estado atual.";
    } else {
    $nome_aluno = trim($_POST['nome_aluno'] ?? ''); $morada = trim($_POST['morada'] ?? '');
    $telefone   = trim($_POST['telefone'] ?? ''); $data_nasc = trim($_POST['data_nascimento'] ?? '');
    $curso_pret = (int)($_POST['curso_pretendido'] ?? 0);
    if (!$nome_aluno || !$morada || !$telefone || !$data_nasc || !$curso_pret) {
        $erro = "Preenche todos os campos obrigatórios.";
    } elseif (!preg_match('/^\d{9}$/', $telefone)) {
        $erro = "O telefone deve ter 9 dígitos.";
    } else {
        $foto_path = $ficha['foto_path'] ?? null;
        if (!empty($_FILES['foto']['name'])) {
            $allowed = ['image/jpeg','image/png']; $max = 2*1024*1024;
            if (!in_array($_FILES['foto']['type'], $allowed))   { $erro = "A foto deve ser JPG ou PNG."; }
            elseif ($_FILES['foto']['size'] > $max)             { $erro = "A foto não pode ultrapassar 2 MB."; }
            elseif ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) { $erro = "Erro no upload da foto."; }
            else {
                if (!is_dir('uploads/fotos')) mkdir('uploads/fotos', 0755, true);
                if ($foto_path && file_exists($foto_path)) unlink($foto_path);
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $dest = 'uploads/fotos/' . uniqid('foto_') . '.' . $ext;
                if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) { $erro = "Erro ao guardar a foto."; }
                else { $foto_path = $dest; }
            }
        }
        if (!$erro) {
            if ($ficha) {
                $upd = $conn->prepare("UPDATE ficha_aluno SET nome_aluno=?,morada=?,telefone=?,data_nascimento=?,curso_pretendido=?,foto_path=?,updated_at=NOW() WHERE aluno_email=?");
                $upd->bind_param("ssssiss", $nome_aluno,$morada,$telefone,$data_nasc,$curso_pret,$foto_path,$email);
                $upd->execute();
            } else {
                $ins = $conn->prepare("INSERT INTO ficha_aluno (aluno_email,nome_aluno,morada,telefone,data_nascimento,curso_pretendido,foto_path) VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param("ssssiss",$email,$nome_aluno,$morada,$telefone,$data_nasc,$curso_pret,$foto_path);
                $ins->execute();
            }
            header("Location: painel_aluno.php?tab=ficha&ok=1"); exit;
        }
    }
} // fim guardar_ficha
}

// ── POST submeter ficha ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submeter_ficha'])) {
    if (!$ficha) {
        $erro = "Guarda a ficha antes de submeter.";
    } elseif (!in_array($ficha['estado'], ['rascunho','rejeitada'])) {
        $erro = "A ficha já foi submetida.";
    } else {
        $sub = $conn->prepare("UPDATE ficha_aluno SET estado='submetida', updated_at=NOW() WHERE aluno_email=?");
        $sub->bind_param("s", $email); $sub->execute();
        header("Location: painel_aluno.php?tab=ficha&submetida=1"); exit;
    }
}

$cursos_list = $conn->query("SELECT Id_cursos, Nome, Sigla FROM cursos ORDER BY Nome");
$active_tab  = $_GET['tab'] ?? 'dashboard';
if (isset($_GET['ok']))       $sucesso = "Ficha guardada com sucesso!";
if (isset($_GET['submetida'])) $sucesso = "Ficha submetida! Aguarda a validação do gestor.";

function nb($nota) {
    if ($nota === null) return '<span class="nb nb-vazio">—</span>';
    return '<span class="nb '.($nota>=10?'nb-ok':'nb-fail').'">'.number_format($nota,1).'</span>';
}
function estado_badge($e) {
    $map = ['aprovado'=>['est-aprovado','fa-check','Aprovado'],'reprovado'=>['est-reprovado','fa-times','Reprovado'],'sem_nota'=>['est-sem','fa-clock','Por avaliar']];
    [$cls,$ico,$lbl] = $map[$e];
    return "<span class='estado-badge $cls'><i class='fas $ico'></i> $lbl</span>";
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel do Aluno</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}
.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar-brand{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:500;color:#fff;}
.topbar-brand i{color:#3b82f6;}
.curso-pill{background:#1e3a5f;color:#60a5fa;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:400;}
.topbar-right{display:flex;align-items:center;gap:12px;}
.user-pill{font-size:13px;color:#6b7280;}
.btn-logout{background:#ef444420;color:#ef4444;border:1px solid #ef444440;text-decoration:none;padding:6px 12px;border-radius:6px;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.btn-logout:hover{background:#ef444440;}
.subnav{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;display:flex;gap:2px;}
.subnav a{color:#6b7280;text-decoration:none;padding:12px 15px;font-size:14px;display:flex;align-items:center;gap:7px;border-bottom:2px solid transparent;transition:.2s;white-space:nowrap;}
.subnav a:hover{color:#e4e6eb;}
.subnav a.active{color:#3b82f6;border-bottom-color:#3b82f6;}
.page{max-width:960px;margin:0 auto;padding:2rem;}
/* Stat cards */
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem;}
.stat-card{background:#1a1d24;border-radius:12px;padding:1.25rem 1.5rem;border:1px solid #1f2937;}
.stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:.4rem;}
.stat-value{font-size:2rem;font-weight:700;line-height:1;}
.sv-blue{color:#3b82f6;} .sv-green{color:#34d399;} .sv-red{color:#f87171;} .sv-amber{color:#fbbf24;}
.stat-sub{font-size:12px;color:#4b5563;margin-top:.35rem;}
.bar-wrap{background:#2a2f38;border-radius:20px;height:5px;width:100%;margin-top:.6rem;}
.bar{height:5px;border-radius:20px;}
/* Cards */
.card{background:#1a1d24;border-radius:12px;padding:1.5rem;border:1px solid #1f2937;margin-bottom:1.25rem;}
.card-title{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}
/* Tables */
table{width:100%;border-collapse:collapse;}
th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;padding:9px 14px;text-align:left;border-bottom:1px solid #1f2937;}
td{padding:11px 14px;border-bottom:1px solid #111318;vertical-align:middle;color:#9ca3af;font-size:14px;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#111318;}
td.main{color:#e4e6eb;font-weight:500;}
/* Notas */
.nb{display:inline-block;padding:3px 9px;border-radius:6px;font-size:13px;font-weight:600;min-width:40px;text-align:center;}
.nb-ok{background:#065f4630;color:#34d399;}
.nb-fail{background:#7f1d1d30;color:#f87171;}
.nb-vazio{background:#2a2f38;color:#4b5563;}
/* Estado badges */
.estado-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.est-aprovado{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.est-reprovado{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}
.est-sem{background:#2a2f38;color:#6b7280;border:1px solid #374151;}
/* Disc cards */
.disc-card{background:#1a1d24;border-radius:10px;border:1px solid #1f2937;margin-bottom:.75rem;overflow:hidden;}
.disc-header{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;cursor:pointer;transition:.15s;}
.disc-header:hover{background:#1e2128;}
.disc-nome{font-size:14px;font-weight:600;color:#fff;}
.disc-body{padding:0 1.25rem 1rem;border-top:1px solid #111318;}
/* Matrícula */
.badge{display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.badge-pendente{background:#78350f30;color:#fbbf24;border:1px solid #78350f50;}
.badge-aprovado{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-rejeitado{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}
/* Formulário */
.field-group{margin-bottom:1rem;}
.field-group label{display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.field-group label span{color:#ef4444;}
input[type=text],input[type=tel],input[type=date],select,textarea{width:100%;padding:9px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;transition:.2s;font-family:inherit;}
input:focus,select:focus,textarea:focus{outline:none;border-color:#3b82f6;background:#2f3540;}
select option{background:#2a2f38;}
textarea{resize:vertical;min-height:65px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem;}
.foto-row{display:flex;align-items:center;gap:1.25rem;margin-bottom:1rem;}
.foto-thumb{width:80px;height:80px;border-radius:8px;border:2px solid #374151;background:#2a2f38;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;}
.foto-thumb img{width:100%;height:100%;object-fit:cover;}
.foto-thumb i{font-size:26px;color:#4b5563;}
.upload-btn{position:relative;}
.upload-btn input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;}
.upload-btn label{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#2a2f38;border:1px dashed #4b5563;border-radius:8px;font-size:13px;color:#9ca3af;cursor:pointer;transition:.2s;}
.upload-btn label:hover{border-color:#3b82f6;color:#3b82f6;}
.btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-sm{padding:6px 12px;font-size:12px;}
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.info-banner{background:#1e3a5f20;border:1px solid #1e40af40;border-radius:8px;padding:10px 14px;font-size:13px;color:#93c5fd;display:flex;gap:8px;margin-bottom:1rem;}
.info-banner i{flex-shrink:0;margin-top:1px;}
.empty-state{text-align:center;padding:2.5rem;color:#4b5563;}
.empty-state i{font-size:32px;margin-bottom:.75rem;display:block;}
.obs-box{background:#2a2f38;border-left:3px solid #ef4444;border-radius:6px;padding:10px 14px;font-size:13px;color:#f87171;margin-top:.75rem;}
@media(max-width:640px){.stats-grid{grid-template-columns:1fr 1fr;}.form-row{grid-template-columns:1fr;}}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <i class="fas fa-graduation-cap"></i>
        <?= htmlspecialchars($ficha['nome_aluno'] ?? $email) ?>
        <?php if ($curso_sigla): ?>
        <span class="curso-pill"><?= htmlspecialchars($curso_sigla) ?></span>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <span class="user-pill"><?= htmlspecialchars($email) ?></span>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<div class="subnav">
    <a href="?tab=dashboard"  class="<?= $active_tab==='dashboard'  ? 'active':'' ?>"><i class="fas fa-chart-bar"></i> Dashboard</a>
    <a href="?tab=disciplinas" class="<?= $active_tab==='disciplinas'? 'active':'' ?>"><i class="fas fa-book-open"></i> Disciplinas</a>
    <a href="?tab=matricula"  class="<?= $active_tab==='matricula'  ? 'active':'' ?>"><i class="fas fa-file-alt"></i> Matrícula</a>
    <a href="?tab=ficha"      class="<?= $active_tab==='ficha'      ? 'active':'' ?>"><i class="fas fa-id-card"></i> Ficha</a>
</div>

<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<?php if ($active_tab === 'dashboard'): ?>
<!-- ══ DASHBOARD ══════════════════════════════════════════════ -->

<?php if (!$matricula_aprovada): ?>

<?php if (!$matricula): ?>
<div class="info-banner">
    <i class="fas fa-info-circle"></i>
    <span>Ainda não submeteste nenhuma candidatura. <a href="?tab=ficha" style="color:#60a5fa">Preenche a ficha</a> e <a href="matricula_nova.php" style="color:#60a5fa">submete uma candidatura</a>.</span>
</div>
<?php elseif ($matricula['estado'] === 'pendente'): ?>
<div class="info-banner">
    <i class="fas fa-clock"></i>
    <span>A tua candidatura ao curso <strong><?= htmlspecialchars($matricula['curso_nome']) ?></strong> está a aguardar aprovação. As disciplinas e notas ficarão disponíveis após a matrícula ser aceite.</span>
</div>
<?php elseif ($matricula['estado'] === 'rejeitado'): ?>
<div class="info-banner" style="border-color:#7f1d1d50;color:#f87171;background:#7f1d1d20;">
    <i class="fas fa-times-circle"></i>
    <span>A tua candidatura foi recusada. <a href="matricula_nova.php" style="color:#f87171;font-weight:500">Submete uma nova candidatura</a> para teres acesso às disciplinas.</span>
</div>
<?php endif; ?>

<?php else: ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Disciplinas</div>
        <div class="stat-value sv-blue"><?= $total ?></div>
        <div class="stat-sub"><?= htmlspecialchars($curso_nome) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Aprovadas</div>
        <div class="stat-value sv-green"><?= $aprovadas ?></div>
        <div class="stat-sub"><?= $total > 0 ? round($aprovadas/$total*100) : 0 ?>% do curso</div>
        <div class="bar-wrap"><div class="bar" style="width:<?= $total>0?round($aprovadas/$total*100):0 ?>%;background:#34d399"></div></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Reprovadas</div>
        <div class="stat-value sv-red"><?= $reprovadas ?></div>
        <div class="stat-sub"><?= $sem_nota ?> por avaliar</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Média geral</div>
        <div class="stat-value <?= $media===null?'sv-amber':($media>=10?'sv-green':'sv-red') ?>">
            <?= $media !== null ? number_format($media,1) : '—' ?>
        </div>
        <?php if ($media !== null): ?>
        <div class="bar-wrap"><div class="bar" style="width:<?= min(100,$media/20*100) ?>%;background:<?= $media>=10?'#34d399':'#f87171' ?>"></div></div>
        <?php endif; ?>
        <div class="stat-sub">Escala 0–20</div>
    </div>
</div>

<?php if (count($disciplinas_data) > 0): ?>
<div class="card">
    <div class="card-title"><i class="fas fa-table" style="color:#3b82f6"></i> Resumo das disciplinas</div>
    <table>
        <thead><tr><th>Disciplina</th><th>Melhor nota</th><th>Avaliações</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($disciplinas_data as $d): ?>
        <tr>
            <td class="main"><?= htmlspecialchars($d['nome']) ?></td>
            <td><?= nb($d['melhor']) ?></td>
            <td>
                <?php if (empty($d['notas'])): ?>
                <span style="font-size:12px;color:#4b5563">Nenhuma</span>
                <?php else: foreach ($d['notas'] as $n): ?>
                <span style="font-size:11px;color:#6b7280;margin-right:4px"><?= $n['epoca'][0] ?>: <?= nb($n['nota']) ?></span>
                <?php endforeach; endif; ?>
            </td>
            <td><?= estado_badge($d['estado']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty-state"><i class="fas fa-book-open"></i><div>Nenhuma disciplina encontrada para o teu curso.</div></div>
<?php endif; ?>

<?php endif; // fim else matricula_aprovada ?>

<?php elseif ($active_tab === 'disciplinas'): ?>
<!-- ══ DISCIPLINAS ════════════════════════════════════════════ -->

<?php if (!$matricula_aprovada): ?>
<div class="info-banner">
    <i class="fas fa-lock"></i>
    <span>As disciplinas ficam disponíveis após a tua matrícula ser aceite pelo gestor.</span>
</div>
<?php elseif ($total > 0): ?>
<div class="stats-grid" style="grid-template-columns:1fr 1fr;margin-bottom:1.5rem;">
    <div class="stat-card">
        <div class="stat-label">Progresso</div>
        <div class="stat-value sv-green"><?= $total>0?round($aprovadas/$total*100):0 ?>%</div>
        <div class="bar-wrap"><div class="bar" style="width:<?= $total>0?round($aprovadas/$total*100):0 ?>%;background:#34d399"></div></div>
        <div class="stat-sub" style="margin-top:.5rem"><?= $aprovadas ?> de <?= $total ?> aprovadas</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Média das melhores notas</div>
        <div class="stat-value <?= $media===null?'sv-amber':($media>=10?'sv-green':'sv-red') ?>"><?= $media!==null?number_format($media,1).' val.':'—' ?></div>
        <?php if ($media!==null): ?>
        <div class="bar-wrap"><div class="bar" style="width:<?= min(100,$media/20*100) ?>%;background:<?= $media>=10?'#34d399':'#f87171' ?>"></div></div>
        <?php endif; ?>
        <div class="stat-sub" style="margin-top:.5rem">Escala 0–20</div>
    </div>
</div>

<?php foreach ($disciplinas_data as $idx => $d): ?>
<div class="disc-card">
    <div class="disc-header" onclick="toggle(<?= $idx ?>)">
        <div style="display:flex;align-items:center;gap:.75rem;">
            <div>
                <div class="disc-nome"><?= htmlspecialchars($d['nome']) ?></div>
                <?php if ($d['melhor'] !== null): ?>
                <div style="font-size:12px;color:#6b7280;margin-top:2px">Melhor nota: <?= nb($d['melhor']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem">
            <?= estado_badge($d['estado']) ?>
            <i class="fas fa-chevron-down" id="ic-<?= $idx ?>" style="color:#4b5563;font-size:12px;transition:.2s"></i>
        </div>
    </div>
    <div class="disc-body" id="db-<?= $idx ?>" style="display:none">
        <?php if (!empty($d['notas'])): ?>
        <table style="margin-top:.75rem">
            <thead><tr><th>Ano letivo</th><th>Época</th><th>Nota</th><th>Resultado</th></tr></thead>
            <tbody>
            <?php foreach ($d['notas'] as $n): ?>
            <tr>
                <td><?= htmlspecialchars($n['ano_letivo']) ?></td>
                <td><?= htmlspecialchars($n['epoca']) ?></td>
                <td><?= nb($n['nota']) ?></td>
                <td>
                    <?php if ($n['nota']===null): ?>
                    <span style="color:#4b5563;font-size:12px">Não lançada</span>
                    <?php elseif ($n['nota']>=10): ?>
                    <span style="color:#34d399;font-size:12px"><i class="fas fa-check"></i> Aprovado</span>
                    <?php else: ?>
                    <span style="color:#f87171;font-size:12px"><i class="fas fa-times"></i> Reprovado</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align:center;padding:1rem;color:#4b5563;font-size:13px"><i class="fas fa-clock"></i> Ainda sem avaliações lançadas</div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php else: ?>
<div class="empty-state"><i class="fas fa-book-open"></i><div>Nenhuma disciplina encontrada para o teu curso.</div></div>
<?php endif; ?>

<?php elseif ($active_tab === 'matricula'): ?>
<!-- ══ MATRÍCULA ═════════════════════════════════════════════ -->

<?php if ($matricula): ?>
<div class="card">
    <div class="card-title"><i class="fas fa-file-alt" style="color:#3b82f6"></i> Candidatura à matrícula</div>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <div>
            <div style="font-size:15px;font-weight:600;color:#fff"><?= htmlspecialchars($matricula['curso_nome']) ?> <span style="color:#6b7280;font-weight:400;font-size:13px">(<?= htmlspecialchars($matricula['curso_sigla']) ?>)</span></div>
            <div style="font-size:12px;color:#6b7280;margin-top:3px">Submetida em <?= date('d/m/Y \à\s H:i', strtotime($matricula['created_at'])) ?></div>
        </div>
        <span class="badge badge-<?= $matricula['estado'] ?>"><?= ['pendente'=>'Pendente','aprovado'=>'Aceite','rejeitado'=>'Recusada'][$matricula['estado']] ?></span>
    </div>
    <?php if ($matricula['observacoes'] && $matricula['estado']==='rejeitado'): ?>
    <div class="obs-box"><i class="fas fa-comment-alt"></i> <strong>Motivo:</strong> <?= nl2br(htmlspecialchars($matricula['observacoes'])) ?></div>
    <?php endif; ?>
    <?php if ($matricula['estado'] === 'aprovado'): ?>
    <div style="margin-top:1rem;padding-top:1rem;border-top:1px solid #1f2937">
        <a href="comprovativo.php" class="btn btn-primary btn-sm" target="_blank">
            <i class="fas fa-file-pdf"></i> Descarregar comprovativo PDF
        </a>
    </div>
    <?php endif; ?>
</div>
<?php if ($matricula['estado']==='rejeitado'): ?>
<a href="matricula_nova.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nova candidatura</a>
<?php endif; ?>
<?php else: ?>
<div class="empty-state">
    <i class="fas fa-inbox"></i>
    <div style="margin-bottom:1rem">Ainda não submeteste nenhuma candidatura.</div>
    <a href="matricula_nova.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Submeter candidatura</a>
</div>
<?php endif; ?>

<?php elseif ($active_tab === 'ficha'): ?>
<!-- ══ FICHA ═════════════════════════════════════════════════ -->

<?php
$estado_ficha = $ficha['estado'] ?? null;
$pode_editar  = !$ficha || in_array($estado_ficha, ['rascunho','rejeitada']);
?>

<?php if ($estado_ficha === 'submetida'): ?>
<div class="info-banner" style="border-color:#1e40af50">
    <i class="fas fa-clock"></i>
    <span>Ficha submetida em <?= date('d/m/Y', strtotime($ficha['updated_at'])) ?>. Aguarda validação pelo gestor pedagógico.</span>
</div>
<?php elseif ($estado_ficha === 'aprovada'): ?>
<div class="info-banner" style="background:#065f4620;border-color:#065f4650;color:#34d399">
    <i class="fas fa-check-circle"></i>
    <span>Ficha aprovada pelo gestor. Já não é possível editar.</span>
</div>
<?php elseif ($estado_ficha === 'rejeitada'): ?>
<div class="alert alert-erro" style="margin-bottom:1rem">
    <i class="fas fa-times-circle"></i>
    <span>Ficha rejeitada.<?php if ($ficha['observacoes']): ?> Motivo: <strong><?= htmlspecialchars($ficha['observacoes']) ?></strong><?php endif; ?> Corrige e volta a submeter.</span>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-title" style="display:flex;justify-content:space-between;align-items:center">
        <span><i class="fas fa-id-card" style="color:#3b82f6"></i> Ficha pessoal</span>
        <?php if ($estado_ficha): ?>
        <?php
        $badge_map = [
            'rascunho'  => ['#374151','#9ca3af','fa-pencil-alt','Rascunho'],
            'submetida' => ['#1e40af30','#60a5fa','fa-paper-plane','Submetida'],
            'aprovada'  => ['#065f4630','#34d399','fa-check','Aprovada'],
            'rejeitada' => ['#7f1d1d30','#f87171','fa-times','Rejeitada'],
        ];
        [$bg,$cor,$ico,$lbl] = $badge_map[$estado_ficha] ?? $badge_map['rascunho'];
        ?>
        <span style="background:<?=$bg?>;color:<?=$cor?>;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;display:inline-flex;align-items:center;gap:5px">
            <i class="fas <?=$ico?>"></i> <?=$lbl?>
        </span>
        <?php endif; ?>
    </div>

    <?php if (!$ficha): ?>
    <div class="info-banner"><i class="fas fa-info-circle"></i><span>Preenche os teus dados. A ficha será validada pelo gestor pedagógico.</span></div>
    <?php endif; ?>

    <?php if ($pode_editar): ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="field-group">
            <label>Nome completo <span>*</span></label>
            <input type="text" name="nome_aluno" value="<?= htmlspecialchars($ficha['nome_aluno'] ?? '') ?>" placeholder="O teu nome completo" required>
        </div>
        <div class="form-row">
            <div class="field-group">
                <label>Data de nascimento <span>*</span></label>
                <input type="date" name="data_nascimento" value="<?= htmlspecialchars($ficha['data_nascimento'] ?? '') ?>" required>
            </div>
            <div class="field-group">
                <label>Telefone <span>*</span></label>
                <input type="tel" name="telefone" maxlength="9" pattern="\d{9}" placeholder="9 dígitos" value="<?= htmlspecialchars($ficha['telefone'] ?? '') ?>" required>
            </div>
        </div>
        <div class="field-group">
            <label>Morada <span>*</span></label>
            <textarea name="morada" placeholder="Rua, número, código postal, localidade" required><?= htmlspecialchars($ficha['morada'] ?? '') ?></textarea>
        </div>
        <div class="field-group">
            <label>Curso pretendido <span>*</span></label>
            <select name="curso_pretendido" required>
                <option value="">-- Seleciona --</option>
                <?php while ($c = $cursos_list->fetch_assoc()): $sel = ($ficha['curso_pretendido'] ?? 0)==$c['Id_cursos']?'selected':''; ?>
                <option value="<?= $c['Id_cursos'] ?>" <?= $sel ?>><?= htmlspecialchars($c['Nome']) ?> (<?= htmlspecialchars($c['Sigla']) ?>)</option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="foto-row">
            <div class="foto-thumb">
                <?php if (!empty($ficha['foto_path']) && file_exists($ficha['foto_path'])): ?>
                    <img id="foto-img" src="<?= htmlspecialchars($ficha['foto_path']) ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-user" id="foto-icon"></i>
                    <img id="foto-img" src="" alt="" style="display:none">
                <?php endif; ?>
            </div>
            <div>
                <div class="upload-btn">
                    <input type="file" name="foto" id="foto-input" accept="image/jpeg,image/png">
                    <label for="foto-input"><i class="fas fa-upload"></i> Foto</label>
                </div>
                <div style="font-size:11px;color:#4b5563;margin-top:5px">JPG ou PNG · máx. 2 MB</div>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem;flex-wrap:wrap">
            <button type="submit" name="guardar_ficha" class="btn btn-secondary btn-sm">
                <i class="fas fa-save"></i> Guardar rascunho
            </button>
            <?php if ($ficha): ?>
            <button type="submit" name="submeter_ficha" class="btn btn-primary btn-sm"
                    onclick="return confirm('Após submeter não poderás editar. Confirmas?')">
                <i class="fas fa-paper-plane"></i> Submeter para validação
            </button>
            <?php else: ?>
            <button type="button" class="btn btn-primary btn-sm" style="opacity:.4;cursor:not-allowed" title="Guarda primeiro">
                <i class="fas fa-paper-plane"></i> Submeter para validação
            </button>
            <?php endif; ?>
        </div>
    </form>

    <?php else: ?>
    <!-- Modo leitura -->
    <div class="form-row">
        <div class="field-group">
            <label>Nome</label>
            <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px"><?= htmlspecialchars($ficha['nome_aluno']) ?></div>
        </div>
        <div class="field-group">
            <label>Email</label>
            <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px;color:#6b7280"><?= htmlspecialchars($email) ?></div>
        </div>
    </div>
    <div class="form-row">
        <div class="field-group">
            <label>Data de nascimento</label>
            <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px"><?= date('d/m/Y', strtotime($ficha['data_nascimento'])) ?></div>
        </div>
        <div class="field-group">
            <label>Telefone</label>
            <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px"><?= htmlspecialchars($ficha['telefone']) ?></div>
        </div>
    </div>
    <div class="field-group">
        <label>Morada</label>
        <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px"><?= nl2br(htmlspecialchars($ficha['morada'])) ?></div>
    </div>
    <div class="field-group">
        <label>Curso pretendido</label>
        <div style="padding:9px 12px;background:#0f1117;border-radius:8px;border:1px solid #1f2937;font-size:14px"><?= htmlspecialchars($ficha['curso_nome'] ?? '—') ?></div>
    </div>
    <?php if (!empty($ficha['foto_path']) && file_exists($ficha['foto_path'])): ?>
    <div class="foto-row">
        <div class="foto-thumb"><img src="<?= htmlspecialchars($ficha['foto_path']) ?>" alt=""></div>
        <span style="font-size:13px;color:#6b7280">Fotografia submetida</span>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php endif; ?>
</div>

<script>
function toggle(idx) {
    const body = document.getElementById('db-' + idx);
    const icon = document.getElementById('ic-' + idx);
    const open = body.style.display === 'block';
    body.style.display = open ? 'none' : 'block';
    icon.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}
document.getElementById('foto-input')?.addEventListener('change', function() {
    const file = this.files[0]; if (!file) return;
    const img = document.getElementById('foto-img');
    const icon = document.getElementById('foto-icon');
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; img.style.display='block'; if(icon) icon.style.display='none'; };
    reader.readAsDataURL(file);
});
</script>
</body>
</html>