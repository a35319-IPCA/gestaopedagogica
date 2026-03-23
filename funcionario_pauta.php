<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 3) { header("Location: login.php"); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: painel_funcionario.php?tab=pautas"); exit; }

// Carregar pauta
$ps2 = $conn->prepare("
    SELECT p.*, d.nome_disciplina
    FROM pautas p
    JOIN disciplinas d ON p.disciplina_id = d.Id_disciplina
    WHERE p.id = ?
");
$ps2->bind_param("i", $id); $ps2->execute();
$pauta = $ps2->get_result()->fetch_assoc();
if (!$pauta) { header("Location: painel_funcionario.php?tab=pautas"); exit; }

$sucesso = ''; $erro = '';

// POST: guardar notas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_notas'])) {
    $notas = $_POST['nota'] ?? [];
    $erros_notas = [];

    foreach ($notas as $aval_id => $nota_raw) {
        $aval_id  = (int)$aval_id;
        $nota_raw = trim($nota_raw);

        if ($nota_raw === '' || $nota_raw === null) {
            // Limpar nota (NULL)
            $upd = $conn->prepare("UPDATE avaliacoes SET nota=NULL WHERE id=? AND pauta_id=?");
            $upd->bind_param("ii", $aval_id, $id);
            $upd->execute();
        } else {
            $nota_val = str_replace(',', '.', $nota_raw);
            if (!is_numeric($nota_val) || $nota_val < 0 || $nota_val > 20) {
                $erros_notas[] = "Nota inválida: '$nota_raw' (deve ser entre 0 e 20).";
                continue;
            }
            $nota_val = round((float)$nota_val, 1);
            $upd = $conn->prepare("UPDATE avaliacoes SET nota=? WHERE id=? AND pauta_id=?");
            $upd->bind_param("dii", $nota_val, $aval_id, $id);
            $upd->execute();
        }
    }

    if ($erros_notas) {
        $erro = implode(' ', $erros_notas);
    } else {
        $sucesso = "Notas guardadas com sucesso.";
    }
}

// Carregar avaliações
$avals = $conn->prepare("
    SELECT a.id, a.aluno_email, a.nota, f.nome_aluno, f.foto_path
    FROM avaliacoes a
    LEFT JOIN ficha_aluno f ON a.aluno_email = f.aluno_email
    WHERE a.pauta_id = ?
    ORDER BY f.nome_aluno ASC, a.aluno_email ASC
");
$avals->bind_param("i", $id); $avals->execute();
$avaliacoes = $avals->get_result();

// Estatísticas rápidas
$stats_q = $conn->prepare("
    SELECT COUNT(*) as total,
           COUNT(nota) as com_nota,
           AVG(nota) as media,
           SUM(CASE WHEN nota >= 10 THEN 1 ELSE 0 END) as aprovados
    FROM avaliacoes WHERE pauta_id=?
");
$stats_q->bind_param("i", $id); $stats_q->execute();
$stats = $stats_q->get_result()->fetch_assoc();
$is_novo = isset($_GET['novo']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pauta — <?= htmlspecialchars($pauta['nome_disciplina']) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}
.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:10;}
.topbar a{color:#6b7280;text-decoration:none;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.topbar a:hover{color:#e4e6eb;}
.topbar-sep{color:#374151;}
.topbar-cur{font-size:13px;color:#e4e6eb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.page{max-width:860px;margin:0 auto;padding:2rem;}
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
.stat{background:#1a1d24;border-radius:10px;padding:1rem 1.25rem;border:1px solid #1f2937;text-align:center;}
.stat-val{font-size:1.75rem;font-weight:700;line-height:1;}
.stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-top:.3rem;}
.sv-blue{color:#3b82f6;} .sv-green{color:#34d399;} .sv-red{color:#f87171;} .sv-amber{color:#fbbf24;}
/* Card */
.card{background:#1a1d24;border-radius:12px;border:1px solid #1f2937;margin-bottom:1.25rem;overflow:hidden;}
.card-header{padding:1rem 1.5rem;border-bottom:1px solid #1f2937;display:flex;align-items:center;justify-content:space-between;}
.card-title{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;}
/* Tabela de notas */
.notas-table{width:100%;border-collapse:collapse;}
.notas-table th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;padding:9px 16px;text-align:left;border-bottom:1px solid #1f2937;}
.notas-table td{padding:9px 16px;border-bottom:1px solid #111318;vertical-align:middle;}
.notas-table tr:last-child td{border-bottom:none;}
.notas-table tr:hover td{background:#111318;}
.aluno-foto{width:34px;height:34px;border-radius:6px;background:#2a2f38;border:1px solid #374151;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.aluno-foto img{width:100%;height:100%;object-fit:cover;}
.aluno-foto i{color:#4b5563;font-size:13px;}
.aluno-nome{font-size:14px;font-weight:500;color:#e4e6eb;}
.aluno-email{font-size:12px;color:#4b5563;}
/* Input de nota */
.nota-input{width:80px;padding:6px 10px;background:#2a2f38;border:1px solid #374151;border-radius:6px;color:#fff;font-size:14px;text-align:center;transition:.2s;}
.nota-input:focus{outline:none;border-color:#3b82f6;background:#2f3540;}
.nota-input.aprovado{border-color:#065f46;background:#065f4615;}
.nota-input.reprovado{border-color:#7f1d1d;background:#7f1d1d15;}
/* Resultado badge */
.res{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:10px;font-size:12px;}
.res-ok{background:#065f4630;color:#34d399;}
.res-fail{background:#7f1d1d30;color:#f87171;}
.res-vazio{background:#2a2f38;color:#4b5563;}
/* Add aluno */
.add-aluno-form{display:flex;gap:.75rem;align-items:flex-end;}
.add-aluno-form input{flex:1;padding:8px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:13px;}
.add-aluno-form input:focus{outline:none;border-color:#3b82f6;}
/* Botões */
.btn{padding:8px 16px;border:none;border-radius:7px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-sm{padding:6px 12px;font-size:12px;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-secondary{background:#2a2f38;color:#e4e6eb;}
.btn-secondary:hover{background:#374151;}
.btn-success{background:#065f46;color:#34d399;}
.btn-success:hover{background:#047857;}
/* Alertas */
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.info-banner{background:#1e3a5f20;border:1px solid #1e40af40;border-radius:8px;padding:10px 14px;font-size:13px;color:#93c5fd;display:flex;gap:8px;margin-bottom:1.25rem;}
.empty{text-align:center;padding:2rem;color:#4b5563;font-size:13px;}
@media(max-width:600px){.stats-row{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>

<div class="topbar">
    <a href="painel_funcionario.php?tab=pautas"><i class="fas fa-arrow-left"></i> Pautas</a>
    <span class="topbar-sep">/</span>
    <span class="topbar-cur"><?= htmlspecialchars($pauta['nome_disciplina']) ?> · <?= htmlspecialchars($pauta['ano_letivo']) ?> · <?= htmlspecialchars($pauta['epoca']) ?></span>
</div>

<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<?php if ($is_novo && $stats['total'] == 0): ?>
<div class="info-banner">
    <i class="fas fa-info-circle"></i>
    <span>Pauta criada. Não foram encontrados alunos com matrícula aprovada nesta disciplina. Podes adicionar alunos manualmente abaixo.</span>
</div>
<?php endif; ?>

<!-- Estatísticas -->
<div class="stats-row">
    <div class="stat">
        <div class="stat-val sv-blue"><?= $stats['total'] ?></div>
        <div class="stat-label">Alunos</div>
    </div>
    <div class="stat">
        <div class="stat-val sv-amber"><?= $stats['com_nota'] ?></div>
        <div class="stat-label">Com nota</div>
    </div>
    <div class="stat">
        <div class="stat-val sv-green"><?= $stats['aprovados'] ?? 0 ?></div>
        <div class="stat-label">Aprovados</div>
    </div>
    <div class="stat">
        <div class="stat-val <?= $stats['media'] !== null ? ($stats['media'] >= 10 ? 'sv-green' : 'sv-red') : 'sv-amber' ?>">
            <?= $stats['media'] !== null ? number_format($stats['media'], 1) : '—' ?>
        </div>
        <div class="stat-label">Média</div>
    </div>
</div>

<!-- Tabela de notas -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-users" style="color:#3b82f6;margin-right:6px"></i>Alunos e notas</span>
        <button type="submit" form="form-notas" name="guardar_notas" class="btn btn-success btn-sm">
            <i class="fas fa-save"></i> Guardar notas
        </button>
    </div>

    <form id="form-notas" method="POST">
    <?php
    $avaliacoes->data_seek(0);
    if ($avaliacoes->num_rows > 0):
    ?>
    <table class="notas-table">
        <thead>
            <tr>
                <th>Aluno</th>
                <th style="text-align:center">Nota (0–20)</th>
                <th style="text-align:center">Resultado</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($av = $avaliacoes->fetch_assoc()):
            $nota = $av['nota'];
            $cls  = $nota === null ? '' : ($nota >= 10 ? 'aprovado' : 'reprovado');
        ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div class="aluno-foto">
                        <?php if (!empty($av['foto_path']) && file_exists($av['foto_path'])): ?>
                            <img src="<?= htmlspecialchars($av['foto_path']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="aluno-nome"><?= htmlspecialchars($av['nome_aluno'] ?? $av['aluno_email']) ?></div>
                        <div class="aluno-email"><?= htmlspecialchars($av['aluno_email']) ?></div>
                    </div>
                </div>
            </td>
            <td style="text-align:center">
                <input type="number" name="nota[<?= $av['id'] ?>]"
                       class="nota-input <?= $cls ?>"
                       value="<?= $nota !== null ? number_format($nota, 1) : '' ?>"
                       min="0" max="20" step="0.1"
                       placeholder="—"
                       oninput="updateRes(this, <?= $av['id'] ?>)">
            </td>
            <td style="text-align:center">
                <span id="res-<?= $av['id'] ?>" class="res <?= $nota===null?'res-vazio':($nota>=10?'res-ok':'res-fail') ?>">
                    <?php if ($nota === null): ?>—
                    <?php elseif ($nota >= 10): ?><i class="fas fa-check"></i> Aprovado
                    <?php else: ?><i class="fas fa-times"></i> Reprovado
                    <?php endif; ?>
                </span>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div class="empty"><i class="fas fa-users" style="font-size:24px;display:block;margin-bottom:.5rem"></i>Nenhum aluno nesta pauta.</div>
    <?php endif; ?>
    </form>
</div>

</div>

<script>
function updateRes(input, id) {
    const val = parseFloat(input.value);
    const res = document.getElementById('res-' + id);
    input.className = 'nota-input';
    if (input.value === '' || isNaN(val)) {
        res.className = 'res res-vazio';
        res.innerHTML = '—';
    } else if (val >= 10) {
        input.classList.add('aprovado');
        res.className = 'res res-ok';
        res.innerHTML = '<i class="fas fa-check"></i> Aprovado';
    } else {
        input.classList.add('reprovado');
        res.className = 'res res-fail';
        res.innerHTML = '<i class="fas fa-times"></i> Reprovado';
    }
}
</script>
</body>
</html>