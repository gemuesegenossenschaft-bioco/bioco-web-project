<?php namespace ProcessWire;

$token = $input->get('token');
$success = false;
$error = '';

if ($token) {
    $newsletter = $modules->get('ProcessNewsletter');
    if ($newsletter && $newsletter->unsubscribeByToken($token)) {
        $success = true;
    } else {
        $error = 'Der Abmeldelink ist ungültig oder wurde bereits verwendet.';
    }
} else {
    $error = 'Kein Abmeldetoken angegeben.';
}

?>

<div id="content">
    <h2>Newsletter Abmeldung</h2>
    <?php if ($success): ?>
        <p>Du wurdest erfolgreich vom Newsletter abgemeldet.</p>
    <?php else: ?>
        <p><?php echo $error; ?></p>
    <?php endif; ?>
</div>
