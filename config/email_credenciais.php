<?php
// Credenciais do SMTP usado para enviar os emails da MVM.
// ATENÇÃO: este arquivo está no .gitignore e não deve ir para o GitHub.
//
// Exemplo com Gmail:
//   1. Ative a verificação em duas etapas na conta Google.
//   2. Crie uma "Senha de app" em https://myaccount.google.com/apppasswords
//   3. Cole a senha de 16 letras abaixo (não use a senha normal da conta).

define('MVM_SMTP_HOST',      'smtp.gmail.com');
define('MVM_SMTP_PORTA',     465);
define('MVM_SMTP_SEGURANCA', 'ssl');            // Porta 465 com SSL direto (alta performance e compatibilidade)
define('MVM_SMTP_USUARIO',   'mvm.centralestoque@gmail.com');
define('MVM_SMTP_SENHA',     'cdzf mdsd vuyq ppde');
?>
