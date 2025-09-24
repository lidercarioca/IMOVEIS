// Aguarda o DOM estar pronto
document.addEventListener('DOMContentLoaded', function() {
  // Inicializa logo se estivermos na tab #add-property
  if (window.location.hash === '#add-property') {
    setTimeout(inicializarFormularioImovel, 100);
  }

  // Aguarda a mudança de tab para #add-property
  document.querySelectorAll('.tab-link').forEach(link => {
    link.addEventListener('click', function() {
      if (this.getAttribute('href') === '#add-property') {
        setTimeout(inicializarFormularioImovel, 100);
      }
    });
  });

  // Observa mudanças no hash da URL
  window.addEventListener('hashchange', function() {
    if (window.location.hash === '#add-property') {
      setTimeout(inicializarFormularioImovel, 100);
    }
  });
});

// Função principal que inicializa o evento de envio do formulário
function inicializarFormularioImovel() {
  console.log('inicializarFormularioImovel rodou');
  
  const form = document.getElementById("property-form");
  if (!form) {
    // Observa até o form estar disponível no DOM
    const observer = new MutationObserver(() => {
      const foundForm = document.getElementById("property-form");
      if (foundForm) {
        observer.disconnect();
        inicializarFormularioImovel();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    return;
  }
  
  // Visualização de miniaturas dos arquivos selecionados
  const fileInput = document.getElementById('property-images');
  const fileListDiv = document.getElementById('selected-files-list');
  
  // Evita múltiplos listeners de miniatura
  if (fileInput && fileListDiv && !fileInput._miniaturaListenerAdded) {
    fileInput._miniaturaListenerAdded = true;
    fileInput.addEventListener('change', function(e) {
      fileListDiv.innerHTML = '';
      if (fileInput.files && fileInput.files.length > 0) {
        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-2 md:grid-cols-4 gap-4';
        Array.from(fileInput.files).forEach(file => {
          const itemDiv = document.createElement('div');
          itemDiv.className = 'flex flex-col items-center border rounded p-2 bg-gray-50';
          if (file.type.startsWith('image/')) {
            const img = document.createElement('img');
            img.className = 'w-20 h-20 object-cover mb-1 rounded shadow';
            img.alt = file.name;
            img.title = file.name;
            const reader = new FileReader();
            reader.onload = function(e) {
              img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            itemDiv.appendChild(img);
          } else {
            const icon = document.createElement('span');
            icon.className = 'fas fa-file fa-2x text-gray-400 mb-1';
            itemDiv.appendChild(icon);
          }
          const info = document.createElement('div');
          info.className = 'text-xs text-center break-all';
          info.innerHTML = `${file.name}<br>${(file.size/1024).toFixed(1)} KB`;
          itemDiv.appendChild(info);
          grid.appendChild(itemDiv);
        });
        fileListDiv.appendChild(grid);
      }
    });
  }

  // Remove inicialização duplicada de miniaturas

  // Form já foi verificado no início da função
  console.log('Formulário encontrado:', form);

  if (form._listenersAdded) {
    return;
  }
  form._listenersAdded = true;

  // Adiciona formatação automática no campo de preço
  const priceInput = form.querySelector('#price');
  if (priceInput) {
    priceInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 2) {
        value = value.slice(0, -2) + '.' + value.slice(-2);
        value = parseFloat(value);
        if (!isNaN(value)) {
          e.target.value = value.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        }
      }
    });
  }

  // Botão Cancelar
  const btnCancelar = document.getElementById('btn-cancelar-imovel');
  if (btnCancelar) {
    btnCancelar.addEventListener('click', function () {
      if (confirm('Deseja realmente cancelar e perder as alterações?')) {
        // Limpa o formulário
        form.reset();
        
        // Remove ID de edição se existir
        const editId = form.querySelector('input[name="edit_id"]');
        if (editId) editId.remove();
        
        // Limpa a visualização das imagens
        const fileListDiv = document.getElementById('selected-files-list');
        if (fileListDiv) {
          fileListDiv.innerHTML = '';
        }
        
        // Redireciona para a página do painel
        window.location.href = 'painel.php?tab=properties';
      }
    });
  }

  // Envio do formulário
  form.addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = new FormData(form);

    // Validação simples de campos obrigatórios
    let camposObrigatorios = form.querySelectorAll('[required]');
    for (let campo of camposObrigatorios) {
      if (!campo.value.trim()) {
        alert(`O campo "${campo.previousElementSibling?.innerText || campo.name}" é obrigatório.`);
        campo.focus();
        return;
      }
    }

    // Campo "location" com base no "address"
    if (formData.has("address")) {
      formData.set("location", formData.get("address"));
      formData.delete("address");
    }

    // Características (checkboxes)
    const features = [];
    form.querySelectorAll('input[name="features[]"]:checked').forEach((el) => {
      features.push(el.value);
    });
    formData.set("features", JSON.stringify(features));

    // Área (com casas decimais)
    const areaInput = form.querySelector('#area');
    if (areaInput) {
      // Pega o valor do campo
      let areaVal = areaInput.value;
      
      // Se estiver vazio
      if (!areaVal.trim()) {
        alert('O campo Área está vazio.');
        areaInput.focus();
        return;
      }

      // Converte vírgula para ponto e remove pontos de milhar
      areaVal = areaVal.replace(/\./g, '').replace(',', '.');
      
      // Converte para número com 2 casas decimais
      const areaNum = parseFloat(areaVal);
      
      if (isNaN(areaNum)) {
        alert('Valor da área inválido.');
        areaInput.focus();
        return;
      }
      
      // Armazena o valor com ponto como separador decimal
      formData.set('area', areaNum.toFixed(2));
    }

    // Preço (formato americano para o backend)
    const priceInput = form.querySelector('#price');
    if (priceInput) {
      let priceVal = priceInput.value;
      priceVal = priceVal.replace(/\./g, '').replace(',', '.');
      if (!priceVal || isNaN(priceVal)) {
        alert('O campo Preço está vazio ou inválido.');
        priceInput.focus();
        return;
      }
      priceVal = parseFloat(priceVal).toFixed(2);
      formData.set('price', priceVal);
    }

    // Ano de Construção
    const yearBuiltInput = form.querySelector('#yearBuilt');
    if (yearBuiltInput) {
      let yearVal = yearBuiltInput.value.trim();
      if (yearVal !== '') {
        formData.set('yearBuilt', yearVal);
      } else {
        formData.delete('yearBuilt');
      }
    }

    // Verifica se é edição ou novo cadastro
    const editId = form.querySelector('input[name="edit_id"]')?.value;
    const endpoint = editId ? "api/updateProperty.php" : "api/addProperty.php";
    if (editId) formData.append('id', editId);

    try {
      // Envia dados principais
      const res = await fetch(endpoint, {
        method: "POST",
        body: formData,
      });

      let result;
      const contentType = res.headers.get('content-type');
      const text = await res.text();
      if (contentType && contentType.includes('application/json') && text.trim().length > 0) {
        try {
          result = JSON.parse(text);
        } catch (e) {
          console.error('Erro ao parsear JSON:', e, text);
          alert('Resposta inválida do servidor ao salvar imóvel.');
          return;
        }
      } else {
        console.error('Resposta inesperada do servidor:', text);
        alert('O servidor não retornou dados válidos ao salvar imóvel.');
        return;
      }
      console.log('Resposta property:', result);
      const propertyId = result.id || result.property_id || editId;

      if (result.success && propertyId) {
        const imagens = document.getElementById("property-images")?.files;

        // O upload de imagens agora é tratado pelo backend (addProperty.php ou updateProperty.php)
        
        alert(editId ? "Imóvel editado com sucesso!" : "Imóvel cadastrado com sucesso!");
        
        // Redireciona para a aba de propriedades para forçar a atualização da lista
        window.location.href = 'painel.php?tab=properties';
      } else {
        alert(result.error || "Erro ao salvar imóvel. Verifique os campos.");
      }
    } catch (err) {
      console.error("Erro ao salvar imóvel:", err);
      if (err && err.message) {
        alert("Erro ao salvar imóvel: " + err.message);
      } else {
        alert("Ocorreu um erro inesperado ao salvar o imóvel.");
      }
    }
  });
}
