/**
 * İnciPOS Chat - Frontend Arayüzü
 * Bu dosya, İnciPOS sistemi içinde kullanıcı dostu bir sohbet arayüzü oluşturur.
 * 
 * Dosya: inci_chat.js
 */

class InciChat {
    constructor() {
        this.chatContainer = null;
        this.messagesList = null;
        this.inputField = null;
        this.sendButton = null;
        this.toggleButton = null;
        this.isOpen = false;
        this.messageHistory = [];
        this.isProcessing = false;
        
        // Arayüzü oluştur ve olayları bağla
        this.init();
    }
    
    /**
     * Sohbet arayüzünü başlat
     */
    init() {
        // Sohbet arayüzü elemanlarını oluştur
        this.createChatUI();
        
        // Olay dinleyicilerini ayarla
        this.setupEventListeners();
        
        // Önceki sohbet geçmişini yükle
        this.loadChatHistory();
    }
    
    /**
     * Sohbet arayüzü elemanlarını oluştur
     */
    createChatUI() {
        // Ana container
        this.chatContainer = document.createElement('div');
        this.chatContainer.className = 'inci-chat-container';
        this.chatContainer.innerHTML = `
            <div class="inci-chat-header">
                <h3>İnci Kırtasiye Asistanı</h3>
                <button class="inci-chat-close" title="Kapat">&times;</button>
            </div>
            <div class="inci-chat-messages"></div>
            <div class="inci-chat-input">
                <textarea placeholder="Bir soru sorun..." rows="2"></textarea>
                <button class="inci-chat-send" title="Gönder">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        `;
        
        // Toggle butonu
        this.toggleButton = document.createElement('button');
        this.toggleButton.className = 'inci-chat-toggle';
        this.toggleButton.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        `;
        
        // DOM referanslarını ayarla
        this.messagesList = this.chatContainer.querySelector('.inci-chat-messages');
        this.inputField = this.chatContainer.querySelector('textarea');
        this.sendButton = this.chatContainer.querySelector('.inci-chat-send');
        this.closeButton = this.chatContainer.querySelector('.inci-chat-close');
        
        // Belgeye elemanları ekle
        document.body.appendChild(this.chatContainer);
        document.body.appendChild(this.toggleButton);
        
        // Başlangıç durumunu ayarla (kapalı)
        this.chatContainer.style.display = 'none';
        
        // Stilleri ekle
        this.addStyles();
    }
    
    /**
     * Olay dinleyicilerini ayarla
     */
    setupEventListeners() {
        // Sohbeti aç/kapat
        this.toggleButton.addEventListener('click', () => this.toggleChat());
        this.closeButton.addEventListener('click', () => this.toggleChat(false));
        
        // Butona tıklandığında mesaj gönder
        this.sendButton.addEventListener('click', () => this.sendMessage());
        
        // Enter tuşuna basıldığında mesaj gönder (Shift+Enter ile yeni satır)
        this.inputField.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }
    
    /**
     * Sohbeti aç/kapat
     * @param {boolean|null} forceState - Belirli bir duruma zorla (true=açık, false=kapalı)
     */
    toggleChat(forceState = null) {
        this.isOpen = forceState !== null ? forceState : !this.isOpen;
        
        if (this.isOpen) {
            this.chatContainer.style.display = 'flex';
            this.toggleButton.classList.add('active');
            
            // Input alanına odaklan
            setTimeout(() => {
                this.inputField.focus();
            }, 300);
            
            // İlk kez açılıyorsa karşılama mesajı göster
            if (this.messageHistory.length === 0) {
                this.addBotMessage("Merhaba! Ben İnci Kırtasiye POS Asistanı. Size nasıl yardımcı olabilirim?\n\nSatış, stok durumu veya ciro bilgisi gibi konularda sorular sorabilirsiniz. Örneğin:\n- En çok satılan ürünler neler?\n- Stok durumu kritik ürünler var mı?\n- Bu ayın cirosu ne kadar?");
            }
        } else {
            this.chatContainer.style.display = 'none';
            this.toggleButton.classList.remove('active');
        }
    }
    
    /**
     * Mesaj gönder
     */
    sendMessage() {
        const message = this.inputField.value.trim();
        
        if (!message || this.isProcessing) {
            return;
        }
        
        // Kullanıcı mesajını sohbete ekle
        this.addUserMessage(message);
        
        // Input alanını temizle
        this.inputField.value = '';
        
        // Yazıyor göstergesini göster
        this.showTypingIndicator();
        
        // İşleniyor bayrağını ayarla
        this.isProcessing = true;
        
        // API'ye istek gönder
        fetch('inci_chat_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            // Yazıyor göstergesini kaldır
            this.hideTypingIndicator();
            
            // Bot yanıtını sohbete ekle
            if (data.success) {
                this.addBotMessage(data.response);
            } else {
                this.addBotMessage("Üzgünüm, bir sorun oluştu. Lütfen tekrar deneyin.");
                console.error('Sohbet API Hatası:', data.error);
            }
            
            // İşleniyor bayrağını temizle
            this.isProcessing = false;
        })
        .catch(error => {
            // Yazıyor göstergesini kaldır
            this.hideTypingIndicator();
            
            // Hata mesajı ekle
            this.addBotMessage("Bağlantı hatası oluştu. Lütfen internet bağlantınızı kontrol edin ve tekrar deneyin.");
            console.error('Fetch Hatası:', error);
            
            // İşleniyor bayrağını temizle
            this.isProcessing = false;
        });
    }
    
    /**
     * Kullanıcı mesajını sohbete ekle
     * @param {string} message - Kullanıcı mesaj içeriği
     */
    addUserMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'inci-chat-message user-message';
        messageDiv.innerHTML = `
            <div class="message-content">${this.formatMessage(message)}</div>
            <div class="message-time">${this.getCurrentTime()}</div>
        `;
        
        this.messagesList.appendChild(messageDiv);
        
        // En aşağı kaydır
        this.scrollToBottom();
        
        // Geçmişe kaydet
        this.messageHistory.push({
            sender: 'user',
            message: message,
            timestamp: new Date().toISOString()
        });
        
        this.saveChatHistory();
    }
    
    /**
     * Bot mesajını sohbete ekle
     * @param {string} message - Bot mesaj içeriği
     */
    addBotMessage(message) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'inci-chat-message bot-message';
        messageDiv.innerHTML = `
            <div class="avatar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                    <line x1="9" y1="9" x2="9.01" y2="9"></line>
                    <line x1="15" y1="9" x2="15.01" y2="9"></line>
                </svg>
            </div>
            <div class="message-bubble">
                <div class="message-content">${this.formatMessage(message)}</div>
                <div class="message-time">${this.getCurrentTime()}</div>
            </div>
        `;
        
        this.messagesList.appendChild(messageDiv);
        
        // En aşağı kaydır
        this.scrollToBottom();
        
        // Geçmişe kaydet
        this.messageHistory.push({
            sender: 'bot',
            message: message,
            timestamp: new Date().toISOString()
        });
        
        this.saveChatHistory();
    }
    
    /**
     * Yazıyor göstergesini göster
     */
    showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'inci-chat-message bot-message typing-indicator';
        typingDiv.innerHTML = `
            <div class="avatar">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                    <line x1="9" y1="9" x2="9.01" y2="9"></line>
                    <line x1="15" y1="9" x2="15.01" y2="9"></line>
                </svg>
            </div>
            <div class="message-bubble">
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        
        typingDiv.id = 'typing-indicator';
        this.messagesList.appendChild(typingDiv);
        
        // En aşağı kaydır
        this.scrollToBottom();
    }
    
    /**
     * Yazıyor göstergesini gizle
     */
    hideTypingIndicator() {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    /**
     * Mesaj metnini formatla
     * @param {string} text - Ham mesaj metni
     * @returns {string} - Formatlanmış HTML
     */
    formatMessage(text) {
        // Satır sonlarını <br> etiketine dönüştür
        let formattedText = text.replace(/\n/g, '<br>');
        
        // URL'leri tıklanabilir bağlantılara dönüştür
        formattedText = formattedText.replace(
            /(https?:\/\/[^\s]+)/g, 
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        return formattedText;
    }
    
    /**
     * Şu anki saati SS:DD formatında al
     * @returns {string} - Formatlanmış saat
     */
    getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    
    /**
     * Sohbet alanını en aşağı kaydır
     */
    scrollToBottom() {
        this.messagesList.scrollTop = this.messagesList.scrollHeight;
    }
    
    /**
     * Sohbet geçmişini localStorage'a kaydet
     */
    saveChatHistory() {
        // localStorage taşmasını önlemek için son 50 mesajı tut
        const historyToSave = this.messageHistory.slice(-50);
        localStorage.setItem('incipos_chat_history', JSON.stringify(historyToSave));
    }
    
    /**
     * Sohbet geçmişini localStorage'dan yükle
     */
    loadChatHistory() {
        try {
            const savedHistory = localStorage.getItem('incipos_chat_history');
            
            if (savedHistory) {
                this.messageHistory = JSON.parse(savedHistory);
                
                // Son 10 mesajı göster
                const recentMessages = this.messageHistory.slice(-10);
                
                for (const msg of recentMessages) {
                    if (msg.sender === 'user') {
                        this.addUserMessage(msg.message);
                    } else {
                        this.addBotMessage(msg.message);
                    }
                }
            }
        } catch (error) {
            console.error('Sohbet geçmişi yüklenirken hata:', error);
            // Hata varsa geçmişi temizle
            localStorage.removeItem('incipos_chat_history');
            this.messageHistory = [];
        }
    }
    
    /**
     * Sohbet arayüzü için CSS stilleri ekle
     */
    addStyles() {
        const styleElement = document.createElement('style');
        styleElement.textContent = `
            /* Ana container */
            .inci-chat-container {
                position: fixed;
                bottom: 80px;
                right: 20px;
                width: 350px;
                height: 500px;
                background-color: #fff;
                border-radius: 12px;
                box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
                display: flex;
                flex-direction: column;
                z-index: 9999;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                transition: all 0.3s ease;
            }
            
            /* Başlık çubuğu */
            .inci-chat-header {
                background: linear-gradient(135deg, #4a148c 0%, #7b1fa2 100%);
                color: white;
                padding: 15px;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .inci-chat-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 500;
            }
            
            .inci-chat-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                padding: 0;
                transition: transform 0.2s ease;
            }
            
            .inci-chat-close:hover {
                transform: scale(1.2);
            }
            
            /* Mesajlar alanı */
            .inci-chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 15px;
                display: flex;
                flex-direction: column;
                background-color: #f5f5f5;
            }
            
            /* Mesaj elementi */
            .inci-chat-message {
                margin-bottom: 15px;
                max-width: 85%;
                display: flex;
                align-items: flex-end;
            }
            
            .user-message {
                align-self: flex-end;
                flex-direction: row-reverse;
            }
            
            .bot-message {
                align-self: flex-start;
            }
            
            .message-bubble {
                background-color: #fff;
                border-radius: 18px;
                padding: 10px 15px;
                position: relative;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            
            .user-message .message-content {
                background-color: #7b1fa2;
                color: white;
                border-radius: 18px;
                padding: 10px 15px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            
            .message-time {
                font-size: 10px;
                color: #888;
                margin-top: 5px;
                text-align: right;
            }
            
            .avatar {
                width: 30px;
                height: 30px;
                margin-right: 10px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f0f0f0;
                color: #7b1fa2;
            }
            
            .user-message .avatar {
                display: none;
            }
            
            /* Yazıyor göstergesi */
            .typing-dots {
                display: flex;
                align-items: center;
                height: 20px;
            }
            
            .typing-dots span {
                height: 8px;
                width: 8px;
                margin: 0 1px;
                background-color: #999;
                border-radius: 50%;
                display: inline-block;
                animation: typingAnimation 1.5s infinite ease-in-out;
            }
            
            .typing-dots span:nth-child(2) {
                animation-delay: 0.2s;
            }
            
            .typing-dots span:nth-child(3) {
                animation-delay: 0.4s;
            }
            
            @keyframes typingAnimation {
                0% { transform: translateY(0px); }
                28% { transform: translateY(-5px); }
                44% { transform: translateY(0px); }
            }
            
            /* Girdi alanı */
            .inci-chat-input {
                padding: 15px;
                border-top: 1px solid #e0e0e0;
                display: flex;
                align-items: center;
                background-color: #fff;
            }
            
            .inci-chat-input textarea {
                flex: 1;
                border: 1px solid #e0e0e0;
                border-radius: 20px;
                padding: 10px 15px;
                font-size: 14px;
                resize: none;
                outline: none;
                font-family: inherit;
                transition: border-color 0.3s ease;
            }
            
            .inci-chat-input textarea:focus {
                border-color: #7b1fa2;
            }
            
            .inci-chat-send {
                background: linear-gradient(135deg, #4a148c 0%, #7b1fa2 100%);
                color: white;
                width: 40px;
                height: 40px;
                border-radius: 50%;
                border: none;
                margin-left: 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.2s ease;
            }
            
            .inci-chat-send:hover {
                transform: scale(1.1);
            }
            
            /* Toggle butonu */
            .inci-chat-toggle {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, #4a148c 0%, #7b1fa2 100%);
                color: white;
                border: none;
                box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
                cursor: pointer;
                z-index: 9998;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.3s ease;
            }
            
            .inci-chat-toggle:hover {
                transform: scale(1.1);
            }
            
            .inci-chat-toggle.active {
                transform: rotate(90deg) scale(0.9);
            }
            
            /* Duyarlı ayarlamalar */
            @media (max-width: 480px) {
                .inci-chat-container {
                    width: 90%;
                    height: 70%;
                    bottom: 70px;
                    right: 5%;
                }
            }
        `;
        
        document.head.appendChild(styleElement);
    }
}

// DOM tamamen yüklendiğinde sohbeti başlat
document.addEventListener('DOMContentLoaded', () => {
    window.inciChat = new InciChat();
});