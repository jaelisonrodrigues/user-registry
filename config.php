<?php
/**
 * Arquivo de configuração.
 * Edite os valores abaixo para reaproveitar o mesmo formulário (formulario.php)
 * em outro evento, sem precisar alterar o código do formulário.
 */

// ---- Dados do evento ----
// Gere um novo UUID v4 para cada evento (ex.: https://www.uuidgenerator.net/)
define('EVENTO_UUID', '4b960ca7-7031-4355-b1d9-062339aa8265');
define('EVENTO_DESCRICAO', 'CRA - Conselho Regional de Administração');
// Data do evento, usada no certificado. Formato: AAAA-MM-DD
define('EVENTO_DATA', '2026-09-12');

// ---- Conexão com o banco de dados ----
define('DB_HOST', 'gamificar4.mysql.dbaas.com.br');
define('DB_NAME', 'gamificar4');
define('DB_USER', 'gamificar4');
define('DB_PASS', 'n@Vq#J94!no9AC');
define('DB_CHARSET', 'utf8mb4');
