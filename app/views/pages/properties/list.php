<?php

/**
 * Properties List Page
 * Listagem de imóveis
 */
?>
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
      <h2>Imóveis</h2>
      <a href="#add-property" class="btn btn-primary">Adicionar Imóvel</a>
    </div>
  </div>

  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-body">
          <table class="table table-hover" id="properties-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Preço</th>
                <th>Localização</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody id="properties-tbody">
              <tr>
                <td colspan="5" class="text-center text-muted">Carregando...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  function normalizePropertiesPayload(payload) {
    if (Array.isArray(payload)) {
      return payload;
    }

    if (payload && payload.success && Array.isArray(payload.data)) {
      return payload.data;
    }

    if (payload && Array.isArray(payload.data)) {
      return payload.data;
    }

    console.warn('[Properties List] Payload inválido ao carregar imóveis', payload);
    return [];
  }

  // Carrega lista de imóveis
  fetch('/api/v1/properties/getProperties.php?panel=1')
    .then(response => response.json())
    .then(data => {
      const tbody = document.getElementById('properties-tbody');
      tbody.innerHTML = '';
      const normalized = normalizePropertiesPayload(data);

      if (normalized.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nenhum imóvel encontrado</td></tr>';
        return;
      }

      normalized.forEach(property => {
        const row = tbody.insertRow();
        row.innerHTML = `
        <td>${property.id}</td>
        <td>${property.title || property.name || '-'}</td>
        <td>R$ ${parseFloat(property.price || 0).toLocaleString('pt-BR')}</td>
        <td>${property.location || property.neighborhood || '-'}</td>
        <td>
          <button class="btn btn-sm btn-info">Editar</button>
          <button class="btn btn-sm btn-danger">Deletar</button>
        </td>
      `;
      });
    })
    .catch(error => console.error('Erro ao carregar imóveis:', error));
</script>