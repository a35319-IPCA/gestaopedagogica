<?php
error_reporting(0);
ini_set('display_errors', 0);

session_start();
require_once 'config.php';

if (isset($_SESSION['login'])) {
    $ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
    $ps->bind_param("s", $_SESSION['user']); $ps->execute();
    $pr = $ps->get_result()->fetch_assoc();
    if (isset($pr['perfil_id']) && $pr['perfil_id'] == 2)     header("Location: painel_aluno.php");
    elseif (isset($pr['perfil_id']) && $pr['perfil_id'] == 3) header("Location: painel_funcionario.php");
    else                                                        header("Location: planoestudos.php");
    exit;
}

if (isset($_POST['login'])) {

    $user = $_POST['username'];
    $pass = $_POST['password'];

    // Pega o utilizador pelo login
    $stmt = $conn->prepare("SELECT * FROM users WHERE Login = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verifica password com hash
        if (password_verify($pass, $row['Pwd'])) {

            $_SESSION['login'] = true;
            $_SESSION['user'] = $user;

            if ($row['perfil_id'] == 2) {
                header("Location: painel_aluno.php");
                exit;
            } else if ($row['perfil_id'] == 1) {
                header("Location: planoestudos.php");
                exit;
            } else if ($row['perfil_id'] == 3) {
                header("Location: painel_funcionario.php");
                exit;
            }

        } else {
            $erro = "Utilizador ou password errados";
        }

    } else {
        $erro = "Utilizador ou password errados";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>

*{
margin:0;
padding:0;
box-sizing:border-box;
}

body{
font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;
background:#0a0c0f;
height:100vh;
display:flex;
align-items:center;
justify-content:center;
color:#e4e6eb;
}

.login-container{
width:100%;
max-width:420px;
padding:30px;
}

.login-card{
background:#1a1d24;
padding:35px;
border-radius:12px;
box-shadow:0 10px 30px rgba(0,0,0,0.4);
}

.logo{
text-align:center;
margin-bottom:30px;
}

.logo i{
font-size:40px;
color:#3b82f6;
margin-bottom:10px;
}

.logo h1{
font-size:22px;
font-weight:500;
}

.form-group{
margin-bottom:18px;
}

.form-group label{
display:block;
font-size:14px;
color:#9ca3af;
margin-bottom:6px;
}

input{
width:100%;
padding:10px 12px;
background:#2a2f38;
border:1px solid #374151;
border-radius:8px;
color:#fff;
font-size:15px;
transition:0.2s;
}

input:focus{
outline:none;
border-color:#3b82f6;
background:#2f3540;
}

button{
width:100%;
padding:11px;
border:none;
border-radius:8px;
background:#3b82f6;
color:white;
font-size:15px;
cursor:pointer;
display:flex;
align-items:center;
justify-content:center;
gap:8px;
transition:0.2s;
}

button:hover{
background:#2563eb;
}

.erro{
margin-top:15px;
background:#ef444420;
border:1px solid #ef4444;
padding:10px;
border-radius:6px;
font-size:14px;
color:#ef4444;
text-align:center;
}

.footer{
margin-top:20px;
text-align:center;
font-size:13px;
color:#6b7280;
}

.register-link{
display:block;
text-align:center;
margin-top:15px;
color:#3b82f6;
text-decoration:none;
font-size:14px;
transition:0.2s;
}

.register-link:hover{
color:#2563eb;
}
</style>
</head>

<body>

<div class="login-container">

<div class="login-card">

<div class="logo">
<i class="fas fa-graduation-cap"></i>
<h1>Painel Académico</h1>
</div>

<form method="POST">

<div class="form-group">
<label>Utilizador</label>
<input type="text" name="username" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<button type="submit" name="login">
<i class="fas fa-sign-in-alt"></i>
Entrar
</button>

</form>

<?php if(isset($erro)): ?>
<div class="erro">
<i class="fas fa-exclamation-circle"></i>
<?= $erro ?>
</div>
<?php endif; ?>

<!-- Link para criar conta -->
<a href="register.php" class="register-link">
<i class="fas fa-user-plus"></i> Criar nova conta
</a>

<div class="footer">
Sistema de gestão de planos
</div>

</div>
</div>

</body>
</html>