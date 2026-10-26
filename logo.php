<?php
// Standard-Vereinslogo (Wappen mit Anfangsbuchstabe), dasselbe Zeichen wie in MISS. Eigenes Logo: ORG_LOGO in der config.php.
$ini = isset($_GET['i']) ? mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', (string)$_GET['i']), 0, 1)) : '';
if ($ini === '') $ini = 'V';
header('Content-Type: image/svg+xml');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
header('Cache-Control: public, max-age=3600');
echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="M32 3 59 13v21c0 13-11 23-27 27C16 57 5 47 5 34V13z" fill="#FAAA00"/><path d="M32 8 54 16v18c0 10-8 18-22 22-14-4-22-12-22-22V16z" fill="#fff" opacity=".18"/>'
    . '<text x="32" y="43" font-family="Georgia,serif" font-size="30" font-weight="700" text-anchor="middle" fill="#005596">' . htmlspecialchars($ini) . '</text></svg>';
