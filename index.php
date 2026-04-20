<?php
/**
 * index.php
 * Redirects the root URL to the admin code-generation page. The exam system
 * has no public landing page in this phase; students receive a direct link
 * to a future login page.
 */

header('Location: admin_generate.php', true, 302);
exit;
