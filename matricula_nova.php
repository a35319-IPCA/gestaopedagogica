<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

$email = $_SESSION['user'];

// Só alunos
$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email);
$ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 2) {
    header("Location: planoestudos.php");
    exit;
}

// Verificar se já tem candidatura ativa (pendente ou aprovada)
$check = $conn->prepare("
    SELECT id, estado FROM pedido_matricula
    WHERE aluno_email = ? AND estado IN ('pendente','aprovado')
    LIMIT 1
");
$check->bind_param("s", $email);
$check->execute();
$existente = $check->get_result()->fetch_assoc();

if ($existente) {
    header("Location: painel_aluno.php");
    exit;
}

$cursos = $conn->query("SELECT Id_cursos, Nome, Sigla FROM cursos ORDER BY Nome");
$erro   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $curso_id = (int)($_POST['curso_id'] ?? 0);

    if (!$curso_id) {
        $erro = "Seleciona um curso.";
    } else {
        // Verificar que o curso existe
        $cv = $conn->prepare("SELECT Id_cursos FROM cursos WHERE Id_cursos = ?");
        $cv->bind_param("i", $curso_id);
        $cv->execute();
        if ($cv->get_result()->num_rows === 0) {
            $erro = "Curso inválido.";
        } else {
            $ins = $conn->prepare("
                INSERT INTO pedido_matricula (aluno_email, curso_id, estado, created_at)
                VALUES (?, ?, 'pendente', NOW())
            ");
            $ins->bind_param("si", $email, $curso_id);
            $ins->execute();
            header("Location: painel_aluno.php?m=ok");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nova Candidatura</title>
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
.card{background:#1a1d24;border-radius:12px;padding:2rem;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.card-title{font-size:1.1rem;font-weight:600;margin-bottom:.4rem;color:#fff;}
.card-sub{font-size:13px;color:#6b7280;margin-bottom:1.5rem;}
.field-group{margin-bottom:1.2rem;}
.field-group label{display:block;font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;}
select{width:100%;padding:10px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:14px;transition:.2s;}
select:focus{outline:none;border-color:#3b82f6;}
select option{background:#2a2f38;}
.actions{display:flex;gap:.75rem;margin-top:1.5rem;}
.btn{padding:10px 20px;border:none;border-radius:8px;font-size:14px;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.2s;font-weight:500;text-decoration:none;}
.btn-primary{background:#3b82f6;color:#fff;}
.btn-primary:hover{background:#2563eb;}
.btn-secondary{background:#2a2f38;color:#e4e6eb;}
.btn-secondary:hover{background:#374151;}
.alert-erro{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:1.25rem;display:flex;align-items:center;gap:8px;background:#ef444420;border:1px solid #ef4444;color:#ef4444;}
.info-banner{background:#1e3a5f20;border:1px solid #1e40af40;border-radius:8px;padding:10px 14px;font-size:13px;color:#93c5fd;display:flex;gap:8px;margin-bottom:1.25rem;}
</style>
</head>
<body>

<div class="topbar">
    <a href="painel_aluno.php"><i class="fas fa-arrow-left"></i> Painel</a>
    <span class="topbar-sep">/</span>
    <span class="topbar-cur">Nova Candidatura</span>
</div>

<div class="page">
    <div class="card">
        <div class="card-title"><i class="fas fa-file-alt" style="color:#3b82f6"></i> Candidatura à Matrícula</div>
        <div class="card-sub">Seleciona o curso ao qual te queres candidatar. A candidatura ficará pendente até ser analisada.</div>

        <?php if ($erro): ?>
        <div class="alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <div class="info-banner">
            <i class="fas fa-info-circle"></i>
            <span>Só podes ter uma candidatura ativa de cada vez. Caso seja recusada, poderás submeter uma nova.</span>
        </div>

        <form method="POST">
            <div class="field-group">
                <label>Curso <span style="color:#ef4444">*</span></label>
                <select name="curso_id" required>
                    <option value="">-- Seleciona um curso --</option>
                    <?php while ($c = $cursos->fetch_assoc()): ?>
                    <option value="<?= $c['Id_cursos'] ?>">
                        <?= htmlspecialchars($c['Nome']) ?> (<?= htmlspecialchars($c['Sigla']) ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submeter candidatura
                </button>
                <a href="painel_aluno.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>