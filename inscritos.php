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

$erro = null;
$inscritos = [];

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare(
        'SELECT nome_completo, email, telefone, tipo_vinculo, categoria, data_inscricao
         FROM formulario_unico_inscricoes
         WHERE evento_uuid = :evento_uuid
         ORDER BY nome_completo ASC'
    );
    $stmt->execute([':evento_uuid' => EVENTO_UUID]);
    $inscritos = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = 'Não foi possível carregar a lista de inscritos no momento.';
    // Em produção: registrar $e->getMessage() em log.
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Inscritos — <?= htmlspecialchars(EVENTO_DESCRICAO) ?></title>
<style>
    body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 16px; color: #222; }
    h1 { font-size: 1.4rem; margin-bottom: 4px; }
    .subtitulo { color: #666; margin-top: 0; }
    .erro { background: #fdecea; color: #a94442; padding: 12px; border-radius: 4px; }
    .total { margin: 16px 0; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 0.92rem; }
    th { background: #f5f7fa; }
    tr:hover { background: #fafafa; }

    @media print {
        .no-print { display: none !important; }
    }
</style>
</head>
<body>

<h1>Lista de Inscritos</h1>
<p class="subtitulo"><?= htmlspecialchars(EVENTO_DESCRICAO) ?></p>

<?php if ($erro): ?>
    <div class="erro"><?= htmlspecialchars($erro) ?></div>
<?php else: ?>

    <div class="total"><?= count($inscritos) ?> inscrito<?= count($inscritos) === 1 ? '' : 's' ?></div>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Telefone</th>
                <th>Vínculo</th>
                <th>Categoria</th>
                <th>Inscrito em</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($inscritos as $pessoa): ?>
                <tr>
                    <td><?= htmlspecialchars($pessoa['nome_completo']) ?></td>
                    <td><?= htmlspecialchars($pessoa['email']) ?></td>
                    <td><?= htmlspecialchars($pessoa['telefone'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($pessoa['tipo_vinculo']) ?></td>
                    <td><?= htmlspecialchars($pessoa['categoria']) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($pessoa['data_inscricao']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()">Imprimir lista</button>
    </div>

<?php endif; ?>

</body>
</html>
