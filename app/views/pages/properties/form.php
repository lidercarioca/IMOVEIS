<?php
/**
 * Properties Form Page
 * Formulário para adicionar/editar imóvel
 */
?>
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col-12">
      <h2>Adicionar Imóvel</h2>
    </div>
  </div>
  
  <div class="row">
    <div class="col-md-8">
      <div class="card">
        <div class="card-body">
          <form id="property-form">
            <div class="mb-3">
              <label class="form-label">Nome do Imóvel</label>
              <input type="text" class="form-control" name="name" required>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Preço</label>
              <input type="number" class="form-control" name="price" step="0.01" required>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Localização</label>
              <input type="text" class="form-control" name="location">
            </div>
            
            <div class="mb-3">
              <label class="form-label">Descrição</label>
              <textarea class="form-control" name="description" rows="4"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">Salvar</button>
            <a href="#properties" class="btn btn-secondary">Cancelar</a>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('property-form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  const data = Object.fromEntries(formData);
  
  fetch('/api/v1/properties/addProperty.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Imóvel criado com sucesso!');
      window.location.hash = '#properties';
    } else {
      alert('Erro: ' + data.message);
    }
  })
  .catch(error => console.error('Erro:', error));
});
</script>
