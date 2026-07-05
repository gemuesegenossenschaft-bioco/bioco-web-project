/**
 * esbuild entry for the committed bundle site/templates/visual-editor-app.js
 * (npm run build:ve-shell — the server cannot build; the artifact is
 * committed and rsynced by scripts/deploy.sh).
 */

import { bootVisualEditorShell } from './app'

function start() {
  bootVisualEditorShell()
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start)
} else {
  start()
}
