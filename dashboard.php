<?php
require __DIR__ . '/config.php';

function conectarBanco(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

/**
 * Retorna contagem de inscritos agrupada por uma coluna, filtrando pelo
 * evento atual (EVENTO_UUID). Usada para montar cada gráfico do dashboard.
 */
function contarPorCampo(PDO $pdo, string $coluna, ?int $limite = null): array
{
    // Lista branca de colunas permitidas, para evitar SQL injection via nome de coluna.
    $colunasPermitidas = [
        'tipo_vinculo', 'categoria', 'genero', 'faixa_etaria',
        'estado', 'cidade', 'nivel_formacao',
    ];
    if (!in_array($coluna, $colunasPermitidas, true)) {
        throw new InvalidArgumentException('Coluna não permitida: ' . $coluna);
    }

    $sql = "SELECT $coluna AS rotulo, COUNT(*) AS total
            FROM formulario_unico_inscricoes
            WHERE evento_uuid = :evento_uuid
            GROUP BY $coluna
            ORDER BY total DESC";
    if ($limite !== null) {
        $sql .= ' LIMIT ' . (int) $limite;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':evento_uuid' => EVENTO_UUID]);
    return $stmt->fetchAll();
}

$erroConexao = null;
$totalInscritos = 0;
$porVinculo = $porCategoria = $porGenero = $porFaixaEtaria = [];
$porEstado = $porNivelFormacao = $topCidades = [];

try {
    $pdo = conectarBanco();

    $stmtTotal = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM formulario_unico_inscricoes WHERE evento_uuid = :evento_uuid'
    );
    $stmtTotal->execute([':evento_uuid' => EVENTO_UUID]);
    $totalInscritos = (int) $stmtTotal->fetch()['total'];

    $porVinculo       = contarPorCampo($pdo, 'tipo_vinculo');
    $porCategoria     = contarPorCampo($pdo, 'categoria');
    $porGenero        = contarPorCampo($pdo, 'genero');
    $porFaixaEtaria   = contarPorCampo($pdo, 'faixa_etaria');
    $porEstado        = contarPorCampo($pdo, 'estado');
    $porNivelFormacao = contarPorCampo($pdo, 'nivel_formacao');
    $topCidades       = contarPorCampo($pdo, 'cidade', 10);
} catch (PDOException $e) {
    $erroConexao = 'Não foi possível carregar as estatísticas no momento.';
    // Em produção: registrar $e->getMessage() em log.
}

/** Converte o resultado de contarPorCampo() em dois arrays (labels, valores) para o Chart.js. */
function separarLabelsValores(array $linhas): array
{
    return [
        array_column($linhas, 'rotulo'),
        array_column($linhas, 'total'),
    ];
}

[$labelsVinculo, $valoresVinculo]     = separarLabelsValores($porVinculo);
[$labelsCategoria, $valoresCategoria] = separarLabelsValores($porCategoria);
[$labelsGenero, $valoresGenero]       = separarLabelsValores($porGenero);
[$labelsFaixa, $valoresFaixa]         = separarLabelsValores($porFaixaEtaria);
[$labelsEstado, $valoresEstado]       = separarLabelsValores($porEstado);
[$labelsNivel, $valoresNivel]         = separarLabelsValores($porNivelFormacao);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Dashboard — <?= htmlspecialchars(EVENTO_DESCRICAO) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<style>
    body { font-family: Arial, sans-serif; max-width: 1100px; margin: 40px auto; padding: 0 16px; color: #222; }
    h1 { font-size: 1.4rem; margin-bottom: 4px; }
    .subtitulo { color: #666; margin-top: 0; }
    .erro { background: #fdecea; color: #a94442; padding: 12px; border-radius: 4px; }
    .cartao-total { background: #eef4ff; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; }
    .cartao-total .numero { font-size: 2.4rem; font-weight: bold; color: #234; }
    .grade { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px; }
    .grafico-box { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 16px; }
    .grafico-box h2 { font-size: 1rem; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
    @media (max-width: 800px) { .grade { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<h1>Dashboard de Estatísticas</h1>
<p class="subtitulo"><?= htmlspecialchars(EVENTO_DESCRICAO) ?></p>

<?php if ($erroConexao): ?>
    <div class="erro"><?= htmlspecialchars($erroConexao) ?></div>
<?php else: ?>

    <div class="cartao-total">
        <div class="numero"><?= $totalInscritos ?></div>
        <div>Total de inscritos</div>
    </div>

    <div class="grade">

        <div class="grafico-box">
            <h2>Interno vs Externo</h2>
            <canvas id="graficoVinculo"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Categoria</h2>
            <canvas id="graficoCategoria"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Gênero</h2>
            <canvas id="graficoGenero"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Faixa etária</h2>
            <canvas id="graficoFaixa"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Por estado</h2>
            <canvas id="graficoEstado"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Nível de formação</h2>
            <canvas id="graficoNivel"></canvas>
        </div>

        <div class="grafico-box">
            <h2>Top 10 cidades</h2>
            <table>
                <thead><tr><th>Cidade</th><th>Inscritos</th></tr></thead>
                <tbody>
                    <?php foreach ($topCidades as $linha): ?>
                        <tr>
                            <td><?= htmlspecialchars($linha['rotulo']) ?></td>
                            <td><?= (int) $linha['total'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <script>
        const paletaCores = [
            '#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f',
            '#edc948', '#b07aa1', '#ff9da7', '#9c755f', '#bab0ac',
        ];

        function criarGrafico(idCanvas, tipo, labels, valores) {
            new Chart(document.getElementById(idCanvas), {
                type: tipo,
                data: {
                    labels: labels,
                    datasets: [{
                        data: valores,
                        backgroundColor: paletaCores,
                    }],
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: tipo === 'pie' } },
                    scales: tipo === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {},
                },
            });
        }

        criarGrafico('graficoVinculo', 'pie', <?= json_encode($labelsVinculo) ?>, <?= json_encode($valoresVinculo) ?>);
        criarGrafico('graficoCategoria', 'bar', <?= json_encode($labelsCategoria) ?>, <?= json_encode($valoresCategoria) ?>);
        criarGrafico('graficoGenero', 'pie', <?= json_encode($labelsGenero) ?>, <?= json_encode($valoresGenero) ?>);
        criarGrafico('graficoFaixa', 'bar', <?= json_encode($labelsFaixa) ?>, <?= json_encode($valoresFaixa) ?>);
        criarGrafico('graficoEstado', 'bar', <?= json_encode($labelsEstado) ?>, <?= json_encode($valoresEstado) ?>);
        criarGrafico('graficoNivel', 'bar', <?= json_encode($labelsNivel) ?>, <?= json_encode($valoresNivel) ?>);
    </script>

<?php endif; ?>

</body>
</html>
