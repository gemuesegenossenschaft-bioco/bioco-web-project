<?php namespace ProcessWire;

// Template file for "home" template used by the homepage
// Note: Root URL is redirected to /processwire/ via .htaccess

?>

<div id="content">
	<?php if($page->body): ?>
		<?php echo $page->body; ?>
	<?php else: ?>
		<h2>Willkommen auf bioco.ch</h2>
		<p>Homepage content</p>
	<?php endif; ?>
</div>	