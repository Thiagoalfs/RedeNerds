/**
 * app_cache.js - Sistema de Cache Inteligente em LocalStorage com Revalidação Condicional (ETag / HTTP 304)
 * Rede Nerds
 */

(function (global) {
    'use strict';

    const AppCache = {
        PREFIX: 'nerds_cache_',

        /**
         * Recupera um item salvo no localStorage
         * @param {string} key
         * @returns {{ data: any, etag: string, cachedAt: number } | null}
         */
        get(key) {
            try {
                const raw = localStorage.getItem(this.PREFIX + key);
                if (!raw) return null;
                const parsed = JSON.parse(raw);
                if (!parsed || typeof parsed !== 'object' || !('data' in parsed)) {
                    return null;
                }
                return parsed;
            } catch (e) {
                console.warn('[AppCache] Falha ao ler localStorage:', e);
                return null;
            }
        },

        /**
         * Salva dados e ETag no localStorage
         * @param {string} key
         * @param {any} data
         * @param {string} etag
         */
        set(key, data, etag = '') {
            try {
                const payload = {
                    data,
                    etag: etag ? String(etag).trim() : '',
                    cachedAt: Date.now()
                };
                localStorage.setItem(this.PREFIX + key, JSON.stringify(payload));
            } catch (e) {
                console.warn('[AppCache] Falha ao salvar no localStorage (possível cota excedida):', e);
            }
        },

        /**
         * Remove uma chave específica do cache
         * @param {string} key
         */
        remove(key) {
            try {
                localStorage.removeItem(this.PREFIX + key);
            } catch (e) {}
        },

        /**
         * Limpa todo o cache gerenciado pela Rede Nerds no localStorage
         */
        clearAll() {
            try {
                const keysToRemove = [];
                for (let i = 0; i < localStorage.length; i++) {
                    const k = localStorage.key(i);
                    if (k && k.startsWith(this.PREFIX)) {
                        keysToRemove.push(k);
                    }
                }
                keysToRemove.forEach(k => localStorage.removeItem(k));
            } catch (e) {}
        },

        /**
         * Busca dados com renderização imediata do cache local e revalidação transparente via ETag / 304.
         *
         * @param {string} cacheKey Nome da chave no cache (ex: 'parceiros', 'servidores', 'equipe')
         * @param {string} url URL do endpoint da API
         * @param {object} options Opções: { onCached: Function, onFresh: Function, onError: Function, fetchOptions: object }
         * @returns {Promise<any>}
         */
        async fetchWithCache(cacheKey, url, options = {}) {
            const { onCached, onFresh, onError, fetchOptions = {} } = options;
            const cached = this.get(cacheKey);
            let hasDeliveredCached = false;

            // 1. Se já temos dados no cache local, entrega imediatamente para renderização instantânea (0ms)
            if (cached && cached.data !== undefined && cached.data !== null) {
                if (typeof onCached === 'function') {
                    try {
                        onCached(cached.data);
                        hasDeliveredCached = true;
                    } catch (renderErr) {
                        console.error(`[AppCache] Erro ao renderizar cache inicial de '${cacheKey}':`, renderErr);
                    }
                }
            }

            // 2. Prepara os cabeçalhos para a checagem com o servidor
            const headers = { ...(fetchOptions.headers || {}) };
            if (cached && cached.etag) {
                headers['If-None-Match'] = cached.etag;
            }

            try {
                const response = await fetch(url, {
                    ...fetchOptions,
                    headers
                });

                // 3. Status 304: O banco de dados NÃO foi alterado. O cache permanece 100% válido.
                if (response.status === 304) {
                    return cached ? cached.data : null;
                }

                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }

                // 4. Status 200: Novos dados ou primeira requisição
                const freshData = await response.json();
                const newEtag = response.headers.get('ETag') || '';

                // Atualiza o localStorage com a nova versão e ETag
                this.set(cacheKey, freshData, newEtag);

                // Dispara callback de dados atualizados
                if (typeof onFresh === 'function') {
                    onFresh(freshData);
                } else if (!hasDeliveredCached && typeof onCached === 'function') {
                    onCached(freshData);
                }

                return freshData;
            } catch (networkErr) {
                // Em caso de falha de conexão (offline), o cache local garante o funcionamento contínuo
                if (!hasDeliveredCached) {
                    if (typeof onError === 'function') {
                        onError(networkErr);
                    } else {
                        console.warn(`[AppCache] Não foi possível carregar '${cacheKey}' da rede nem do cache:`, networkErr);
                    }
                } else {
                    console.info(`[AppCache] Rede indisponível. Mantendo dados do cache para '${cacheKey}'.`);
                }

                return cached ? cached.data : null;
            }
        }
    };

    global.AppCache = AppCache;

})(typeof window !== 'undefined' ? window : this);
