<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$gestor_email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $gestor_email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 1) { header("Location: painel_aluno.php"); exit; }

$sucesso = ''; $erro = '';

// POST: decisão sobre ficha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decisao_ficha'])) {
    $aluno_email = trim($_POST['aluno_email']);
    $decisao     = $_POST['decisao'];
    $obs         = trim($_POST['observacoes'] ?? '');

    if (!in_array($decisao, ['aprovada','rejeitada'])) {
        $erro = "Decisão inválida.";
    } elseif ($decisao === 'rejeitada' && !$obs) {
        $erro = "Indica o motivo da rejeição.";
    } else {
        $upd = $conn->prepare("
            UPDATE ficha_aluno
            SET estado=?, observacoes=?, gestor_email=?, data_decisao=NOW(), updated_at=NOW()
            WHERE aluno_email=? AND estado='submetida'
        ");
        $upd->bind_param("ssss", $decisao, $obs, $gestor_email, $aluno_email);
        $upd->execute();
        $sucesso = $decisao === 'aprovada' ? "Ficha aprovada." : "Ficha rejeitada.";
    }
}

// Fichas submetidas (pendentes de validação)
$sub_stmt = $conn->prepare("
    SELECT f.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla
    FROM ficha_aluno f
    LEFT JOIN cursos c ON f.curso_pretendido = c.Id_cursos
    WHERE f.estado = 'submetida'
    ORDER BY f.updated_at ASC
");
$sub_stmt->execute();
$submetidas   = $sub_stmt->get_result();
$n_submetidas = $submetidas->num_rows;

// Histórico (aprovadas + rejeitadas)
$hist_stmt = $conn->prepare("
    SELECT f.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla
    FROM ficha_aluno f
    LEFT JOIN cursos c ON f.curso_pretendido = c.Id_cursos
    WHERE f.estado IN ('aprovada','rejeitada')
    ORDER BY f.data_decisao DESC LIMIT 30
");
$hist_stmt->execute();
$historico = $hist_stmt->get_result();

$active_page = 'fichas';
$n_pendentes = 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fichas de Alunos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'gestor_nav.php'; ?>
<style>
.ficha-card{background:#1a1d24;border-radius:10px;border:1px solid #1f2937;margin-bottom:.75rem;overflow:hidden;}
.ficha-header{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;cursor:pointer;transition:.15s;}
.ficha-header:hover{background:#1e2128;}
.ficha-foto{width:48px;height:48px;border-radius:8px;background:#2a2f38;border:1px solid #374151;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.ficha-foto img{width:100%;height:100%;object-fit:cover;}
.ficha-foto i{color:#4b5563;font-size:18px;}
.ficha-nome{font-size:14px;font-weight:600;color:#fff;}
.ficha-meta{font-size:12px;color:#6b7280;margin-top:2px;}
.ficha-curso{font-size:12px;color:#60a5fa;margin-top:2px;}
.ficha-body{border-top:1px solid #111318;padding:1.25rem;}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;}
.field-item label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#4b5563;margin-bottom:3px;}
.field-item div{font-size:13px;color:#e4e6eb;}
.decisao-form{background:#2a2f38;border-radius:8px;padding:1rem;margin-top:.75rem;}
.decisao-form textarea{width:100%;background:#1a1d24;border:1px solid #374151;border-radius:6px;color:#fff;font-size:13px;padding:8px 10px;resize:vertical;min-height:70px;font-family:inherit;margin-bottom:.75rem;transition:.2s;}
.decisao-form textarea:focus{outline:none;border-color:#3b82f6;}
.decisao-btns{display:flex;gap:.6rem;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:12px;font-size:12px;font-weight:500;}
.badge-aprovada{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-rejeitada{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}
.badge-submetida{background:#1e40af30;color:#60a5fa;border:1px solid #1e40af50;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.section-title{font-size:1rem;font-weight:600;color:#fff;display:flex;align-items:center;gap:8px;}
.section-title i{color:#3b82f6;}
.count-pill{padding:2px 9px;border-radius:12px;font-size:12px;font-weight:600;}
.count-pill.has{background:#ef444430;color:#f87171;border:1px solid #ef444450;}
.count-pill.zero{background:#2a2f38;color:#4b5563;border:1px solid #374151;}
table{width:100%;border-collapse:collapse;}
th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;padding:9px 14px;text-align:left;border-bottom:1px solid #1f2937;}
td{padding:10px 14px;border-bottom:1px solid #111318;color:#9ca3af;font-size:13px;vertical-align:middle;}
td.main{color:#e4e6eb;font-weight:500;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#111318;}
.btn{padding:8px 16px;border:none;border-radius:7px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-sm{padding:6px 12px;font-size:12px;}
.btn-success{background:#065f46;color:#34d399;}
.btn-success:hover{background:#047857;}
.btn-danger{background:#7f1d1d;color:#f87171;}
.btn-danger:hover{background:#991b1b;}
.empty{text-align:center;padding:2.5rem;color:#4b5563;background:#1a1d24;border-radius:10px;border:1px solid #1f2937;}
.empty i{font-size:28px;margin-bottom:.75rem;display:block;}
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
</style>
</head>
<body>
<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<!-- Fichas submetidas -->
<div class="section-header">
    <span class="section-title"><i class="fas fa-inbox"></i> Fichas para validar</span>
    <span class="count-pill <?= $n_submetidas > 0 ? 'has' : 'zero' ?>"><?= $n_submetidas ?></span>
</div>

<?php if ($n_submetidas > 0): ?>
    <?php $i = 0; while ($f = $submetidas->fetch_assoc()): ?>
    <div class="ficha-card">
        <div class="ficha-header" onclick="toggle(<?= $i ?>)">
            <div class="ficha-foto">
                <?php if (!empty($f['foto_path']) && file_exists($f['foto_path'])): ?>
                    <img src="<?= htmlspecialchars($f['foto_path']) ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div style="flex:1">
                <div class="ficha-nome"><?= htmlspecialchars($f['nome_aluno'] ?? $f['aluno_email']) ?></div>
                <div class="ficha-meta"><?= htmlspecialchars($f['aluno_email']) ?></div>
                <div class="ficha-curso"><i class="fas fa-book" style="font-size:10px"></i> <?= htmlspecialchars($f['curso_nome'] ?? '—') ?> (<?= htmlspecialchars($f['curso_sigla'] ?? '—') ?>)</div>
            </div>
            <div style="display:flex;align-items:center;gap:.75rem">
                <span class="badge badge-submetida"><i class="fas fa-paper-plane"></i> Submetida</span>
                <i class="fas fa-chevron-down" id="ic-<?= $i ?>" style="color:#4b5563;font-size:12px;transition:.2s"></i>
            </div>
        </div>

        <div class="ficha-body" id="db-<?= $i ?>" style="display:none">
            <!-- Dados completos -->
            <div class="field-row">
                <div class="field-item"><label>Data de nascimento</label><div><?= $f['data_nascimento'] ? date('d/m/Y', strtotime($f['data_nascimento'])) : '—' ?></div></div>
                <div class="field-item"><label>Telefone</label><div><?= htmlspecialchars($f['telefone'] ?? '—') ?></div></div>
            </div>
            <div class="field-item" style="margin-bottom:.75rem"><label>Morada</label><div><?= nl2br(htmlspecialchars($f['morada'] ?? '—')) ?></div></div>
            <div class="field-item"><label>Submetida em</label><div><?= date('d/m/Y \à\s H:i', strtotime($f['updated_at'])) ?></div></div>

            <!-- Formulário de decisão -->
            <div class="decisao-form">
                <form method="POST">
                    <input type="hidden" name="aluno_email" value="<?= htmlspecialchars($f['aluno_email']) ?>">
                    <textarea name="observacoes" placeholder="Observações (obrigatório se rejeitar)..."></textarea>
                    <div class="decisao-btns">
                        <button type="submit" name="decisao_ficha" value="ok"
                                onclick="this.form.querySelector('[name=decisao]').value='aprovada';return confirm('Aprovar esta ficha?')"
                                class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i> Aprovar
                        </button>
                        <button type="submit" name="decisao_ficha" value="ok"
                                onclick="this.form.querySelector('[name=decisao]').value='rejeitada'"
                                class="btn btn-danger btn-sm">
                            <i class="fas fa-times"></i> Rejeitar
                        </button>
                        <input type="hidden" name="decisao" value="">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php $i++; endwhile; ?>

<?php else: ?>
<div class="empty">
    <i class="fas fa-check-circle" style="color:#34d399"></i>
    Não há fichas para validar.
</div>
<?php endif; ?>

<!-- Histórico -->
<div class="section-header" style="margin-top:2rem">
    <span class="section-title"><i class="fas fa-history"></i> Histórico de validações</span>
</div>

<?php if ($historico->num_rows > 0): ?>
<div style="background:#1a1d24;border-radius:10px;border:1px solid #1f2937;overflow:hidden">
    <table>
        <thead><tr><th>Aluno</th><th>Curso</th><th>Decisão</th><th>Data</th></tr></thead>
        <tbody>
        <?php while ($h = $historico->fetch_assoc()): ?>
        <tr>
            <td>
                <div class="main"><?= htmlspecialchars($h['nome_aluno'] ?? '—') ?></div>
                <div style="font-size:11px;color:#4b5563"><?= htmlspecialchars($h['aluno_email']) ?></div>
            </td>
            <td><?= htmlspecialchars($h['curso_nome'] ?? '—') ?> <span style="color:#4b5563">(<?= htmlspecialchars($h['curso_sigla'] ?? '—') ?>)</span></td>
            <td><span class="badge badge-<?= $h['estado'] ?>"><?= $h['estado'] === 'aprovada' ? 'Aprovada' : 'Rejeitada' ?></span></td>
            <td><?= $h['data_decisao'] ? date('d/m/Y', strtotime($h['data_decisao'])) : '—' ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty"><i class="fas fa-folder-open"></i>Ainda não há validações registadas.</div>
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
</script>
</body>
</html>