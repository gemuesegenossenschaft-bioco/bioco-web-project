<?php namespace ProcessWire;

/**
 * FormProcessor Module
 * 
 * Handles form submissions for Contact, Subscribe, Open Visit Day, and Waiting List forms
 * Integrates with DOIManager for double opt-in confirmation
 */

class FormProcessor extends WireData implements Module {

	public static function getModuleInfo() {
		return array(
			'title' => 'Form Processor',
			'version' => 1,
			'summary' => 'Processes form submissions and handles double opt-in',
			'autoload' => true,
		);
	}

	public function init() {
		// Module initialization
	}

	/**
	 * Process contact form submission
	 */
	public function processContactForm($post) {
		$name = $this->sanitizer->text($post->name ?? '');
		$email = $this->sanitizer->email($post->email ?? '');
		$phone = $this->sanitizer->text($post->phone ?? '');
		$subject = $this->sanitizer->text($post->subject ?? '');
		$message = $this->sanitizer->textarea($post->message ?? '');
		
		// Validation
		if(empty($name) || empty($email) || empty($subject) || empty($message)) {
			return ['success' => false, 'error' => 'Bitte füllen Sie alle Pflichtfelder aus.'];
		}
		
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return ['success' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'];
		}
		
		// Store form submission
		$formData = [
			'form_type' => 'contact',
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'subject' => $subject,
			'message' => $message,
			'created' => time(),
		];
		
		// Initiate DOI process
		$doiManager = $this->modules->get('DOIManager');
		$doiResult = $doiManager->initiateDOI($email, 'contact', $formData);
		
		if($doiResult['success']) {
			// Track form submission
			if($this->modules->isInstalled('MatomoTracker')) {
				$this->modules->get('MatomoTracker')->trackEvent('Form', 'Contact', 'Submit');
			}
			
			return ['success' => true, 'doi_token' => $doiResult['token']];
		} else {
			return ['success' => false, 'error' => $doiResult['error'] ?? 'Fehler beim Versenden der E-Mail.'];
		}
	}

	/**
	 * Process subscribe form submission
	 */
	public function processSubscribeForm($post) {
		$email = $this->sanitizer->email($post->email ?? '');
		$name = $this->sanitizer->text($post->name ?? '');
		
		// Validation
		if(empty($email)) {
			return ['success' => false, 'error' => 'Bitte geben Sie eine E-Mail-Adresse ein.'];
		}
		
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return ['success' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'];
		}
		
		if(empty($post->privacy_accept)) {
			return ['success' => false, 'error' => 'Bitte akzeptieren Sie die Datenschutzbestimmungen.'];
		}
		
		// Store form submission
		$formData = [
			'form_type' => 'subscribe',
			'email' => $email,
			'name' => $name,
			'created' => time(),
		];
		
		// Initiate DOI process
		$doiManager = $this->modules->get('DOIManager');
		$doiResult = $doiManager->initiateDOI($email, 'subscribe', $formData);
		
		if($doiResult['success']) {
			// Track form submission
			if($this->modules->isInstalled('MatomoTracker')) {
				$this->modules->get('MatomoTracker')->trackEvent('Form', 'Subscribe', 'Submit');
			}
			
			return ['success' => true, 'doi_token' => $doiResult['token']];
		} else {
			return ['success' => false, 'error' => $doiResult['error'] ?? 'Fehler beim Versenden der E-Mail.'];
		}
	}

	/**
	 * Process open visit day form submission
	 */
	public function processVisitDayForm($post) {
		$name = $this->sanitizer->text($post->name ?? '');
		$email = $this->sanitizer->email($post->email ?? '');
		$phone = $this->sanitizer->text($post->phone ?? '');
		$visitDate = $this->sanitizer->date($post->visit_date ?? '');
		$participants = (int)($post->participants ?? 0);
		$notes = $this->sanitizer->textarea($post->notes ?? '');
		
		// Validation
		if(empty($name) || empty($email) || empty($phone) || empty($visitDate) || $participants < 1) {
			return ['success' => false, 'error' => 'Bitte füllen Sie alle Pflichtfelder aus.'];
		}
		
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return ['success' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'];
		}
		
		if(empty($post->privacy_accept)) {
			return ['success' => false, 'error' => 'Bitte akzeptieren Sie die Datenschutzbestimmungen.'];
		}
		
		// Store form submission
		$formData = [
			'form_type' => 'visit',
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'visit_date' => $visitDate,
			'participants' => $participants,
			'notes' => $notes,
			'created' => time(),
		];
		
		// Initiate DOI process
		$doiManager = $this->modules->get('DOIManager');
		$doiResult = $doiManager->initiateDOI($email, 'visit', $formData);
		
		if($doiResult['success']) {
			// Track form submission
			if($this->modules->isInstalled('MatomoTracker')) {
				$this->modules->get('MatomoTracker')->trackEvent('Form', 'VisitDay', 'Submit');
			}
			
			return ['success' => true, 'doi_token' => $doiResult['token']];
		} else {
			return ['success' => false, 'error' => $doiResult['error'] ?? 'Fehler beim Versenden der E-Mail.'];
		}
	}

	/**
	 * Process waiting list form submission
	 */
	public function processWaitingListForm($post) {
		$name = $this->sanitizer->text($post->name ?? '');
		$email = $this->sanitizer->email($post->email ?? '');
		$phone = $this->sanitizer->text($post->phone ?? '');
		$interest = $this->sanitizer->text($post->interest ?? '');
		$notes = $this->sanitizer->textarea($post->notes ?? '');
		
		// Validation
		if(empty($name) || empty($email) || empty($phone) || empty($interest)) {
			return ['success' => false, 'error' => 'Bitte füllen Sie alle Pflichtfelder aus.'];
		}
		
		if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return ['success' => false, 'error' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.'];
		}
		
		if(empty($post->privacy_accept)) {
			return ['success' => false, 'error' => 'Bitte akzeptieren Sie die Datenschutzbestimmungen.'];
		}
		
		// Store form submission
		$formData = [
			'form_type' => 'waiting_list',
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'interest' => $interest,
			'notes' => $notes,
			'created' => time(),
		];
		
		// Initiate DOI process
		$doiManager = $this->modules->get('DOIManager');
		$doiResult = $doiManager->initiateDOI($email, 'waiting_list', $formData);
		
		if($doiResult['success']) {
			// Track form submission
			if($this->modules->isInstalled('MatomoTracker')) {
				$this->modules->get('MatomoTracker')->trackEvent('Form', 'WaitingList', 'Submit');
			}
			
			return ['success' => true, 'doi_token' => $doiResult['token']];
		} else {
			return ['success' => false, 'error' => $doiResult['error'] ?? 'Fehler beim Versenden der E-Mail.'];
		}
	}

	/**
	 * Finalize form submission after DOI confirmation
	 */
	public function finalizeSubmission($token) {
		$doiManager = $this->modules->get('DOIManager');
		$doiData = $doiManager->confirmDOI($token);
		
		if(!$doiData) {
			return ['success' => false, 'error' => 'Ungültiger oder abgelaufener Bestätigungslink.'];
		}
		
		$formData = $doiData['form_data'];
		$formType = $formData['form_type'];
		
		// Process based on form type
		switch($formType) {
			case 'contact':
				$this->processConfirmedContact($formData);
				break;
			case 'subscribe':
				$this->processConfirmedSubscribe($formData);
				break;
			case 'visit':
				$this->processConfirmedVisit($formData);
				break;
			case 'waiting_list':
				$this->processConfirmedWaitingList($formData);
				break;
		}
		
		// Track DOI confirmation
		if($this->modules->isInstalled('MatomoTracker')) {
			$this->modules->get('MatomoTracker')->trackEvent('DOI', 'Confirm', $formType);
		}
		
		return ['success' => true, 'form_type' => $formType];
	}

	/**
	 * Process confirmed contact form
	 */
	private function processConfirmedContact($data) {
		// Send notification email to admin
		$mail = wireMail();
		$mail->to($this->config->admin_email);
		$mail->from($this->config->email_from);
		$mail->subject('Neue Kontaktanfrage: ' . $data['subject']);
		$mail->bodyHTML($this->renderEmailTemplate('form-notification', $data));
		$mail->send();
	}

	/**
	 * Process confirmed subscription
	 */
	private function processConfirmedSubscribe($data) {
		// Persist subscriber in Newsletter module (double opt-in already done)
		if($this->modules->isInstalled('ProcessNewsletter')) {
			$newsletter = $this->modules->get('ProcessNewsletter');
			if($newsletter) {
				$newsletter->recordSubscriber($data['email'], $data['name'] ?? '', 'subscribe_form');
			}
		}

		$mail = wireMail();
		$mail->to($data['email']);
		$mail->from($this->config->email_from);
		$mail->subject('Newsletter-Anmeldung bestätigt');
		$mail->bodyHTML($this->renderEmailTemplate('newsletter-confirmed', $data));
		$mail->send();
	}

	/**
	 * Process confirmed visit day form
	 */
	private function processConfirmedVisit($data) {
		// Send notification email to admin
		$mail = wireMail();
		$mail->to($this->config->admin_email);
		$mail->from($this->config->email_from);
		$mail->subject('Neue Anmeldung Tag der offenen Tür');
		$mail->bodyHTML($this->renderEmailTemplate('form-notification', $data));
		$mail->send();
	}

	/**
	 * Process confirmed waiting list form
	 */
	private function processConfirmedWaitingList($data) {
		// Send notification email to admin
		$mail = wireMail();
		$mail->to($this->config->admin_email);
		$mail->from($this->config->email_from);
		$mail->subject('Neue Anmeldung Warteliste');
		$mail->bodyHTML($this->renderEmailTemplate('form-notification', $data));
		$mail->send();
	}

	/**
	 * Render email template
	 */
	private function renderEmailTemplate($template, $data) {
		$templateFile = $this->config->paths->templates . "emails/{$template}.php";
		if(file_exists($templateFile)) {
			ob_start();
			// Make variables available to template
			$config = $this->config;
			include($templateFile);
			return ob_get_clean();
		}
		return '';
	}
}
