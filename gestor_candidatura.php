<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$gestor_email = $_SESSION['user'];

// Só gestores
$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $gestor_email);
$ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 1) {
    header("Location: painel_aluno.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: planoestudos.php");
    exit;
}

// Carregar candidatura + ficha do aluno
$stmt = $conn->prepare("
    SELECT m.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla,
           f.nome_aluno, f.morada, f.telefone, f.data_nascimento, f.foto_path
    FROM pedido_matricula m
    LEFT JOIN cursos c ON m.curso_id = c.Id_cursos
    LEFT JOIN ficha_aluno f ON m.aluno_email = f.aluno_email
    WHERE m.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$cand = $stmt->get_result()->fetch_assoc();

if (!$cand) {
    header("Location: planoestudos.php");
    exit;
}

$erro    = '';
$sucesso = '';

// POST: decisão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cand['estado'] === 'pendente') {
    $decisao      = $_POST['decisao'] ?? '';
    $observacoes  = trim($_POST['observacoes'] ?? '');

    if (!in_array($decisao, ['aprovado', 'rejeitado'])) {
        $erro = "Decisão inválida.";
    } elseif ($decisao === 'rejeitado' && !$observacoes) {
        $erro = "Indica o motivo da recusa.";
    } else {
        $upd = $conn->prepare("
            UPDATE pedido_matricula
            SET estado = ?, observacoes = ?, funcionario_email = ?, data_decisao = NOW()
            WHERE id = ?
        ");
        $upd->bind_param("sssi", $decisao, $observacoes, $gestor_email, $id);
        $upd->execute();

        // Recarregar
        $stmt->execute();
        $cand    = $stmt->get_result()->fetch_assoc();
        $sucesso = $decisao === 'aprovado' ? "Candidatura aceite com sucesso." : "Candidatura recusada.";
    }
}

function badge($estado) {
    switch ($estado) {
        case 'pendente':  return ['label'=>'Pendente', 'class'=>'badge-pendente'];
        case 'aprovado':  return ['label'=>'Aceite',   'class'=>'badge-aceite'];
        case 'rejeitado': return ['label'=>'Recusada', 'class'=>'badge-recusada'];
        default:          return ['label'=>$estado,    'class'=>''];
    }
}
$b = badge($cand['estado']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Candidatura #<?= $id ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}

.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;gap:12px;position:sticky;top:0;z-index:100;}
.topbar a{color:#6b7280;text-decoration:none;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.topbar a:hover{color:#e4e6eb;}
.topbar-sep{color:#374151;}
.topbar-cur{font-size:13px;color:#e4e6eb;}

.page{max-width:860px;margin:0 auto;padding:2rem;display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;}
.page-full{max-width:860px;margin:0 auto;padding:0 2rem 2rem;}

.card{background:#1a1d24;border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.card-title{font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.25rem;display:flex;align-items:center;gap:6px;}
.card-title i{color:#3b82f6;}

.field{margin-bottom:1rem;}
.field label{display:block;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#4b5563;margin-bottom:4px;}
.field-val{font-size:14px;color:#e4e6eb;}
.field-val.muted{color:#9ca3af;}

.foto-big{width:100%;aspect-ratio:1;border-radius:10px;object-fit:cover;border:2px solid #2a2f38;display:block;margin-bottom:1rem;}
.foto-placeholder{width:100%;aspect-ratio:1;border-radius:10px;background:#2a2f38;border:2px solid #374151;display:flex;align-items:center;justify-content:center;margin-bottom:1rem;}
.foto-placeholder i{font-size:48px;color:#374151;}

.badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.badge-pendente{background:#78350f30;color:#fbbf24;border:1px solid #78350f50;}
.badge-aceite{background:#065f4630;color:#34d399;border:1px solid #065f4650;}
.badge-recusada{background:#7f1d1d30;color:#f87171;border:1px solid #7f1d1d50;}

/* Formulário de decisão */
.decisao-card{background:#1a1d24;border-radius:12px;padding:1.5rem;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.decisao-title{font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.25rem;}
textarea{width:100%;padding:10px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;resize:vertical;min-height:90px;font-family:inherit;transition:.2s;}
textarea:focus{outline:none;border-color:#3b82f6;}
textarea::placeholder{color:#4b5563;}
.btn-row{display:flex;gap:.75rem;margin-top:1.25rem;}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-aceitar{background:#065f46;color:#34d399;}
.btn-aceitar:hover{background:#047857;}
.btn-recusar{background:#7f1d1d;color:#f87171;}
.btn-recusar:hover{background:#991b1b;}
.btn-back{background:#2a2f38;color:#e4e6eb;}
.btn-back:hover{background:#374151;}

.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;}
.alert-erro{background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.alert-sucesso{background:#10b98120;border:1px solid #10b981;color:#10b981;}

.obs-box{background:#2a2f38;border-radius:8px;padding:12px 14px;font-size:13px;color:#9ca3af;margin-top:1rem;border-left:3px solid #374151;}
.obs-box.recusada{border-left-color:#ef4444;color:#f87171;}
.obs-box.aceite{border-left-color:#34d399;color:#6ee7b7;}

.decisao-feita{display:flex;flex-direction:column;gap:.5rem;}
.decisao-meta{font-size:12px;color:#4b5563;margin-top:.5rem;}

@media(max-width:640px){.page{grid-template-columns:1fr;}}
</style>
</head>
<body>

<div class="topbar">
    <a href="planoestudos.php"><i class="fas fa-arrow-left"></i> Candidaturas</a>
    <span class="topbar-sep">/</span>
    <span class="topbar-cur">
        <?= htmlspecialchars($cand['nome_aluno'] ?? $cand['aluno_email']) ?>
    </span>
</div>

<div class="page-full" style="padding-top:2rem">
    <?php if ($erro): ?>
    <div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>
    <?php if ($sucesso): ?>
    <div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
</div>

<div class="page">

    <!-- Coluna esquerda: Ficha do aluno -->
    <div>
        <div class="card">
            <div class="card-title"><i class="fas fa-id-card"></i> Ficha do aluno</div>

            <?php if (!empty($cand['foto_path']) && file_exists($cand['foto_path'])): ?>
                <img src="<?= htmlspecialchars($cand['foto_path']) ?>" class="foto-big" alt="Foto">
            <?php else: ?>
                <div class="foto-placeholder"><i class="fas fa-user"></i></div>
            <?php endif; ?>

            <div class="field">
                <label>Nome</label>
                <div class="field-val"><?= htmlspecialchars($cand['nome_aluno'] ?? '—') ?></div>
            </div>
            <div class="field">
                <label>Email</label>
                <div class="field-val muted"><?= htmlspecialchars($cand['aluno_email']) ?></div>
            </div>
            <div class="field">
                <label>Data de nascimento</label>
                <div class="field-val">
                    <?= $cand['data_nascimento'] ? date('d/m/Y', strtotime($cand['data_nascimento'])) : '—' ?>
                </div>
            </div>
            <div class="field">
                <label>Telefone</label>
                <div class="field-val"><?= htmlspecialchars($cand['telefone'] ?? '—') ?></div>
            </div>
            <div class="field" style="margin-bottom:0">
                <label>Morada</label>
                <div class="field-val"><?= nl2br(htmlspecialchars($cand['morada'] ?? '—')) ?></div>
            </div>
        </div>
    </div>

    <!-- Coluna direita: Candidatura + Decisão -->
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- Dados da candidatura -->
        <div class="card">
            <div class="card-title"><i class="fas fa-file-alt"></i> Candidatura</div>

            <div class="field">
                <label>Curso pretendido</label>
                <div class="field-val">
                    <?= htmlspecialchars($cand['curso_nome']) ?>
                    <span style="color:#6b7280;font-size:13px;">(<?= htmlspecialchars($cand['curso_sigla']) ?>)</span>
                </div>
            </div>
            <div class="field">
                <label>Submetida em</label>
                <div class="field-val muted"><?= date('d/m/Y \à\s H:i', strtotime($cand['created_at'])) ?></div>
            </div>
            <div class="field" style="margin-bottom:0">
                <label>Estado</label>
                <div><span class="badge <?= $b['class'] ?>"><?= $b['label'] ?></span></div>
            </div>
        </div>

        <!-- Decisão -->
        <?php if ($cand['estado'] === 'pendente'): ?>
        <div class="decisao-card">
            <div class="decisao-title"><i class="fas fa-gavel" style="color:#3b82f6;margin-right:6px"></i>Tomar decisão</div>

            <form method="POST">
                <div style="margin-bottom:.75rem;font-size:13px;color:#6b7280;">
                    Observações <span style="color:#4b5563">(obrigatório se recusar)</span>
                </div>
                <textarea name="observacoes" placeholder="Adiciona uma observação ou motivo..."></textarea>

                <div class="btn-row">
                    <button type="submit" name="decisao" value="aprovado" class="btn btn-aceitar"
                            onclick="return confirm('Confirmas a aprovação desta candidatura?')">
                        <i class="fas fa-check"></i> Aceitar
                    </button>
                    <button type="submit" name="decisao" value="rejeitado" class="btn btn-recusar"
                            onclick="return confirm('Confirmas a recusa desta candidatura?')">
                        <i class="fas fa-times"></i> Recusar
                    </button>
                </div>
            </form>
        </div>

        <?php else: ?>
        <!-- Decisão já tomada -->
        <div class="decisao-card">
            <div class="decisao-title">Decisão registada</div>
            <div class="decisao-feita">
                <span class="badge <?= $b['class'] ?>" style="align-self:flex-start;font-size:13px;padding:6px 14px;">
                    <?= $b['label'] ?>
                </span>
                <?php if ($cand['observacoes']): ?>
                <div class="obs-box <?= $cand['estado'] === 'rejeitado' ? 'recusada' : 'aceite' ?>">
                    <strong>Observações:</strong> <?= nl2br(htmlspecialchars($cand['observacoes'])) ?>
                </div>
                <?php endif; ?>
                <div class="decisao-meta">
                    Decidido em <?= date('d/m/Y \à\s H:i', strtotime($cand['data_decisao'])) ?>
                    por <?= htmlspecialchars($cand['funcionario_email']) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>