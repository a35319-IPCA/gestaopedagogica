<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 1) { header("Location: painel_aluno.php"); exit; }

$sucesso = ''; $erro = '';

if (isset($_GET['del_disciplina'])) {
    $id = intval($_GET['del_disciplina']);
    $stmt = $conn->prepare("DELETE FROM disciplinas WHERE Id_disciplina = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: gestor_disciplinas.php?ok=del"); exit;
}
if (isset($_POST['add_disciplina'])) {
    $nome = trim($_POST['nome']);
    if (!$nome) { $erro = "O nome da disciplina é obrigatório."; }
    else {
        $stmt = $conn->prepare("INSERT INTO disciplinas (nome_disciplina) VALUES (?)");
        $stmt->bind_param("s", $nome); $stmt->execute();
        header("Location: gestor_disciplinas.php?ok=add"); exit;
    }
}
if (isset($_POST['edit_disciplina'])) {
    $id = intval($_POST['id']); $nome = trim($_POST['nome']);
    $stmt = $conn->prepare("UPDATE disciplinas SET nome_disciplina=? WHERE Id_disciplina=?");
    $stmt->bind_param("si", $nome, $id); $stmt->execute();
    header("Location: gestor_disciplinas.php?ok=edit"); exit;
}

$disciplinas = $conn->query("SELECT * FROM disciplinas ORDER BY nome_disciplina");

if (isset($_GET['ok'])) {
    $msgs = ['add'=>'Disciplina adicionada.','edit'=>'Disciplina atualizada.','del'=>'Disciplina eliminada.'];
    $sucesso = $msgs[$_GET['ok']] ?? '';
}

$active_page = 'disciplinas';
$n_pendentes = 0;
$n_submetidas = 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Disciplinas</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'gestor_nav.php'; ?>
</head>
<body>
<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<!-- Adicionar -->
<div class="card">
    <div class="card-title"><i class="fas fa-plus" style="color:#3b82f6"></i> Nova disciplina</div>
    <form method="POST" style="display:flex;gap:1rem;align-items:flex-end;">
        <div class="form-group" style="flex:1;margin:0">
            <label>Nome da disciplina</label>
            <input type="text" name="nome" placeholder="ex: Programação Web II" required>
        </div>
        <button type="submit" name="add_disciplina" class="btn btn-primary btn-sm" style="margin-bottom:1px">
            <i class="fas fa-plus"></i> Adicionar
        </button>
    </form>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-title"><i class="fas fa-chalkboard" style="color:#3b82f6"></i> Disciplinas</div>
    <table>
        <thead><tr><th>ID</th><th>Nome</th><th></th></tr></thead>
        <tbody>
        <?php while ($row = $disciplinas->fetch_assoc()): ?>
        <tr>
            <td><span class="badge-id">#<?= $row['Id_disciplina'] ?></span></td>
            <td class="td-main"><?= htmlspecialchars($row['nome_disciplina']) ?></td>
            <td style="text-align:right">
                <a href="?edit_disciplina=<?= $row['Id_disciplina'] ?>" class="btn-edit-sm"><i class="fas fa-edit"></i></a>
                <a href="?del_disciplina=<?= $row['Id_disciplina'] ?>" class="btn-danger-sm"
                   onclick="return confirm('Eliminar disciplina?')"><i class="fas fa-trash"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php if (isset($_GET['edit_disciplina'])):
        $id  = intval($_GET['edit_disciplina']);
        $res = $conn->query("SELECT * FROM disciplinas WHERE Id_disciplina = $id");
        $de  = $res->fetch_assoc();
        if ($de): ?>
    <div class="edit-box">
        <h3>Editar — <?= htmlspecialchars($de['nome_disciplina']) ?></h3>
        <form method="POST" style="display:flex;gap:1rem;align-items:flex-end;">
            <input type="hidden" name="id" value="<?= $de['Id_disciplina'] ?>">
            <div class="form-group" style="flex:1;margin:0">
                <label>Nome</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($de['nome_disciplina']) ?>" required>
            </div>
            <button type="submit" name="edit_disciplina" class="btn btn-primary btn-sm" style="margin-bottom:1px">
                <i class="fas fa-save"></i> Guardar
            </button>
            <a href="gestor_disciplinas.php" style="font-size:13px;color:#6b7280;text-decoration:none;white-space:nowrap">Cancelar</a>
        </form>
    </div>
    <?php endif; endif; ?>
</div>

</div>
</body>
</html>