<?php namespace ProcessWire;

/**
 * DOIManager Module
 * 
 * Handles double opt-in (DOI) email confirmation process
 * Stores pending confirmations in database and validates tokens
 */

class DOIManager extends WireData implements Module {

	public static function getModuleInfo() {
		return array(
			'title' => 'DOI Manager',
			'version' => 1,
			'summary' => 'Manages double opt-in email confirmations',
			'autoload' => true,
		);
	}

	public function init() {
		$this->addHookAfter('LazyCron::everyHour', $this, 'hookCleanupExpired');
	}

	public function ___install() {
		$this->createDatabaseTable();
	}

	public function hookCleanupExpired(\ProcessWire\HookEvent $event) {
		$this->cleanupExpiredTokens();
	}

	/**
	 * Create database table for DOI tokens
	 */
	private function createDatabaseTable() {
		$database = $this->wire()->database;
		
		$sql = "CREATE TABLE IF NOT EXISTS `doi_tokens` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`token` varchar(64) NOT NULL,
			`email` varchar(255) NOT NULL,
			`form_type` varchar(50) NOT NULL,
			`form_data` text NOT NULL,
			`created` int(11) NOT NULL,
			`expires` int(11) NOT NULL,
			`confirmed` tinyint(1) DEFAULT 0,
			`confirmed_at` int(11) DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `token` (`token`),
			KEY `email` (`email`),
			KEY `expires` (`expires`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
		
		try {
			$database->exec($sql);
		} catch(\Exception $e) {
			// Table might already exist, ignore
		}
	}

	/**
	 * Initiate DOI process - generate token and send confirmation email
	 */
	public function initiateDOI($email, $formType, $formData) {
		// Generate unique token
		$token = $this->generateToken();
		
		// Set expiration (24 hours)
		$expires = time() + (24 * 60 * 60);
		
		// Store in database
		$database = $this->wire()->database;
		$sql = "INSERT INTO doi_tokens (token, email, form_type, form_data, created, expires) 
				VALUES (?, ?, ?, ?, ?, ?)";
		
		try {
			$query = $database->prepare($sql);
			$query->execute([
				$token,
				$email,
				$formType,
				json_encode($formData),
				time(),
				$expires
			]);
		} catch(\Exception $e) {
			$this->error("DOI Manager: Error saving token - " . $e->getMessage());
			return ['success' => false, 'error' => 'Fehler beim Speichern der Daten.'];
		}
		
		// Send confirmation email
		$emailResult = $this->sendConfirmationEmail($email, $token, $formType);
		
		if($emailResult) {
			return ['success' => true, 'token' => $token];
		} else {
			return ['success' => false, 'error' => 'Fehler beim Versenden der E-Mail.'];
		}
	}

	/**
	 * Confirm DOI token
	 */
	public function confirmDOI($token) {
		$database = $this->wire()->database;
		
		// Check if token exists and is valid
		$sql = "SELECT * FROM doi_tokens WHERE token = ? AND expires > ? AND confirmed = 0";
		$query = $database->prepare($sql);
		$query->execute([$token, time()]);
		$row = $query->fetch(\PDO::FETCH_ASSOC);
		
		if(!$row) {
			return false;
		}
		
		// Mark as confirmed
		$updateSql = "UPDATE doi_tokens SET confirmed = 1, confirmed_at = ? WHERE token = ?";
		$updateQuery = $database->prepare($updateSql);
		$updateQuery->execute([time(), $token]);
		
		$formData = json_decode($row['form_data'], true);
		if(!is_array($formData)) {
			$formData = [];
		}
		
		return [
			'email' => $row['email'],
			'form_type' => $row['form_type'],
			'form_data' => $formData
		];
	}

	/**
	 * Generate unique token
	 */
	private function generateToken() {
		do {
			$token = bin2hex(random_bytes(32));
			$database = $this->wire()->database;
			$sql = "SELECT COUNT(*) FROM doi_tokens WHERE token = ?";
			$query = $database->prepare($sql);
			$query->execute([$token]);
			$exists = $query->fetchColumn() > 0;
		} while($exists);
		
		return $token;
	}

	/**
	 * Send confirmation email
	 */
	private function sendConfirmationEmail($email, $token, $formType) {
		$config = $this->config;
		$host = $config->httpHosts[0] ?? $_SERVER['HTTP_HOST'] ?? 'cms.bioco.ch';
		$scheme = $config->https ? 'https' : 'http';
		$confirmationUrl = $scheme . '://' . $host . $config->urls->root . "doi-confirm/?token=" . $token;
		
		$mail = wireMail();
		$mail->to($email);
		$mail->from($config->email_from, $config->email_from_name);
		$mail->subject('Bitte bestätige deine Anmeldung');
		
		// Load email template
		$templateFile = $config->paths->templates . "emails/doi-confirmation.php";
		$emailBody = '';
		
		if(file_exists($templateFile)) {
			ob_start();
			// Make variables available to template
			$config = $this->config;
			include($templateFile);
			$emailBody = ob_get_clean();
		} else {
			// Fallback email content
			$emailBody = "Hallo,\n\nvielen Dank für deine Anmeldung auf bioco.ch. Bitte bestätige deine Anmeldung durch Klick auf folgenden Link:\n\n";
			$emailBody .= $confirmationUrl . "\n\n";
			$emailBody .= "Dieser Link ist 24 Stunden gültig.\n\n";
			$emailBody .= "Falls du dich nicht angemeldet hast, kannst du diese E-Mail ignorieren.";
		}
		
		$mail->bodyHTML($emailBody);
		
		try {
			return $mail->send();
		} catch(\Exception $e) {
			$this->error("Error sending DOI email: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Clean up expired tokens (call via cron or lazy cron)
	 */
	public function cleanupExpiredTokens() {
		$database = $this->wire()->database;
		try {
			$sql = "DELETE FROM doi_tokens WHERE expires < ? AND confirmed = 0";
			$query = $database->prepare($sql);
			$query->execute([time()]);
			return $query->rowCount();
		} catch(\Exception $e) {
			$this->error("DOI Manager: Error cleaning up tokens - " . $e->getMessage());
			return 0;
		}
	}
}
