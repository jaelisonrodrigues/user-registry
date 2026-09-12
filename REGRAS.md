# Sistema de Cadastro de Eventos — Regras e Decisões de Design

## Visão geral

MVP de um formulário único (`formulario.php`) que grava inscrições de
participantes de um evento diretamente em uma única tabela (`formulario_unico_inscricoes`).
O objetivo é permitir, no futuro, gerar certificados e relatórios
estatísticos filtrando os dados pelo UUID do evento.

## Arquivos

| Arquivo | Função |
|---|---|
| `config.php` | Configurações do evento atual (UUID, descrição) e credenciais do banco. |
| `schema.sql` | Script de criação da tabela `formulario_unico_inscricoes`. Executar uma única vez por banco. |
| `formulario.php` | Página única: exibe o formulário, valida, verifica duplicidade e insere no banco. |

## Decisão de arquitetura: tabela única

Optou-se por manter **todos os dados em uma única tabela**, incluindo a
descrição do evento repetida em cada linha (`evento_descricao`). Isso é uma
escolha consciente para acelerar o MVP, mesmo sabendo que:

- Corrigir a descrição de um evento exige atualizar todas as linhas daquele
  `evento_uuid` (ou aceitar a inconsistência).
- Não há uma tabela separada de "eventos" — cada evento existe apenas como
  um valor de `evento_uuid` presente nas inscrições.

Uma evolução futura natural seria separar em `eventos` e `formulario_unico_inscricoes`
(relacionadas por `evento_uuid`), mas isso está fora do escopo do MVP atual.

## Como trocar de evento

O `evento_uuid` e `evento_descricao` ficam fixos em `config.php`. Para reaproveitar
o mesmo `formulario.php` em um novo evento, basta:

1. Gerar um novo UUID (v4) para `EVENTO_UUID`.
2. Atualizar `EVENTO_DESCRICAO` com o nome do novo evento.
3. Nenhuma outra alteração de código é necessária.

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

## Comportamento pós-submissão

- Em caso de sucesso: mensagem de confirmação exibida na própria página,
  informando que um e-mail de confirmação será enviado.
- **Importante:** o envio do e-mail em si não está implementado neste MVP.
  O código já indica, com um comentário, o ponto exato onde essa integração
  (ex.: via PHPMailer/SMTP) deveria entrar.
- Em caso de erro de validação ou duplicidade: os erros são listados no topo
  do formulário, e os dados já preenchidos pelo usuário são mantidos nos
  campos (exceto os selects dependentes de estado/cidade/categoria, que são
  repopulados via JavaScript ao carregar a página).

## Segurança básica aplicada

- Todas as consultas ao banco usam **prepared statements** (PDO), evitando
  SQL injection.
- Toda saída de dados do usuário na página é tratada com `htmlspecialchars()`,
  evitando XSS refletido.
- Erros de banco de dados não são exibidos ao usuário (mensagem genérica),
  para não vazar detalhes internos da aplicação.

## Pontos em aberto / próximos passos possíveis

- Implementar o envio real do e-mail de confirmação.
- Criar uma tela de relatórios/estatísticas que consulte `formulario_unico_inscricoes` por
  `evento_uuid`.
- Criar a geração de certificados a partir dos dados de `formulario_unico_inscricoes`.
- Avaliar, se o sistema crescer, migrar para um modelo com tabela `eventos`
  separada de `formulario_unico_inscricoes`.
