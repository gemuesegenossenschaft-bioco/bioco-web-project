<?php namespace ProcessWire;

/**
 * Process Content Planning Module v2
 * 
 * Dashboard for planning web content with list/kanban views,
 * GitHub issue creation, page linking, publish control, and docs feed.
 */

class ProcessContentPlanning extends Process {

    const TABLE_NAME = 'planning_items';

    public static function getModuleInfo() {
        return [
            'title' => 'Content Planning',
            'version' => 200,
            'summary' => 'Plan content with list/kanban views, page linking, GitHub issues',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'content-planning',
                'parent' => 'page',  // Under Pages, shows at top
                'title' => 'Content Planning',
                'icon' => 'calendar-check-o'
            ],
            'nav' => [
                [
                    'url' => 'content-planning',
                    'label' => 'Content Planning',
                    'icon' => 'calendar-check-o',
                    'navJSON' => 'navJSON/"{"url":"content-planning","label":"Content Planning","icon":"calendar-check-o"}"'
                ]
            ]
        ];
    }

    public function init() {
        parent::init();
        $this->createDatabaseTable();
        $this->migrateDatabase();
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
     * Header with docs link and view toggle
     */
    private function renderHeader($currentView) {
        $listActive = $currentView === 'list' ? 'active' : '';
        $kanbanActive = $currentView === 'kanban' ? 'active' : '';
        $baseUrl = $this->wire('page')->url;
        
        return "
        <div class='planning-header'>
            <a href='https://docs.bioco.ch' target='_blank' class='docs-link'>
                <i class='fa fa-external-link'></i> docs.bioco.ch
            </a>
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
            <th>Type</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Page</th>
            <th>Created</th>
            <th>Actions</th>
        </tr></thead><tbody>";
        
        foreach ($items as $item) {
            $pageLink = $this->renderPageLink($item);
            $createdDate = $this->timeAgo($item['created']);
            $resolvedBadge = $item['resolved_at'] ? "<span class='resolved-badge'>Resolved " . $this->timeAgo($item['resolved_at']) . "</span>" : '';
            
            $out .= "<tr data-id='{$item['id']}'>
                <td>
                    <strong>{$this->wire('sanitizer')->entities($item['title'])}</strong>
                    {$resolvedBadge}
                </td>
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
                <td>{$pageLink}</td>
                <td class='date-cell'>{$createdDate}</td>
                <td class='actions-cell'>
                    <button class='edit-btn ui-button' data-id='{$item['id']}'><i class='fa fa-pencil'></i></button>
                    <button class='delete-btn ui-button' data-id='{$item['id']}'><i class='fa fa-trash'></i></button>
                    " . $this->renderGitHubButton($item) . "
                    " . $this->renderPublishButton($item) . "
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
        
        return "
        <div class='kanban-card' data-id='{$item['id']}'>
            <div class='card-header'>
                <span class='badge type-{$item['item_type']}'>{$item['item_type']}</span>
                <span class='badge priority-{$item['priority']}'>{$item['priority']}</span>
            </div>
            <div class='card-title'>{$this->wire('sanitizer')->entities($item['title'])}</div>
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
     * Get all CMS pages for dropdown
     */
    private function getPageOptions() {
        $pages = $this->wire('pages')->find("template!=admin, include=all, limit=500, sort=path");
        $options = ['<option value="">-- Select Page --</option>'];
        
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
    private function getUserOptions() {
        $users = $this->wire('users')->find("roles!=guest, limit=100");
        $options = ['<option value="">-- Select Owner --</option>'];
        
        foreach ($users as $user) {
            $name = $this->wire('sanitizer')->entities($user->name);
            $options[] = "<option value='{$user->id}'>{$name}</option>";
        }
        
        return implode("\n", $options);
    }

    /**
     * Get owner name from item
     */
    private function getOwnerName($item) {
        if (!empty($item['owner_id'])) {
            $user = $this->wire('users')->get($item['owner_id']);
            if ($user->id) {
                return $this->wire('sanitizer')->entities($user->name);
            }
        }
        return $this->wire('sanitizer')->entities($item['owner'] ?: '-');
    }

    /**
     * Add/Edit form with page selector
     */
    private function renderAddForm() {
        $pageOptions = $this->getPageOptions();
        $userOptions = $this->getUserOptions();
        
        return "
        <div id='item-form-overlay' class='form-overlay' style='display:none;'>
            <div class='form-modal'>
                <h3 id='form-title'>Add Item</h3>
                <form id='item-form'>
                    <input type='hidden' name='id' id='item-id' value=''>
                    <div class='form-row'>
                        <label>Title *</label>
                        <input type='text' name='title' id='item-title' required>
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
                        <label>Owner</label>
                        <select name='owner_id' id='item-owner'>
                            {$userOptions}
                        </select>
                    </div>
                    <div class='form-row'>
                        <label>Linked Page</label>
                        <div class='page-selector'>
                            <select name='linked_page_id' id='item-page'>
                                {$pageOptions}
                            </select>
                            <a href='#' id='edit-page-link' class='edit-page-btn' target='_blank' style='display:none;'>
                                <i class='fa fa-external-link'></i>
                            </a>
                        </div>
                    </div>
                    <div class='form-row'>
                        <label>Details</label>
                        <textarea name='details' id='item-details' rows='4'></textarea>
                    </div>
                    <div class='form-actions'>
                        <button type='button' class='ui-button cancel-btn'>Cancel</button>
                        <button type='submit' class='ui-button ui-priority-primary'>Save</button>
                    </div>
                </form>
            </div>
        </div>
        <button id='add-item-btn' class='ui-button ui-priority-primary'>
            <i class='fa fa-plus'></i> Add Item
        </button>";
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
            
            // Page selector edit link
            const pageSelect = document.getElementById('item-page');
            const editPageLink = document.getElementById('edit-page-link');
            
            pageSelect.onchange = function() {
                if (this.value) {
                    editPageLink.href = adminUrl + 'page/edit/?id=' + this.value;
                    editPageLink.style.display = 'inline-flex';
                } else {
                    editPageLink.style.display = 'none';
                }
            };
            
            // Add button
            document.getElementById('add-item-btn').onclick = function() {
                document.getElementById('form-title').textContent = 'Add Item';
                document.getElementById('item-form').reset();
                document.getElementById('item-id').value = '';
                document.getElementById('item-page').value = '';
                editPageLink.style.display = 'none';
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
                            document.getElementById('item-type').value = item.item_type;
                            document.getElementById('item-priority').value = item.priority;
                            document.getElementById('item-status').value = item.status;
                            document.getElementById('item-owner').value = item.owner_id || '';
                            document.getElementById('item-page').value = item.linked_page_id || '';
                            document.getElementById('item-details').value = item.details || '';
                            
                            if (item.linked_page_id) {
                                editPageLink.href = adminUrl + 'page/edit/?id=' + item.linked_page_id;
                                editPageLink.style.display = 'inline-flex';
                            } else {
                                editPageLink.style.display = 'none';
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
            'item_type' => $san->name($input->post('item_type')) ?: 'content',
            'details' => $san->textarea($input->post('details')),
            'priority' => $san->name($input->post('priority')) ?: 'medium',
            'status' => $newStatus,
            'owner_id' => (int)$input->post('owner_id') ?: null,
            'linked_page_id' => (int)$input->post('linked_page_id') ?: null,
            'modified' => time(),
        ];
        
        if (empty($data['title'])) {
            return ['success' => false, 'error' => 'Title required'];
        }
        
        $table = self::TABLE_NAME;
        $db = $this->wire()->database;
        
        if ($id) {
            // Check if status changed to done
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
                    title=?, item_type=?, details=?, priority=?, status=?, owner_id=?, linked_page_id=?, modified=?, resolved_at=?
                    WHERE id=?";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner_id'], 
                $data['linked_page_id'], $data['modified'], $resolvedAt, $id
            ]);
        } else {
            $data['created'] = time();
            $resolvedAt = $newStatus === 'done' ? time() : null;
            
            $sql = "INSERT INTO `{$table}` 
                    (title, item_type, details, priority, status, owner_id, linked_page_id, created, modified, resolved_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner_id'],
                $data['linked_page_id'], $data['created'], $data['modified'], $resolvedAt
            ]);
            $id = $db->lastInsertId();
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
        $resolvedAt = $status === 'done' ? time() : null;
        
        // Check current status
        $item = $this->getItem($id);
        if ($item['status'] === 'done' && $status === 'done') {
            $resolvedAt = $item['resolved_at'];
        }
        
        $sql = "UPDATE `{$table}` SET status=?, modified=?, resolved_at=? WHERE id=?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$status, time(), $resolvedAt, $id]);
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
