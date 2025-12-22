document.addEventListener('DOMContentLoaded', function () {
  const launcher = document.getElementById('portal-export-launcher');
  const modalEl = document.getElementById('portalExportModal');
  const runBtn = document.getElementById('portalExportRun');
  const resultBox = document.getElementById('portalExportResult');

  if (!launcher || !modalEl) return;

  // Bootstrap modal
  let bsModal = null;
  try {
    bsModal = new bootstrap.Modal(modalEl);
  } catch (e) {
    // bootstrap not available yet; fallback to simple toggle
  }

  launcher.addEventListener('click', function () {
    if (bsModal) bsModal.show();
    else modalEl.style.display = 'block';
  });

  runBtn.addEventListener('click', async function () {
    runBtn.disabled = true;
    resultBox.style.display = 'none';
    resultBox.innerHTML = '';

    const portal = document.getElementById('portalSelect').value;
    const format = document.getElementById('formatSelect').value;
    const limit = parseInt(document.getElementById('limitInput').value || '500', 10);
    const endpoint = document.getElementById('endpointInput').value || '';
    const apiKey = document.getElementById('apiKeyInput').value || '';
    const push = document.getElementById('pushCheckbox').checked ? 1 : 0;

    const payload = {
      portal, format, limit, endpoint, api_key: apiKey, push
    };

    try {
      const resp = await fetch('/api/runPortalExport.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
      });

      const data = await resp.json();
      resultBox.style.display = 'block';
      let html = '';
      if (data.success) {
        html += `<div class="alert alert-success">${data.message || 'Export realizado com sucesso'}</div>`;
        if (data.details) html += `<pre style="white-space:pre-wrap;">${escapeHtml(JSON.stringify(data.details, null, 2))}</pre>`;
      } else {
        html += `<div class="alert alert-danger">Erro: ${data.error || 'Falha ao executar export'}</div>`;
        if (data.details) html += `<pre style="white-space:pre-wrap;">${escapeHtml(JSON.stringify(data.details, null, 2))}</pre>`;
      }
      resultBox.innerHTML = html;

    } catch (err) {
      resultBox.style.display = 'block';
      resultBox.innerHTML = `<div class="alert alert-danger">Erro ao contatar servidor: ${err.message}</div>`;
    } finally {
      runBtn.disabled = false;
    }
  });

  function escapeHtml(s) {
    return (s+'').replace(/[&<>\"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; });
  }
});
