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

// CRUD Cursos
if (isset($_GET['del_curso'])) {
    $id = intval($_GET['del_curso']);
    $stmt = $conn->prepare("DELETE FROM cursos WHERE Id_cursos = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: gestor_cursos.php?ok=del"); exit;
}
if (isset($_POST['add_curso'])) {
    $nome = trim($_POST['nome']); $sigla = trim($_POST['sigla']);
    if (!$nome) { $erro = "O nome do curso é obrigatório."; }
    else {
        $stmt = $conn->prepare("INSERT INTO cursos (Nome, Sigla) VALUES (?, ?)");
        $stmt->bind_param("ss", $nome, $sigla); $stmt->execute();
        header("Location: gestor_cursos.php?ok=add"); exit;
    }
}
if (isset($_POST['edit_curso'])) {
    $id = intval($_POST['id']); $nome = trim($_POST['nome']); $sigla = trim($_POST['sigla']);
    $stmt = $conn->prepare("UPDATE cursos SET Nome=?, Sigla=? WHERE Id_cursos=?");
    $stmt->bind_param("ssi", $nome, $sigla, $id); $stmt->execute();
    header("Location: gestor_cursos.php?ok=edit"); exit;
}

// CRUD Plano de estudos
if (isset($_GET['del_plano_curso'], $_GET['del_plano_disciplina'])) {
    $ci = intval($_GET['del_plano_curso']); $di = intval($_GET['del_plano_disciplina']);
    $stmt = $conn->prepare("DELETE FROM plano_estudos WHERE cursos=? AND disciplinas=?");
    $stmt->bind_param("ii", $ci, $di); $stmt->execute();
    header("Location: gestor_cursos.php?ok=plano_del"); exit;
}
if (isset($_POST['add_plano'])) {
    $ci  = intval($_POST['curso_id']); $di = intval($_POST['disciplina_id']);
    $ano = (int)($_POST['ano'] ?? 1);
    $sem = (int)($_POST['semestre'] ?? 1);
    if ($ci && $di) {
        $chk = $conn->prepare("SELECT 1 FROM plano_estudos WHERE cursos=? AND disciplinas=?");
        $chk->bind_param("ii", $ci, $di); $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO plano_estudos (cursos, disciplinas, ano, semestre) VALUES (?,?,?,?)");
            $stmt->bind_param("iiii", $ci, $di, $ano, $sem); $stmt->execute();
        } else { $erro = "Esta disciplina já está no plano deste curso."; }
    }
    if (!$erro) { header("Location: gestor_cursos.php?ok=plano_add"); exit; }
}

// Queries
$cursos      = $conn->query("SELECT * FROM cursos ORDER BY Nome");
$disciplinas = $conn->query("SELECT * FROM disciplinas ORDER BY nome_disciplina");
$plano       = $conn->query("
    SELECT p.cursos AS curso_id, p.disciplinas AS disciplina_id,
           p.ano, p.semestre,
           c.Nome AS nome_curso, d.nome_disciplina
    FROM plano_estudos p
    JOIN cursos c ON p.cursos = c.Id_cursos
    JOIN disciplinas d ON p.disciplinas = d.Id_disciplina
    ORDER BY c.Nome, p.ano, p.semestre, d.nome_disciplina
");

if (isset($_GET['ok'])) {
    $msgs = ['add'=>'Curso adicionado.','edit'=>'Curso atualizado.','del'=>'Curso eliminado.',
             'plano_add'=>'Disciplina adicionada ao plano.','plano_del'=>'Vínculo removido.'];
    $sucesso = $msgs[$_GET['ok']] ?? '';
}

$active_page = 'cursos';
$n_pendentes = 0;
$n_submetidas = 0;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cursos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<?php include 'gestor_nav.php'; ?>
</head>
<body>
<div class="page">

<?php if ($sucesso): ?><div class="alert alert-sucesso"><i class="fas fa-check-circle"></i> <?= $sucesso ?></div><?php endif; ?>
<?php if ($erro):    ?><div class="alert alert-erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erro) ?></div><?php endif; ?>

<!-- ── Adicionar curso ── -->
<div class="card">
    <div class="card-title"><i class="fas fa-plus" style="color:#3b82f6"></i> Novo curso</div>
    <form method="POST">
        <div class="form-row">
            <div class="form-group">
                <label>Nome do curso</label>
                <input type="text" name="nome" placeholder="ex: Engenharia de Software" required>
            </div>
            <div class="form-group">
                <label>Sigla</label>
                <input type="text" name="sigla" placeholder="ex: ES" maxlength="10">
            </div>
        </div>
        <button type="submit" name="add_curso" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Adicionar
        </button>
    </form>
</div>

<!-- ── Lista de cursos ── -->
<div class="card">
    <div class="card-title"><i class="fas fa-book" style="color:#3b82f6"></i> Cursos</div>
    <table>
        <thead><tr><th>ID</th><th>Nome</th><th>Sigla</th><th></th></tr></thead>
        <tbody>
        <?php $cursos->data_seek(0); while ($row = $cursos->fetch_assoc()): ?>
        <tr>
            <td><span class="badge-id">#<?= $row['Id_cursos'] ?></span></td>
            <td class="td-main"><?= htmlspecialchars($row['Nome']) ?></td>
            <td><?= htmlspecialchars($row['Sigla']) ?></td>
            <td style="text-align:right">
                <a href="?edit_curso=<?= $row['Id_cursos'] ?>" class="btn-edit-sm"><i class="fas fa-edit"></i></a>
                <a href="?del_curso=<?= $row['Id_cursos'] ?>" class="btn-danger-sm"
                   onclick="return confirm('Eliminar curso?')"><i class="fas fa-trash"></i></a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <?php if (isset($_GET['edit_curso'])):
        $id = intval($_GET['edit_curso']);
        $res = $conn->query("SELECT * FROM cursos WHERE Id_cursos = $id");
        $ce  = $res->fetch_assoc();
        if ($ce): ?>
    <div class="edit-box">
        <h3>Editar — <?= htmlspecialchars($ce['Nome']) ?></h3>
        <form method="POST">
            <input type="hidden" name="id" value="<?= $ce['Id_cursos'] ?>">
            <div class="form-row">
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="nome" value="<?= htmlspecialchars($ce['Nome']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Sigla</label>
                    <input type="text" name="sigla" value="<?= htmlspecialchars($ce['Sigla']) ?>" maxlength="10">
                </div>
            </div>
            <div style="display:flex;gap:.75rem;align-items:center">
                <button type="submit" name="edit_curso" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Guardar</button>
                <a href="gestor_cursos.php" style="font-size:13px;color:#6b7280;text-decoration:none">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; endif; ?>
</div>

<!-- ── Plano de estudos ── -->
<div class="card">
    <div class="card-title"><i class="fas fa-link" style="color:#3b82f6"></i> Plano de estudos</div>

    <form method="POST" style="margin-bottom:1rem;">
        <div class="form-row">
            <div class="form-group">
                <label>Curso</label>
                <select name="curso_id" required>
                    <option value="">Seleciona...</option>
                    <?php $cursos->data_seek(0); while ($c = $cursos->fetch_assoc()): ?>
                    <option value="<?= $c['Id_cursos'] ?>"><?= htmlspecialchars($c['Nome']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Disciplina</label>
                <select name="disciplina_id" required>
                    <option value="">Seleciona...</option>
                    <?php while ($d = $disciplinas->fetch_assoc()): ?>
                    <option value="<?= $d['Id_disciplina'] ?>"><?= htmlspecialchars($d['nome_disciplina']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Ano</label>
                <select name="ano">
                    <option value="1">1º Ano</option>
                    <option value="2">2º Ano</option>
                    <option value="3">3º Ano</option>
                </select>
            </div>
            <div class="form-group">
                <label>Semestre</label>
                <select name="semestre">
                    <option value="1">1º Semestre</option>
                    <option value="2">2º Semestre</option>
                </select>
            </div>
        </div>
        <button type="submit" name="add_plano" class="btn btn-primary btn-sm">
            <i class="fas fa-link"></i> Vincular
        </button>
    </form>

    <hr>

    <table>
        <thead><tr><th>Curso</th><th>Disciplina</th><th>Ano</th><th>Semestre</th><th></th></tr></thead>
        <tbody>
        <?php while ($row = $plano->fetch_assoc()): ?>
        <tr>
            <td class="td-main"><?= htmlspecialchars($row['nome_curso']) ?></td>
            <td><?= htmlspecialchars($row['nome_disciplina']) ?></td>
            <td style="text-align:center"><?= $row['ano'] ? $row['ano'].'º' : '—' ?></td>
            <td style="text-align:center"><?= $row['semestre'] ? $row['semestre'].'º' : '—' ?></td>
            <td style="text-align:right">
                <a href="?del_plano_curso=<?= $row['curso_id'] ?>&del_plano_disciplina=<?= $row['disciplina_id'] ?>"
                   class="btn-danger-sm" onclick="return confirm('Remover vínculo?')">
                    <i class="fas fa-unlink"></i>
                </a>
            </td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

</div>
</body>
</html>