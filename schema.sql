-- Script de criação da tabela usada pelo formulário de inscrição.
-- Execute este script uma única vez no banco de dados definido em config.php.

CREATE TABLE IF NOT EXISTS formulario_unico_inscricoes (
    id                INT AUTO_INCREMENT PRIMARY KEY,

    -- Identificação do evento (duplicado por linha; decisão consciente para MVP)
    evento_uuid       VARCHAR(36)  NOT NULL,
    evento_descricao  VARCHAR(150) NOT NULL,

    -- Identificação da pessoa
    nome_completo     VARCHAR(150) NOT NULL,
    email             VARCHAR(150) NOT NULL,
    telefone          VARCHAR(20)  NULL,

    -- Demográficos
    faixa_etaria      ENUM('18-24','25-34','35-44','45-54','55-64','65+') NOT NULL,
    genero            ENUM('Masculino','Feminino','Outro','Prefiro não informar') NOT NULL,
    estado            CHAR(2)      NOT NULL,
    cidade            VARCHAR(100) NOT NULL,

    -- Perfil
    ocupacao          VARCHAR(100) NOT NULL,
    nivel_formacao    ENUM(
                          'Ensino Fundamental','Ensino Médio','Ensino Técnico',
                          'Graduando','Graduado',
                          'Pós-graduando','Pós-graduado',
                          'Mestrando','Mestre',
                          'Doutorando','Doutor'
                      ) NOT NULL,

    -- Vínculo institucional
    tipo_vinculo      ENUM('Interno','Externo') NOT NULL,
    categoria         ENUM('Aluno','Professor','Técnico','Convidado') NOT NULL,

    -- Metadados
    data_inscricao    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Uma mesma pessoa não pode se inscrever duas vezes no mesmo evento,
    -- mas pode se inscrever em eventos diferentes.
    UNIQUE KEY uk_email_evento (email, evento_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
