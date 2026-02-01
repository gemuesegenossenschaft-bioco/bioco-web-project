<?php namespace ProcessWire;

// Newsletter subscription form template
// Uses wireframe structure from _main.php

$formSubmitted = false;
$formError = '';
$successMessage = '';

// Process form submission
if($input->post->submit_subscribe_form) {
	$formProcessor = $modules->get('FormProcessor');
	$result = $formProcessor->processSubscribeForm($input->post);
	
	if($result['success']) {
		$formSubmitted = true;
		$successMessage = 'Vielen Dank! Bitte bestätigen Sie Ihre Anmeldung über den Link in der E-Mail, die wir Ihnen gesendet haben.';
	} else {
		$formError = $result['error'] ?? 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.';
	}
}

?>

<div id="content">
	<h2>Newsletter abonnieren</h2>
	
	<?php if($formSubmitted && $successMessage): ?>
		<div class="form-success wireframe-box">
			<p><?php echo $successMessage; ?></p>
		</div>
	<?php elseif($formError): ?>
		<div class="form-error wireframe-box">
			<p><?php echo $formError; ?></p>
		</div>
	<?php endif; ?>
	
	<?php if(!$formSubmitted): ?>
	<form method="post" action="" class="subscribe-form">
		<div class="form-group">
			<label for="subscribe_email">E-Mail-Adresse *</label>
			<input type="email" id="subscribe_email" name="email" required value="<?php echo htmlspecialchars($input->post->email, ENT_QUOTES, 'UTF-8'); ?>" />
		</div>
		
		<div class="form-group">
			<label for="subscribe_name">Name (optional)</label>
			<input type="text" id="subscribe_name" name="name" value="<?php echo htmlspecialchars($input->post->name, ENT_QUOTES, 'UTF-8'); ?>" />
		</div>
		
		<div class="form-group">
			<label>
				<input type="checkbox" name="privacy_accept" required />
				Ich akzeptiere die Datenschutzbestimmungen *
			</label>
		</div>
		
		<input type="hidden" name="form_type" value="subscribe" />
		<input type="submit" name="submit_subscribe_form" value="Abonnieren" class="cta-button" />
	</form>
	<?php endif; ?>
</div>

<script>
// Track form submission attempt
document.querySelector('.subscribe-form')?.addEventListener('submit', function() {
	if(typeof _paq !== 'undefined') {
		_paq.push(['trackEvent', 'Form', 'Subscribe', 'Submit']);
	}
});
</script>
