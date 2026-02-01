<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bestätigung deiner Anmeldung</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
	
	<h1 style="color: #0b5c2e;">Bestätigung deiner Anmeldung</h1>
	
	<p>Hallo,</p>
	
	<p>vielen Dank für deine Anmeldung auf bioco.ch. Bitte bestätige deine Anmeldung durch Klick auf folgenden Link:</p>
	
	<p style="text-align: center; margin: 30px 0;">
		<a href="<?php echo $confirmationUrl; ?>" 
		   style="display: inline-block; background-color: #0b5c2e; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 4px; font-weight: bold;">
			Anmeldung bestätigen
		</a>
	</p>
	
	<p>Oder kopiere diesen Link in deinen Browser:</p>
	<p style="word-break: break-all; color: #666;"><?php echo isset($confirmationUrl) ? $confirmationUrl : ''; ?></p>
	
	<p><strong>Wichtig:</strong> Dieser Link ist 24 Stunden gültig.</p>
	
	<p>Falls du dich nicht angemeldet hast, kannst du diese E-Mail ignorieren.</p>
	
	<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
	
	<p style="font-size: 12px; color: #999;">
		bioco.ch<br>
		<?php if(isset($config)): ?>
			Absender: <?php echo isset($config->email_from_name) ? $config->email_from_name : 'bioco.ch'; ?> (<?php echo isset($config->email_from) ? $config->email_from : 'hallo@bioco.ch'; ?>)
		<?php endif; ?>
	</p>
	
</body>
</html>
