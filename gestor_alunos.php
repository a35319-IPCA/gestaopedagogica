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

if (isset($_GET['del_aluno'])) {
    $id = intval($_GET['del_aluno']);
    $stmt = $conn->prepare("DELETE FROM alunos WHERE ID = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: gestor_alunos.php?ok=del"); exit;
}
if (isset($_POST['add_aluno'])) {
    $nome  = trim($_POST['nome']);  $nasc  = trim($_POST['data_nascimento']);
    $em    = trim($_POST['email']); $tel   = trim($_POST['telefone']);
    $curso = !empty($_POST['curso']) ? intval($_POST['curso']) : null;
    if (!$nome) { $erro = "O nome é obrigatório."; }
    else {
        $stmt = $conn->prepare("INSERT INTO alunos (nome_aluno, data_nascimento, email, telefone, aluno_curso) VALUES (?,?,?,?,?)");
        $stmt->bind_param("ssssi", $nome, $nasc, $em, $tel, $curso); $stmt->execute();
        header("Location: gestor_alunos.php?ok=add"); exit;
    }
}
if (isset($_POST['edit_aluno'])) {
    $id    = intval($_POST['id']); $nome  = trim($_POST['nome']);
    $nasc  = trim($_POST['data_nascimento']); $em = trim($_POST['email']);
    $tel   = trim($_POST['telefone']);
    $curso = !empty($_POST['curso']) ? intval($_POST['curso']) : null;
    $stmt = $conn->prepare("UPDATE alunos SET nome_aluno=?,data_nascimento=?,email=?,telefone=?,aluno_curso=? WHERE ID=?");
    $stmt->bind_param("ssssii", $nome, $nasc, $em, $tel, $curso, $id); $stmt->execute();
    header("Location: gestor_alunos.php?ok=edit"); exit;
}

$alunos  = $conn->query("SELECT a.*, c.Sigla AS curso_sigla, c.Nome AS curso_nome FROM alunos a LEFT JOIN cursos c ON a.aluno_curso = c.Id_cursos ORDER BY a.nome_aluno");
$cursos  = $conn->query("SELECT Id_cursos, Nome FROM cursos ORDER BY Nome");

if (isset($_GET['ok'])) {
    $msgs = ['add'=>'Aluno adicionado.','edit'=>'Aluno atualizado.','del'=>'Aluno eliminado.'];
    $sucesso = $msgs[$_GET['ok']] ?? '';
}

$active_page = 'alunos';
$n_pendentes = 0;
$n_submetidas = 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alunos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'gestor_nav.php'; ?>
</head>
<body>
<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<!-- Adicionar -->
<div class="card">
    <div class="card-title"><i class="fas fa-user-plus" style="color:#3b82f6"></i> Novo aluno</div>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Nome completo</label>
                <input type="text" name="nome" placeholder="ex: João Silva" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="aluno@email.com">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Data de nascimento</label>
                <input type="date" name="data_nascimento">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input type="text" name="telefone" placeholder="9 dígitos">
            </div>
        </div>
        <div class="form-group">
            <label>Curso</label>
            <select name="curso">
                <option value="">Seleciona...</option>
                <?php while ($c = $cursos->fetch_assoc()): ?>
                <option value="<?= $c['Id_cursos'] ?>"><?= htmlspecialchars($c['Nome']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" name="add_aluno" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Adicionar
        </button>
    </form>
</div>

<!-- Lista -->
<div class="card">
    <div class="card-title"><i class="fas fa-users" style="color:#3b82f6"></i> Alunos (<?= $alunos->num_rows ?>)</div>
    <table>
        <thead>
            <tr><th>ID</th><th>Nome</th><th>Email</th><th>Curso</th><th>Nasc.</th><th></th></tr>
        </thead>
        <tbody>
        <?php while ($row = $alunos->fetch_assoc()): ?>
        <tr>
            <td><span class="badge-id">#<?= $row['ID'] ?></span></td>
            <td class="td-main"><?= htmlspecialchars($row['nome_aluno']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= htmlspecialchars($row['curso_sigla'] ?? '—') ?></td>
            <td><?= $row['data_nascimento'] ? date('d/m/Y', strtotime($row['data_nascimento'])) : '—' ?></td>
            <td style="text-align:right">
                <a href="?edit_aluno=<?= $row['ID'] ?>" class="btn-edit-sm"><i class="fas fa-edit"></i></a>
                <a href="?del_aluno=<?= $row['ID'] ?>" class="btn-danger-sm"
                   onclick="return confirm('Eliminar aluno?')"><i class="fas fa-trash"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php if (isset($_GET['edit_aluno'])):
        $id  = intval($_GET['edit_aluno']);
        $res = $conn->query("SELECT * FROM alunos WHERE ID = $id");
        $ae  = $res->fetch_assoc();
        $co  = $conn->query("SELECT Id_cursos, Nome FROM cursos ORDER BY Nome");
        if ($ae): ?>
    <div class="edit-box">
        <h3>Editar — <?= htmlspecialchars($ae['nome_aluno']) ?></h3>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $ae['ID'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($ae['nome_aluno']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($ae['email']) ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Data de nascimento</label>
                    <input type="date" name="data_nascimento" value="<?= htmlspecialchars($ae['data_nascimento']) ?>">
                </div>
                <div class="form-group">
                    <label>Telefone</label>
                    <input type="text" name="telefone" value="<?= htmlspecialchars($ae['telefone']) ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Curso</label>
                <select name="curso">
                    <option value="">Seleciona...</option>
                    <?php while ($c = $co->fetch_assoc()):
                        $sel = ($c['Id_cursos'] == $ae['aluno_curso']) ? 'selected' : ''; ?>
                    <option value="<?= $c['Id_cursos'] ?>" <?= $sel ?>><?= htmlspecialchars($c['Nome']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex;gap:.75rem;align-items:center">
                <button type="submit" name="edit_aluno" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Guardar</button>
                <a href="gestor_alunos.php" style="font-size:13px;color:#6b7280;text-decoration:none">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; endif; ?>
</div>

</div>
</body>
</html>