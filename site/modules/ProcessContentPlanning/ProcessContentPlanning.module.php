<?php namespace ProcessWire;

/**
 * Process Content Planning Module
 * 
 * Minimal dashboard for planning web content with list/kanban views,
 * GitHub issue creation, and docs.bioco.ch change feed.
 */

class ProcessContentPlanning extends Process {

    const TABLE_NAME = 'planning_items';

    public static function getModuleInfo() {
        return [
            'title' => 'Content Planning',
            'version' => 100,
            'summary' => 'Plan content with list/kanban views, create GitHub issues',
            'permission' => 'page-edit',
            'page' => [
                'name' => 'content-planning',
                'parent' => 'setup',
                'title' => 'Content Planning'
            ],
        ];
    }

    public function init() {
        parent::init();
        $this->createDatabaseTable();
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
            `github_issue_url` varchar(255) DEFAULT '',
            `github_issue_number` int(11) DEFAULT NULL,
            `created` int(11) NOT NULL,
            `modified` int(11) NOT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `priority` (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        try {
            $this->wire()->database->exec($sql);
        } catch(\Exception $e) {
            // Table exists
        }
    }

    /**
     * Main execute: render dashboard
     */
    public function ___execute() {
        $this->wire('modules')->get('JqueryUI')->use('vex');
        
        // Load CSS
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
            $out .= "<div class='kanban-column' data-status='{$key}'>";
            $out .= "<div class='column-header'>{$label}</div>";
            $out .= "<div class='column-items'>";
            
            foreach ($items as $item) {
                if ($item['status'] === $key) {
                    $out .= $this->renderCard($item);
                }
            }
            
            $out .= "</div></div>";
        }
        
        $out .= "</div>";
        $out .= $this->renderDocsFeed();
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
            <th>Owner</th>
            <th>GitHub</th>
            <th>Actions</th>
        </tr></thead><tbody>";
        
        foreach ($items as $item) {
            $ghLink = $item['github_issue_url'] 
                ? "<a href='{$item['github_issue_url']}' target='_blank'>#{$item['github_issue_number']}</a>"
                : '<span class="no-issue">-</span>';
            
            $out .= "<tr data-id='{$item['id']}'>
                <td><strong>{$this->wire('sanitizer')->entities($item['title'])}</strong></td>
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
                <td>{$this->wire('sanitizer')->entities($item['owner'])}</td>
                <td>{$ghLink}</td>
                <td>
                    <button class='edit-btn ui-button' data-id='{$item['id']}'><i class='fa fa-pencil'></i></button>
                    <button class='delete-btn ui-button' data-id='{$item['id']}'><i class='fa fa-trash'></i></button>
                    " . ($item['github_issue_url'] ? '' : "<button class='github-btn ui-button' data-id='{$item['id']}'><i class='fa fa-github'></i></button>") . "
                </td>
            </tr>";
        }
        
        $out .= "</tbody></table></div>";
        $out .= $this->renderDocsFeed();
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
        
        return "
        <div class='kanban-card' data-id='{$item['id']}'>
            <div class='card-header'>
                <span class='badge type-{$item['item_type']}'>{$item['item_type']}</span>
                <span class='badge priority-{$item['priority']}'>{$item['priority']}</span>
            </div>
            <div class='card-title'>{$this->wire('sanitizer')->entities($item['title'])}</div>
            <div class='card-footer'>
                {$ghBadge}
                <span class='card-owner'>{$this->wire('sanitizer')->entities($item['owner'])}</span>
            </div>
            <div class='card-actions'>
                <button class='edit-btn' data-id='{$item['id']}'><i class='fa fa-pencil'></i></button>
                <button class='delete-btn' data-id='{$item['id']}'><i class='fa fa-trash'></i></button>
                " . ($item['github_issue_url'] ? '' : "<button class='github-btn' data-id='{$item['id']}'><i class='fa fa-github'></i></button>") . "
            </div>
        </div>";
    }

    /**
     * Docs feed sidebar
     */
    private function renderDocsFeed() {
        $commits = $this->fetchDocsCommits();
        
        $out = "<div class='docs-feed'>";
        $out .= "<div class='feed-header'><i class='fa fa-history'></i> Docs Changes</div>";
        $out .= "<div class='feed-items'>";
        
        if (empty($commits)) {
            $out .= "<div class='feed-empty'>No recent changes</div>";
        } else {
            foreach ($commits as $c) {
                $msg = $this->wire('sanitizer')->entities(substr($c['message'], 0, 60));
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
     * Add/Edit form (hidden, shown via JS)
     */
    private function renderAddForm() {
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
                        <input type='text' name='owner' id='item-owner'>
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
        $ajaxUrl = $this->wire('page')->url;
        return "
        <script>
        (function() {
            const ajaxUrl = '{$ajaxUrl}';
            
            // Add button
            document.getElementById('add-item-btn').onclick = function() {
                document.getElementById('form-title').textContent = 'Add Item';
                document.getElementById('item-form').reset();
                document.getElementById('item-id').value = '';
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
                            document.getElementById('item-owner').value = item.owner || '';
                            document.getElementById('item-details').value = item.details || '';
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
            
            // Status select change
            document.querySelectorAll('.status-select').forEach(sel => {
                sel.onchange = function() {
                    const form = new FormData();
                    form.append('action', 'status');
                    form.append('id', this.dataset.id);
                    form.append('status', this.value);
                    fetch(ajaxUrl, { method: 'POST', body: form })
                        .then(r => r.json());
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
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }

    /**
     * Process POST requests on main page (non-AJAX compatible)
     */
    public function ___executePost() {
        $action = $this->wire('input')->post('action');
        
        if ($action === 'get' || $this->wire('input')->get('action')) {
            return $this->executeAjax();
        }
        
        return $this->executeAjax();
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
        $data = [
            'title' => $san->text($input->post('title')),
            'item_type' => $san->name($input->post('item_type')) ?: 'content',
            'details' => $san->textarea($input->post('details')),
            'priority' => $san->name($input->post('priority')) ?: 'medium',
            'status' => $san->name($input->post('status')) ?: 'backlog',
            'owner' => $san->text($input->post('owner')),
            'modified' => time(),
        ];
        
        if (empty($data['title'])) {
            return ['success' => false, 'error' => 'Title required'];
        }
        
        $table = self::TABLE_NAME;
        $db = $this->wire()->database;
        
        if ($id) {
            $sql = "UPDATE `{$table}` SET 
                    title=?, item_type=?, details=?, priority=?, status=?, owner=?, modified=?
                    WHERE id=?";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner'], $data['modified'], $id
            ]);
        } else {
            $data['created'] = time();
            $sql = "INSERT INTO `{$table}` 
                    (title, item_type, details, priority, status, owner, created, modified)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $query = $db->prepare($sql);
            $query->execute([
                $data['title'], $data['item_type'], $data['details'],
                $data['priority'], $data['status'], $data['owner'],
                $data['created'], $data['modified']
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
        $sql = "UPDATE `{$table}` SET status=?, modified=? WHERE id=?";
        $query = $this->wire()->database->prepare($sql);
        $query->execute([$status, time(), $id]);
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
        
        if (!$token || !$repo) {
            return ['success' => false, 'error' => 'GitHub not configured (set githubToken and githubRepo in config.php)'];
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
        return ['success' => false, 'error' => $error['message'] ?? 'GitHub API error'];
    }

    private function fetchDocsCommits() {
        $repo = $this->wire('config')->githubDocsRepo ?? '';
        if (!$repo) return [];
        
        // Cache for 5 minutes
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

    /**
     * Module install
     */
    public function ___install() {
        parent::___install();
        $this->createDatabaseTable();
    }

    /**
     * Module uninstall
     */
    public function ___uninstall() {
        // Keep table for data preservation
        parent::___uninstall();
    }
}
