<?php

// Unterstuetzt Formular-POST und optionalen GET-Parameter fuer Direktaufrufe im Debugging.
$name = trim((string)($_POST['completename'] ?? $_GET['completename'] ?? ''));

if ($name === '') {
    echo 'Name fehlt. Bitte Formular ueber `formular.html` absenden.';
    exit;
}

echo 'Hallo ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '!<br>Danke für deine Registrierung!';

?>