<?php
require __DIR__ . '/config.php';

$erros = [];
$sucesso = false;

function conectarBanco(): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    return new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---- Coleta e limpeza básica dos campos ----
    $nome_completo  = trim($_POST['nome_completo'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $telefone       = trim($_POST['telefone'] ?? '');
    $faixa_etaria   = $_POST['faixa_etaria'] ?? '';
    $genero         = $_POST['genero'] ?? '';
    $estado         = strtoupper(trim($_POST['estado'] ?? ''));
    $cidade         = trim($_POST['cidade'] ?? '');
    $ocupacao       = trim($_POST['ocupacao'] ?? '');
    $nivel_formacao = $_POST['nivel_formacao'] ?? '';
    $tipo_vinculo   = $_POST['tipo_vinculo'] ?? '';
    $categoria      = $_POST['categoria'] ?? '';

    // ---- Validação ----
    if ($nome_completo === '') {
        $erros[] = 'Nome completo é obrigatório.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if ($faixa_etaria === '') {
        $erros[] = 'Faixa etária é obrigatória.';
    }
    if ($genero === '') {
        $erros[] = 'Gênero é obrigatório.';
    }
    if ($estado === '' || strlen($estado) !== 2) {
        $erros[] = 'Estado é obrigatório.';
    }
    if ($cidade === '') {
        $erros[] = 'Cidade é obrigatória.';
    }
    if ($ocupacao === '') {
        $erros[] = 'Ocupação/área de atuação é obrigatória.';
    }
    if ($nivel_formacao === '') {
        $erros[] = 'Nível de formação é obrigatório.';
    }
    if (!in_array($tipo_vinculo, ['Interno', 'Externo'], true)) {
        $erros[] = 'Tipo de vínculo é obrigatório.';
    }
    $categoriasValidas = [
        'Interno' => ['Aluno', 'Professor', 'Técnico'],
        'Externo' => ['Convidado'],
    ];
    if (
        $tipo_vinculo === '' ||
        !isset($categoriasValidas[$tipo_vinculo]) ||
        !in_array($categoria, $categoriasValidas[$tipo_vinculo], true)
    ) {
        $erros[] = 'Categoria inválida para o tipo de vínculo selecionado.';
    }

    // ---- Verificação de duplicidade (mesmo e-mail, mesmo evento) + inserção ----
    if (empty($erros)) {
        try {
            $pdo = conectarBanco();

            $verifica = $pdo->prepare(
                'SELECT id FROM formulario_unico_inscricoes WHERE email = :email AND evento_uuid = :evento_uuid'
            );
            $verifica->execute([
                ':email'       => $email,
                ':evento_uuid' => EVENTO_UUID,
            ]);

            if ($verifica->fetch()) {
                $erros[] = 'Este e-mail já possui inscrição registrada para este evento.';
            } else {
                $insere = $pdo->prepare(
                    'INSERT INTO formulario_unico_inscricoes
                        (evento_uuid, evento_descricao, nome_completo, email, telefone,
                         faixa_etaria, genero, estado, cidade, ocupacao, nivel_formacao,
                         tipo_vinculo, categoria)
                     VALUES
                        (:evento_uuid, :evento_descricao, :nome_completo, :email, :telefone,
                         :faixa_etaria, :genero, :estado, :cidade, :ocupacao, :nivel_formacao,
                         :tipo_vinculo, :categoria)'
                );
                $insere->execute([
                    ':evento_uuid'      => EVENTO_UUID,
                    ':evento_descricao' => EVENTO_DESCRICAO,
                    ':nome_completo'    => $nome_completo,
                    ':email'            => $email,
                    ':telefone'         => $telefone !== '' ? $telefone : null,
                    ':faixa_etaria'     => $faixa_etaria,
                    ':genero'           => $genero,
                    ':estado'           => $estado,
                    ':cidade'           => $cidade,
                    ':ocupacao'         => $ocupacao,
                    ':nivel_formacao'   => $nivel_formacao,
                    ':tipo_vinculo'     => $tipo_vinculo,
                    ':categoria'        => $categoria,
                ]);

                $sucesso = true;

                // Ponto de extensão: disparar e-mail de confirmação real aqui
                // (ex.: via PHPMailer/SMTP). Por enquanto, apenas avisamos na tela.
            }
        } catch (PDOException $e) {
            $erros[] = 'Erro ao salvar a inscrição. Tente novamente mais tarde.';
            // Em produção: registrar $e->getMessage() em log, não exibir ao usuário.
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<title>Inscrição — <?= htmlspecialchars(EVENTO_DESCRICAO) ?></title>
<style>
    body { font-family: Arial, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; color: #222; }
    h1 { font-size: 1.4rem; }
    label { display: block; margin-top: 14px; font-weight: bold; }
    input, select { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
    button { margin-top: 24px; padding: 10px 20px; cursor: pointer; }
    .erro { background: #fdecea; color: #a94442; padding: 12px; border-radius: 4px; margin-top: 16px; }
    .sucesso { background: #eafaf0; color: #1a7f4a; padding: 12px; border-radius: 4px; margin-top: 16px; }
    .opcional { font-weight: normal; color: #666; font-size: 0.85rem; }
</style>
</head>
<body>

<h1>Inscrição — <?= htmlspecialchars(EVENTO_DESCRICAO) ?></h1>

<?php if ($sucesso): ?>
    <div class="sucesso">
        Inscrição realizada com sucesso! Um e-mail de confirmação será enviado em breve.
    </div>
<?php else: ?>

    <?php if (!empty($erros)): ?>
        <div class="erro">
            <ul>
                <?php foreach ($erros as $erro): ?>
                    <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Nome completo
            <input type="text" name="nome_completo" value="<?= htmlspecialchars($_POST['nome_completo'] ?? '') ?>" required>
        </label>

        <label>E-mail
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
        </label>

        <label>Telefone/WhatsApp <span class="opcional">(opcional)</span>
            <input type="text" name="telefone" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
        </label>

        <label>Faixa etária
            <select name="faixa_etaria" required>
                <option value="">Selecione</option>
                <?php foreach (['18-24','25-34','35-44','45-54','55-64','65+'] as $f): ?>
                    <option value="<?= $f ?>" <?= (($_POST['faixa_etaria'] ?? '') === $f) ? 'selected' : '' ?>><?= $f ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Gênero
            <select name="genero" required>
                <option value="">Selecione</option>
                <?php foreach (['Masculino','Feminino','Outro','Prefiro não informar'] as $g): ?>
                    <option value="<?= $g ?>" <?= (($_POST['genero'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Estado
            <select name="estado" id="estado" required>
                <option value="">Carregando estados...</option>
            </select>
        </label>

        <label>Cidade
            <select name="cidade" id="cidade" required disabled>
                <option value="">Selecione o estado primeiro</option>
            </select>
        </label>

        <label>Ocupação/área de atuação
            <input type="text" name="ocupacao" value="<?= htmlspecialchars($_POST['ocupacao'] ?? '') ?>" required>
        </label>

        <label>Nível de formação
            <select name="nivel_formacao" required>
                <option value="">Selecione</option>
                <?php foreach ([
                    'Ensino Fundamental','Ensino Médio','Ensino Técnico',
                    'Graduando','Graduado','Pós-graduando','Pós-graduado',
                    'Mestrando','Mestre','Doutorando','Doutor',
                ] as $n): ?>
                    <option value="<?= $n ?>" <?= (($_POST['nivel_formacao'] ?? '') === $n) ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>Tipo de vínculo
            <select name="tipo_vinculo" id="tipo_vinculo" required>
                <option value="">Selecione</option>
                <option value="Interno" <?= (($_POST['tipo_vinculo'] ?? '') === 'Interno') ? 'selected' : '' ?>>Interno</option>
                <option value="Externo" <?= (($_POST['tipo_vinculo'] ?? '') === 'Externo') ? 'selected' : '' ?>>Externo</option>
            </select>
        </label>

        <label>Categoria
            <select name="categoria" id="categoria" required disabled>
                <option value="">Selecione o tipo de vínculo primeiro</option>
            </select>
        </label>

        <button type="submit">Enviar inscrição</button>
    </form>

<?php endif; ?>

<script>
// ---- Estado/Cidade via API do IBGE ----
const selectEstado = document.getElementById('estado');
const selectCidade = document.getElementById('cidade');
const estadoPreSelecionado = <?= json_encode($_POST['estado'] ?? '') ?>;
const cidadePreSelecionada = <?= json_encode($_POST['cidade'] ?? '') ?>;

fetch('https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome')
    .then(res => res.json())
    .then(estados => {
        selectEstado.innerHTML = '<option value="">Selecione</option>';
        estados.forEach(uf => {
            const opt = document.createElement('option');
            opt.value = uf.sigla;
            opt.textContent = uf.nome + ' (' + uf.sigla + ')';
            if (uf.sigla === estadoPreSelecionado) opt.selected = true;
            selectEstado.appendChild(opt);
        });
        if (estadoPreSelecionado) carregarCidades(estadoPreSelecionado);
    })
    .catch(() => {
        selectEstado.innerHTML = '<option value="">Erro ao carregar estados</option>';
    });

selectEstado.addEventListener('change', () => carregarCidades(selectEstado.value));

function carregarCidades(sigla) {
    if (!sigla) {
        selectCidade.innerHTML = '<option value="">Selecione o estado primeiro</option>';
        selectCidade.disabled = true;
        return;
    }
    selectCidade.disabled = true;
    selectCidade.innerHTML = '<option value="">Carregando cidades...</option>';

    fetch(`https://servicodados.ibge.gov.br/api/v1/localidades/estados/${sigla}/municipios`)
        .then(res => res.json())
        .then(municipios => {
            selectCidade.innerHTML = '<option value="">Selecione</option>';
            municipios.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.nome;
                opt.textContent = m.nome;
                if (m.nome === cidadePreSelecionada) opt.selected = true;
                selectCidade.appendChild(opt);
            });
            selectCidade.disabled = false;
        })
        .catch(() => {
            selectCidade.innerHTML = '<option value="">Erro ao carregar cidades</option>';
        });
}

// ---- Tipo de vínculo → Categoria (fixo, sem API) ----
const selectTipoVinculo = document.getElementById('tipo_vinculo');
const selectCategoria = document.getElementById('categoria');
const categoriaPreSelecionada = <?= json_encode($_POST['categoria'] ?? '') ?>;

const categoriasPorVinculo = {
    'Interno': ['Aluno', 'Professor', 'Técnico'],
    'Externo': ['Convidado'],
};

selectTipoVinculo.addEventListener('change', () => atualizarCategorias(selectTipoVinculo.value));

function atualizarCategorias(tipo) {
    if (!tipo || !categoriasPorVinculo[tipo]) {
        selectCategoria.innerHTML = '<option value="">Selecione o tipo de vínculo primeiro</option>';
        selectCategoria.disabled = true;
        return;
    }
    selectCategoria.innerHTML = '<option value="">Selecione</option>';
    categoriasPorVinculo[tipo].forEach(cat => {
        const opt = document.createElement('option');
        opt.value = cat;
        opt.textContent = cat;
        if (cat === categoriaPreSelecionada) opt.selected = true;
        selectCategoria.appendChild(opt);
    });
    selectCategoria.disabled = false;
}

if (selectTipoVinculo.value) {
    atualizarCategorias(selectTipoVinculo.value);
}
</script>

</body>
</html>
