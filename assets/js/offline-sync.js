/**
 * OfflineSync Manager - Gerenciador de Fila de Sincronização Offline (VERSÃO CORRIGIDA)
 * Correções aplicadas:
 * - init() com tratamento de erro correto
 * - Verificação de suporte para IndexedDB
 * - Timeout com AbortController
 * - Promises com reject em transações
 * - Verificação de document.body
 */

class OfflineSyncManager {
  constructor() {
    this.dbName = 'rr_imoveis_sync';
    this.storeName = 'sync_queue';
    this.db = null;
    this.isOnline = navigator.onLine;
    this.isInitialized = false;
    this.initError = null;
    this.initPromise = null;
    
    // Inicializa com tratamento de erro correto
    this.initPromise = this.initAsync();
  }

  // Método wrapper para inicialização assíncrona
  async initAsync() {
    try {
      await this.init();
      this.isInitialized = true;
      this.initError = null;
      
      return true;
    } catch (error) {
      console.error('✗ OfflineSync - Erro crítico na inicialização:', error);
      this.isInitialized = false;
      this.initError = error;
      return false;
    }
  }

  // Aguarda inicialização estar pronta com timeout
  async waitForInit() {
    // Se já inicializado com sucesso, retorna
    if (this.isInitialized) return true;
    
    // Se já foi tentado e falhou, lança o erro
    if (this.initError) throw this.initError;
    
    // Aguarda a inicialização com timeout de 5 segundos
    if (this.initPromise) {
      try {
        const timeout = new Promise((_, reject) => 
          setTimeout(() => reject(new Error('Timeout ao inicializar OfflineSync')), 5000)
        );
        await Promise.race([this.initPromise, timeout]);
      } catch (error) {
        throw error;
      }
    }
    
    // Verifica estado final após inicialização
    if (!this.isInitialized) {
      throw new Error('OfflineSync não inicializado: IndexedDB indisponível');
    }
    
    return true;
  }

  /**
   * Inicializa IndexedDB e listeners de conexão
   */
  async init() {
    // ✅ CORREÇÃO 3: Verifica suporte para IndexedDB ANTES de usar
    if (!('indexedDB' in window)) {
      throw new Error('IndexedDB não é suportado neste navegador');
    }
    
    try {
      await this.openDatabase();
      this.setupOnlineListeners();
      
      // Aguarda DOM estar pronto antes de chamar showSyncStatus
      if (document.body) {
        this.showSyncStatus();
      }
      
      // Se está online, tenta sincronizar imediatamente
      if (this.isOnline) {
        this.syncQueue();
      }
    } catch (error) {
      console.error('Erro ao inicializar OfflineSync:', error);
      throw error;
    }
  }

  /**
   * Abre ou cria banco IndexedDB
   */
  openDatabase() {
    return new Promise((resolve, reject) => {
      if (!('indexedDB' in window)) {
        reject(new Error('IndexedDB não suportado'));
        return;
      }

      try {
        const request = indexedDB.open(this.dbName, 4);
        
        request.onerror = () => {
          console.error('IndexedDB erro ao abrir:', request.error);
          reject(request.error);
        };
        
        request.onsuccess = () => {
          const db = request.result;
          if (!db) {
            reject(new Error('IndexedDB retornou null'));
            return;
          }
          this.db = db;
          
          resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
          try {
            const db = event.target.result;
            if (!db.objectStoreNames.contains(this.storeName)) {
              db.createObjectStore(this.storeName, { keyPath: 'id', autoIncrement: true });
              
            }
          } catch (err) {
            console.error('Erro ao criar object store:', err);
            reject(err);
          }
        };
      } catch (err) {
        console.error('Erro ao chamar indexedDB.open:', err);
        reject(err);
      }
    });
  }

  /**
   * Setup de listeners online/offline
   */
  setupOnlineListeners() {
    window.addEventListener('online', () => {
      this.isOnline = true;
      
      this.showSyncStatus('Online - Sincronizando...', 'success');
      this.syncQueue();
    });

    window.addEventListener('offline', () => {
      this.isOnline = false;
      
      this.showSyncStatus('Offline - Sincronizando quando voltar...', 'offline');
    });
  }

  /**
   * Adiciona à fila de sincronização
   */
  async addToQueue(endpoint, data, method = 'POST') {
    await this.waitForInit();
    
    if (!this.db) {
      throw new Error('IndexedDB não está inicializado');
    }

    const item = {
      endpoint,
      data,
      method,
      timestamp: Date.now(),
      status: 'pending',
      retries: 0,
      maxRetries: 3,
      syncedAt: null,
      errorMessage: null,
      errorAt: null
    };

    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([this.storeName], 'readwrite');
      const store = transaction.objectStore(this.storeName);
      const request = store.add(item);

      request.onerror = () => {
        console.error('Erro ao adicionar à fila:', request.error);
        reject(request.error);
      };
      
      request.onsuccess = () => {
        console.log(`✓ Item adicionado à fila: ${endpoint} (ID: ${request.result})`);
        resolve(request.result);
      };
    });
  }

  /**
   * Sincroniza fila inteira
   */
  async syncQueue() {
    await this.waitForInit();
    if (!this.isOnline || !this.db) return;

    const pending = await this.getPendingRequests();
    if (pending.length === 0) {
      
      return;
    }

    console.log(`⏳ Sincronizando ${pending.length} item(ns)...`);
    this.showSyncStatus(`Sincronizando ${pending.length} item(ns)...`, 'warning');

    for (const item of pending) {
      // Para antes de time out total
      if (!this.isOnline) break;
      
      await this.syncItem(item);
    }

    this.showSyncStatus('✓ Sincronização concluída!', 'success');
  }

  /**
   * Sincroniza item individual
   * ✅ CORREÇÃO 2: Com timeout correto (AbortController)
   */
  async syncItem(item) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);
    
    try {
      const response = await fetch(item.endpoint, {
        method: item.method,
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(item.data),
        signal: controller.signal
      });

      if (response.ok) {
        await this.markAsSync(item.id);
        
        return true;
      } else {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }
    } catch (error) {
      console.error(`✗ Erro ao sincronizar ${item.endpoint}:`, error);
      item.retries += 1;
      item.errorAt = Date.now();
      item.errorMessage = error.message;

      if (item.retries >= item.maxRetries) {
        await this.markAsError(item.id, error.message);
      } else {
        await this.updateItem(item);
      }
      return false;
    } finally {
      clearTimeout(timeoutId);
    }
  }

  /**
   * Obtém requisições pendentes
   */
  async getPendingRequests() {
    await this.waitForInit();
    if (!this.db) return [];

    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([this.storeName], 'readonly');
      const store = transaction.objectStore(this.storeName);
      const request = store.getAll();

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const all = request.result;
        const pending = all.filter(i => i.status === 'pending');
        resolve(pending);
      };
    });
  }

  /**
   * ✅ CORREÇÃO 4: Marcar como sincronizado com tratamento de erro
   */
  async markAsSync(id) {
    await this.waitForInit();
    return new Promise((resolve, reject) => {
      if (!this.db) {
        reject(new Error('IndexedDB não inicializado'));
        return;
      }

      const transaction = this.db.transaction([this.storeName], 'readwrite');
      const store = transaction.objectStore(this.storeName);
      const request = store.get(id);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const item = request.result;
        if (!item) {
          reject(new Error(`Item ${id} não encontrado`));
          return;
        }
        
        item.status = 'synced';
        item.syncedAt = Date.now();

        const updateRequest = store.put(item);
        updateRequest.onerror = () => reject(updateRequest.error);
        updateRequest.onsuccess = () => resolve();
      };
    });
  }

  /**
   * Marca como erro
   */
  async markAsError(id, errorMessage) {
    await this.waitForInit();
    return new Promise((resolve, reject) => {
      if (!this.db) {
        reject(new Error('IndexedDB não inicializado'));
        return;
      }

      const transaction = this.db.transaction([this.storeName], 'readwrite');
      const store = transaction.objectStore(this.storeName);
      const request = store.get(id);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const item = request.result;
        if (item) {
          item.status = 'failed';
          item.errorMessage = errorMessage;
          item.errorAt = Date.now();
          
          const updateRequest = store.put(item);
          updateRequest.onerror = () => reject(updateRequest.error);
          updateRequest.onsuccess = () => resolve();
        }
      };
    });
  }

  /**
   * Atualiza item
   */
  async updateItem(item) {
    await this.waitForInit();
    return new Promise((resolve, reject) => {
      if (!this.db) {
        reject(new Error('IndexedDB não inicializado'));
        return;
      }

      const transaction = this.db.transaction([this.storeName], 'readwrite');
      const store = transaction.objectStore(this.storeName);
      const request = store.put(item);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve();
    });
  }

  /**
   * Obtém estatísticas da fila
   * ✅ CORREÇÃO 4: Com tratamento de erro em Promise
   */
  async getQueueStats() {
    await this.waitForInit();
    if (!this.db) return { total: 0, pending: 0, synced: 0, failed: 0 };

    return new Promise((resolve, reject) => {
      const transaction = this.db.transaction([this.storeName], 'readonly');
      const store = transaction.objectStore(this.storeName);
      const request = store.getAll();

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        const all = request.result;
        resolve({
          total: all.length,
          pending: all.filter(i => i.status === 'pending').length,
          synced: all.filter(i => i.status === 'synced').length,
          failed: all.filter(i => i.status === 'failed').length
        });
      };
    });
  }

  /**
   * ✅ CORREÇÃO 5: Cria status div com verificação de document.body
   */
  createStatusDiv() {
    const div = document.createElement('div');
    div.id = 'sync-status';
    div.style.cssText = `
      position: fixed;
      bottom: 20px;
      right: 20px;
      padding: 12px 16px;
      border-radius: 4px;
      font-size: 14px;
      font-weight: 500;
      z-index: 9999;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    `;
    
    // Verifica se document.body existe
    if (document.body) {
      document.body.appendChild(div);
    }
    return div;
  }

  /**
   * Mostra status de sincronização
   */
  async showSyncStatus(message = null, type = null) {
    // Não executa se DOM não estiver pronto
    if (document.readyState === 'loading') {
      return;
    }

    const stats = await this.getQueueStats();
    const statusDiv = document.getElementById('sync-status') || this.createStatusDiv();

    if (message) {
      statusDiv.textContent = message;
      statusDiv.className = `sync-status ${type || 'info'}`;
      statusDiv.style.display = 'block';

      if (type === 'success') {
        setTimeout(() => {
          statusDiv.style.display = 'none';
        }, 3000);
      }
    }
  }
}

// ✅ CORREÇÃO 1: Inicializa APÓS DOM estar pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    if (!window.offlineSync) {
      window.offlineSync = new OfflineSyncManager();
      
    }
    addSyncStatusStyles();
  });
} else {
  if (!window.offlineSync) {
    window.offlineSync = new OfflineSyncManager();
    
  }
  addSyncStatusStyles();
}

function addSyncStatusStyles() {
  if (document.getElementById('sync-status-styles')) return; // Evita duplicação
  
  const style = document.createElement('style');
  style.id = 'sync-status-styles';
  style.textContent = `
    #sync-status {
      background-color: #3b82f6;
      color: white;
      animation: slideIn 0.3s ease;
    }
    #sync-status.success {
      background-color: #10b981;
    }
    #sync-status.warning {
      background-color: var(--cor-destaque);
    }
    #sync-status.offline {
      background-color: #ef4444;
    }
    #sync-status.error {
      background-color: #dc2626;
    }
    @keyframes slideIn {
      from {
        transform: translateX(400px);
        opacity: 0;
      }
      to {
        transform: translateX(0);
        opacity: 1;
      }
    }
  `;
  document.head.appendChild(style);
}
