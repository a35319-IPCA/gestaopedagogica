<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 3) { header("Location: login.php"); exit; }

// ── POST: decisão de matrícula ────────────────────────────────
$sucesso = ''; $erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decisao_matricula'])) {
    $mid     = (int)$_POST['matricula_id'];
    $decisao = $_POST['decisao'];
    $obs     = trim($_POST['observacoes'] ?? '');

    if (!in_array($decisao, ['aprovado','rejeitado'])) {
        $erro = "Decisão inválida.";
    } elseif ($decisao === 'rejeitado' && !$obs) {
        $erro = "Indica o motivo da recusa.";
    } else {
        $upd = $conn->prepare("UPDATE pedido_matricula SET estado=?, observacoes=?, funcionario_email=?, data_decisao=NOW() WHERE id=? AND estado='pendente'");
        $upd->bind_param("sssi", $decisao, $obs, $email, $mid);
        $upd->execute();
        $sucesso = $decisao === 'aprovado' ? "Matrícula aprovada." : "Matrícula recusada.";
    }
}

// ── Queries ───────────────────────────────────────────────────
// Matrículas pendentes
$pend = $conn->prepare("
    SELECT m.id, m.aluno_email, m.created_at,
           c.Nome AS curso_nome, c.Sigla AS curso_sigla,
           f.nome_aluno, f.foto_path
    FROM pedido_matricula m
    LEFT JOIN cursos c ON m.curso_id = c.Id_cursos
    LEFT JOIN ficha_aluno f ON m.aluno_email = f.aluno_email
    WHERE m.estado = 'pendente'
    ORDER BY m.created_at ASC
");
$pend->execute();
$pendentes   = $pend->get_result();
$n_pendentes = $pendentes->num_rows;

// Histórico matrículas
$hist = $conn->prepare("
    SELECT m.id, m.aluno_email, m.estado, m.data_decisao, m.observacoes,
           c.Nome AS curso_nome, c.Sigla AS curso_sigla, f.nome_aluno
    FROM pedido_matricula m
    LEFT JOIN cursos c ON m.curso_id = c.Id_cursos
    LEFT JOIN ficha_aluno f ON m.aluno_email = f.aluno_email
    WHERE m.estado IN ('aprovado','rejeitado')
    ORDER BY m.data_decisao DESC LIMIT 30
");
$hist->execute();
$historico = $hist->get_result();

// Pautas existentes
$pautas_q = $conn->query("
    SELECT p.id, p.ano_letivo, p.epoca, p.created_at,
           d.nome_disciplina,
           COUNT(a.id) AS total_alunos,
           COUNT(a.nota) AS com_nota
    FROM pautas p
    JOIN disciplinas d ON p.disciplina_id = d.Id_disciplina
    LEFT JOIN avaliacoes a ON a.pauta_id = p.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

$active_tab = $_GET['tab'] ?? 'matriculas';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel — Serviços Académicos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}
.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.topbar-brand{display:flex;align-items:center;gap:9px;font-size:15px;font-weight:500;color:#fff;}
.topbar-brand i{color:#3b82f6;}
.topbar-right{display:flex;align-items:center;gap:12px;}
.user-pill{font-size:13px;color:#6b7280;}
.btn-logout{background:#ef444420;color:#ef4444;border:1px solid #ef444440;text-decoration:none;padding:6px 12px;border-radius:6px;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.btn-logout:hover{background:#ef444440;}
.subnav{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;display:flex;gap:2px;}
.subnav a{color:#6b7280;text-decoration:none;padding:12px 15px;font-size:14px;display:flex;align-items:center;gap:7px;border-bottom:2px solid transparent;transition:.2s;}
.subnav a:hover{color:#e4e6eb;}
.subnav a.active{color:#3b82f6;border-bottom-color:#3b82f6;}
.n-pill{background:#ef4444;color:#fff;padding:1px 6px;border-radius:10px;font-size:11px;font-weight:600;}
.page{max-width:960px;margin:0 auto;padding:2rem;}
.card{background:#1a1d24;border-radius:12px;border:1px solid #1f2937;overflow:hidden;margin-bottom:1.25rem;}
.card-body{padding:1.5rem;}
.card-title{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}
/* Matrículas pendentes */
.cand-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid #111318;transition:.15s;}
.cand-row:last-child{border-bottom:none;}
.cand-row:hover{background:#1e2128;}
.cand-foto{width:40px;height:40px;border-radius:8px;background:#2a2f38;border:1px solid #374151;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.cand-foto img{width:100%;height:100%;object-fit:cover;}
.cand-foto i{color:#4b5563;font-size:15px;}
.cand-nome{font-size:14px;font-weight:500;color:#fff;}
.cand-meta{font-size:12px;color:#6b7280;}
.cand-curso{font-size:12px;color:#60a5fa;margin-top:2px;}
/* Formulário inline de decisão */
.decisao-form{background:#2a2f38;border-radius:8px;padding:.875rem 1rem;margin-top:.75rem;}
.decisao-form textarea{width:100%;background:#1a1d24;border:1px solid #374151;border-radius:6px;color:#fff;font-size:13px;padding:8px 10px;resize:vertical;min-height:60px;font-family:inherit;margin-bottom:.75rem;}
.decisao-form textarea:focus{outline:none;border-color:#3b82f6;}
.decisao-btns{display:flex;gap:.6rem;}
/* Tabela */
table{width:100%;border-collapse:collapse;}
th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;padding:9px 14px;text-align:left;border-bottom:1px solid #1f2937;}
td{padding:10px 14px;border-bottom:1px solid #111318;color:#9ca3af;font-size:13px;vertical-align:middle;}
td.main{color:#e4e6eb;font-weight:500;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#111318;}
/* Badges */
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:12px;font-size:12px;font-weight:500;}
.badge-aprovado{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-rejeitado{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}
.badge-pendente{background:#78350f30;color:#fbbf24;border:1px solid #78350f50;}
.badge-ok{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-incompleta{background:#78350f30;color:#fbbf24;border:1px solid #78350f50;}
/* Botões */
.btn{padding:8px 16px;border:none;border-radius:7px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-sm{padding:6px 12px;font-size:12px;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-success{background:#065f46;color:#34d399;}
.btn-success:hover{background:#047857;}
.btn-danger{background:#7f1d1d;color:#f87171;}
.btn-danger:hover{background:#991b1b;}
.btn-secondary{background:#2a2f38;color:#e4e6eb;}
.btn-secondary:hover{background:#374151;}
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.empty{text-align:center;padding:2.5rem;color:#4b5563;}
.empty i{font-size:28px;margin-bottom:.75rem;display:block;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.section-title{font-size:1rem;font-weight:600;color:#fff;display:flex;align-items:center;gap:8px;}
.section-title i{color:#3b82f6;}
</style>
</head>
<body>

<div class="topbar">
    <div class="topbar-brand">
        <i class="fas fa-university"></i>
        Serviços Académicos
    </div>
    <div class="topbar-right">
        <span class="user-pill"><?= htmlspecialchars($email) ?></span>
        <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</div>

<div class="subnav">
    <a href="?tab=matriculas" class="<?= $active_tab==='matriculas'?'active':'' ?>">
        <i class="fas fa-file-alt"></i> Matrículas
        <?php if ($n_pendentes > 0): ?>
        <span class="n-pill"><?= $n_pendentes ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=pautas" class="<?= $active_tab==='pautas'?'active':'' ?>">
        <i class="fas fa-list-alt"></i> Pautas
    </a>
</div>

<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<?php if ($active_tab === 'matriculas'): ?>
<!-- ══ MATRÍCULAS ══════════════════════════════════════════════ -->

<!-- Pendentes -->
<div class="section-header">
    <span class="section-title"><i class="fas fa-inbox"></i> Pendentes</span>
    <span style="font-size:13px;color:#4b5563"><?= $n_pendentes ?> candidatura<?= $n_pendentes != 1 ? 's' : '' ?></span>
</div>

<?php if ($n_pendentes > 0): ?>
<div class="card">
    <?php while ($c = $pendentes->fetch_assoc()): ?>
    <div class="cand-row">
        <div style="display:flex;align-items:flex-start;gap:.875rem;flex:1">
            <div class="cand-foto">
                <?php if (!empty($c['foto_path']) && file_exists($c['foto_path'])): ?>
                    <img src="<?= htmlspecialchars($c['foto_path']) ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div style="flex:1">
                <div class="cand-nome"><?= htmlspecialchars($c['nome_aluno'] ?? $c['aluno_email']) ?></div>
                <div class="cand-meta"><?= htmlspecialchars($c['aluno_email']) ?> · <?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></div>
                <div class="cand-curso"><i class="fas fa-book" style="font-size:10px"></i> <?= htmlspecialchars($c['curso_nome']) ?> (<?= htmlspecialchars($c['curso_sigla']) ?>)</div>

                <!-- Formulário de decisão inline -->
                <div class="decisao-form" style="margin-top:.75rem">
                    <form method="POST">
                        <input type="hidden" name="matricula_id" value="<?= $c['id'] ?>">
                        <textarea name="observacoes" placeholder="Observações (obrigatório se recusar)..."></textarea>
                        <div class="decisao-btns">
                            <button type="submit" name="decisao_matricula" value="ok"
                                    onclick="this.form.querySelector('[name=decisao]').value='aprovado'"
                                    class="btn btn-success btn-sm">
                                <i class="fas fa-check"></i> Aprovar
                            </button>
                            <button type="submit" name="decisao_matricula" value="ok"
                                    onclick="this.form.querySelector('[name=decisao]').value='rejeitado'"
                                    class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Recusar
                            </button>
                            <input type="hidden" name="decisao" value="">
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>
<?php else: ?>
<div class="empty card"><i class="fas fa-check-circle" style="color:#34d399"></i>Não há candidaturas pendentes.</div>
<?php endif; ?>

<!-- Histórico -->
<div class="section-header" style="margin-top:2rem">
    <span class="section-title"><i class="fas fa-history"></i> Histórico</span>
</div>

<?php if ($historico->num_rows > 0): ?>
<div class="card">
    <table>
        <thead><tr><th>Aluno</th><th>Curso</th><th>Decisão</th><th>Data</th></tr></thead>
        <tbody>
        <?php while ($h = $historico->fetch_assoc()): ?>
        <tr>
            <td>
                <div class="main"><?= htmlspecialchars($h['nome_aluno'] ?? '—') ?></div>
                <div style="font-size:11px;color:#4b5563"><?= htmlspecialchars($h['aluno_email']) ?></div>
            </td>
            <td><?= htmlspecialchars($h['curso_nome']) ?> <span style="color:#4b5563">(<?= htmlspecialchars($h['curso_sigla']) ?>)</span></td>
            <td><span class="badge badge-<?= $h['estado'] ?>"><?= $h['estado']==='aprovado'?'Aprovado':'Recusado' ?></span></td>
            <td><?= $h['data_decisao'] ? date('d/m/Y', strtotime($h['data_decisao'])) : '—' ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty card"><i class="fas fa-folder-open"></i>Ainda não há decisões registadas.</div>
<?php endif; ?>

<?php elseif ($active_tab === 'pautas'): ?>
<!-- ══ PAUTAS ═════════════════════════════════════════════════ -->

<div class="section-header">
    <span class="section-title"><i class="fas fa-list-alt"></i> Pautas de avaliação</span>
    <a href="funcionario_pauta_nova.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Nova pauta
    </a>
</div>

<?php if ($pautas_q && $pautas_q->num_rows > 0): ?>
<div class="card">
    <table>
        <thead><tr><th>Disciplina</th><th>Ano letivo</th><th>Época</th><th>Alunos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php while ($p = $pautas_q->fetch_assoc()): ?>
        <tr>
            <td class="main"><?= htmlspecialchars($p['nome_disciplina']) ?></td>
            <td><?= htmlspecialchars($p['ano_letivo']) ?></td>
            <td><?= htmlspecialchars($p['epoca']) ?></td>
            <td style="text-align:center"><?= $p['com_nota'] ?>/<?= $p['total_alunos'] ?></td>
            <td>
                <?php if ($p['total_alunos'] > 0 && $p['com_nota'] == $p['total_alunos']): ?>
                <span class="badge badge-ok"><i class="fas fa-check"></i> Completa</span>
                <?php else: ?>
                <span class="badge badge-incompleta"><i class="fas fa-clock"></i> Incompleta</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="funcionario_pauta.php?id=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-edit"></i> Lançar notas
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="empty card"><i class="fas fa-list-alt"></i>Ainda não há pautas criadas. <a href="funcionario_pauta_nova.php" style="color:#3b82f6">Criar primeira pauta</a>.</div>
<?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>