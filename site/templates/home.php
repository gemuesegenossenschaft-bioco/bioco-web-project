<?php namespace ProcessWire;

// Template file for "home" template used by the homepage
// Redirect to content planning if user is logged in
if ($user->isLoggedin()) {
    $session->redirect('/processwire/content-planning/');
}

// If not logged in, redirect to login page
$session->redirect('/processwire/');

?>

<div id="content">
	<?php if($page->body): ?>
		<?php echo $page->body; ?>
	<?php else: ?>
		<h2>Willkommen auf bioco.ch</h2>
		<p>Homepage content</p>
	<?php endif; ?>
</div>	