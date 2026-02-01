<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Newsletter-Anmeldung bestätigt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
	
	<h1 style="color: #0b5c2e;">Newsletter-Anmeldung bestätigt</h1>
	
	<p>Hallo<?php echo !empty($data['name']) ? ' ' . htmlspecialchars($data['name']) : ''; ?>,</p>
	
	<p>vielen Dank für deine Anmeldung zu unserem Newsletter. Deine Anmeldung wurde erfolgreich bestätigt.</p>
	
	<p>Du erhältst ab sofort regelmässig Neuigkeiten von bioco.ch.</p>
	
	<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
	
	<p style="font-size: 12px; color: #999;">
		bioco.ch<br>
		<?php if(isset($config)): ?>
			Absender: <?php echo isset($config->email_from_name) ? $config->email_from_name : 'bioco.ch'; ?> (<?php echo isset($config->email_from) ? $config->email_from : 'hallo@bioco.ch'; ?>)
		<?php endif; ?>
	</p>
	
</body>
</html>
