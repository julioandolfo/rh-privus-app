/**
 * Gerenciamento de Notificações Push
 */

const PushNotifications = {
    vapidPublicKey: null,
    registration: null,
    basePath: '/rh-privus',
    
    // Inicializa (chamar após login)
    async init() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            console.warn('Push notifications não suportadas neste browser');
            return false;
        }
        
        try {
            // Detecta base path automaticamente
            const path = window.location.pathname;
            if (path.includes('/rh-privus')) {
                this.basePath = '/rh-privus';
            } else {
                this.basePath = '';
            }
            
            // Busca chave VAPID pública do servidor
            const response = await fetch(this.basePath + '/api/push/vapid-key.php');
            if (!response.ok) {
                throw new Error('Erro ao buscar chave VAPID');
            }
            const data = await response.json();
            this.vapidPublicKey = data.publicKey;
            
            if (!this.vapidPublicKey) {
                throw new Error('Chave VAPID não encontrada');
            }
            
            // Registra service worker
            this.registration = await navigator.serviceWorker.register(this.basePath + '/sw.js');
            console.log('Service Worker registrado:', this.registration.scope);
            
            // Aguarda service worker estar pronto
            if (this.registration.installing) {
                await new Promise(resolve => {
                    this.registration.installing.addEventListener('statechange', () => {
                        if (this.registration.installing.state === 'installed') {
                            resolve();
                        }
                    });
                });
            }
            
            // Solicita permissão e subscribe
            await this.subscribe();
            
            return true;
        } catch (error) {
            console.error('Erro ao inicializar push notifications:', error);
            return false;
        }
    },
    
    // Solicita permissão e cria subscription
    async subscribe() {
        if (!this.registration) {
            throw new Error('Service Worker não registrado');
        }
        
        // Verifica se já tem subscription ativa
        const existingSubscription = await this.registration.pushManager.getSubscription();
        if (existingSubscription) {
            // Atualiza subscription no servidor
            await this.sendSubscriptionToServer(existingSubscription);
            return existingSubscription;
        }
        
        // Verifica permissão
        let permission = Notification.permission;
        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }
        
        if (permission !== 'granted') {
            throw new Error('Permissão de notificação negada');
        }
        
        // Cria subscription
        const subscription = await this.registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
        });
        
        // Envia subscription para o servidor
        await this.sendSubscriptionToServer(subscription);
        
        return subscription;
    },
    
    // Envia subscription para o servidor
    async sendSubscriptionToServer(subscription) {
        const subscriptionData = {
            endpoint: subscription.endpoint,
            keys: {
                p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                auth: this.arrayBufferToBase64(subscription.getKey('auth'))
            }
        };
        
        const response = await fetch(this.basePath + '/api/push/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include', // Inclui cookies de sessão
            body: JSON.stringify(subscriptionData)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Erro ao registrar subscription');
        }
        
        return await response.json();
    },
    
    // Converte chave VAPID para formato correto
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },
    
    // Converte ArrayBuffer para Base64
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return window.btoa(binary);
    },
    
    // Cancela subscription
    async unsubscribe() {
        if (!this.registration) {
            return;
        }
        
        const subscription = await this.registration.pushManager.getSubscription();
        if (subscription) {
            await subscription.unsubscribe();
        }
    },
    
    // Verifica se está subscrito
    async isSubscribed() {
        if (!this.registration) {
            return false;
        }
        
        const subscription = await this.registration.pushManager.getSubscription();
        return !!subscription;
    }
};

// Exportar para uso global
window.PushNotifications = PushNotifications;

// Auto-inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        // Inicializa apenas se usuário estiver logado (verifica se há sessão)
        // A inicialização real deve ser feita após login
    });
} else {
    // DOM já está pronto
}

