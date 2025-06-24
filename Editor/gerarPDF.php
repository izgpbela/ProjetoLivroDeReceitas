gerar.pdf

<?php
require_once '../BancoDeDados/conexao.php';
require_once '../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$id_livro = $_POST['id_livro'] ?? $_GET['id_livro'] ?? null;
if (!$id_livro) {
    die("Livro não especificado.");
}


// Buscar dados do livro
$sqlLivro = "SELECT * FROM livros WHERE id_livro = ?";
$stmtLivro = $conn->prepare($sqlLivro);
$stmtLivro->execute([$id_livro]);
$livro = $stmtLivro->fetch(PDO::FETCH_ASSOC);

if (!$livro) {
    die("Livro não encontrado.");
}

// Buscar receitas associadas ao livro
$sqlReceitas = "
    SELECT 
        r.nome_receita, 
        r.modo_preparo, 
        r.tempo_preparo, 
        r.porcoes, 
        r.dificuldade, 
        r.descricao,
        f.nome AS nome_cozinheiro, 
        fr.foto AS blob_foto
    FROM receitas r
    INNER JOIN livro_receita lr ON r.nome_receita = lr.nome_receita
    INNER JOIN funcionarios f ON f.id_funcionario = r.id_funcionario
    LEFT JOIN foto_receita fr ON fr.nome_receita = r.nome_receita
    WHERE lr.id_livro = ?
";
$stmtReceitas = $conn->prepare($sqlReceitas);
$stmtReceitas->execute([$id_livro]);
$receitas = $stmtReceitas->fetchAll(PDO::FETCH_ASSOC);


// Adicionei
// Buscar nome do editor
$sqlEditor = "SELECT f.nome FROM funcionarios f 
              INNER JOIN livros l ON f.id_funcionario = l.id_funcionario 
              WHERE l.id_livro = ?";
$stmtEditor = $conn->prepare($sqlEditor);
$stmtEditor->execute([$id_livro]);
$editor = $stmtEditor->fetchColumn();

// Adicionei
// Buscar avaliações dos degustadores
$sqlDegustacoes = "SELECT d.nome_receita, d.nota, d.descricao, f.id_funcionario, f.nome AS nome_degustador
                   FROM degustacoes d
                   INNER JOIN funcionarios f ON f.id_funcionario = d.id_funcionario
                   WHERE d.nome_receita IN (
                       SELECT nome_receita FROM livro_receita WHERE id_livro = ?
                   )";
$stmtDegustacoes = $conn->prepare($sqlDegustacoes);
$stmtDegustacoes->execute([$id_livro]);
$degustacoes = $stmtDegustacoes->fetchAll(PDO::FETCH_ASSOC);

// Adicionei
// Organizar por receita
$avaliacoesPorReceita = [];
foreach ($degustacoes as $d) {
    $avaliacoesPorReceita[$d['nome_receita']][] = $d;
}

// Opções do Dompdf
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

// HTML com estilo do protótipo
$html = '
<meta charset="UTF-8">
<style>
    body {
        font-family: "DejaVu Sans", serif;
        font-size: 12pt;
        text-align: justify;
        line-height: 1.5;
        margin: 40px;
    }
    h1 {
        text-align: center;
        font-size: 20pt;
        font-weight: bold;
        margin-bottom: 30px;
    }
    h2 {
        font-size: 16pt;
        font-weight: bold;
        margin-top: 25px;
        text-align: center;
    }
    h3 {
        font-size: 14pt;
        font-weight: bold;
        margin-top: 15px;
    }
    p {
        margin: 4px 0;
    }
    hr {
        margin: 20px 0;
    }
    .page-break {
        page-break-after: always;
    }
    .receita {
        margin-bottom: 10px;
}
</style>
';

// Cabeçalho do livro
$html .= '<h1>' . htmlspecialchars($livro['titulo']) . '</h1>';
$html .= '<p><strong>ISBN:</strong> ' . htmlspecialchars($livro['isbn']) . '</p>';
$html .= '<p><strong>Descrição:</strong> ' . htmlspecialchars($livro['descricao']) . '</p>';
// Para exibir o editor do livro
$html .= '<p><strong>Editor Responsável:</strong> ' . htmlspecialchars($editor) . '</p>';

$html .= '<h2>Receitas</h2>';

// Receitas
// Aqui para não ter páginas extras no final do PDF
$ultimaReceita = end($receitas);
foreach ($receitas as $r) {
    // Para agrupamento visual e controle de espaçamento
    $html .= '<div class="receita">';
    $html .= '<h3>' . htmlspecialchars($r['nome_receita']) . '</h3>';
    $html .= '<p><strong>Modo de Preparo:</strong> ' . nl2br(htmlspecialchars($r['modo_preparo'])) . '</p>';
    $html .= '<p><strong>Tempo de Preparo:</strong> ' . htmlspecialchars($r['tempo_preparo']) . ' minutos</p>';
    $html .= '<p><strong>Porções:</strong> ' . htmlspecialchars($r['porcoes']) . '</p>';
    $html .= '<p><strong>Dificuldade:</strong> ' . htmlspecialchars($r['dificuldade']) . '</p>';
    $html .= '<p><strong>Descrição:</strong> ' . htmlspecialchars($r['descricao']) . '</p>';
    
    // Nome do cozinheiro
    $html .= '<p><strong>Cozinheiro Responsável:</strong> ' . htmlspecialchars($r['nome_cozinheiro']) . '</p>';
    
    // Foto da receita (se existir)
    if (!empty($r['blob_foto'])) {
        $base64 = base64_encode($r['blob_foto']);
        $html .= '<p><strong>Foto da Receita:</strong><br>';
        $html .= '<img src="data:image/jpeg;base64,' . $base64 . '" style="max-width: 400px; height: auto;"></p>';
    }
    // Adicionei
    // Avaliações dos degustadores
    if (!empty($avaliacoesPorReceita[$r['nome_receita']])) {
        $html .= '<p><strong>Avaliações dos Degustadores:</strong></p>';
        foreach ($avaliacoesPorReceita[$r['nome_receita']] as $avaliacao) {
            $html .= '<br>Degustador: ' . htmlspecialchars($avaliacao['nome_degustador']) .
                     ' (ID: ' . htmlspecialchars($avaliacao['id_funcionario']) . ')<br>';
            $html .= 'Nota: ' . htmlspecialchars($avaliacao['nota']) . '</br>';
            $html .= 'Comentário: ' . htmlspecialchars($avaliacao['descricao']) . '</p>';
        }
    } 
    // Fim da receita
    $html .= '</div>';

    // Só adiciona quebra de página se não for a última receita
    if ($r !== $ultimaReceita) {
        $html .= '<div style="page-break-after: always;"></div>';
    }
}

// Gerar e exibir PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("Livro_" . $livro['id_livro'] . ".pdf", ["Attachment" => false]);
exit;

