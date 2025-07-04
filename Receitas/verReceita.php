<?php
// Conexão
$conexao = new mysqli("localhost", "root", "yara123", "teste_trabalho_1", 3307);

if ($conexao->connect_error) {
    die("Erro na conexão: " . $conexao->connect_error);
}

// Sanitize input
$nome_receita = htmlspecialchars($_GET['nome'] ?? '');

if (empty($nome_receita)) {
    die("Nome da receita não especificado.");
}

// Get recipe details
$stmt = $conexao->prepare("SELECT r.*, f.nome as autor_nome 
                          FROM receitas r 
                          JOIN funcionarios f ON r.id_funcionario = f.id_funcionario 
                          WHERE r.nome_receita = ?");
if (!$stmt) {
    die("Erro na preparação da consulta: " . $conexao->error);
}

$stmt->bind_param("s", $nome_receita);
if (!$stmt->execute()) {
    die("Erro ao executar a consulta: " . $stmt->error);
}

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Receita não encontrada.");
}

$receita = $result->fetch_assoc();
$stmt->close();

// Check for photo - MODIFICADO PARA BUSCAR O BLOB DIRETAMENTE
$foto_data = null;
$foto_type = 'image/jpeg'; // valor padrão
$foto_stmt = $conexao->prepare("SELECT foto, tipo FROM foto_receita WHERE nome_receita = ? LIMIT 1");
if ($foto_stmt) {
    $foto_stmt->bind_param("s", $nome_receita);
    if ($foto_stmt->execute()) {
        $foto_result = $foto_stmt->get_result();
        if ($foto_result->num_rows > 0) {
            $foto = $foto_result->fetch_assoc();
            $foto_data = $foto['foto'];
            $foto_type = $foto['tipo'];
        }
    }
    $foto_stmt->close();
}


// Buscar ingredientes detalhados
$ingredientes_detalhados = [];
$ing_stmt = $conexao->prepare("
    SELECT i.nome AS nome_ingrediente, ri.quantidade_ingrediente, m.medida
    FROM receita_ingrediente ri
    JOIN ingredientes i ON i.id_ingrediente = ri.id_ingrediente
    JOIN medidas m ON m.id_medida = ri.id_medida
    WHERE ri.nome_receita = ?
");
if ($ing_stmt) {
    $ing_stmt->bind_param("s", $nome_receita);
    if ($ing_stmt->execute()) {
        $ing_result = $ing_stmt->get_result();
        while ($ing = $ing_result->fetch_assoc()) {
            $ingredientes_detalhados[] = $ing;
        }
    }
    $ing_stmt->close();
}

// Buscar degustações
$degustacoes = [];
$deg_stmt = $conexao->prepare("
    SELECT d.*, f.nome as nome_funcionario
    FROM degustacoes d
    JOIN funcionarios f ON d.id_funcionario = f.id_funcionario
    WHERE d.nome_receita = ?
");
if ($deg_stmt) {
    $deg_stmt->bind_param("s", $nome_receita);
    if ($deg_stmt->execute()) {
        $deg_result = $deg_stmt->get_result();
        while ($deg = $deg_result->fetch_assoc()) {
            $degustacoes[] = $deg;
        }
    }
    $deg_stmt->close();
}

$conexao->close();

// Determina se tem foto
$tem_foto = !is_null($foto_data);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($receita['nome_receita']) ?> | Código de Sabores</title>
    <script src="script.js"></script>

    <link rel="stylesheet" href="../styles/exibirReceitas.css">
    <link rel="shortcut icon" href="../assets/favicon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<body class="light-mode">
    <header>
        <h1 class="logo">Código de Sabores</h1>
        <nav class="nav-links">
            <a href="../Cozinheiro/receitasChef.php"> Voltar para página do cozinheiro</a>
            <a href="../Receitas/listarReceitas.php">Receitas</a>
        </nav>
        <div class="header-right">
            <button id="theme-toggle" class="theme-toggle"><i class="fas fa-moon"></i><i class="fas fa-sun"></i></button>
            <div class="header-buttons">
                <a href="../loginSenha/login.html" class="get-started-btn">Entrar</a>
            </div>
        </div>
    </header>

    <section class="recipe-hero">
        <?php if ($tem_foto): ?>
<img src="data:<?= htmlspecialchars($foto_type) ?>;base64,<?= base64_encode($foto_data) ?>"
                 alt="Foto da Receita <?= htmlspecialchars($nome_receita) ?>" >
        <?php else: ?>
            <p>Foto não disponível.</p>
        <?php endif; ?>

        <h1><?= htmlspecialchars($receita['nome_receita']) ?></h1>
        <p><?= nl2br(htmlspecialchars($receita['descricao'] ?? '')) ?></p>
    </section>

    <div class="recipe-container">
        <main class="recipe-content">
            <article>
                <h2><?= htmlspecialchars($receita['nome_receita']) ?></h2>
                
                <?php if ($tem_foto): ?>
                    <img src="data:image/jpeg;base64,<?= base64_encode($foto_data) ?>" 
                         alt="Foto da Receita <?= htmlspecialchars($nome_receita) ?>" 
                         style="max-width: 400px;">
                <?php else: ?>
                    <p>Foto não disponível.</p>
                <?php endif; ?>

                <h3>Ingredientes</h3>
                <ul class="ingredients-list">
                    <?php if (!empty($ingredientes_detalhados)): ?>
                        <?php foreach ($ingredientes_detalhados as $ing): ?>
                            <li>
                                <?= htmlspecialchars($ing['quantidade_ingrediente']) ?>
                                <?= htmlspecialchars($ing['medida']) ?> de
                                <?= htmlspecialchars($ing['nome_ingrediente']) ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>Ingredientes não cadastrados.</li>
                    <?php endif; ?>
                </ul>

                <h3>Modo de Preparo</h3>
                <ol class="instructions-list">
                    <?php
                    $etapas = explode("\n", $receita['modo_preparo']);
                    foreach ($etapas as $etapa):
                        if (!empty(trim($etapa))): ?>
                            <li><?= htmlspecialchars(trim($etapa)) ?></li>
                    <?php endif;
                    endforeach;
                    ?>
                </ol>

                <?php if (!empty($receita['descricao'])): ?>
                    <h3>Descrição</h3>
                    <p><?= nl2br(htmlspecialchars($receita['descricao'])) ?></p>
                <?php endif; ?>
            </article>

            <section class="degustacoes-section">
                <h2>Degustações</h2>
                <?php if (!empty($degustacoes)): ?>
                    <ul class="degustacoes-list">
                        <?php foreach ($degustacoes as $deg): ?>
                            <li>
                                <strong><?= htmlspecialchars($deg['nome_funcionario']) ?></strong>
                                (<?= htmlspecialchars($deg['data_degustacao']) ?>) - 
                                Nota: <strong><?= htmlspecialchars($deg['nota']) ?></strong><br>
                                <?= nl2br(htmlspecialchars($deg['descricao'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Esta receita ainda não foi degustada.</p>
                <?php endif; ?>
            </section>
        </main>

        <aside class="recipe-sidebar">
            <div class="recipe-info-card">
                <h3>Informações</h3>
                <div class="info-item"><i class="fas fa-clock"></i><span>Tempo de preparo: <?= htmlspecialchars($receita['tempo_preparo']) ?></span></div>
                <div class="info-item"><i class="fas fa-utensils"></i><span>Porções: <?= htmlspecialchars($receita['porcoes']) ?></span></div>
                <div class="info-item"><i class="fas fa-fire"></i><span>Dificuldade: <?= htmlspecialchars($receita['dificuldade']) ?></span></div>
                <div class="info-item"><i class="fas fa-user"></i><span>Cozinheiro: <?= htmlspecialchars($receita['autor_nome']) ?></span></div>
                <div class="info-item"><i class="fas fa-calendar"></i><span>Data de criação: <?= htmlspecialchars($receita['data_criacao']) ?></span></div>
            </div>

            <div class="recipe-info-card">
                <!-- <h3>Compartilhe</h3>
                <div class="redes">
                    <a href="#"><i class="fab fa-facebook fa-2x"></i></a>
                    <a href="#"><i class="fab fa-instagram fa-2x"></i></a>
                    <a href="#"><i class="fab fa-whatsapp fa-2x"></i></a>
                </div> -->
            </div>
        </aside>
    </div>

    <footer>
        <div class="footer-grid">
            <div class="footer-column">
                <h3>Empresa</h3>
                <ul>
                    <li><a href="../sobre/sobre.html">Sobre</a></li>
                    <li><a href="#">Carreiras</a></li>
                    <li><a href="#">Imprensa</a></li>
                    <li><a href="../contato/contato.html">Contato</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Suporte</h3>
                <ul>
                    <li><a href="#">Central de Ajuda</a></li>
                    <li><a href="#">Plataforma de Desenvolvedor</a></li>
                </ul>
            </div>
            <div class="footer-column">
                <h3>Siga</h3>
                <ul>
                    <li><a href="#">Instagram</a></li>
                    <li><a href="#">Facebook</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2025 Código De Sabores, Inc. Todos os direitos reservados.</p>
            <div class="social-icons">
                <a href="#">Privacidade</a>
                <a href="#">Termos</a>
                <a href="#">Cookies</a>
            </div>
        </div>
    </footer>

    <script src="../js/PagReceita.js"></script>
</body>

</html>
