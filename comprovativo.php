<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['login'])) { header("Location: login.php"); exit; }
$email = $_SESSION['user'];

$ps = $conn->prepare("SELECT perfil_id FROM users WHERE Login = ?");
$ps->bind_param("s", $email); $ps->execute();
$pr = $ps->get_result()->fetch_assoc();
if (!$pr || $pr['perfil_id'] != 2) { header("Location: planoestudos.php"); exit; }

// Ficha do aluno
$fs = $conn->prepare("
    SELECT f.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla
    FROM ficha_aluno f
    LEFT JOIN cursos c ON f.curso_pretendido = c.Id_cursos
    WHERE f.aluno_email = ?
");
$fs->bind_param("s", $email); $fs->execute();
$ficha = $fs->get_result()->fetch_assoc();

if (!$ficha) {
    die("Ficha não encontrada. Preenche a tua ficha antes de gerar o comprovativo.");
}

// Matrícula aprovada
$ms = $conn->prepare("
    SELECT m.*, c.Nome AS curso_nome, c.Sigla AS curso_sigla
    FROM pedido_matricula m
    LEFT JOIN cursos c ON m.curso_id = c.Id_cursos
    WHERE m.aluno_email = ? AND m.estado = 'aprovado'
    ORDER BY m.data_decisao DESC LIMIT 1
");
$ms->bind_param("s", $email); $ms->execute();
$matricula = $ms->get_result()->fetch_assoc();

if (!$matricula) {
    die("Matrícula não aprovada. O comprovativo só está disponível após a matrícula ser aceite.");
}

require('fpdf/fpdf.php');

function enc($s) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s);
}

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(25, 25, 25);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$W = 160; // largura útil (210 - 25 - 25)

// ── Cabeçalho ─────────────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(30, 64, 175);
$pdf->Cell($W, 10, enc('IPCA — Instituto Politécnico do Cávado e do Ave'), 0, 1, 'C');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell($W, 6, enc('Serviços Académicos'), 0, 1, 'C');

// Linha azul
$pdf->Ln(3);
$pdf->SetDrawColor(30, 64, 175);
$pdf->SetLineWidth(0.8);
$pdf->Line(25, $pdf->GetY(), 185, $pdf->GetY());
$pdf->Ln(6);

// Título do documento
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell($W, 8, enc('COMPROVATIVO DE MATRÍCULA'), 0, 1, 'C');
$pdf->Ln(6);

// ── Foto + dados lado a lado ───────────────────────────────────
$foto_w   = 35;
$foto_h   = 42;
$dados_x  = 25 + $foto_w + 8;
$dados_w  = $W - $foto_w - 8;
$bloco_y  = $pdf->GetY();

// Foto
$foto_path = $ficha['foto_path'] ?? '';
if (!empty($foto_path) && file_exists($foto_path)) {
    $ext = strtolower(pathinfo($foto_path, PATHINFO_EXTENSION));
    $tipo = ($ext === 'png') ? 'PNG' : 'JPEG';
    try {
        $pdf->Image($foto_path, 25, $bloco_y, $foto_w, $foto_h, $tipo);
    } catch (Exception $e) {
        // Se a imagem falhar, desenha placeholder
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Rect(25, $bloco_y, $foto_w, $foto_h);
    }
} else {
    // Placeholder sem foto
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Rect(25, $bloco_y, $foto_w, $foto_h, 'DF');
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(160, 160, 160);
    $pdf->SetXY(25, $bloco_y + $foto_h / 2 - 3);
    $pdf->Cell($foto_w, 6, enc('Sem foto'), 0, 0, 'C');
}

// Dados do aluno (coluna direita)
$pdf->SetXY($dados_x, $bloco_y);

$campos = [
    'Nome'          => $ficha['nome_aluno'] ?? '—',
    'Email'         => $email,
    'Data nasc.'    => $ficha['data_nascimento'] ? date('d/m/Y', strtotime($ficha['data_nascimento'])) : '—',
    'Telefone'      => $ficha['telefone'] ?? '—',
    'Morada'        => $ficha['morada'] ?? '—',
];

foreach ($campos as $label => $valor) {
    $pdf->SetX($dados_x);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(80, 80, 80);
    $pdf->Cell(28, 6, enc($label . ':'), 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(30, 30, 30);
    // MultiCell para morada (pode ser longa)
    if ($label === 'Morada') {
        $pdf->MultiCell($dados_w - 28, 6, enc($valor), 0, 'L');
    } else {
        $pdf->Cell($dados_w - 28, 6, enc($valor), 0, 1, 'L');
        $pdf->SetX($dados_x);
    }
}

// Avançar para baixo do bloco foto+dados
$after_bloco = max($bloco_y + $foto_h, $pdf->GetY()) + 8;
$pdf->SetY($after_bloco);

// ── Linha separadora ──────────────────────────────────────────
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetLineWidth(0.3);
$pdf->Line(25, $pdf->GetY(), 185, $pdf->GetY());
$pdf->Ln(6);

// ── Dados da matrícula ────────────────────────────────────────
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 64, 175);
$pdf->Cell($W, 7, enc('Dados da Matrícula'), 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(35, 6, enc('Curso:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 6, enc($matricula['curso_nome'] . ' (' . $matricula['curso_sigla'] . ')'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(35, 6, enc('Estado:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(5, 95, 70);
$pdf->Cell(0, 6, enc('Matrícula aprovada'), 0, 1);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(80, 80, 80);
$pdf->Cell(35, 6, enc('Data de aprovação:'), 0, 0);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(30, 30, 30);
$pdf->Cell(0, 6, enc(date('d/m/Y', strtotime($matricula['data_decisao']))), 0, 1);

$pdf->Ln(4);

// ── Declaração ────────────────────────────────────────────────
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetLineWidth(0.3);
$pdf->Line(25, $pdf->GetY(), 185, $pdf->GetY());
$pdf->Ln(5);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(50, 50, 50);
$texto = 'Declara-se que ' . ($ficha['nome_aluno'] ?? $email) . ' se encontra devidamente matriculado(a) no curso de ' . $matricula['curso_nome'] . ' (' . $matricula['curso_sigla'] . ') nesta instituição, no ano letivo em vigor.';
$pdf->MultiCell($W, 6, enc($texto), 0, 'J');

$pdf->Ln(4);

// Assinatura (placeholder)
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(120, 120, 120);
$pdf->Cell($W / 2, 6, enc('Barcelos, ' . date('d/m/Y')), 0, 0, 'L');
$pdf->Cell($W / 2, 6, enc('Os Serviços Académicos'), 0, 1, 'R');

$pdf->Ln(8);
$pdf->SetDrawColor(180, 180, 180);
$pdf->Line(110, $pdf->GetY(), 185, $pdf->GetY());
$pdf->Ln(3);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell($W, 5, enc('Assinatura e carimbo'), 0, 1, 'R');

// ── Rodapé ────────────────────────────────────────────────────
$pdf->SetY(270);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);
$pdf->Line(25, $pdf->GetY(), 185, $pdf->GetY());
$pdf->Ln(2);
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(160, 160, 160);
$pdf->Cell($W, 4, enc('Documento gerado automaticamente em ' . date('d/m/Y \à\s H:i') . ' — válido sem assinatura manuscrita.'), 0, 1, 'C');

// ── Output ────────────────────────────────────────────────────
$nome = 'Comprovativo_' . preg_replace('/[^a-zA-Z0-9]/', '_', $ficha['nome_aluno'] ?? 'Aluno') . '_' . date('Ymd') . '.pdf';
$pdf->Output('D', $nome);
exit;
?>