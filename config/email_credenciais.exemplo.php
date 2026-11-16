<?php
// Exemplo de configuração de credenciais SMTP para envio de e-mails da MVM.
// Copie este arquivo para "config/email_credenciais.php" e preencha seus dados.
//
// Exemplo com Gmail:
//   1. Ative a verificação em duas etapas na conta Google.
//   2. Crie uma "Senha de app" em https://myaccount.google.com/apppasswords
//   3. Cole a senha de 16 letras abaixo (não use a senha normal da conta).

define('MVM_SMTP_HOST',      'smtp.gmail.com');
define('MVM_SMTP_PORTA',     465);
define('MVM_SMTP_SEGURANCA', 'ssl');
define('MVM_SMTP_USUARIO',   'seu_email@gmail.com');
define('MVM_SMTP_SENHA',     'sua_senha_de_app_aqui');
?>
