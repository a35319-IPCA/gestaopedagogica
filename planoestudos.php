<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 1) { header("Location: painel_aluno.php"); exit; }

// Candidaturas pendentes
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

// Histórico
$hist = $conn->prepare("
    SELECT m.id, m.aluno_email, m.estado, m.data_decisao,
           c.Nome AS curso_nome, c.Sigla AS curso_sigla, f.nome_aluno
    FROM pedido_matricula m
    LEFT JOIN cursos c ON m.curso_id = c.Id_cursos
    LEFT JOIN ficha_aluno f ON m.aluno_email = f.aluno_email
    WHERE m.estado IN ('aprovado','rejeitado')
    ORDER BY m.data_decisao DESC LIMIT 30
");
$hist->execute();
$historico = $hist->get_result();

$active_page = 'candidaturas';
$n_submetidas = 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Candidaturas</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'gestor_nav.php'; ?>
<style>
.cand-card{background:#1a1d24;border-radius:10px;padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;border:1px solid #1f2937;margin-bottom:.6rem;transition:.2s;}
.cand-card:hover{border-color:#2a2f38;background:#1e2128;}
.cand-foto{width:42px;height:42px;border-radius:8px;background:#2a2f38;border:1px solid #374151;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.cand-foto img{width:100%;height:100%;object-fit:cover;}
.cand-foto i{color:#4b5563;font-size:16px;}
.cand-nome{font-size:14px;font-weight:500;color:#fff;}
.cand-meta{font-size:12px;color:#6b7280;}
.cand-curso{font-size:12px;color:#60a5fa;margin-top:2px;}
.badge{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:12px;font-size:12px;font-weight:500;}
.badge-aprovado{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-rejeitado{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}
.count-pill{padding:2px 9px;border-radius:12px;font-size:12px;font-weight:600;}
.count-pill.has{background:#ef444430;color:#f87171;border:1px solid #ef444450;}
.count-pill.zero{background:#2a2f38;color:#4b5563;border:1px solid #374151;}
.section-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;}
.section-title{font-size:1rem;font-weight:600;color:#fff;display:flex;align-items:center;gap:8px;}
.section-title i{color:#3b82f6;}
.empty{text-align:center;padding:2.5rem;color:#4b5563;background:#1a1d24;border-radius:10px;border:1px solid #1f2937;}
.empty i{font-size:28px;margin-bottom:.75rem;display:block;}
.hist-table th{font-size:11px;}
.hist-table td{font-size:13px;}
</style>
</head>
<body>
<div class="page">

    <!-- Pendentes -->
    <div style="margin-bottom:2rem;">
        <div class="section-header">
            <span class="section-title"><i class="fas fa-inbox"></i> Candidaturas pendentes</span>
            <span class="count-pill <?= $n_pendentes > 0 ? 'has' : 'zero' ?>"><?= $n_pendentes ?></span>
        </div>

        <?php if ($n_pendentes > 0): ?>
            <?php while ($c = $pendentes->fetch_assoc()): ?>
            <div class="cand-card">
                <div style="display:flex;align-items:center;gap:.875rem;">
                    <div class="cand-foto">
                        <?php if (!empty($c['foto_path']) && file_exists($c['foto_path'])): ?>
                            <img src="<?= htmlspecialchars($c['foto_path']) ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="cand-nome"><?= htmlspecialchars($c['nome_aluno'] ?? $c['aluno_email']) ?></div>
                        <div class="cand-meta"><?= htmlspecialchars($c['aluno_email']) ?></div>
                        <div class="cand-curso"><i class="fas fa-book" style="font-size:10px"></i> <?= htmlspecialchars($c['curso_nome']) ?> (<?= htmlspecialchars($c['curso_sigla']) ?>)</div>
                    </div>
                </div>
                <a href="gestor_candidatura.php?id=<?= $c['id'] ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-eye"></i> Ver
                </a>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty">
                <i class="fas fa-check-circle" style="color:#34d399"></i>
                Não há candidaturas pendentes.
            </div>
        <?php endif; ?>
    </div>

    <!-- Histórico -->
    <div>
        <div class="section-header">
            <span class="section-title"><i class="fas fa-history"></i> Histórico</span>
        </div>

        <?php if ($historico->num_rows > 0): ?>
        <div class="card" style="padding:0;overflow:hidden;">
            <table class="hist-table">
                <thead>
                    <tr>
                        <th style="padding:12px 16px">Aluno</th>
                        <th>Curso</th>
                        <th>Decisão</th>
                        <th>Data</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($h = $historico->fetch_assoc()): ?>
                    <tr>
                        <td style="padding-left:16px">
                            <div class="td-main" style="color:#e4e6eb;font-weight:500"><?= htmlspecialchars($h['nome_aluno'] ?? '—') ?></div>
                            <div style="font-size:11px;color:#4b5563"><?= htmlspecialchars($h['aluno_email']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($h['curso_nome']) ?> <span style="color:#4b5563">(<?= htmlspecialchars($h['curso_sigla']) ?>)</span></td>
                        <td><span class="badge badge-<?= $h['estado'] ?>"><?= $h['estado'] === 'aprovado' ? 'Aceite' : 'Recusada' ?></span></td>
                        <td><?= $h['data_decisao'] ? date('d/m/Y', strtotime($h['data_decisao'])) : '—' ?></td>
                        <td><a href="gestor_candidatura.php?id=<?= $h['id'] ?>" style="color:#4b5563;font-size:12px;text-decoration:none"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="empty"><i class="fas fa-folder-open"></i>Ainda não há decisões registadas.</div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>