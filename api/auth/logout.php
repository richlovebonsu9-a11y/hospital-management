<?php
// Logout handler
session_start();
session_destroy();

setcookie('sb_user', '', time() - 3600, '/');
setcookie('sb_token', '', time() - 3600, '/');

header('Location: /login?logout=success');
exit;
