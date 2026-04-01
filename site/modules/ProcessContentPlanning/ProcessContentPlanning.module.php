<?php namespace ProcessWire;

/**
 * Process Plan & Bugs Module v2
 * 
 * Dashboard for planning web content with list/kanban views,
 * GitHub issue creation, page linking, publish control, and docs feed.
 */

class ProcessContentPlanning extends Process {

    const TABLE_NAME = 'planning_items';
    const DRAFT_TABLE = 'planning_draft_fields';

    public static function getModuleInfo() {
        return [
            'title' => 'Plan & Bugs',
            'version' => 201,
            'summary' => 'Plan content and track bugs with list/kanban views, page linking, GitHub issues',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'content-planning',
                'parent' => '',  // Empty = top-level menu item
                'title' => 'Plan & Bugs',
                'icon' => 'calendar-check-o'
            ]
        ];
    }

    public function init() {
        parent::init();
        $this->ensureAdminPageLabel();
        $this->createDatabaseTable();
        $this->createDraftFieldsTable();
        $this->migrateDatabase();
    }

    private function ensureAdminPageLabel() {
        $page = $this->wire('pages')->get('process=ProcessContentPlanning, include=all');
        if (!$page->id) return;
        if ($page->title === 'Plan & Bugs') return;
        $page->of(false);
        $page->title = 'Plan & Bugs';
        $page->save('title');
    }

    /**
     * Create database table if not exists
     */
    private function createDatabaseTable() {
        $table = self::TABLE_NAME;
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `item_type` varchar(50) DEFAULT 'content',
            `details` text,
            `priority` varchar(20) DEFAULT 'medium',
            `status` varchar(20) DEFAULT 'backlog',
            `owner` varchar(100) DEFAULT '',
            `owner_id` int(11) DEFAULT NULL,
            `linked_page_id` int(11) DEFAULT NULL,
            `github_issue_url` varchar(255) DEFAULT '',
            `github_issue_number` int(11) DEFAULT NULL,
            `created` int(11) NOT NULL,
            `modified` int(11) NOT NULL,
            `resolved_at` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `priority` (`priority`),
            KEY `linked_page_id` (`linked_page_id`),
            KEY `owner_id` (`owner_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $this->wire()->database->exec($sql);
        } catch(\Exception $e) {
            // Table exists
        }
    }

    /**
     * Create draft fields table if not exists
     */
    private function createDraftFieldsTable() {
        $table = self::DRAFT_TABLE;
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `planning_item_id` int(11) NOT NULL,
            `field_name` varchar(255) NOT NULL,
            `field_value` longtext,
            `field_type` varchar(50),
            `created` int(11) NOT NULL,
            `modified` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `item_field` (`planning_item_id`, `field_name`),
            KEY `planning_item_id` (`planning_item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $this->wire()->database->exec($sql);
        } catch(\Exception $e) {
            // Table exists
        }
    }

    /**
     * Migrate existing table to add new columns
     */
    private function migrateDatabase() {
        $table = self::TABLE_NAME;
        $db = $this->wire()->database;
        
        // Add linked_page_id if not exists
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `linked_page_id` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        // Add resolved_at if not exists
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `resolved_at` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        // Add owner_id if not exists
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `owner_id` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        // Add assignment fields
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `assigner_id` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `assignee_id` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `last_notified` int(11) DEFAULT NULL");
        } catch(\Exception $e) {}
        
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `notify_assignee` tinyint(1) DEFAULT 1");
        } catch(\Exception $e) {}
        
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `notification_frequency` varchar(20) DEFAULT 'immediate'");
        } catch(\Exception $e) {}
        
        // Add indexes for assignment fields
        try {
            $db->exec("ALTER TABLE `{$table}` ADD KEY `assigner_id` (`assigner_id`)");
        } catch(\Exception $e) {}
        
        try {
            $db->exec("ALTER TABLE `{$table}` ADD KEY `assignee_id` (`assignee_id`)");
        } catch(\Exception $e) {}
        
        // Add topic field for issues without page reference
        try {
            $db->exec("ALTER TABLE `{$table}` ADD COLUMN `topic` varchar(255) DEFAULT NULL");
        } catch(\Exception $e) {}
    }

    /**
     * Main execute: render dashboard
     */
    public function ___execute() {
        $this->wire('modules')->get('JqueryUI')->use('vex');
        
        $cssUrl = $this->wire('config')->urls($this) . 'planning.css';
        $this->wire('config')->styles->add($cssUrl);

        $view = $this->wire('input')->get('view') ?: 'kanban';
        
        $out = $this->renderHeader($view);
        
        if ($view === 'list') {
            $out .= $this->renderListView();
        } else {
            $out .= $this->renderKanbanView();
        }
        
        $out .= $this->renderAddForm();
        $out .= $this->renderScripts();
        
        return $out;
    }
    
    /**
     * Export planning data as SQL backup
     */
    public function ___executeExport() {
        $backup = $this->wire('database')->backups();
        
        $tables = [self::TABLE_NAME, self::DRAFT_TABLE];
        $filename = 'planning-backup-' . date('Y-m-d-His') . '.sql';
        
        try {
            $file = $backup->backup([
                'tables' => $tables,
                'filename' => $filename
            ]);
            
            if ($file) {
                wireSendFile($file, ['forceDownload' => true, 'exit' => true]);
            } else {
                $this->error("Backup failed - no file created");
                return $this->___execute();
            }
        } catch(\Exception $e) {
            $this->error("Backup error: " . $e->getMessage());
            return $this->___execute();
        }
    }

    /**
     * Header with docs link, view toggle, and export button
     */
    private function renderHeader($currentView) {
        $listActive = $currentView === 'list' ? 'active' : '';
        $kanbanActive = $currentView === 'kanban' ? 'active' : '';
        $baseUrl = $this->wire('page')->url;
        
        return "
        <div class='planning-header'>
            <div class='header-left'>
                <a href='https://docs.bioco.ch' target='_blank' class='docs-link'>
                    <i class='fa fa-external-link'></i> docs.bioco.ch
                </a>
                <a href='{$baseUrl}export/' class='export-btn ui-button'>
                    <i class='fa fa-download'></i> Export Backup
                </a>
            </div>
            <div class='header-center'>
                <button id='add-item-btn' class='ui-button ui-priority-primary'>
                    <i class='fa fa-plus'></i> Add Item
                </button>
            </div>
            <div class='view-toggle'>
                <a href='{$baseUrl}?view=list' class='view-btn {$listActive}'>
                    <i class='fa fa-list'></i> List
                </a>
                <a href='{$baseUrl}?view=kanban' class='view-btn {$kanbanActive}'>
                    <i class='fa fa-columns'></i> Kanban
                </a>
            </div>
        </div>";
    }

    /**
     * Kanban board view
     */
    private function renderKanbanView() {
        $statuses = ['backlog' => 'Backlog', 'progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
        $items = $this->getItems();
        
        $out = "<div class='planning-container'>";
        $out .= "<div class='kanban-board'>";
        
        foreach ($statuses as $key => $label) {
            $count = count(array_filter($items, fn($i) => $i['status'] === $key));
            $out .= "<div class='kanban-column' data-status='{$key}'>";
            $out .= "<div class='column-header'>{$label} <span class='count'>({$count})</span></div>";
            $out .= "<div class='column-items'>";
            
            foreach ($items as $item) {
                if ($item['status'] === $key) {
                    $out .= $this->renderCard($item);
                }
            }
            
            $out .= "</div></div>";
        }
        
        $out .= "</div>";
        $out .= $this->renderSidebar();
        $out .= "</div>";
        
        return $out;
    }

    /**
     * List table view
     */
    private function renderListView() {
        $items = $this->getItems();
        
        $out = "<div class='planning-container'>";
        $out .= "<div class='list-view'>";
        $out .= "<table class='AdminDataTable'>";
        $out .= "<thead><tr>
            <th>Title</th>
            <th>Topic</th>
            <th>Type</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Assigned To</th>
            <th>Assigned By</th>
            <th>Page</th>
            <th>Created</th>
            <th>Actions</th>
        </tr></thead><tbody>";
        
        foreach ($items as $item) {
            $pageLink = $this->renderPageLink($item);
            $createdDate = $this->timeAgo($item['created']);
            $resolvedBadge = $item['resolved_at'] ? "<span class='resolved-badge'>Resolved " . $this->timeAgo($item['resolved_at']) . "</span>" : '';
            $assigneeName = $this->getOwnerName($item);
            $assignerName = $this->getAssignerName($item);
            $topicDisplay = !empty($item['topic']) ? $this->wire('sanitizer')->entities($item['topic']) : '-';
            
            $out .= "<tr data-id='{$item['id']}'>
                <td>
                    <strong>{$this->wire('sanitizer')->entities($item['title'])}</strong>
                    {$resolvedBadge}
                </td>
                <td class='topic-cell'>{$topicDisplay}</td>
                <td><span class='badge type-{$item['item_type']}'>{$item['item_type']}</span></td>
                <td><span class='badge priority-{$item['priority']}'>{$item['priority']}</span></td>
                <td>
                    <select class='status-select' data-id='{$item['id']}'>
                        <option value='backlog'" . ($item['status'] === 'backlog' ? ' selected' : '') . ">Backlog</option>
                        <option value='progress'" . ($item['status'] === 'progress' ? ' selected' : '') . ">In Progress</option>
                        <option value='review'" . ($item['status'] === 'review' ? ' selected' : '') . ">Review</option>
                        <option value='done'" . ($item['status'] === 'done' ? ' selected' : '') . ">Done</option>
                    </select>
                </td>
                <td>{$assigneeName}</td>
                <td>{$assignerName}</td>
                <td>{$pageLink}</td>
                <td class='date-cell'>{$createdDate}</td>
                <td class='actions-cell'>
                    <button class='edit-btn ui-button' data-id='{$item['id']}'><i class='fa fa-pencil'></i></button>
                    <button class='delete-btn ui-button' data-id='{$item['id']}'><i class='fa fa-trash'></i></button>
                    " . $this->renderGitHubButton($item) . "
                    " . $this->renderPublishButton($item) . "
                    " . $this->renderPublishDraftButton($item) . "
                </td>
            </tr>";
        }
        
        $out .= "</tbody></table></div>";
        $out .= $this->renderSidebar();
        $out .= "</div>";
        
        return $out;
    }

    /**
     * Render single card for kanban
     */
    private function renderCard($item) {
        $ghBadge = $item['github_issue_url'] 
            ? "<a href='{$item['github_issue_url']}' target='_blank' class='gh-badge'><i class='fa fa-github'></i> #{$item['github_issue_number']}</a>"
            : '';
        
        $pageLink = $this->renderPageLink($item, true);
        $createdDate = $this->timeAgo($item['created']);
        $resolvedInfo = $item['resolved_at'] ? "<div class='card-resolved'>Resolved " . $this->timeAgo($item['resolved_at']) . "</div>" : '';
        $ownerName = $this->getOwnerName($item);
        $topicBadge = !empty($item['topic']) ? "<div class='card-topic'><i class='fa fa-tag'></i> {$this->wire('sanitizer')->entities($item['topic'])}</div>" : '';
        
        return "
        <div class='kanban-card' data-id='{$item['id']}'>
            <div class='card-header'>
                <span class='badge type-{$item['item_type']}'>{$item['item_type']}</span>
                <span class='badge priority-{$item['priority']}'>{$item['priority']}</span>
            </div>
            <div class='card-title'>{$this->wire('sanitizer')->entities($item['title'])}</div>
            {$topicBadge}
            {$pageLink}
            <div class='card-meta'>
                <span class='card-date'><i class='fa fa-clock-o'></i> {$createdDate}</span>
                <span class='card-owner'>{$ownerName}</span>
            </div>
            {$resolvedInfo}
            <div class='card-footer'>
                {$ghBadge}
            </div>
            <div class='card-actions'>
                <button class='edit-btn' data-id='{$item['id']}'><i class='fa fa-pencil'></i></button>
                <button class='delete-btn' data-id='{$item['id']}'><i class='fa fa-trash'></i></button>
                " . $this->renderGitHubButton($item) . "
                " . $this->renderPublishButton($item) . "
                " . $this->renderPublishDraftButton($item) . "
            </div>
        </div>";
    }

    /**
     * Render page link with edit button
     */
    private function renderPageLink($item, $compact = false) {
        if (empty($item['linked_page_id'])) {
            return $compact ? '' : '<span class="no-page">-</span>';
        }
        
        $page = $this->wire('pages')->get($item['linked_page_id']);
        if (!$page->id) {
            return $compact ? '' : '<span class="no-page">-</span>';
        }
        
        $editUrl = $this->wire('config')->urls->admin . "page/edit/?id=" . $page->id;
        $title = $this->wire('sanitizer')->entities($page->title);
        $isPublished = !$page->isUnpublished();
        $statusIcon = $isPublished ? 'fa-check-circle published' : 'fa-eye-slash unpublished';
        
        if ($compact) {
            return "<div class='card-page'>
                <i class='fa {$statusIcon}'></i>
                <a href='{$editUrl}' target='_blank' title='{$title}'>{$title}</a>
            </div>";
        }
        
        return "<div class='page-link'>
            <i class='fa {$statusIcon}'></i>
            <a href='{$editUrl}' target='_blank'>{$title}</a>
        </div>";
    }

    /**
     * Render GitHub button if no issue exists
     */
    private function renderGitHubButton($item) {
        if ($item['github_issue_url']) return '';
        return "<button class='github-btn ui-button' data-id='{$item['id']}' title='Create GitHub Issue'><i class='fa fa-github'></i></button>";
    }

    /**
     * Render publish/unpublish button if page is linked
     */
    private function renderPublishButton($item) {
        if (empty($item['linked_page_id'])) return '';
        
        $page = $this->wire('pages')->get($item['linked_page_id']);
        if (!$page->id) return '';
        
        $isPublished = !$page->isUnpublished();
        $action = $isPublished ? 'unpublish' : 'publish';
        $icon = $isPublished ? 'fa-eye-slash' : 'fa-eye';
        $title = $isPublished ? 'Unpublish Page' : 'Publish Page';
        
        return "<button class='publish-btn ui-button' data-id='{$item['id']}' data-action='{$action}' title='{$title}'><i class='fa {$icon}'></i></button>";
    }
    
    /**
     * Render publish draft button if page is linked and drafts exist
     */
    private function renderPublishDraftButton($item) {
        if (empty($item['linked_page_id'])) return '';
        
        $draftFields = $this->getAllDraftFields($item['id']);
        if (empty($draftFields)) return '';
        
        return "<button class='publish-draft-btn ui-button' data-id='{$item['id']}' title='Publish Draft Changes'><i class='fa fa-upload'></i> Publish Draft</button>";
    }

    /**
     * Sidebar with GitHub issues and docs feed
     */
    private function renderSidebar() {
        $out = "<div class='planning-sidebar'>";
        $out .= $this->renderGitHubIssuesPanel();
        $out .= $this->renderDocsFeed();
        $out .= "</div>";
        return $out;
    }

    /**
     * GitHub issues panel for features/bugs
     */
    private function renderGitHubIssuesPanel() {
        $issues = $this->fetchGitHubIssues();
        
        $out = "<div class='github-issues-panel'>";
        $out .= "<div class='panel-header'><i class='fa fa-github'></i> Open Issues</div>";
        $out .= "<div class='panel-items'>";
        
        if (empty($issues)) {
            $out .= "<div class='panel-empty'>No open issues</div>";
        } else {
            foreach ($issues as $issue) {
                $labels = '';
                foreach ($issue['labels'] as $label) {
                    $labels .= "<span class='issue-label'>{$label}</span>";
                }
                $out .= "
                <div class='issue-item'>
                    <a href='{$issue['url']}' target='_blank'>#{$issue['number']} {$this->wire('sanitizer')->entities($issue['title'])}</a>
                    <div class='issue-meta'>{$labels} · {$issue['time']}</div>
                </div>";
            }
        }
        
        $out .= "</div></div>";
        return $out;
    }

    /**
     * Docs feed panel
     */
    private function renderDocsFeed() {
        $commits = $this->fetchDocsCommits();
        
        $out = "<div class='docs-feed'>";
        $out .= "<div class='panel-header'><i class='fa fa-history'></i> Docs Changes</div>";
        $out .= "<div class='panel-items'>";
        
        if (empty($commits)) {
            $out .= "<div class='panel-empty'>No recent changes</div>";
        } else {
            foreach ($commits as $c) {
                $msg = $this->wire('sanitizer')->entities(substr($c['message'], 0, 50));
                $out .= "
                <div class='feed-item'>
                    <a href='{$c['url']}' target='_blank'>{$msg}</a>
                    <div class='feed-meta'>{$c['author']} · {$c['time']}</div>
                </div>";
            }
        }
        
        $out .= "</div></div>";
        return $out;
    }

    /**
     * Get all CMS pages for dropdown (only /content/ branch, exclude 404)
     */
    private function getPageOptions() {
        // Find all front-end pages, exclude system/admin templates
        $excludeTemplates = 'admin|user|role|permission|api|api-events|visual-editor|MediaLibrary|site_settings|repeater_content_sections';
        $pages = $this->wire('pages')->find("template!=$excludeTemplates, id>1, include=all, limit=500, sort=path");
        $options = ['<option value="">-- No Page (Optional) --</option>'];
        
        foreach ($pages as $p) {
            $status = $p->isUnpublished() ? ' (unpublished)' : '';
            $title = $this->wire('sanitizer')->entities($p->title . $status);
            $options[] = "<option value='{$p->id}'>{$p->path} - {$title}</option>";
        }
        
        return implode("\n", $options);
    }

    /**
     * Get all ProcessWire users for dropdown
     */
    private function getUserOptions($includeEmpty = true) {
        // Direct DB query to bypass PW access control on user pages
        $db = $this->wire('database');
        $stmt = $db->prepare("SELECT p.id, p.name FROM pages p JOIN templates t ON p.templates_id = t.id WHERE t.name = 'user' AND p.name != 'guest' ORDER BY p.name LIMIT 100");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $options = [];
        if ($includeEmpty) {
            $options[] = '<option value="">-- Select User --</option>';
        }
        foreach ($rows as $row) {
            $name = $this->wire('sanitizer')->entities($row['name']);
            $options[] = "<option value='{$row['id']}'>{$name}</option>";
        }
        return implode("\n", $options);
    }

    /**
     * Get assignee name from item
     */
    private function getOwnerName($item) {
        // Try assignee_id first (new field), fallback to owner_id (old field)
        $userId = $item['assignee_id'] ?? $item['owner_id'] ?? null;
        
        if ($userId) {
            $user = $this->wire('users')->get($userId);
            if ($user->id) {
                return $this->wire('sanitizer')->entities($user->name);
            }
        }
        return $this->wire('sanitizer')->entities($item['owner'] ?: '-');
    }
    
    /**
     * Get assigner name from item
     */
    private function getAssignerName($item) {
        if (!empty($item['assigner_id'])) {
            $user = $this->wire('users')->get($item['assigner_id']);
            if ($user->id) {
                return $this->wire('sanitizer')->entities($user->name);
            }
        }
        return '-';
    }

    /**
     * Add/Edit form with page selector
     */
    private function renderAddForm() {
        $pageOptions = $this->getPageOptions();
        $userOptions = $this->getUserOptions();
        $debugInfo = "<!-- DEBUG v2: pages=" . substr_count($pageOptions, '<option') . " users=" . substr_count($userOptions, '<option') . " -->";
        
        return "{$debugInfo}
        <div id='item-form-overlay' class='form-overlay' style='display:none;'>
            <div class='form-modal'>
                <h3 id='form-title'>Add Item</h3>
                <form id='item-form'>
                    <input type='hidden' name='id' id='item-id' value=''>
                    <div class='form-row'>
                        <label>Title *</label>
                        <input type='text' name='title' id='item-title' required>
                        <small>Auto-filled from page if selected, or enter manually</small>
                    </div>
                    <div class='form-row'>
                        <label>Topic</label>
                        <input type='text' name='topic' id='item-topic' placeholder='e.g., New feature idea, Bug in checkout'>
                        <small>Optional: Describe the topic/issue if not related to specific page</small>
                    </div>
                    <div class='form-row form-row-highlight'>
                        <label>Linked Page (Optional)</label>
                        <div class='page-selector'>
                            <select name='linked_page_id' id='item-page'>
                                {$pageOptions}
                            </select>
                            <a href='#' id='edit-page-link' class='edit-page-btn' target='_blank' style='display:none;'>
                                <i class='fa fa-external-link'></i>
                            </a>
                        </div>
                        <small>Select a page to edit its content fields below (only /content/ pages)</small>
                    </div>
                    <div class='form-row'>
                        <label>Type</label>
                        <select name='item_type' id='item-type'>
                            <option value='content'>Content</option>
                            <option value='feature'>Feature</option>
                            <option value='bug'>Bug</option>
                            <option value='seo'>SEO</option>
                        </select>
                    </div>
                    <div class='form-row'>
                        <label>Priority</label>
                        <select name='priority' id='item-priority'>
                            <option value='low'>Low</option>
                            <option value='medium' selected>Medium</option>
                            <option value='high'>High</option>
                            <option value='urgent'>Urgent</option>
                        </select>
                    </div>
                    <div class='form-row'>
                        <label>Status</label>
                        <select name='status' id='item-status'>
                            <option value='backlog'>Backlog</option>
                            <option value='progress'>In Progress</option>
                            <option value='review'>Review</option>
                            <option value='done'>Done</option>
                        </select>
                    </div>
                    <div class='form-row'>
                        <label>Assigned By (Assigner)</label>
                        <select name='assigner_id' id='item-assigner'>
                            {$userOptions}
                        </select>
                    </div>
                    <div class='form-row'>
                        <label>Assigned To (Assignee)</label>
                        <div class='assignee-selector'>
                            <select name='assignee_id' id='item-assignee'>
                                {$userOptions}
                            </select>
                            <button type='button' id='assign-to-me-btn' class='ui-button'>
                                <i class='fa fa-user'></i> Assign to Me
                            </button>
                        </div>
                    </div>
                    <div class='form-row'>
                        <label>Details</label>
                        <textarea name='details' id='item-details' rows='4'></textarea>
                    </div>
                    <div id='field-editor-container'></div>
                    <div class='form-actions'>
                        <button type='button' class='ui-button cancel-btn'>Cancel</button>
                        <button type='submit' class='ui-button ui-priority-primary'>Save</button>
                    </div>
                </form>
            </div>
        </div>";
    }

    /**
     * JavaScript for interactions
     */
    private function renderScripts() {
        $ajaxUrl = $this->wire('page')->url . 'ajax/';
        $adminUrl = $this->wire('config')->urls->admin;
        
        return "
        <script>
        (function() {
            const ajaxUrl = '{$ajaxUrl}';
            const adminUrl = '{$adminUrl}';
            const currentUserId = " . $this->wire('user')->id . ";
            
            // Assign to Me button
            document.getElementById('assign-to-me-btn').onclick = function() {
                document.getElementById('item-assignee').value = currentUserId;
            };
            
            // Page selector edit link
            const pageSelect = document.getElementById('item-page');
            const editPageLink = document.getElementById('edit-page-link');
            
            pageSelect.onchange = function() {
                if (this.value) {
                    editPageLink.href = adminUrl + 'page/edit/?id=' + this.value;
                    editPageLink.style.display = 'inline-flex';
                    
                    // Auto-populate title from page title
                    const selectedOption = this.options[this.selectedIndex];
                    const pageTitle = selectedOption.text.split(' - ')[1] || selectedOption.text;
                    const titleInput = document.getElementById('item-title');
                    if (!titleInput.value || titleInput.dataset.autofilled) {
                        titleInput.value = pageTitle;
                        titleInput.dataset.autofilled = 'true';
                    }
                    
                    // Load field editor if editing existing item
                    const itemId = document.getElementById('item-id').value;
                    if (itemId) {
                        loadFieldEditor(itemId, this.value);
                    }
                } else {
                    editPageLink.style.display = 'none';
                    document.getElementById('field-editor-container').innerHTML = '';
                    document.getElementById('item-title').value = '';
                    delete document.getElementById('item-title').dataset.autofilled;
                }
            };
            
            // Load field editor for page
            function loadFieldEditor(itemId, pageId) {
                if (!itemId || !pageId) return;
                
                fetch(ajaxUrl + '?action=getfields&id=' + itemId + '&page_id=' + pageId)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('field-editor-container').innerHTML = data.html;
                            attachDraftSaveHandlers();
                        }
                    });
            }
            
            // Attach save handlers to draft field buttons
            function attachDraftSaveHandlers() {
                document.querySelectorAll('.save-draft-btn').forEach(btn => {
                    btn.onclick = function() {
                        const fieldName = this.dataset.field;
                        const itemId = this.dataset.item;
                        const fieldInput = this.closest('.draft-field').querySelector('.draft-input');
                        
                        if (!fieldInput) return;
                        
                        let value = fieldInput.value;
                        if (fieldInput.type === 'checkbox') {
                            value = fieldInput.checked ? '1' : '0';
                        }
                        
                        const form = new FormData();
                        form.append('action', 'savefield');
                        form.append('id', itemId);
                        form.append('field_name', fieldName);
                        form.append('field_value', value);
                        
                        fetch(ajaxUrl, { method: 'POST', body: form })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    this.textContent = 'Saved!';
                                    setTimeout(() => { this.textContent = 'Save Draft'; }, 1500);
                                } else {
                                    alert('Error saving draft');
                                }
                            });
                    };
                });
            }
            
            // Add button
            document.getElementById('add-item-btn').onclick = function() {
                document.getElementById('form-title').textContent = 'Add Item';
                document.getElementById('item-form').reset();
                document.getElementById('item-id').value = '';
                document.getElementById('item-page').value = '';
                document.getElementById('field-editor-container').innerHTML = '';
                editPageLink.style.display = 'none';
                delete document.getElementById('item-title').dataset.autofilled;
                // Auto-set assigner to current user
                document.getElementById('item-assigner').value = currentUserId;
                document.getElementById('item-form-overlay').style.display = 'flex';
            };
            
            // Cancel button
            document.querySelector('.cancel-btn').onclick = function() {
                document.getElementById('item-form-overlay').style.display = 'none';
            };
            
            // Form submit
            document.getElementById('item-form').onsubmit = function(e) {
                e.preventDefault();
                const form = new FormData(this);
                form.append('action', form.get('id') ? 'update' : 'create');
                
                fetch(ajaxUrl, {
                    method: 'POST',
                    body: form
                }).then(r => r.json()).then(data => {
                    if (data.success) location.reload();
                    else alert(data.error || 'Error');
                });
            };
            
            // Edit buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.onclick = function() {
                    const id = this.dataset.id;
                    fetch(ajaxUrl + '?action=get&id=' + id)
                        .then(r => r.json())
                        .then(item => {
                            document.getElementById('form-title').textContent = 'Edit Item';
                            document.getElementById('item-id').value = item.id;
                            document.getElementById('item-title').value = item.title;
                            document.getElementById('item-topic').value = item.topic || '';
                            document.getElementById('item-type').value = item.item_type;
                            document.getElementById('item-priority').value = item.priority;
                            document.getElementById('item-status').value = item.status;
                            document.getElementById('item-assigner').value = item.assigner_id || '';
                            document.getElementById('item-assignee').value = item.assignee_id || item.owner_id || '';
                            document.getElementById('item-page').value = item.linked_page_id || '';
                            document.getElementById('item-details').value = item.details || '';
                            
                            if (item.linked_page_id) {
                                editPageLink.href = adminUrl + 'page/edit/?id=' + item.linked_page_id;
                                editPageLink.style.display = 'inline-flex';
                                loadFieldEditor(item.id, item.linked_page_id);
                            } else {
                                editPageLink.style.display = 'none';
                                document.getElementById('field-editor-container').innerHTML = '';
                            }
                            
                            document.getElementById('item-form-overlay').style.display = 'flex';
                        });
                };
            });
            
            // Delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.onclick = function() {
                    if (!confirm('Delete this item?')) return;
                    const form = new FormData();
                    form.append('action', 'delete');
                    form.append('id', this.dataset.id);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => { if (data.success) location.reload(); });
                };
            });
            
            // GitHub buttons
            document.querySelectorAll('.github-btn').forEach(btn => {
                btn.onclick = function() {
                    if (!confirm('Create GitHub issue for this item?')) return;
                    const form = new FormData();
                    form.append('action', 'github');
                    form.append('id', this.dataset.id);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert('Issue created: ' + data.url);
                                location.reload();
                            } else {
                                alert(data.error || 'Error creating issue');
                            }
                        });
                };
            });
            
            // Publish buttons
            document.querySelectorAll('.publish-btn').forEach(btn => {
                btn.onclick = function() {
                    const action = this.dataset.action;
                    const msg = action === 'publish' ? 'Publish this page?' : 'Unpublish this page?';
                    if (!confirm(msg)) return;
                    
                    const form = new FormData();
                    form.append('action', action);
                    form.append('id', this.dataset.id);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) location.reload();
                            else alert(data.error || 'Error');
                        });
                };
            });
            
            // Status select change
            document.querySelectorAll('.status-select').forEach(sel => {
                sel.onchange = function() {
                    const form = new FormData();
                    form.append('action', 'status');
                    form.append('id', this.dataset.id);
                    form.append('status', this.value);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => { if (data.success) location.reload(); });
                };
            });
            
            // Publish draft buttons
            document.querySelectorAll('.publish-draft-btn').forEach(btn => {
                btn.onclick = function() {
                    if (!confirm('Publish all draft changes to the page?')) return;
                    
                    const form = new FormData();
                    form.append('action', 'publishdraft');
                    form.append('id', this.dataset.id);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message || 'Draft published successfully');
                                location.reload();
                            } else {
                                alert(data.error || 'Error publishing draft');
                            }
                        });
                };
            });
        })();
        </script>";
    }

    /**
     * Handle AJAX requests
     */
    public function ___executeAjax() {
        header('Content-Type: application/json');
        
        $action = $this->wire('input')->get('action') ?: $this->wire('input')->post('action');
        $id = (int)($this->wire('input')->get('id') ?: $this->wire('input')->post('id'));
        
        switch ($action) {
            case 'get':
                $item = $this->getItem($id);
                echo json_encode($item ?: ['error' => 'Not found']);
                break;
            case 'create':
            case 'update':
                $result = $this->saveItem();
                echo json_encode($result);
                break;
            case 'delete':
                $result = $this->deleteItem($id);
                echo json_encode($result);
                break;
            case 'status':
                $status = $this->wire('sanitizer')->name($this->wire('input')->post('status'));
                $result = $this->updateStatus($id, $status);
                echo json_encode($result);
                break;
            case 'github':
                $result = $this->createGitHubIssueForItem($id);
                echo json_encode($result);
                break;
            case 'publish':
                $result = $this->publishPage($id);
                echo json_encode($result);
                break;
            case 'unpublish':
                $result = $this->unpublishPage($id);
                echo json_encode($result);
                break;
            case 'savefield':
                $fieldName = $this->wire('sanitizer')->fieldName($this->wire('input')->post('field_name'));
                $fieldValue = $this->wire('input')->post('field_value');
                $fieldType = $this->wire('sanitizer')->text($this->wire('input')->post('field_type'));
                $result = $this->saveDraftFieldValue($id, $fieldName, $fieldValue, $fieldType);
                echo json_encode(['success' => $result]);
                break;
            case 'publishdraft':
                $result = $this->publishDraftChanges($id);
                echo json_encode($result);
                break;
            case 'getfields':
                $pageId = (int)$this->wire('input')->get('page_id');
                $html = $this->renderPageFields($pageId, $id);
                echo json_encode(['success' => true, 'html' => $html]);
                break;
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }

    // =========================================================================
    // DATABASE METHODS
    // =========================================================================

    private function getItems() {
        $table = self::TABLE_NAME;
        $sql = "SELECT * FROM `{$table}` ORDER BY 
                FIELD(priority, 'urgent', 'high', 'medium', 'low'),
                FIELD(status, 'backlog', 'progress', 'review', 'done'),
                created DESC";
        $query = $this->wire()->database->prepare($sql);
        $query->execute();
        return $query->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getItem($id) {
        $table = self::TABLE_NAME;
        $sql = "SELECT * FROM `{$table}` WHERE id = ?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$id]);
        return $query->fetch(\PDO::FETCH_ASSOC);
    }

    private function saveItem() {
        $input = $this->wire('input');
        $san = $this->wire('sanitizer');
        
        $id = (int)$input->post('id');
        $newStatus = $san->name($input->post('status')) ?: 'backlog';
        
        $data = [
            'title' => $san->text($input->post('title')),
            'topic' => $san->text($input->post('topic')) ?: null,
            'item_type' => $san->name($input->post('item_type')) ?: 'content',
            'details' => $san->textarea($input->post('details')),
            'priority' => $san->name($input->post('priority')) ?: 'medium',
            'status' => $newStatus,
            'owner_id' => (int)$input->post('owner_id') ?: null,
            'assigner_id' => (int)$input->post('assigner_id') ?: null,
            'assignee_id' => (int)$input->post('assignee_id') ?: null,
            'linked_page_id' => (int)$input->post('linked_page_id') ?: null,
            'modified' => time(),
        ];
        
        if (empty($data['title'])) {
            return ['success' => false, 'error' => 'Title required'];
        }
        
        $table = self::TABLE_NAME;
        $db = $this->wire()->database;
        
        if ($id) {
            // Get old item for change detection
            $oldItem = $this->getItem($id);
            $resolvedAt = null;
            if ($newStatus === 'done' && $oldItem['status'] !== 'done') {
                $resolvedAt = time();
            } elseif ($newStatus !== 'done') {
                $resolvedAt = null;
            } else {
                $resolvedAt = $oldItem['resolved_at'];
            }
            
            $sql = "UPDATE `{$table}` SET 
                    title=?, topic=?, item_type=?, details=?, priority=?, status=?, owner_id=?, assigner_id=?, assignee_id=?, linked_page_id=?, modified=?, resolved_at=?
                    WHERE id=?";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['topic'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner_id'], 
                $data['assigner_id'], $data['assignee_id'],
                $data['linked_page_id'], $data['modified'], $resolvedAt, $id
            ]);
            
            // Check for changes and send notifications
            $currentItem = $this->getItem($id);
            
            // Assignment change
            if ($oldItem['assignee_id'] != $currentItem['assignee_id'] && $currentItem['assignee_id']) {
                $this->sendNotification($currentItem['assignee_id'], $currentItem, 'Assigned to You');
            }
            
            // Status change
            if ($oldItem['status'] != $currentItem['status'] && $currentItem['assignee_id']) {
                $statusLabels = ['backlog' => 'Backlog', 'progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
                $oldStatus = $statusLabels[$oldItem['status']] ?? $oldItem['status'];
                $newStatus = $statusLabels[$currentItem['status']] ?? $currentItem['status'];
                $this->sendNotification($currentItem['assignee_id'], $currentItem, 'Status Changed', "Changed from <strong>{$oldStatus}</strong> to <strong>{$newStatus}</strong>");
            }
            
            // Priority change to high/urgent
            if ($oldItem['priority'] != $currentItem['priority'] && 
                in_array($currentItem['priority'], ['high', 'urgent']) && 
                $currentItem['assignee_id']) {
                $this->sendNotification($currentItem['assignee_id'], $currentItem, 'Priority Changed', "Priority upgraded to <strong>{$currentItem['priority']}</strong>");
            }
        } else {
            $data['created'] = time();
            $resolvedAt = $newStatus === 'done' ? time() : null;
            
            $sql = "INSERT INTO `{$table}` 
                    (title, topic, item_type, details, priority, status, owner_id, assigner_id, assignee_id, linked_page_id, created, modified, resolved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['topic'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner_id'],
                $data['assigner_id'], $data['assignee_id'],
                $data['linked_page_id'], $data['created'], $data['modified'], $resolvedAt
            ]);
            $id = $db->lastInsertId();
            
            // Send notification for new assignment
            if ($data['assignee_id']) {
                $newItem = $this->getItem($id);
                $this->sendNotification($data['assignee_id'], $newItem, 'New Task Assigned');
            }
        }
        
        return ['success' => true, 'id' => $id];
    }

    private function deleteItem($id) {
        $table = self::TABLE_NAME;
        $sql = "DELETE FROM `{$table}` WHERE id = ?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$id]);
        return ['success' => true];
    }

    private function updateStatus($id, $status) {
        $table = self::TABLE_NAME;
        
        // Get old item for change detection
        $oldItem = $this->getItem($id);
        
        $resolvedAt = $status === 'done' ? time() : null;
        if ($oldItem['status'] === 'done' && $status === 'done') {
            $resolvedAt = $oldItem['resolved_at'];
        }
        
        $sql = "UPDATE `{$table}` SET status=?, modified=?, resolved_at=? WHERE id=?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$status, time(), $resolvedAt, $id]);
        
        // Send notification if status changed
        if ($oldItem['status'] != $status && $oldItem['assignee_id']) {
            $currentItem = $this->getItem($id);
            $statusLabels = ['backlog' => 'Backlog', 'progress' => 'In Progress', 'review' => 'Review', 'done' => 'Done'];
            $oldStatus = $statusLabels[$oldItem['status']] ?? $oldItem['status'];
            $newStatus = $statusLabels[$status] ?? $status;
            $this->sendNotification($oldItem['assignee_id'], $currentItem, 'Status Changed', "Changed from <strong>{$oldStatus}</strong> to <strong>{$newStatus}</strong>");
        }
        
        return ['success' => true];
    }

    // =========================================================================
    // PAGE PUBLISH METHODS
    // =========================================================================

    private function publishPage($itemId) {
        $item = $this->getItem($itemId);
        if (!$item || empty($item['linked_page_id'])) {
            return ['success' => false, 'error' => 'No linked page'];
        }
        
        $page = $this->wire('pages')->get($item['linked_page_id']);
        if (!$page->id) {
            return ['success' => false, 'error' => 'Page not found'];
        }
        
        $page->of(false);
        $page->removeStatus(Page::statusUnpublished);
        $page->save();
        
        return ['success' => true];
    }

    private function unpublishPage($itemId) {
        $item = $this->getItem($itemId);
        if (!$item || empty($item['linked_page_id'])) {
            return ['success' => false, 'error' => 'No linked page'];
        }
        
        $page = $this->wire('pages')->get($item['linked_page_id']);
        if (!$page->id) {
            return ['success' => false, 'error' => 'Page not found'];
        }
        
        $page->of(false);
        $page->addStatus(Page::statusUnpublished);
        $page->save();
        
        return ['success' => true];
    }

    // =========================================================================
    // DRAFT FIELD METHODS
    // =========================================================================
    
    /**
     * Get draft field value for a planning item
     */
    private function getDraftFieldValue($itemId, $fieldName) {
        $table = self::DRAFT_TABLE;
        $sql = "SELECT field_value FROM `{$table}` WHERE planning_item_id = ? AND field_name = ?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$itemId, $fieldName]);
        $result = $query->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['field_value'] : null;
    }
    
    /**
     * Save draft field value for a planning item
     */
    private function saveDraftFieldValue($itemId, $fieldName, $value, $fieldType = '') {
        $table = self::DRAFT_TABLE;
        $now = time();
        
        // Check if draft already exists
        $existing = $this->getDraftFieldValue($itemId, $fieldName);
        
        if ($existing !== null) {
            // Update existing draft
            $sql = "UPDATE `{$table}` SET field_value=?, field_type=?, modified=? WHERE planning_item_id=? AND field_name=?";
            $query = $this->wire()->database->prepare($sql);
            $query->execute([$value, $fieldType, $now, $itemId, $fieldName]);
        } else {
            // Insert new draft
            $sql = "INSERT INTO `{$table}` (planning_item_id, field_name, field_value, field_type, created, modified) VALUES (?, ?, ?, ?, ?, ?)";
            $query = $this->wire()->database->prepare($sql);
            $query->execute([$itemId, $fieldName, $value, $fieldType, $now, $now]);
        }
        
        return true;
    }
    
    /**
     * Get all draft fields for a planning item
     */
    private function getAllDraftFields($itemId) {
        $table = self::DRAFT_TABLE;
        $sql = "SELECT * FROM `{$table}` WHERE planning_item_id = ?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$itemId]);
        $results = $query->fetchAll(\PDO::FETCH_ASSOC);
        
        $fields = [];
        foreach ($results as $row) {
            $fields[$row['field_name']] = $row['field_value'];
        }
        return $fields;
    }
    
    /**
     * Render page fields editor
     */
    private function renderPageFields($pageId, $planningItemId) {
        if (!$pageId) return '';
        
        $page = $this->wire('pages')->get($pageId);
        if (!$page->id) return '<div class="field-editor-error">Page not found</div>';
        
        $template = $page->template;
        $out = "<div class='page-fields-editor'>";
        $out .= "<h4>Editing Fields: {$this->wire('sanitizer')->entities($page->title)}</h4>";
        $out .= "<div class='fields-container'>";
        
        foreach ($template->fields as $field) {
            // Skip system fields
            if (in_array($field->name, ['id', 'name', 'parent', 'template', 'status', 'created', 'modified', 'createdUser', 'modifiedUser', 'sort'])) {
                continue;
            }
            
            // Load draft value or current page value
            $draftValue = $this->getDraftFieldValue($planningItemId, $field->name);
            $currentValue = $page->get($field->name);
            $value = $draftValue !== null ? $draftValue : $currentValue;
            
            // Render field input based on type
            $out .= $this->renderFieldInput($field, $value, $planningItemId);
        }
        
        $out .= "</div></div>";
        return $out;
    }
    
    /**
     * Render individual field input based on type
     */
    private function renderFieldInput($field, $value, $planningItemId) {
        $fieldType = $field->type->className();
        $fieldName = $field->name;
        $fieldLabel = $this->wire('sanitizer')->entities($field->label ?: $field->name);
        $fieldId = "draft-field-{$fieldName}";
        
        $out = "<div class='draft-field' data-field='{$fieldName}' data-item='{$planningItemId}'>";
        $out .= "<label for='{$fieldId}'>{$fieldLabel}</label>";
        
        switch ($fieldType) {
            case 'FieldtypeText':
                $val = $this->wire('sanitizer')->entities($value);
                $out .= "<input type='text' id='{$fieldId}' name='{$fieldName}' value='{$val}' class='draft-input'>";
                break;
                
            case 'FieldtypeTextarea':
                $val = $this->wire('sanitizer')->entities($value);
                $out .= "<textarea id='{$fieldId}' name='{$fieldName}' rows='4' class='draft-input'>{$val}</textarea>";
                break;
                
            case 'FieldtypeDatetime':
                $val = $value ? date('Y-m-d', (int)$value) : '';
                $out .= "<input type='date' id='{$fieldId}' name='{$fieldName}' value='{$val}' class='draft-input'>";
                break;
                
            case 'FieldtypeCheckbox':
                $checked = $value ? 'checked' : '';
                $out .= "<input type='checkbox' id='{$fieldId}' name='{$fieldName}' {$checked} class='draft-input'>";
                break;
                
            case 'FieldtypeURL':
                $val = $this->wire('sanitizer')->entities($value);
                $out .= "<input type='url' id='{$fieldId}' name='{$fieldName}' value='{$val}' class='draft-input'>";
                break;
                
            case 'FieldtypeEmail':
                $val = $this->wire('sanitizer')->entities($value);
                $out .= "<input type='email' id='{$fieldId}' name='{$fieldName}' value='{$val}' class='draft-input'>";
                break;
                
            default:
                // For complex types (images, files, page references), show readonly
                $displayVal = is_object($value) ? get_class($value) : (string)$value;
                $out .= "<div class='field-readonly'>{$this->wire('sanitizer')->entities(substr($displayVal, 0, 100))}</div>";
                $out .= "<small>This field type ({$fieldType}) can only be edited in the page editor.</small>";
                break;
        }
        
        $out .= "<button type='button' class='save-draft-btn ui-button' data-field='{$fieldName}' data-item='{$planningItemId}'>Save Draft</button>";
        $out .= "</div>";
        
        return $out;
    }
    
    /**
     * Publish draft changes to actual page
     */
    private function publishDraftChanges($itemId) {
        $item = $this->getItem($itemId);
        if (!$item || empty($item['linked_page_id'])) {
            return ['success' => false, 'error' => 'No linked page'];
        }
        
        $page = $this->wire('pages')->get($item['linked_page_id']);
        if (!$page->id) {
            return ['success' => false, 'error' => 'Page not found'];
        }
        
        $draftFields = $this->getAllDraftFields($itemId);
        if (empty($draftFields)) {
            return ['success' => false, 'error' => 'No draft changes to publish'];
        }
        
        $page->of(false);
        
        foreach ($draftFields as $fieldName => $value) {
            if ($page->template->hasField($fieldName)) {
                $field = $page->template->fieldgroup->getField($fieldName);
                $fieldType = $field->type->className();
                
                // Convert value based on field type
                if ($fieldType === 'FieldtypeDatetime') {
                    $value = strtotime($value);
                } elseif ($fieldType === 'FieldtypeCheckbox') {
                    $value = $value ? 1 : 0;
                }
                
                $page->set($fieldName, $value);
            }
        }
        
        $page->save();
        
        // Clear draft fields after publishing
        $table = self::DRAFT_TABLE;
        $sql = "DELETE FROM `{$table}` WHERE planning_item_id = ?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$itemId]);
        
        return ['success' => true, 'message' => 'Draft changes published successfully'];
    }

    // =========================================================================
    // EMAIL NOTIFICATION METHODS
    // =========================================================================
    
    /**
     * Send email notification to user
     */
    private function sendNotification($userId, $item, $changeType, $additionalInfo = '') {
        if (!$userId) return;
        
        $user = $this->wire('users')->get($userId);
        if (!$user->id || !$user->email) {
            $this->wire()->log->save('planning-notifications', "Cannot send notification: User {$userId} has no email");
            return;
        }
        
        $itemUrl = $this->wire('pages')->get('template=admin')->httpUrl . 'content-planning/';
        $subject = "Plan & Bugs: {$changeType} - {$item['title']}";
        
        $body = "
        <html>
        <body style='font-family: sans-serif;'>
            <h2>{$changeType}</h2>
            <p><strong>Item:</strong> {$item['title']}</p>
            <p><strong>Type:</strong> {$item['item_type']}</p>
            <p><strong>Status:</strong> {$item['status']}</p>
            <p><strong>Priority:</strong> {$item['priority']}</p>";
        
        if ($additionalInfo) {
            $body .= "<p>{$additionalInfo}</p>";
        }
        
        $body .= "
            <p><a href='{$itemUrl}'>View Plan &amp; Bugs Dashboard</a></p>
        </body>
        </html>";
        
        try {
            $mail = $this->wire('mail')->new();
            $mail->to($user->email);
            $mail->subject($subject);
            $mail->bodyHTML($body);
            $sent = $mail->send();
            
            if ($sent) {
                $this->wire()->log->save('planning-notifications', "Sent '{$changeType}' notification to {$user->email} for item #{$item['id']}");
                
                // Update last_notified timestamp
                $table = self::TABLE_NAME;
                $sql = "UPDATE `{$table}` SET last_notified=? WHERE id=?";
                $query = $this->wire()->database->prepare($sql);
                $query->execute([time(), $item['id']]);
            } else {
                $this->wire()->log->save('planning-notifications', "Failed to send notification to {$user->email}");
            }
        } catch(\Exception $e) {
            $this->wire()->log->save('planning-notifications', "Error sending notification: " . $e->getMessage());
        }
    }

    // =========================================================================
    // GITHUB METHODS
    // =========================================================================

    private function createGitHubIssueForItem($id) {
        $item = $this->getItem($id);
        if (!$item) return ['success' => false, 'error' => 'Item not found'];
        if ($item['github_issue_url']) return ['success' => false, 'error' => 'Issue already exists'];
        
        $token = $this->wire('config')->githubToken ?? '';
        $repo = $this->wire('config')->githubRepo ?? '';
        
        if (!$token) {
            return ['success' => false, 'error' => 'GitHub token not configured. Set $config->githubToken in site/config.php'];
        }
        if (!$repo) {
            return ['success' => false, 'error' => 'GitHub repo not configured. Set $config->githubRepo in site/config.php'];
        }
        
        $issueData = [
            'title' => $item['title'],
            'body' => sprintf(
                "## Planning Item\n\n%s\n\n---\n**Type**: %s\n**Priority**: %s",
                $item['details'] ?: 'No details',
                $item['item_type'],
                $item['priority']
            ),
            'labels' => [$item['item_type']],
        ];
        
        $ch = curl_init("https://api.github.com/repos/{$repo}/issues");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: ProcessWire-Planning',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($issueData),
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode === 201) {
            $issue = json_decode($response, true);
            $table = self::TABLE_NAME;
            $sql = "UPDATE `{$table}` SET github_issue_url=?, github_issue_number=? WHERE id=?";
            $query = $this->wire()->database->prepare($sql);
            $query->execute([$issue['html_url'], $issue['number'], $id]);
            
            return ['success' => true, 'url' => $issue['html_url'], 'number' => $issue['number']];
        }
        
        $error = json_decode($response, true);
        $errorMsg = $error['message'] ?? 'Unknown error';
        
        // Log detailed error
        $this->wire()->log->save('github-planning', "GitHub API Error (HTTP {$httpCode}): {$errorMsg} | Response: {$response} | Curl Error: {$curlError}");
        
        // Return user-friendly message
        if ($httpCode === 401) {
            return ['success' => false, 'error' => 'Bad credentials: GitHub token is invalid or expired. Check token in config.php'];
        } elseif ($httpCode === 404) {
            return ['success' => false, 'error' => "Repo not found: {$repo}. Check githubRepo in config.php"];
        } elseif ($httpCode === 403) {
            return ['success' => false, 'error' => 'Permission denied: Token needs "Issues: Read and write" permission'];
        }
        
        return ['success' => false, 'error' => "GitHub API error ({$httpCode}): {$errorMsg}"];
    }

    private function fetchGitHubIssues() {
        $repo = $this->wire('config')->githubRepo ?? '';
        if (!$repo) return [];
        
        $cache = $this->wire('cache');
        $cacheKey = 'planning_github_issues';
        $cached = $cache->get($cacheKey);
        if ($cached !== null) return $cached;
        
        $token = $this->wire('config')->githubToken ?? '';
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: ProcessWire-Planning',
        ];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        
        // Fetch all open issues (no label filter to show all)
        $ch = curl_init("https://api.github.com/repos/{$repo}/issues?state=open&per_page=20");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            // Log error for debugging
            $this->wire()->log->save('github-planning', "Failed to fetch issues (HTTP {$httpCode}): {$response}");
            return [];
        }
        
        $issues = json_decode($response, true);
        if (!is_array($issues)) return [];
        
        $result = [];
        
        foreach ($issues as $issue) {
            if (isset($issue['pull_request'])) continue;
            
            $labels = [];
            foreach ($issue['labels'] as $label) {
                $labels[] = $label['name'];
            }
            
            $result[] = [
                'number' => $issue['number'],
                'title' => $issue['title'],
                'url' => $issue['html_url'],
                'labels' => $labels,
                'time' => $this->timeAgo(strtotime($issue['created_at'])),
            ];
        }
        
        $cache->save($cacheKey, $result, 300);
        return $result;
    }

    private function fetchDocsCommits() {
        $repo = $this->wire('config')->githubDocsRepo ?? '';
        if (!$repo) return [];
        
        $cache = $this->wire('cache');
        $cacheKey = 'planning_docs_commits';
        $cached = $cache->get($cacheKey);
        if ($cached !== null) return $cached;
        
        $token = $this->wire('config')->githubToken ?? '';
        $headers = [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'User-Agent: ProcessWire-Planning',
        ];
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
        
        $ch = curl_init("https://api.github.com/repos/{$repo}/commits?per_page=10");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return [];
        
        $commits = json_decode($response, true);
        $result = [];
        
        foreach ($commits as $c) {
            $date = strtotime($c['commit']['author']['date']);
            $result[] = [
                'message' => explode("\n", $c['commit']['message'])[0],
                'author' => $c['commit']['author']['name'],
                'time' => $this->timeAgo($date),
                'url' => $c['html_url'],
            ];
        }
        
        $cache->save($cacheKey, $result, 300);
        return $result;
    }

    private function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 60) return 'just now';
        if ($diff < 3600) return floor($diff/60) . 'm ago';
        if ($diff < 86400) return floor($diff/3600) . 'h ago';
        if ($diff < 604800) return floor($diff/86400) . 'd ago';
        return date('M j', $timestamp);
    }

    public function ___install() {
        parent::___install();
        $this->createDatabaseTable();
    }

    public function ___uninstall() {
        parent::___uninstall();
    }
}
