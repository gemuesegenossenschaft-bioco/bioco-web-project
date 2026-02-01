<?php namespace ProcessWire;

/**
 * ProcessNewsletter
 *
 * Lightweight in-house newsletter manager with:
 * - DOI-backed subscriber list (uses existing DOIManager/FormProcessor flow)
 * - Draft and scheduled campaigns
 * - Page picker for Aktuelles (news_item) and upcoming events
 * - LazyCron-based send queue with SMTP throttling
 *
 * NOTE: Requires WireMailSmtp (or another WireMail transport) to be configured.
 */

class ProcessNewsletter extends Process implements Module
{
    const TABLE_SUBSCRIBERS = 'newsletter_subscribers';
    const TABLE_CAMPAIGNS   = 'newsletter_campaigns';
    const TABLE_QUEUE       = 'newsletter_send_queue';

    // Queue limits (can be overridden via $config->newsletter_batch_size)
    const DEFAULT_BATCH_SIZE = 50;

    public static function getModuleInfo()
    {
        return [
            'title' => 'Newsletter',
            'version' => 1,
            'summary' => 'Manage subscribers and send scheduled newsletters',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'newsletter',
                'parent' => '',
                'title' => 'Newsletter',
                'icon' => 'envelope-o',
            ],
            'autoload' => true,
        ];
    }

    public function init()
    {
        parent::init();
        $this->addHookAfter('LazyCron::every5Minutes', $this, 'processQueue');
    }

    public function ___install()
    {
        parent::___install();
        $this->createTables();
    }

    public function ___uninstall()
    {
        parent::___uninstall();
    }

    /**
     * Admin entry point
     */
    public function ___execute()
    {
        $view = $this->wire()->input->get('view') ?: 'campaigns';

        if ($this->wire()->input->post->save_campaign) {
            return $this->handleSaveCampaign();
        }

        if ($view === 'subscribers') {
            return $this->renderSubscribersView();
        }

        return $this->renderCampaignsView();
    }

    /* ---------------------------------------------------------------------
     * Data layer
     * ------------------------------------------------------------------- */

    private function createTables()
    {
        $db = $this->wire()->database;

        // Subscribers
        $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_SUBSCRIBERS . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `email` varchar(255) NOT NULL,
            `name` varchar(255) DEFAULT '',
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `source` varchar(50) DEFAULT NULL,
            `unsubscribe_token` varchar(64) NOT NULL,
            `created` int(11) NOT NULL,
            `confirmed_at` int(11) DEFAULT NULL,
            `unsubscribed_at` int(11) DEFAULT NULL,
            `last_sent` int(11) DEFAULT NULL,
            `last_opened` int(11) DEFAULT NULL,
            `last_clicked` int(11) DEFAULT NULL,
            `meta` json DEFAULT NULL,
            `updated` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `email_unique` (`email`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns for existing installs
        try {
            $db->exec("ALTER TABLE `" . self::TABLE_SUBSCRIBERS . "` ADD COLUMN `updated` int(11) DEFAULT NULL");
        } catch (\Exception $e) {
            // Ignore if column exists
        }

        // Campaigns
        $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_CAMPAIGNS . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `subject` varchar(255) NOT NULL,
            `preheader` varchar(255) DEFAULT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'draft',
            `scheduled_at` int(11) DEFAULT NULL,
            `sent_at` int(11) DEFAULT NULL,
            `body_intro` text,
            `content_blocks` longtext,
            `audience` varchar(50) DEFAULT 'all',
            `author_id` int(11) DEFAULT NULL,
            `created` int(11) NOT NULL,
            `updated` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `scheduled_at` (`scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Send queue
        $db->exec("CREATE TABLE IF NOT EXISTS `" . self::TABLE_QUEUE . "` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `campaign_id` int(11) NOT NULL,
            `subscriber_id` int(11) NOT NULL,
            `status` varchar(20) NOT NULL DEFAULT 'pending',
            `scheduled_at` int(11) DEFAULT NULL,
            `sent_at` int(11) DEFAULT NULL,
            `attempts` int(11) NOT NULL DEFAULT 0,
            `last_error` text,
            `created` int(11) NOT NULL,
            `updated` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `campaign_subscriber` (`campaign_id`, `subscriber_id`),
            KEY `status` (`status`),
            KEY `scheduled_at` (`scheduled_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Public helper used by FormProcessor after DOI confirmation.
     */
    public function recordSubscriber(string $email, string $name = '', string $source = 'subscribe_form')
    {
        $email = $this->wire()->sanitizer->email($email);
        if (!$email) return false;

        $db = $this->wire()->database;
        $now = time();

        // Check if subscriber exists
        $stmt = $db->prepare("SELECT id, status FROM `" . self::TABLE_SUBSCRIBERS . "` WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            // Reactivate if needed
            $status = $existing['status'];
            $updates = [];
            $params = [];
            if ($status !== 'confirmed') {
                $updates[] = "status='confirmed'";
                $updates[] = "confirmed_at=?";
                $updates[] = "unsubscribed_at=NULL";
                $params[] = $now;
            }
            if ($name) {
                $updates[] = "name=?";
                $params[] = $name;
            }
            if ($updates) {
                $updates[] = "updated=?";
                $params[] = $now;
                $params[] = $existing['id'];
                $sql = "UPDATE `" . self::TABLE_SUBSCRIBERS . "` SET " . implode(',', $updates) . " WHERE id=?";
                $db->prepare($sql)->execute($params);
            }
            return $existing['id'];
        }

        $token = bin2hex(random_bytes(32));
        $sql = "INSERT INTO `" . self::TABLE_SUBSCRIBERS . "` (email, name, status, source, unsubscribe_token, created, confirmed_at, updated)
                VALUES (?, ?, 'confirmed', ?, ?, ?, ?, ?)";
        $db->prepare($sql)->execute([$email, $name, $source, $token, $now, $now, $now]);

        return $db->lastInsertId();
    }

    /* ---------------------------------------------------------------------
     * Campaign handling
     * ------------------------------------------------------------------- */

    private function handleSaveCampaign()
    {
        $input = $this->wire()->input;
        $id = (int)$input->post->campaign_id;

        $title = trim($input->post->title);
        $subject = trim($input->post->subject);
        $preheader = trim($input->post->preheader);
        $bodyIntro = trim($input->post->body_intro);
        $scheduledAt = $input->post->scheduled_at ? strtotime($input->post->scheduled_at) : null;
        $status = $scheduledAt ? 'scheduled' : 'draft';

        $selectedPages = $input->post->page_ids ?: [];
        if (!is_array($selectedPages)) $selectedPages = [$selectedPages];
        $blocks = $this->buildContentBlocks($selectedPages);

        $now = time();
        $db = $this->wire()->database;

        if ($id) {
            $sql = "UPDATE `" . self::TABLE_CAMPAIGNS . "`
                    SET title=?, subject=?, preheader=?, status=?, scheduled_at=?, body_intro=?, content_blocks=?, updated=?
                    WHERE id=?";
            $db->prepare($sql)->execute([
                $title,
                $subject,
                $preheader,
                $status,
                $scheduledAt,
                $bodyIntro,
                json_encode($blocks),
                $now,
                $id
            ]);
        } else {
            $sql = "INSERT INTO `" . self::TABLE_CAMPAIGNS . "`
                    (title, subject, preheader, status, scheduled_at, body_intro, content_blocks, audience, author_id, created, updated)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'all', ?, ?, ?)";
            $db->prepare($sql)->execute([
                $title,
                $subject,
                $preheader,
                $status,
                $scheduledAt,
                $bodyIntro,
                json_encode($blocks),
                $this->wire()->user->id,
                $now,
                $now
            ]);
            $id = $db->lastInsertId();
        }

        // If scheduled time already passed, enqueue immediately
        if ($scheduledAt && $scheduledAt <= time()) {
            $this->enqueueCampaign((int)$id);
        }

        $this->message('Campaign saved.');
        return $this->renderCampaignsView();
    }

    private function renderCampaignsView()
    {
        $db = $this->wire()->database;
        $campaigns = $db->query("SELECT * FROM `" . self::TABLE_CAMPAIGNS . "` ORDER BY created DESC")->fetchAll(\PDO::FETCH_ASSOC);

        $out = "<h2>Newsletter Kampagnen</h2>";
        $out .= "<p><a class='ui-button' href='{$this->wire()->page->url}?view=campaigns&action=new'><i class='fa fa-plus'></i> Neue Kampagne</a>
                 <a class='ui-button' href='{$this->wire()->page->url}?view=subscribers'><i class='fa fa-users'></i> Abonnenten</a></p>";

        $out .= "<table class='AdminDataTable'><thead><tr>
            <th>Titel</th><th>Status</th><th>Geplant</th><th>Gesendet</th><th>Aktionen</th>
        </tr></thead><tbody>";

        foreach ($campaigns as $c) {
            $actions = [];
            $actions[] = "<a href='{$this->wire()->page->url}?view=campaigns&action=edit&id={$c['id']}'>Bearbeiten</a>";
            if ($c['status'] === 'scheduled') {
                $actions[] = "<a href='{$this->wire()->page->url}?view=campaigns&action=sendnow&id={$c['id']}'>Jetzt senden</a>";
            }
            $scheduled = $c['scheduled_at'] ? date('Y-m-d H:i', $c['scheduled_at']) : '-';
            $sent = $c['sent_at'] ? date('Y-m-d H:i', $c['sent_at']) : '-';

            $out .= "<tr>
                <td>{$this->wire()->sanitizer->entities($c['title'])}</td>
                <td>{$c['status']}</td>
                <td>{$scheduled}</td>
                <td>{$sent}</td>
                <td>" . implode(' | ', $actions) . "</td>
            </tr>";
        }

        $out .= "</tbody></table>";

        // New / edit form
        $action = $this->wire()->input->get('action');
        if ($action === 'new' || $action === 'edit') {
            $campaign = null;
            if ($action === 'edit') {
                $id = (int)$this->wire()->input->get('id');
                $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_CAMPAIGNS . "` WHERE id=?");
                $stmt->execute([$id]);
                $campaign = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            $out .= $this->renderCampaignForm($campaign);
        }

        if ($action === 'sendnow') {
            $this->enqueueCampaign((int)$this->wire()->input->get('id'), true);
            $this->message('Sendung angestossen (Queue erstellt).');
            $out .= "<div class='notice'>Sende-Queue erstellt. Der Versand läuft via LazyCron alle 5 Minuten.</div>";
        }

        return $out;
    }

    private function renderCampaignForm(?array $campaign = null)
    {
        $id = $campaign['id'] ?? 0;
        $title = $campaign['title'] ?? '';
        $subject = $campaign['subject'] ?? '';
        $preheader = $campaign['preheader'] ?? '';
        $bodyIntro = $campaign['body_intro'] ?? '';
        $scheduledAt = !empty($campaign['scheduled_at']) ? date('Y-m-d\TH:i', $campaign['scheduled_at']) : '';
        $selectedIds = [];
        if (!empty($campaign['content_blocks'])) {
            $decoded = json_decode($campaign['content_blocks'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $b) {
                    if (!empty($b['page_id'])) $selectedIds[] = (int)$b['page_id'];
                }
            }
        }

        $newsPages = $this->getNewsPages();
        $eventPages = $this->getUpcomingEvents();

        $out = "<h3>Kampagne " . ($id ? "bearbeiten" : "erstellen") . "</h3>";
        $out .= "<form method='post' action='{$this->wire()->page->url}?view=campaigns'>";
        $out .= "<input type='hidden' name='campaign_id' value='{$id}'>";
        $out .= "<div class='Inputfield'>";
        $out .= "<label>Titel (intern)</label><input class='uk-input' type='text' name='title' value='" . $this->wire()->sanitizer->entities($title) . "' required>";
        $out .= "</div>";
        $out .= "<div class='Inputfield'>";
        $out .= "<label>Betreff</label><input class='uk-input' type='text' name='subject' value='" . $this->wire()->sanitizer->entities($subject) . "' required>";
        $out .= "</div>";
        $out .= "<div class='Inputfield'>";
        $out .= "<label>Preheader</label><input class='uk-input' type='text' name='preheader' value='" . $this->wire()->sanitizer->entities($preheader) . "'>";
        $out .= "</div>";
        $out .= "<div class='Inputfield'>";
        $out .= "<label>Intro-Text</label><textarea class='uk-textarea' name='body_intro' rows='4'>" . $this->wire()->sanitizer->entities($bodyIntro) . "</textarea>";
        $out .= "</div>";

        $out .= "<fieldset class='Inputfield'><legend>Inhalte einfügen</legend>";
        $out .= "<p>Aktuelles</p>";
        foreach ($newsPages as $p) {
            $checked = in_array($p->id, $selectedIds) ? 'checked' : '';
            $out .= "<label><input type='checkbox' name='page_ids[]' value='{$p->id}' {$checked}> {$p->title}</label><br>";
        }
        $out .= "<p>Nächste Events</p>";
        foreach ($eventPages as $p) {
            $checked = in_array($p->id, $selectedIds) ? 'checked' : '';
            $label = $p->title;
            if ($p->hasField('event_start') && $p->event_start) {
                $label .= " (" . date('d.m.Y', $p->event_start) . ")";
            }
            $out .= "<label><input type='checkbox' name='page_ids[]' value='{$p->id}' {$checked}> {$label}</label><br>";
        }
        $out .= "</fieldset>";

        $out .= "<div class='Inputfield'>";
        $out .= "<label>Sendezeit (leer = Entwurf)</label><input class='uk-input' type='datetime-local' name='scheduled_at' value='{$scheduledAt}'>";
        $out .= "</div>";

        $out .= "<input class='ui-button' type='submit' name='save_campaign' value='Speichern'>";
        $out .= "</form>";

        return $out;
    }

    private function renderSubscribersView()
    {
        $db = $this->wire()->database;
        $subscribers = $db->query("SELECT * FROM `" . self::TABLE_SUBSCRIBERS . "` ORDER BY created DESC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC);

        $out = "<h2>Abonnenten</h2>";
        $out .= "<p><a class='ui-button' href='{$this->wire()->page->url}?view=campaigns'>&laquo; Zurück zu Kampagnen</a></p>";
        $out .= "<table class='AdminDataTable'><thead><tr>
            <th>Email</th><th>Name</th><th>Status</th><th>Quelle</th><th>Angemeldet</th>
        </tr></thead><tbody>";
        foreach ($subscribers as $s) {
            $created = $s['created'] ? date('Y-m-d H:i', $s['created']) : '-';
            $out .= "<tr>
                <td>{$this->wire()->sanitizer->entities($s['email'])}</td>
                <td>{$this->wire()->sanitizer->entities($s['name'])}</td>
                <td>{$s['status']}</td>
                <td>{$s['source']}</td>
                <td>{$created}</td>
            </tr>";
        }
        $out .= "</tbody></table>";

        return $out;
    }

    /**
     * Turn selected pages into snapshot blocks for stable email content.
     */
    private function buildContentBlocks(array $pageIds): array
    {
        $blocks = [];
        $pages = $this->wire()->pages;

        foreach ($pageIds as $id) {
            $page = $pages->get((int)$id);
            if (!$page->id) continue;

            $imageUrl = '';
            $imageAlt = '';
            $imageFieldNames = ['card_image', 'hero_image', 'event_media', 'images'];
            foreach ($imageFieldNames as $field) {
                if ($page->hasField($field) && count($page->$field)) {
                    $img = $page->$field->first();
                    $imageUrl = $img->url;
                    $imageAlt = $img->description ?: $page->title;
                    break;
                }
            }

            $summary = '';
            if ($page->hasField('summary') && $page->summary) $summary = $page->summary;
            elseif ($page->hasField('event_summary') && $page->event_summary) $summary = $page->event_summary;
            elseif ($page->hasField('body') && $page->body) $summary = $page->body;

            $blocks[] = [
                'page_id' => $page->id,
                'title' => $page->title,
                'summary' => $summary ? $this->wire()->sanitizer->truncate($summary, 280) : '',
                'url' => $page->httpUrl,
                'image' => $imageUrl,
                'image_alt' => $imageAlt,
                'type' => $page->template->name,
                'event_date' => ($page->hasField('event_start') && $page->event_start) ? $page->event_start : null,
            ];
        }

        return $blocks;
    }

    private function getNewsPages()
    {
        return $this->wire()->pages->find("template=news_item, sort=-created, limit=10");
    }

    private function getUpcomingEvents()
    {
        $selector = "template=event, sort=event_start, limit=10";
        if ($this->wire()->fields->has('event_status')) {
            $selector = "template=event, (event_status=upcoming|event_status=''), sort=event_start, limit=10";
        }
        return $this->wire()->pages->find($selector);
    }

    /* ---------------------------------------------------------------------
     * Queue + sending
     * ------------------------------------------------------------------- */

    public function processQueue(\ProcessWire\HookEvent $event)
    {
        $this->enqueueScheduledCampaigns();
        $this->sendPendingQueue();
    }

    private function enqueueScheduledCampaigns()
    {
        $db = $this->wire()->database;
        $now = time();
        $stmt = $db->prepare("SELECT id FROM `" . self::TABLE_CAMPAIGNS . "` WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= ?");
        $stmt->execute([$now]);
        $campaigns = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($campaigns as $campaignId) {
            $this->enqueueCampaign((int)$campaignId);
        }
    }

    /**
     * Build queue for a campaign.
     */
    private function enqueueCampaign(int $campaignId, bool $forceSendNow = false)
    {
        $db = $this->wire()->database;
        $now = time();

        // Avoid duplicate queue creation
        $exists = $db->prepare("SELECT COUNT(*) FROM `" . self::TABLE_QUEUE . "` WHERE campaign_id=?");
        $exists->execute([$campaignId]);
        if ($exists->fetchColumn() > 0 && !$forceSendNow) return;

        $subs = $db->query("SELECT id FROM `" . self::TABLE_SUBSCRIBERS . "` WHERE status='confirmed'")->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($subs)) return;

        $queueStmt = $db->prepare("INSERT IGNORE INTO `" . self::TABLE_QUEUE . "` (campaign_id, subscriber_id, status, scheduled_at, created, updated) VALUES (?, ?, 'pending', ?, ?, ?)");

        foreach ($subs as $sid) {
            $queueStmt->execute([$campaignId, $sid, $now, $now, $now]);
        }
    }

    private function sendPendingQueue()
    {
        $db = $this->wire()->database;
        $batchSize = (int)($this->wire()->config->newsletter_batch_size ?? self::DEFAULT_BATCH_SIZE);
        $now = time();

        $stmt = $db->prepare("SELECT q.id, q.campaign_id, q.subscriber_id
                              FROM `" . self::TABLE_QUEUE . "` q
                              WHERE q.status='pending' AND (q.scheduled_at IS NULL OR q.scheduled_at <= ?)
                              LIMIT ?");
        $stmt->execute([$now, $batchSize]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$rows) return;

        foreach ($rows as $row) {
            $campaign = $this->getCampaign((int)$row['campaign_id']);
            $subscriber = $this->getSubscriber((int)$row['subscriber_id']);

            if (!$campaign || !$subscriber) {
                $db->prepare("UPDATE `" . self::TABLE_QUEUE . "` SET status='failed', last_error='Missing campaign or subscriber', updated=? WHERE id=?")
                    ->execute([time(), $row['id']]);
                continue;
            }

            $sent = $this->sendSingle($campaign, $subscriber);

            if ($sent) {
                $db->prepare("UPDATE `" . self::TABLE_QUEUE . "` SET status='sent', sent_at=?, updated=? WHERE id=?")
                    ->execute([time(), time(), $row['id']]);
                $db->prepare("UPDATE `" . self::TABLE_SUBSCRIBERS . "` SET last_sent=? WHERE id=?")
                    ->execute([time(), $subscriber['id']]);
            } else {
                $db->prepare("UPDATE `" . self::TABLE_QUEUE . "` SET status='failed', attempts=attempts+1, last_error='Send failed', updated=? WHERE id=?")
                    ->execute([time(), $row['id']]);
            }
        }

        // Mark campaign as sent when no pending rows remain
        $campaignIds = array_unique(array_column($rows, 'campaign_id'));
        foreach ($campaignIds as $cid) {
            $pending = $db->prepare("SELECT COUNT(*) FROM `" . self::TABLE_QUEUE . "` WHERE campaign_id=? AND status='pending'");
            $pending->execute([$cid]);
            if ((int)$pending->fetchColumn() === 0) {
                $db->prepare("UPDATE `" . self::TABLE_CAMPAIGNS . "` SET status='sent', sent_at=? WHERE id=?")
                    ->execute([time(), $cid]);
            }
        }
    }

    private function sendSingle(array $campaign, array $subscriber): bool
    {
        $blocks = json_decode($campaign['content_blocks'] ?? '[]', true) ?: [];

        $unsubscribeUrl = $this->buildUnsubscribeUrl($subscriber);
        $htmlBody = $this->renderEmail($campaign, $blocks, $subscriber, $unsubscribeUrl);

        $mail = wireMail();
        $mail->to($subscriber['email'], $subscriber['name']);
        $fromEmail = $this->wire()->config->email_from ?? $this->wire()->config->wireMail('fromEmail');
        $fromName = $this->wire()->config->email_from_name ?? $this->wire()->config->wireMail('fromName');
        $mail->from($fromEmail, $fromName);
        $mail->subject($campaign['subject']);
        $mail->header('List-Unsubscribe', '<' . $unsubscribeUrl . '>');
        $mail->header('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        $mail->bodyHTML($htmlBody);

        try {
            return (bool)$mail->send();
        } catch (\Exception $e) {
            $this->error("Newsletter send error: " . $e->getMessage());
            return false;
        }
    }

    private function renderEmail(array $campaign, array $blocks, array $subscriber, string $unsubscribeUrl): string
    {
        $templateFile = $this->wire()->config->paths->templates . "emails/newsletter-campaign.php";
        if (!file_exists($templateFile)) {
            return $campaign['body_intro'] ?: '';
        }

        $campaignData = $campaign;
        $blocksData = $blocks;
        $subscriberData = $subscriber;
        $config = $this->wire()->config;
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    private function getCampaign(int $id): ?array
    {
        $db = $this->wire()->database;
        $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_CAMPAIGNS . "` WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getSubscriber(int $id): ?array
    {
        $db = $this->wire()->database;
        $stmt = $db->prepare("SELECT * FROM `" . self::TABLE_SUBSCRIBERS . "` WHERE id=?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buildUnsubscribeUrl(array $subscriber): string
    {
        if (empty($subscriber['unsubscribe_token'])) {
            $subscriber['unsubscribe_token'] = bin2hex(random_bytes(32));
            $this->wire()->database->prepare("UPDATE `" . self::TABLE_SUBSCRIBERS . "` SET unsubscribe_token=? WHERE id=?")
                ->execute([$subscriber['unsubscribe_token'], $subscriber['id']]);
        }
        $host = $this->wire()->config->httpHosts[0] ?? $_SERVER['HTTP_HOST'] ?? 'cms.bioco.ch';
        $scheme = $this->wire()->config->https ? 'https' : 'http';
        return $scheme . '://' . $host . $this->wire()->config->urls->root . "unsubscribe/?token=" . $subscriber['unsubscribe_token'];
    }

    /**
     * Public endpoint to unsubscribe by token.
     */
    public function unsubscribeByToken(string $token): bool
    {
        $token = trim($token);
        if (!$token) return false;

        $db = $this->wire()->database;
        $stmt = $db->prepare("SELECT id FROM `" . self::TABLE_SUBSCRIBERS . "` WHERE unsubscribe_token=?");
        $stmt->execute([$token]);
        $id = $stmt->fetchColumn();
        if (!$id) return false;

        $db->prepare("UPDATE `" . self::TABLE_SUBSCRIBERS . "` SET status='unsubscribed', unsubscribed_at=?, updated=? WHERE id=?")
            ->execute([time(), time(), $id]);
        return true;
    }
}
