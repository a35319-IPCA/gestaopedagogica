<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 3) { header("Location: login.php"); exit; }

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $disc_id    = (int)$_POST['disciplina_id'];
    $ano_letivo = trim($_POST['ano_letivo']);
    $epoca      = $_POST['epoca'];

    if (!$disc_id || !$ano_letivo || !$epoca) {
        $erro = "Preenche todos os campos.";
    } elseif (!in_array($epoca, ['Normal','Recurso','Especial'])) {
        $erro = "Época inválida.";
    } else {
        // Verificar duplicado
        $chk = $conn->prepare("SELECT id FROM pautas WHERE disciplina_id=? AND ano_letivo=? AND epoca=?");
        $chk->bind_param("iss", $disc_id, $ano_letivo, $epoca); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $erro = "Já existe uma pauta para esta disciplina, ano letivo e época.";
        } else {
            $ins = $conn->prepare("INSERT INTO pautas (disciplina_id, ano_letivo, epoca, funcionario_email) VALUES (?,?,?,?)");
            $ins->bind_param("isss", $disc_id, $ano_letivo, $epoca, $email);
            $ins->execute();
            $pauta_id = $conn->insert_id;

            // Criar registos de avaliação para todos os alunos com matrícula aprovada neste curso
            // (via plano_estudos: disciplina -> cursos -> alunos com matrícula aprovada)
            $alunos_q = $conn->prepare("
                SELECT DISTINCT m.aluno_email
                FROM pedido_matricula m
                JOIN plano_estudos pe ON pe.cursos = m.curso_id
                WHERE pe.disciplinas = ? AND m.estado = 'aprovado'
            ");
            $alunos_q->bind_param("i", $disc_id); $alunos_q->execute();
            $alunos_res = $alunos_q->get_result();

            while ($a = $alunos_res->fetch_assoc()) {
                $ins_av = $conn->prepare("INSERT IGNORE INTO avaliacoes (pauta_id, aluno_email) VALUES (?,?)");
                $ins_av->bind_param("is", $pauta_id, $a['aluno_email']);
                $ins_av->execute();
            }

            header("Location: funcionario_pauta.php?id=$pauta_id&novo=1");
            exit;
        }
    }
}

$disciplinas = $conn->query("SELECT Id_disciplina, nome_disciplina FROM disciplinas ORDER BY nome_disciplina");
$ano_atual   = date('Y') . '/' . (date('Y') + 1);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nova Pauta</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;color:#e4e6eb;min-height:100vh;}
.topbar{background:#111318;border-bottom:1px solid #1f2937;padding:0 2rem;height:56px;display:flex;align-items:center;gap:12px;position:sticky;top:0;}
.topbar a{color:#6b7280;text-decoration:none;font-size:13px;display:flex;align-items:center;gap:5px;transition:.2s;}
.topbar a:hover{color:#e4e6eb;}
.topbar-sep{color:#374151;}
.topbar-cur{font-size:13px;color:#e4e6eb;}
.page{max-width:520px;margin:3rem auto;padding:0 1.5rem;}
.card{background:#1a1d24;border-radius:12px;padding:1.75rem;border:1px solid #1f2937;}
.card-title{font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;margin-bottom:1.5rem;}
.field-group{margin-bottom:1.1rem;}
.field-group label{display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:5px;}
.field-group label span{color:#ef4444;}
input[type=text],select{width:100%;padding:9px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;transition:.2s;font-family:inherit;}
input:focus,select:focus{outline:none;border-color:#3b82f6;background:#2f3540;}
select option{background:#2a2f38;}
.btn{padding:9px 18px;border:none;border-radius:8px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-secondary{background:#2a2f38;color:#e4e6eb;}
.btn-secondary:hover{background:#374151;}
.actions{display:flex;gap:.75rem;margin-top:1.5rem;}
.alert-erro{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.info-banner{background:#1e3a5f20;border:1px solid #1e40af40;border-radius:8px;padding:10px 14px;font-size:13px;color:#93c5fd;display:flex;gap:8px;margin-bottom:1.25rem;}
</style>
</head>
<body>

<div class="topbar">
    <a href="painel_funcionario.php?tab=pautas"><i class="fas fa-arrow-left"></i> Pautas</a>
    <span class="topbar-sep">/</span>
    <span class="topbar-cur">Nova pauta</span>
</div>

<div class="page">
    <div class="card">
        <div class="card-title"><i class="fas fa-plus" style="color:#3b82f6;margin-right:6px"></i>Nova pauta de avaliação</div>

        <?php if ($erro): ?>
        <div class="alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <span>Ao criar a pauta, serão adicionados automaticamente todos os alunos com matrícula aprovada nessa disciplina.</span>
        </div>

        <form method="POST">
            <div class="field-group">
                <label>Disciplina <span>*</span></label>
                <select name="disciplina_id" required>
                    <option value="">-- Seleciona --</option>
                    <?php while ($d = $disciplinas->fetch_assoc()): ?>
                    <option value="<?= $d['Id_disciplina'] ?>"><?= htmlspecialchars($d['nome_disciplina']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="field-group">
                <label>Ano letivo <span>*</span></label>
                <input type="text" name="ano_letivo" value="<?= $ano_atual ?>" placeholder="ex: 2024/2025" required>
            </div>
            <div class="field-group">
                <label>Época <span>*</span></label>
                <select name="epoca" required>
                    <option value="Normal">Normal</option>
                    <option value="Recurso">Recurso</option>
                    <option value="Especial">Especial</option>
                </select>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Criar pauta
                </button>
                <a href="painel_funcionario.php?tab=pautas" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>