<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['login'])) {
    header("Location: painel_aluno.php");
    exit;
}

$erro = '';
$sucesso = '';

if (isset($_POST['register'])) {

    $email = trim($_POST['email']);
    $pass = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);

    if ($email && $pass) {

        // Verifica se o email já tem conta
        $check = $conn->prepare("SELECT * FROM users WHERE Login = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $erro = "Este email já tem uma conta criada.";
        } else {
            // Insere na tabela users com perfil_id = 2
            $stmt = $conn->prepare("INSERT INTO users (Login, Pwd, perfil_id) VALUES (?, ?, 2)");
            $stmt->bind_param("ss", $email, $pass);
            if ($stmt->execute()) {
                // Login automático
                $_SESSION['login'] = true;
                $_SESSION['user'] = $email;
                header("Location: painel_aluno.php");
                exit;
            } else {
                $erro = "Erro ao criar conta. Tenta novamente.";
            }
        }

    } else {
        $erro = "Preenche todos os campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Registo de Aluno</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Ubuntu;background:#0a0c0f;height:100vh;display:flex;align-items:center;justify-content:center;color:#e4e6eb;}
.login-container{width:100%;max-width:420px;padding:30px;}
.login-card{background:#1a1d24;padding:35px;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.4);}
.logo{text-align:center;margin-bottom:30px;}
.logo i{font-size:40px;color:#3b82f6;margin-bottom:10px;}
.logo h1{font-size:22px;font-weight:500;}
.form-group{margin-bottom:18px;}
.form-group label{display:block;font-size:14px;color:#9ca3af;margin-bottom:6px;}
input{width:100%;padding:10px 12px;background:#2a2f38;border:1px solid #374151;border-radius:8px;color:#fff;font-size:15px;transition:0.2s;}
input:focus{outline:none;border-color:#3b82f6;background:#2f3540;}
button{width:100%;padding:11px;border:none;border-radius:8px;background:#3b82f6;color:white;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:0.2s;}
button:hover{background:#2563eb;}
.erro{margin-top:15px;background:#ef444420;border:1px solid #ef4444;padding:10px;border-radius:6px;font-size:14px;color:#ef4444;text-align:center;}
.footer{margin-top:20px;text-align:center;font-size:13px;color:#6b7280;}
.login-link{display:block;text-align:center;margin-top:15px;color:#3b82f6;text-decoration:none;font-size:14px;transition:0.2s;}
.login-link:hover{color:#2563eb;}
</style>
</head>
<body>

<div class="login-container">
<div class="login-card">

<div class="logo">
<i class="fas fa-user-plus"></i>
<h1>Registo de Aluno</h1>
</div>

<?php if($erro): ?>
<div class="erro"><i class="fas fa-exclamation-circle"></i> <?= $erro ?></div>
<?php endif; ?>

<form method="POST">

<div class="form-group">
<label>Email</label>
<input type="email" name="email" required>
</div>

<div class="form-group">
<label>Password</label>
<input type="password" name="password" required>
</div>

<button type="submit" name="register">
<i class="fas fa-user-plus"></i> Criar conta
</button>
</form>

<a href="login.php" class="login-link"><i class="fas fa-sign-in-alt"></i> Já tenho conta</a>

<div class="footer">
Sistema de gestão de planos
</div>

</div>
</div>

</body>
</html>