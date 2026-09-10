<?php
setcookie('probe_insecure', 'x', ['secure' => false, 'httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
header('X-Debug-HTTPS: ' . (empty($_SERVER['HTTPS']) ? 'EMPTY' : ('SET=' . $_SERVER['HTTPS'])));
header('X-Debug-PORT: ' . ($_SERVER['SERVER_PORT'] ?? 'n/a'));
header('X-Debug-PROTO: ' . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'n/a'));
echo 'probe-ok';
