<?php namespace ProcessWire;

/**
 * MatomoTracker Module
 * 
 * Provides server-side and client-side Matomo tracking (cookieless mode)
 * Swiss DSG compliant - no cookie banner required
 */

class MatomoTracker extends WireData implements Module {

	public static function getModuleInfo() {
		return array(
			'title' => 'Matomo Tracker',
			'version' => 1,
			'summary' => 'Matomo cookieless analytics tracking',
			'autoload' => true,
		);
	}

	/**
	 * Track event server-side (for form submissions, etc.)
	 */
	public function trackEvent($category, $action, $name = '') {
		// Store event in session for client-side tracking
		// This ensures events are tracked even if JavaScript fails
		$session = $this->wire()->session;
		if($session) {
			$events = $session->get('matomo_events');
			if(!is_array($events)) $events = [];
			$events[] = [
				'category' => $category,
				'action' => $action,
				'name' => $name,
				'time' => time()
			];
			$session->set('matomo_events', $events);
		}
	}

	/**
	 * Get tracking script HTML
	 */
	public function getTrackingScript() {
		$config = $this->config;
		
		if(!$config->matomo_enabled) {
			return '';
		}
		
		$matomoUrl = $config->matomo_url;
		$siteId = $config->matomo_site_id;
		
		$script = "
		var _paq = window._paq = window._paq || [];
		_paq.push(['trackPageView']);
		_paq.push(['enableLinkTracking']);
		_paq.push(['setTrackerUrl', '{$matomoUrl}matomo.php']);
		_paq.push(['setSiteId', {$siteId}]);
		_paq.push(['disableCookies']); // Cookieless mode for DSG compliance
		
		// Track pending events from session
		_paq.push(function() {
			var events = " . json_encode($this->getPendingEvents()) . ";
			if(events && events.length > 0) {
				events.forEach(function(event) {
					_paq.push(['trackEvent', event.category, event.action, event.name]);
				});
			}
		});
		
		(function() {
			var u = '{$matomoUrl}';
			var d = document, g = d.createElement('script'), s = d.getElementsByTagName('script')[0];
			g.async = true;
			g.src = u + 'matomo.js';
			s.parentNode.insertBefore(g, s);
		})();
		";
		
		return $script;
	}

	/**
	 * Get pending events from session
	 */
	private function getPendingEvents() {
		$session = $this->wire()->session;
		if($session) {
			$events = $session->get('matomo_events');
			if(!is_array($events)) $events = [];
			// Clear events after retrieving
			$session->remove('matomo_events');
			return $events;
		}
		return [];
	}

	/**
	 * Track CTA click (client-side)
	 */
	public static function trackCTA($ctaName) {
		return "if(typeof _paq !== 'undefined') { _paq.push(['trackEvent', 'CTA', 'Click', '{$ctaName}']); }";
	}
}
