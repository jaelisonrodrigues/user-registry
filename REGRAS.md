# Sistema de Cadastro de Eventos — Regras e Decisões de Design

## Visão geral

Sistema com um formulário único (`formulario.php`) que grava inscrições de
participantes de um evento em uma única tabela
(`formulario_unico_inscricoes`), mais três páginas de consulta sobre esses
dados: `dashboard.php` (estatísticas), `certificado.php` (emissão de
certificado por e-mail) e `inscritos.php` (listagem simples). Todas filtram
os dados pelo `evento_uuid` configurado em `config.php`.

## Arquivos

| Arquivo | Função |
|---|---|
| `config.php` | Configurações do evento atual (UUID, descrição, data) e credenciais do banco. |
| `schema.sql` | Script de criação da tabela `formulario_unico_inscricoes`. Executar uma única vez por banco. |
| `formulario.php` | Página do formulário: exibe, valida, verifica duplicidade e insere no banco. |
| `dashboard.php` | Estatísticas do evento atual: total de inscritos e distribuição por vínculo, categoria, gênero, faixa etária, estado, nível de formação e top 10 cidades. |
| `certificado.php` | Pede o e-mail da pessoa e gera um certificado (proporção 16:9) para impressão/PDF. |
| `inscritos.php` | Listagem simples dos inscritos do evento atual, em ordem alfabética por nome. |

## Decisão de arquitetura: tabela única

Optou-se por manter **todos os dados em uma única tabela**, incluindo a
descrição do evento repetida em cada linha (`evento_descricao`). Isso é uma
escolha consciente para acelerar o MVP, mesmo sabendo que:

- Corrigir a descrição de um evento exige atualizar todas as linhas daquele
  `evento_uuid` (ou aceitar a inconsistência).
- Não há uma tabela separada de "eventos" — cada evento existe apenas como
  um valor de `evento_uuid` presente nas inscrições.

Uma evolução futura natural seria separar em `eventos` e
`formulario_unico_inscricoes` (relacionadas por `evento_uuid`), mas isso
está fora do escopo do MVP atual.

## Nome da tabela

A tabela recebeu o nome `formulario_unico_inscricoes` propositalmente longo
e descritivo, para deixar claro — dentro de um banco que já tem outras
tabelas de outros sistemas — que ela pertence a este formulário isolado e
independente, sem exigir um prefixo curto genérico.

## Banco de dados

O `config.php` está configurado para conectar no banco online já usado por
outro aplicativo PHP do usuário (`gamificar4.mysql.dbaas.com.br`),
reaproveitando as mesmas credenciais. A conexão é feita via **PDO** (não
mysqli), mas isso não muda nada do lado do servidor MySQL — apenas a
biblioteca PHP usada para falar com ele.

**Atenção:** o `config.php` contém a senha do banco em texto puro. Se este
projeto for versionado em Git ou repositório compartilhado, o ideal é
adicionar `config.php` ao `.gitignore` e manter só um `config.example.php`
sem os valores reais.

## Como trocar de evento

`EVENTO_UUID`, `EVENTO_DESCRICAO` e `EVENTO_DATA` ficam fixos em `config.php`.
Para reaproveitar as mesmas páginas em um novo evento, basta:

1. Gerar um novo UUID (v4) para `EVENTO_UUID`.
2. Atualizar `EVENTO_DESCRICAO` com o nome do novo evento.
3. Atualizar `EVENTO_DATA` (formato `AAAA-MM-DD`) com a data do novo evento.
4. Nenhuma outra alteração de código é necessária — `formulario.php`,
   `dashboard.php`, `certificado.php` e `inscritos.php` já refletem a
   mudança automaticamente.

## Campos do formulário

| Campo | Coluna no banco | Tipo | Obrigatório? |
|---|---|---|---|
| Nome completo | `nome_completo` | VARCHAR(150) | Sim |
| E-mail | `email` | VARCHAR(150) | Sim |
| Telefone/WhatsApp | `telefone` | VARCHAR(20) | Não |
| Faixa etária | `faixa_etaria` | ENUM | Sim |
| Gênero | `genero` | ENUM | Sim |
| Estado | `estado` | CHAR(2) | Sim |
| Cidade | `cidade` | VARCHAR(100) | Sim |
| Ocupação/área de atuação | `ocupacao` | VARCHAR(100) | Sim |
| Nível de formação | `nivel_formacao` | ENUM | Sim |
| Tipo de vínculo | `tipo_vinculo` | ENUM('Interno','Externo') | Sim |
| Categoria | `categoria` | ENUM('Aluno','Professor','Técnico','Convidado') | Sim |

Campos de metadados (preenchidos automaticamente, não vêm do formulário):
`id` (chave primária) e `data_inscricao` (timestamp da submissão).

### Por que faixa etária em vez de data de nascimento?

Decisão consciente: para fins estatísticos, faixa etária entrega o que é
necessário com menor exposição de dado pessoal do que uma data de nascimento
completa.

### Por que gênero é obrigatório mesmo com "Prefiro não informar"?

O objetivo é garantir que o campo sempre seja preenchido (evitando dados
ausentes nos relatórios), mas sem forçar a pessoa a se identificar em uma
categoria específica — "Prefiro não informar" é uma resposta válida e
contabilizável.

## Selects dependentes

### Estado → Cidade

Implementado via **API pública do IBGE**, sem necessidade de chave de API:

- Lista de estados: `GET https://servicodados.ibge.gov.br/api/v1/localidades/estados`
- Lista de municípios de um estado: `GET https://servicodados.ibge.gov.br/api/v1/localidades/estados/{UF}/municipios`

Fluxo: ao carregar a página, o JavaScript busca a lista de estados e popula
o primeiro `<select>`. Ao escolher um estado, uma segunda chamada busca os
municípios daquele estado e popula o `<select>` de cidade. O campo cidade
fica desabilitado até que um estado seja selecionado.

**Vantagem sobre lista hardcoded:** dados oficiais, sempre atualizados, e
evita inconsistência de nomes de cidade digitados manualmente (ex.: "Recife"
vs. "recife").

### Tipo de vínculo → Categoria

Lógica fixa em JavaScript (sem API, pois as opções não mudam com frequência):

```
Interno → Aluno / Professor / Técnico
Externo → Convidado
```

O campo categoria fica desabilitado até que um tipo de vínculo seja
selecionado. Essa mesma lógica de opções é validada novamente no lado do
servidor (PHP), para impedir que alguém envie uma combinação inválida
manipulando o formulário.

**Por que essa divisão em duas camadas?** O "tipo de vínculo" (Interno/Externo)
é um valor estável, comparável entre diferentes eventos ao longo do tempo.
Já a "categoria" pode ser ajustada por evento no futuro (por exemplo,
adicionando novas opções de público externo), sem quebrar a possibilidade de
comparar estatísticas de "interno vs. externo" entre eventos diferentes.

## Regra de duplicidade de e-mail

Uma mesma pessoa **pode se inscrever em eventos diferentes**, mas **não pode
se inscrever duas vezes no mesmo evento**. Isso é garantido em duas camadas:

1. **Aplicação (PHP):** antes de inserir, o script verifica se já existe uma
   linha com o mesmo `email` e o mesmo `evento_uuid`, exibindo uma mensagem
   de erro amigável caso exista.
2. **Banco de dados:** uma constraint `UNIQUE (email, evento_uuid)` na tabela
   garante a regra mesmo em caso de concorrência (duas submissões quase
   simultâneas), evitando inconsistência que a checagem em PHP sozinha não
   cobriria.

## Comportamento pós-submissão do formulário

- Em caso de sucesso: mensagem de confirmação exibida na própria página,
  informando que um e-mail de confirmação será enviado.
- **Importante:** o envio do e-mail em si não está implementado. O código
  já indica, com um comentário, o ponto exato onde essa integração (ex.:
  via PHPMailer/SMTP) deveria entrar.
- Em caso de erro de validação ou duplicidade: os erros são listados no topo
  do formulário, e os dados já preenchidos pelo usuário são mantidos nos
  campos (exceto os selects dependentes de estado/cidade/categoria, que são
  repopulados via JavaScript ao carregar a página).

## Dashboard de estatísticas (`dashboard.php`)

- Escopo: apenas o evento atual (`EVENTO_UUID` do `config.php`).
- Estatísticas mostradas: total de inscritos, Interno vs. Externo (pizza),
  categoria (barras), gênero (pizza), faixa etária (barras), estado (barras),
  nível de formação (barras) e uma tabela com as 10 cidades com mais
  inscritos.
- Os gráficos usam a biblioteca **Chart.js** (versão 4.4.1), carregada via
  CDN (cdnjs.cloudflare.com). Não requer instalação local.
- Uma função PHP única (`contarPorCampo`) faz o `SELECT ... GROUP BY` para
  qualquer uma das colunas, usando uma lista branca de colunas permitidas
  para evitar SQL injection via nome de coluna.

## Certificado (`certificado.php`)

- Fluxo: a página pede o e-mail da pessoa; busca `nome_completo` e
  `evento_descricao` na tabela, filtrando por `email` e `evento_uuid`; se
  encontrado, exibe o certificado.
- Layout do certificado, em proporção **16:9** (`aspect-ratio: 16/9`):
  - Terço superior, centralizado: "CERTIFICADO"
  - Centro: "Certificamos que **[nome]** participou do evento **[evento]**"
  - Rodapé: data por extenso (ex.: "12 de Setembro de 2026"), formatada a
    partir de `EVENTO_DATA` (ver decisão abaixo).
- Um botão "Imprimir certificado" aciona `window.print()`. O CSS de
  impressão oculta formulário/botões e ajusta a página para paisagem, então
  o usuário pode imprimir ou "Salvar como PDF" pelo próprio diálogo de
  impressão do navegador.

### Por que a data do certificado vem de `EVENTO_DATA` e não da data de inscrição?

Decisão do usuário: a data exibida no certificado é a **data do evento**
(fixa, configurada uma vez em `config.php`), e não a data em que a pessoa se
inscreveu nem a data em que o certificado é impresso.

## Listagem de inscritos (`inscritos.php`)

- Escopo: apenas o evento atual (`EVENTO_UUID`).
- Ordenação: alfabética por `nome_completo`.
- Colunas exibidas: nome, e-mail, telefone, tipo de vínculo, categoria e
  data/hora de inscrição.
- Contador do total de inscritos exibido no topo.
- Campos demográficos (estado, cidade, gênero, faixa etária, ocupação,
  nível de formação) foram deixados de fora desta listagem por decisão de
  design — eles já aparecem de forma agregada no `dashboard.php`; aqui o
  objetivo é identificar rapidamente cada pessoa inscrita.
- Botão de impressão simples (sem formatação especial de página).

## Segurança básica aplicada

- Todas as consultas ao banco usam **prepared statements** (PDO), evitando
  SQL injection — inclusive a lista branca de colunas em `dashboard.php`
  para os `GROUP BY` dinâmicos.
- Toda saída de dados do usuário nas páginas é tratada com
  `htmlspecialchars()`, evitando XSS refletido.
- Erros de banco de dados não são exibidos ao usuário (mensagem genérica),
  para não vazar detalhes internos da aplicação.

## Pontos em aberto / próximos passos possíveis

- Implementar o envio real do e-mail de confirmação no `formulario.php`.
- Avaliar, se o sistema crescer, migrar para um modelo com tabela `eventos`
  separada de `formulario_unico_inscricoes`.
- Proteger `dashboard.php`, `certificado.php` e `inscritos.php` com algum
  tipo de autenticação, caso venham a ficar acessíveis publicamente (hoje
  qualquer pessoa com a URL consegue ver estatísticas e a lista de
  inscritos).
- Mover a senha do banco para fora do controle de versão (`.gitignore` +
  `config.example.php`), caso o projeto seja versionado em repositório.
