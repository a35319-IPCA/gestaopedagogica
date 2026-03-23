-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 23-Mar-2026 às 02:22
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `ipca-vnf`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `alunos`
--

CREATE TABLE `alunos` (
  `ID` int(100) NOT NULL,
  `aluno_curso` int(11) NOT NULL,
  `nome_aluno` varchar(100) NOT NULL,
  `data_nascimento` date NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefone` int(100) NOT NULL,
  `morada` varchar(255) DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `alunos`
--

INSERT INTO `alunos` (`ID`, `aluno_curso`, `nome_aluno`, `data_nascimento`, `email`, `telefone`, `morada`, `foto_path`) VALUES
(1, 1, 'Rafael Faria', '2001-05-14', 'rafael@ipca.pt', 912345678, NULL, NULL);

-- --------------------------------------------------------

--
-- Estrutura da tabela `avaliacoes`
--

CREATE TABLE `avaliacoes` (
  `id` int(11) NOT NULL,
  `pauta_id` int(11) NOT NULL,
  `aluno_email` varchar(100) NOT NULL,
  `nota` decimal(4,1) DEFAULT NULL COMMENT 'NULL = ainda não lançada',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `avaliacoes`
--

INSERT INTO `avaliacoes` (`id`, `pauta_id`, `aluno_email`, `nota`, `updated_at`) VALUES
(1, 1, 'rafael@ipca.pt', 14.0, '2026-03-22 23:06:04'),
(2, 2, 'rafael@ipca.pt', 8.0, '2026-03-22 23:06:04'),
(3, 3, 'rafael@ipca.pt', 11.5, '2026-03-22 23:06:04'),
(4, 4, 'rafael@ipca.pt', 16.0, '2026-03-22 23:06:04'),
(5, 5, 'rafael@ipca.pt', 19.0, '2026-03-22 23:38:16'),
(6, 5, 'manel@ipca.pt', 10.0, '2026-03-22 23:38:09');

-- --------------------------------------------------------

--
-- Estrutura da tabela `cursos`
--

CREATE TABLE `cursos` (
  `Id_cursos` int(11) NOT NULL,
  `Nome` varchar(255) NOT NULL,
  `Sigla` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `cursos`
--

INSERT INTO `cursos` (`Id_cursos`, `Nome`, `Sigla`) VALUES
(1, 'Desenvolvimento Web e Multimédia', 'DWM'),
(2, 'Engenharia Informática', 'EI'),
(3, 'Design de Comunicação', 'DC');

-- --------------------------------------------------------

--
-- Estrutura da tabela `disciplinas`
--

CREATE TABLE `disciplinas` (
  `Id_disciplina` int(11) NOT NULL,
  `nome_disciplina` varchar(255) NOT NULL,
  `Sigla` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `disciplinas`
--

INSERT INTO `disciplinas` (`Id_disciplina`, `nome_disciplina`, `Sigla`) VALUES
(1, 'Programação Web I', 'PW1'),
(2, 'Programação Web II', 'PW2'),
(3, 'Acesso e Armazenamento de Dados', 'AAD'),
(4, 'Design de Interfaces', 'DI'),
(5, 'Multimédia', 'MM'),
(6, 'Algoritmos e Estruturas de Dados', 'AED'),
(7, 'Sistemas Operativos', 'SO'),
(8, 'Redes de Computadores', 'RC');

-- --------------------------------------------------------

--
-- Estrutura da tabela `ficha_aluno`
--

CREATE TABLE `ficha_aluno` (
  `id` int(11) NOT NULL,
  `aluno_email` varchar(100) NOT NULL,
  `nome_aluno` varchar(100) NOT NULL DEFAULT '',
  `curso_pretendido` int(11) DEFAULT NULL,
  `morada` varchar(255) DEFAULT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `estado` enum('rascunho','submetida','aprovada','rejeitada') NOT NULL DEFAULT 'rascunho',
  `observacoes` text DEFAULT NULL,
  `gestor_email` varchar(100) DEFAULT NULL,
  `data_decisao` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `ficha_aluno`
--

INSERT INTO `ficha_aluno` (`id`, `aluno_email`, `nome_aluno`, `curso_pretendido`, `morada`, `telefone`, `data_nascimento`, `foto_path`, `estado`, `observacoes`, `gestor_email`, `data_decisao`, `created_at`, `updated_at`) VALUES
(1, 'rafael@ipca.pt', 'Rafael Faria', 1, 'Rua das Flores, 12, 2º Esq, 4750-123 Barcelos', '912345678', '2001-05-14', NULL, 'aprovada', NULL, 'gestor@ipca.pt', '2026-03-22 23:06:04', '2026-03-22 23:06:04', '2026-03-22 23:06:04'),
(2, 'ana@ipca.pt', 'Ana Silva', 1, 'Av. da República, 45, 3750-456 Braga', '923456789', '2002-08-22', NULL, 'aprovada', '', 'gestor@ipca.pt', '2026-03-22 23:35:51', '2026-03-22 23:06:04', '2026-03-22 23:35:51'),
(3, 'joao@ipca.pt', 'João Costa', 2, 'Travessa do Castelo, 7, 4800-789 Guimarães', '934567890', '2000-11-03', NULL, 'rascunho', NULL, NULL, NULL, '2026-03-22 23:06:04', '2026-03-22 23:06:04'),
(4, 'manel@ipca.pt', 'Maneli di Santineli', 3, 'ruuruuuaurauaur', '102901902', '0000-00-00', 'uploads/fotos/foto_69c07be6540de.png', 'aprovada', '', 'gestor@ipca.pt', '2026-03-22 23:35:46', '2026-03-22 23:31:50', '2026-03-22 23:35:46');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pautas`
--

CREATE TABLE `pautas` (
  `id` int(11) NOT NULL,
  `disciplina_id` int(11) NOT NULL,
  `ano_letivo` varchar(10) NOT NULL COMMENT 'Ex: 2025/2026',
  `epoca` enum('Normal','Recurso','Especial') NOT NULL DEFAULT 'Normal',
  `funcionario_email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `pautas`
--

INSERT INTO `pautas` (`id`, `disciplina_id`, `ano_letivo`, `epoca`, `funcionario_email`, `created_at`) VALUES
(1, 1, '2024/2025', 'Normal', 'funcionario@ipca.pt', '2026-03-22 23:06:04'),
(2, 2, '2024/2025', 'Normal', 'funcionario@ipca.pt', '2026-03-22 23:06:04'),
(3, 2, '2024/2025', 'Recurso', 'funcionario@ipca.pt', '2026-03-22 23:06:04'),
(4, 3, '2024/2025', 'Normal', 'funcionario@ipca.pt', '2026-03-22 23:06:04'),
(5, 1, '2026/2027', 'Normal', 'funcionario@ipca.pt', '2026-03-22 23:37:49');

-- --------------------------------------------------------

--
-- Estrutura da tabela `pedido_matricula`
--

CREATE TABLE `pedido_matricula` (
  `id` int(11) NOT NULL,
  `aluno_email` varchar(100) NOT NULL,
  `curso_id` int(11) NOT NULL,
  `estado` enum('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'pendente',
  `observacoes` text DEFAULT NULL,
  `funcionario_email` varchar(100) DEFAULT NULL,
  `data_decisao` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `pedido_matricula`
--

INSERT INTO `pedido_matricula` (`id`, `aluno_email`, `curso_id`, `estado`, `observacoes`, `funcionario_email`, `data_decisao`, `created_at`) VALUES
(1, 'rafael@ipca.pt', 1, 'aprovado', 'Documentação verificada e aprovada.', 'funcionario@ipca.pt', '2026-03-22 23:06:04', '2026-03-22 23:06:04'),
(2, 'ana@ipca.pt', 1, 'rejeitado', 'N gostei', 'gestor@ipca.pt', '2026-03-22 23:19:30', '2026-03-22 23:06:04'),
(3, 'manel@ipca.pt', 1, 'aprovado', '', 'gestor@ipca.pt', '2026-03-22 23:35:37', '2026-03-22 23:34:42');

-- --------------------------------------------------------

--
-- Estrutura da tabela `perfis`
--

CREATE TABLE `perfis` (
  `ID` int(99) NOT NULL,
  `Perfil` varchar(99) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `perfis`
--

INSERT INTO `perfis` (`ID`, `Perfil`) VALUES
(1, 'Gestor Pedagógico'),
(2, 'Aluno'),
(3, 'Funcionário Académico');

-- --------------------------------------------------------

--
-- Estrutura da tabela `plano_estudos`
--

CREATE TABLE `plano_estudos` (
  `cursos` int(11) NOT NULL,
  `disciplinas` int(11) NOT NULL,
  `ano` tinyint(1) DEFAULT NULL,
  `semestre` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `plano_estudos`
--

INSERT INTO `plano_estudos` (`cursos`, `disciplinas`, `ano`, `semestre`) VALUES
(1, 1, 1, 1),
(1, 2, 1, 2),
(1, 3, 1, 2),
(1, 4, 2, 1),
(1, 5, 2, 2),
(2, 3, 2, 2),
(2, 6, 1, 1),
(2, 7, 1, 2),
(2, 8, 2, 1);

-- --------------------------------------------------------

--
-- Estrutura da tabela `users`
--

CREATE TABLE `users` (
  `Login` varchar(99) NOT NULL,
  `Pwd` varchar(99) NOT NULL,
  `perfil_id` int(99) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `users`
--

INSERT INTO `users` (`Login`, `Pwd`, `perfil_id`) VALUES
('gestor@ipca.pt', '$2y$10$4G/Thh9.4h47jEy2L1Ujo.AAbYcGQI49rTE7thLiTLvECY0ZbZ9Oy', 1),
('funcionario@ipca.pt', '$2y$10$iVOLltmJ9x0483.vHQLqyeimK5.gXFvR4rbBLP4S4lTP3.bf0IiT.', 3),
('rafael@ipca.pt', '$2y$10$lFx78Ug07GWu01S3L7vE.uTaFCmSVLS7PPB4YPTEmCdbJbBVzFAY2', 2),
('ana@ipca.pt', '$2y$10$oldB458tldUdYd1/V0kTYudkHvpPmM/XeBlo1cTTQYxv/Q9GFdwCG', 2),
('joao@ipca.pt', '$2y$10$mkePBUDzIn2VJQovTj6bbe0AGJCpV7B41MNmOd73pjgUu/niEfXJi', 2),
('manel@ipca.pt', '$2y$10$ZJ/..1.yRDye3qIA2a0AT.LCQVogeZD0KN3FzLMFVjR.jhQchWF..', 2);

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `alunos`
--
ALTER TABLE `alunos`
  ADD PRIMARY KEY (`ID`);

--
-- Índices para tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_avaliacao` (`pauta_id`,`aluno_email`);

--
-- Índices para tabela `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`Id_cursos`);

--
-- Índices para tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  ADD PRIMARY KEY (`Id_disciplina`);

--
-- Índices para tabela `ficha_aluno`
--
ALTER TABLE `ficha_aluno`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ficha_aluno` (`aluno_email`),
  ADD KEY `fk_ficha_curso` (`curso_pretendido`);

--
-- Índices para tabela `pautas`
--
ALTER TABLE `pautas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pauta` (`disciplina_id`,`ano_letivo`,`epoca`);

--
-- Índices para tabela `pedido_matricula`
--
ALTER TABLE `pedido_matricula`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pm_curso` (`curso_id`);

--
-- Índices para tabela `perfis`
--
ALTER TABLE `perfis`
  ADD PRIMARY KEY (`ID`);

--
-- Índices para tabela `plano_estudos`
--
ALTER TABLE `plano_estudos`
  ADD PRIMARY KEY (`cursos`,`disciplinas`),
  ADD KEY `disciplinas` (`disciplinas`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `alunos`
--
ALTER TABLE `alunos`
  MODIFY `ID` int(100) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `cursos`
--
ALTER TABLE `cursos`
  MODIFY `Id_cursos` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `disciplinas`
--
ALTER TABLE `disciplinas`
  MODIFY `Id_disciplina` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de tabela `ficha_aluno`
--
ALTER TABLE `ficha_aluno`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `pautas`
--
ALTER TABLE `pautas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `pedido_matricula`
--
ALTER TABLE `pedido_matricula`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `perfis`
--
ALTER TABLE `perfis`
  MODIFY `ID` int(99) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `avaliacoes`
--
ALTER TABLE `avaliacoes`
  ADD CONSTRAINT `fk_aval_pauta` FOREIGN KEY (`pauta_id`) REFERENCES `pautas` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `ficha_aluno`
--
ALTER TABLE `ficha_aluno`
  ADD CONSTRAINT `fk_ficha_curso` FOREIGN KEY (`curso_pretendido`) REFERENCES `cursos` (`Id_cursos`) ON DELETE SET NULL;

--
-- Limitadores para a tabela `pautas`
--
ALTER TABLE `pautas`
  ADD CONSTRAINT `fk_pauta_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `disciplinas` (`Id_disciplina`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `pedido_matricula`
--
ALTER TABLE `pedido_matricula`
  ADD CONSTRAINT `fk_pm_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`Id_cursos`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `plano_estudos`
--
ALTER TABLE `plano_estudos`
  ADD CONSTRAINT `plano_estudos_ibfk_1` FOREIGN KEY (`cursos`) REFERENCES `cursos` (`Id_cursos`) ON DELETE CASCADE,
  ADD CONSTRAINT `plano_estudos_ibfk_2` FOREIGN KEY (`disciplinas`) REFERENCES `disciplinas` (`Id_disciplina`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
