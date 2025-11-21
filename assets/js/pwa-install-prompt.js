/**
 * Script para detectar e facilitar instalação do PWA
 * Mostra instruções quando o PWA está pronto para instalar
 */

const PWAInstallPrompt = {
    deferredPrompt: null,
    isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream,
    isAndroid: /Android/.test(navigator.userAgent),
    
    init() {
        // Detecta quando o browser está pronto para instalar (Android)
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallBanner();
        });
        
        // Verifica se já está instalado
        if (window.matchMedia('(display-mode: standalone)').matches) {
            console.log('✅ PWA já está instalado!');
            return;
        }
        
        // Mostra instruções para iOS
        if (this.isIOS) {
            this.showIOSInstructions();
        }
        
        // Log para debug
        console.log('PWA Install Prompt inicializado');
        console.log('iOS:', this.isIOS);
        console.log('Android:', this.isAndroid);
    },
    
    showInstallBanner() {
        // Verifica se foi dispensado há menos de 30 dias
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (dismissed) {
            const dismissedDate = parseInt(dismissed);
            const thirtyDays = 30 * 24 * 60 * 60 * 1000; // 30 dias em milissegundos
            const daysSinceDismissed = Date.now() - dismissedDate;
            
            if (daysSinceDismissed < thirtyDays) {
                const daysRemaining = Math.ceil((thirtyDays - daysSinceDismissed) / (24 * 60 * 60 * 1000));
                console.log(`Banner de instalação dispensado. Aparecerá novamente em ${daysRemaining} dias.`);
                return; // Não mostra o banner
            }
        }
        
        // Cria banner de instalação para Android
        const banner = document.createElement('div');
        banner.id = 'pwa-install-banner';
        banner.innerHTML = `
            <div style="
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: linear-gradient(135deg, #009ef7 0%, #0088d1 100%);
                color: white;
                padding: 15px 20px;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="flex: 1;">
                    <div style="font-weight: bold; margin-bottom: 5px;">📱 Instalar RH Privus</div>
                    <div style="font-size: 12px; opacity: 0.9;">Adicione à tela inicial para acesso rápido</div>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button id="pwa-install-btn" style="
                        background: white;
                        color: #009ef7;
                        border: none;
                        padding: 8px 20px;
                        border-radius: 5px;
                        font-weight: bold;
                        cursor: pointer;
                    ">Instalar</button>
                    <button id="pwa-install-close" style="
                        background: transparent;
                        color: white;
                        border: 1px solid white;
                        padding: 8px 15px;
                        border-radius: 5px;
                        cursor: pointer;
                    ">Agora não</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(banner);
        
        // Botão de instalar
        document.getElementById('pwa-install-btn').addEventListener('click', () => {
            this.install();
        });
        
        // Botão de fechar
        document.getElementById('pwa-install-close').addEventListener('click', () => {
            banner.remove();
            // Salva preferência para não mostrar novamente por 30 dias
            localStorage.setItem('pwa-install-dismissed', Date.now());
            console.log('Banner dispensado. Aparecerá novamente em 30 dias.');
        });
    },
    
    async install() {
        if (!this.deferredPrompt) {
            // Se não tem prompt automático, mostra instruções manuais
            this.showManualInstructions();
            return;
        }
        
        // Mostra prompt de instalação
        this.deferredPrompt.prompt();
        
        // Aguarda resposta do usuário
        const { outcome } = await this.deferredPrompt.userChoice;
        
        if (outcome === 'accepted') {
            console.log('✅ Usuário aceitou instalação');
            document.getElementById('pwa-install-banner')?.remove();
        } else {
            console.log('❌ Usuário recusou instalação');
        }
        
        this.deferredPrompt = null;
    },
    
    showIOSInstructions() {
        // Verifica se já mostrou hoje
        const lastShown = localStorage.getItem('pwa-ios-instructions-shown');
        const oneDay = 24 * 60 * 60 * 1000;
        
        if (lastShown && (Date.now() - parseInt(lastShown)) < oneDay) {
            return; // Já mostrou hoje
        }
        
        // Cria modal com instruções para iOS
        const modal = document.createElement('div');
        modal.id = 'pwa-ios-modal';
        modal.innerHTML = `
            <div style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            ">
                <div style="
                    background: white;
                    border-radius: 15px;
                    padding: 25px;
                    max-width: 400px;
                    width: 100%;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
                ">
                    <h2 style="margin-top: 0; color: #333;">🍎 Instalar no iOS</h2>
                    <ol style="text-align: left; line-height: 1.8;">
                        <li>Toque no botão <strong>compartilhar</strong> (□↑) na barra inferior</li>
                        <li>Role para baixo</li>
                        <li>Toque em <strong>"Adicionar à Tela de Início"</strong></li>
                        <li>Confirme</li>
                    </ol>
                    <div style="margin-top: 20px; padding: 15px; background: #f0f8ff; border-radius: 8px;">
                        <strong>💡 Dica:</strong> Use o Safari (não funciona no Chrome iOS)
                    </div>
                    <button id="pwa-ios-close" style="
                        margin-top: 20px;
                        width: 100%;
                        padding: 12px;
                        background: #009ef7;
                        color: white;
                        border: none;
                        border-radius: 8px;
                        font-weight: bold;
                        cursor: pointer;
                    ">Entendi</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        document.getElementById('pwa-ios-close').addEventListener('click', () => {
            modal.remove();
            localStorage.setItem('pwa-ios-instructions-shown', Date.now());
        });
    },
    
    showManualInstructions() {
        const instructions = this.isIOS 
            ? 'No Safari, toque no botão compartilhar (□↑) e selecione "Adicionar à Tela de Início"'
            : 'No Chrome, toque no menu (⋮) e selecione "Adicionar à tela inicial"';
        
        alert(`Para instalar:\n\n${instructions}`);
    }
};

// Inicializa quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => PWAInstallPrompt.init());
} else {
    PWAInstallPrompt.init();
}

