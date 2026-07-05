/**
 * Shell CSS, extracted verbatim from the old visual-editor.php <style> block.
 * Injected by main.ts so the PHP bootstrap stays small. The only removals are
 * the page-picker rules (.ve-page-nav / .ve-page-search / .ve-page-list /
 * .ve-page-item / .ve-page-count) — that feature is gone by design.
 * One addition: .ve-config-editor spacing for the sidebar config editor.
 */

export const SHELL_CSS = `
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
    background: #111827;
    color: #e5e7eb;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.ve-toolbar {
    align-items: center;
    background: #0f172a;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 12px;
    padding: 10px 14px;
    z-index: 10;
}
.ve-toolbar-logo {
    color: #8ab272;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}
.ve-toolbar button,
.ve-toolbar a,
.ve-field-editor input,
.ve-field-editor select,
.ve-field-editor textarea {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #e5e7eb;
    font: inherit;
}
.ve-toolbar-spacer {
    flex: 1;
}
.ve-toolbar-actions {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.ve-btn {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 12px;
    text-decoration: none;
    transition: background 0.15s, border-color 0.15s, opacity 0.15s;
}
.ve-btn:hover { background: #1f2937; }
.ve-btn-primary {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
.ve-btn-primary:hover { background: #3f6c4e; }
.ve-btn-danger {
    background: #7f1d1d;
    border-color: #7f1d1d;
    color: #fff;
}
.ve-btn-danger:hover { background: #991b1b; }
.ve-btn:disabled { cursor: not-allowed; opacity: 0.55; }
.ve-status {
    background: #1f2937;
    border-radius: 999px;
    font-size: 11px;
    padding: 4px 10px;
    white-space: nowrap;
}
.ve-status.is-ready { background: #17321f; color: #9ae6b4; }
.ve-status.is-loading { background: #3b2f17; color: #f6e05e; }
.ve-status.is-error { background: #3b1717; color: #feb2b2; }
.ve-mode-switch {
    display: flex;
    gap: 6px;
}
.ve-mode-btn.is-active {
    background: #4a7c59;
    border-color: #4a7c59;
    color: #fff;
}
.ve-main {
    display: flex;
    flex: 1;
    min-height: 0;
}
.ve-sidebar {
    background: #0f172a;
    border-right: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    width: 430px;
}
.ve-sidebar-header {
    align-items: flex-start;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 12px 14px;
}
.ve-sidebar-page {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}
.ve-sidebar-kicker {
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.ve-sidebar-title {
    font-size: 13px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-sidebar-path {
    color: #94a3b8;
    font-size: 11px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-section-list-wrap {
    border-bottom: 1px solid #1f2937;
    max-height: 34%;
    min-height: 160px;
    overflow-y: auto;
}
.ve-section-list {
    list-style: none;
}
.ve-section-item {
    align-items: center;
    border-left: 3px solid transparent;
    border-bottom: 1px solid #1f2937;
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px 14px;
}
.ve-section-item:hover { background: #111827; }
.ve-section-item.is-active {
    background: #111827;
    border-left-color: #4a7c59;
}
.ve-section-drag {
    color: #64748b;
    cursor: grab;
    font-size: 15px;
    user-select: none;
}
.ve-section-info {
    flex: 1;
    min-width: 0;
}
.ve-section-title {
    font-size: 13px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-section-meta {
    color: #94a3b8;
    display: flex;
    gap: 4px;
    margin-top: 3px;
}
.ve-layout-badge {
    background: #1e293b;
    border-radius: 999px;
    font-size: 10px;
    padding: 2px 7px;
}
.ve-section-actions {
    display: flex;
    gap: 4px;
}
.ve-icon-btn {
    align-items: center;
    background: transparent;
    border: none;
    border-radius: 6px;
    color: #94a3b8;
    cursor: pointer;
    display: inline-flex;
    height: 28px;
    justify-content: center;
    width: 28px;
}
.ve-icon-btn:hover {
    background: #1f2937;
    color: #e5e7eb;
}
.ve-field-editor {
    display: flex;
    flex: 1;
    flex-direction: column;
    min-height: 0;
}
.ve-editor-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: 14px;
}
.ve-empty-state {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
    padding: 18px 14px;
}
.ve-field-group {
    margin-bottom: 14px;
}
.ve-field-group label {
    color: #94a3b8;
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 5px;
    text-transform: uppercase;
}
.ve-field-group input,
.ve-field-group select,
.ve-field-group textarea {
    padding: 8px 10px;
    width: 100%;
}
.ve-field-group textarea {
    min-height: 110px;
    resize: vertical;
}
.ve-form-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.ve-form-grid .ve-field-group-full {
    grid-column: 1 / -1;
}
.ve-actions-bar {
    border-top: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 14px;
}
.ve-help {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 4px;
}
.ve-dirty-pill {
    background: #3b2f17;
    border-radius: 999px;
    color: #f6e05e;
    font-size: 10px;
    margin-left: 6px;
    padding: 2px 6px;
}
.ve-iframe-wrap {
    background: #fff;
    flex: 1;
    min-width: 0;
    position: relative;
}
.ve-iframe-wrap iframe {
    border: none;
    height: 100%;
    width: 100%;
}
.ve-info-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    padding: 12px;
}
.ve-info-card + .ve-info-card {
    margin-top: 12px;
}
.ve-info-card strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-info-card p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
.ve-media-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 30;
}
.ve-media-modal.is-open {
    display: flex;
}
.ve-media-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 460px;
    width: 100%;
}
.ve-media-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    justify-content: space-between;
    padding: 14px;
}
.ve-media-grid {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    overflow-y: auto;
    padding: 14px;
}
.ve-preset-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 40;
}
.ve-preset-modal.is-open {
    display: flex;
}
.ve-preset-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 520px;
    width: 100%;
}
.ve-preset-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 14px;
}
.ve-preset-controls {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 160px;
    padding: 12px 14px;
}
.ve-preset-list {
    display: grid;
    gap: 10px;
    overflow-y: auto;
    padding: 0 14px 14px;
}
.ve-preset-item {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 10px;
    padding: 10px;
}
.ve-preset-item strong {
    display: block;
    font-size: 13px;
}
.ve-preset-item p {
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.45;
    margin-top: 6px;
}
.ve-preset-item .ve-inline-actions {
    margin-top: 10px;
}
.ve-add-modal {
    align-items: stretch;
    background: rgba(15, 23, 42, 0.72);
    display: none;
    inset: 0;
    justify-content: flex-end;
    position: fixed;
    z-index: 41;
}
.ve-add-modal.is-open {
    display: flex;
}
.ve-add-panel {
    background: #0f172a;
    border-left: 1px solid #1f2937;
    display: flex;
    flex-direction: column;
    height: 100%;
    max-width: 560px;
    width: 100%;
}
.ve-add-header {
    align-items: center;
    border-bottom: 1px solid #1f2937;
    display: flex;
    gap: 8px;
    justify-content: space-between;
    padding: 14px;
}
.ve-add-controls {
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr 160px;
    padding: 12px 14px;
}
.ve-add-scroll {
    overflow-y: auto;
    padding: 0 14px 14px;
}
.ve-add-group-label {
    color: #94a3b8;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    margin: 16px 0 8px;
    text-transform: uppercase;
}
.ve-add-group-label:first-child {
    margin-top: 4px;
}
.ve-add-grid {
    display: grid;
    gap: 6px;
    grid-template-columns: 1fr 1fr;
}
.ve-add-card {
    align-items: flex-start;
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    gap: 10px;
    padding: 10px;
    transition: border-color 0.15s;
}
.ve-add-card:hover {
    border-color: #4a7c59;
}
.ve-add-icon {
    align-items: center;
    background: #1e293b;
    border-radius: 6px;
    color: #94a3b8;
    display: flex;
    flex-shrink: 0;
    font-size: 15px;
    height: 32px;
    justify-content: center;
    width: 32px;
}
.ve-add-card:hover .ve-add-icon {
    background: #4a7c59;
    color: #e5e7eb;
}
.ve-add-text {
    flex: 1;
    min-width: 0;
}
.ve-add-label {
    font-size: 12px;
    font-weight: 600;
}
.ve-add-desc {
    color: #64748b;
    font-size: 10px;
    line-height: 1.4;
    margin-top: 2px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.ve-media-card {
    background: #111827;
    border: 1px solid #1f2937;
    border-radius: 12px;
    cursor: pointer;
    overflow: hidden;
    padding: 0;
    text-align: left;
}
.ve-media-card img {
    display: block;
    height: 120px;
    object-fit: cover;
    width: 100%;
}
.ve-media-card-body {
    padding: 10px;
}
.ve-media-card-body strong {
    display: block;
    font-size: 12px;
    margin-bottom: 4px;
}
.ve-media-card-body span {
    color: #94a3b8;
    display: block;
    font-size: 11px;
}
.ve-busy-overlay {
    align-items: center;
    background: rgba(15, 23, 42, 0.78);
    display: none;
    inset: 0;
    justify-content: center;
    position: fixed;
    z-index: 80;
}
.ve-busy-overlay.is-visible {
    display: flex;
}
.ve-busy-dialog {
    align-items: center;
    background: #0f172a;
    border: 1px solid #334155;
    border-radius: 18px;
    box-shadow: 0 28px 70px rgba(15, 23, 42, 0.45);
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 360px;
    padding: 28px 24px;
    text-align: center;
    width: calc(100vw - 32px);
}
.ve-busy-dialog strong {
    font-size: 18px;
    font-weight: 700;
}
.ve-busy-dialog p {
    color: #94a3b8;
    font-size: 13px;
    line-height: 1.5;
}
.ve-busy-spinner {
    animation: ve-spin 0.9s linear infinite;
    border: 5px solid rgba(148, 163, 184, 0.22);
    border-radius: 999px;
    border-top-color: #8ab272;
    height: 54px;
    width: 54px;
}
@keyframes ve-spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.ve-ownership-header {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 8px 0 5px;
    border-bottom: 1px solid #1f2937;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.ve-ownership-header::before {
    content: '';
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 999px;
    flex-shrink: 0;
}
.ve-ownership-ve { color: #8ab272; }
.ve-ownership-ve::before { background: #4a7c59; }
.ve-ownership-pw { color: #f59e0b; margin-top: 10px; }
.ve-ownership-pw::before { background: #b45309; }
.ve-ownership-list {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-bottom: 4px;
}
.ve-ownership-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 5px 8px;
    background: #111827;
    border-radius: 6px;
    font-size: 12px;
    gap: 8px;
}
.ve-ownership-item-label { color: #e5e7eb; flex-shrink: 0; }
.ve-ownership-item-hint { color: #4b5563; font-size: 10px; text-align: right; flex: 1; }
.ve-ownership-pw-btn {
    background: #1c2030;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #f59e0b;
    cursor: pointer;
    font: inherit;
    font-size: 10px;
    padding: 3px 7px;
    white-space: nowrap;
    flex-shrink: 0;
}
.ve-ownership-pw-btn:hover { background: #232b3e; border-color: #f59e0b; }
.ve-ownership-pw-btn:disabled { cursor: not-allowed; opacity: 0.45; }
.ve-config-editor {
    margin-top: 6px;
    margin-bottom: 4px;
}
.ve-config-editor .ve-field-group {
    margin-bottom: 10px;
}
.ve-collection-add {
    align-items: end;
    display: grid;
    gap: 8px;
    grid-template-columns: 1fr auto;
    margin-bottom: 12px;
}
.ve-collection-add label {
    color: #94a3b8;
    display: block;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.04em;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.ve-collection-add input[type="date"] {
    background: #111827;
    border: 1px solid #334155;
    border-radius: 8px;
    color: #e5e7eb;
    font: inherit;
    padding: 7px 10px;
    width: 100%;
}
.ve-collection-add .ve-btn { white-space: nowrap; }
.ve-collection-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
`
