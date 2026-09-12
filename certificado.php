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

/** Formata uma data AAAA-MM-DD para "12 de Setembro de 2026", em português. */
function formatarDataPorExtenso(string $dataIso): string
{
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];
    $timestamp = strtotime($dataIso);
    if ($timestamp === false) {
        return $dataIso;
    }
    $dia = (int) date('j', $timestamp);
    $mes = $meses[(int) date('n', $timestamp)];
    $ano = date('Y', $timestamp);
    return "{$dia} de {$mes} de {$ano}";
}

$email = trim($_GET['email'] ?? '');
$erro = null;
$participante = null;

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        try {
            $pdo = conectarBanco();
            $stmt = $pdo->prepare(
                'SELECT nome_completo, evento_descricao
                 FROM formulario_unico_inscricoes
                 WHERE email = :email AND evento_uuid = :evento_uuid'
            );
            $stmt->execute([
                ':email'       => $email,
                ':evento_uuid' => EVENTO_UUID,
            ]);
            $participante = $stmt->fetch();

            if (!$participante) {
                $erro = 'Não encontramos inscrição com este e-mail para este evento.';
            }
        } catch (PDOException $e) {
            $erro = 'Não foi possível consultar os dados no momento.';
            // Em produção: registrar $e->getMessage() em log.
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Certificado — <?= htmlspecialchars(EVENTO_DESCRICAO) ?></title>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 640px;
        margin: 40px auto;
        padding: 0 16px;
        color: #222;
    }
    label { display: block; margin-top: 14px; font-weight: bold; }
    input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
    button { margin-top: 20px; padding: 10px 20px; cursor: pointer; }
    .erro { background: #fdecea; color: #a94442; padding: 12px; border-radius: 4px; margin-top: 16px; }

    /* ---- Certificado em proporção 16:9 ---- */
    .certificado {
        width: 100%;
        aspect-ratio: 16 / 9;
        margin: 32px auto;
        border: 2px solid #1a3d63;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
        text-align: center;
        padding: 5% 8%;
        box-sizing: border-box;
        background: #fdfdfd;
    }
    .certificado .titulo {
        font-size: clamp(1.5rem, 4vw, 2.6rem);
        letter-spacing: 0.15em;
        font-weight: bold;
        color: #1a3d63;
    }
    .certificado .corpo {
        font-size: clamp(1rem, 2.2vw, 1.4rem);
        line-height: 1.6;
        max-width: 90%;
    }
    .certificado .corpo strong { color: #1a3d63; }
    .certificado .data {
        font-size: clamp(0.9rem, 1.8vw, 1.1rem);
        color: #444;
    }

    .acoes { text-align: center; margin-top: 16px; }

    @media print {
        body { margin: 0; padding: 0; max-width: none; }
        label, input, .acoes, form { display: none !important; }
        .certificado { margin: 0; border: 2px solid #1a3d63; page-break-inside: avoid; }
        @page { size: landscape; margin: 0; }
    }
</style>
</head>
<body>

<?php if (!$participante): ?>

    <h1>Emitir certificado</h1>

    <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="GET" action="">
        <label>Informe o e-mail usado na inscrição
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
        </label>
        <button type="submit">Gerar certificado</button>
    </form>

<?php else: ?>

    <div class="certificado">
        <div class="titulo">CERTIFICADO</div>

        <div class="corpo">
            Certificamos que <strong><?= htmlspecialchars($participante['nome_completo']) ?></strong>
            participou do evento <strong><?= htmlspecialchars($participante['evento_descricao']) ?></strong>
        </div>

        <div class="data"><?= htmlspecialchars(formatarDataPorExtenso(EVENTO_DATA)) ?></div>
    </div>

    <div class="acoes">
        <button onclick="window.print()">Imprimir certificado</button>
    </div>

<?php endif; ?>

</body>
</html>
