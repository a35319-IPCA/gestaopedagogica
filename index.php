<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// Já autenticado — redirecionar para o painel correto
require_once 'config.php';
$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $_SESSION['user']); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();

if (!$pr) {
    session_destroy();
    header("Location: login.php");
} elseif ($pr['perfil_id'] == 2) {
    header("Location: painel_aluno.php");
} elseif ($pr['perfil_id'] == 3) {
    header("Location: painel_funcionario.php");
} else {
    header("Location: planoestudos.php");
}
exit;
?>