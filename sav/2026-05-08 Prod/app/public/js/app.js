/**
 * projetPhoto - Main Application JS
 */

const App = {
    baseUrl: BASE_URL,
    config: BOOK_CONFIG,

    /**
     * Initialize app on DOMContentLoaded
     */
    init() {
        // Export button
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportBook();
            });
        }
    },

    /**
     * Export book to Word document
     */
    exportBook() {
        if (confirm('Êtes-vous sûr de vouloir exporter le livre en Word?\n\nCela peut prendre quelques secondes...')) {
            const loading = document.createElement('div');
            loading.className = 'loading';
            loading.textContent = 'Export en cours...';
            document.body.appendChild(loading);

            fetch(this.baseUrl + '/api/export.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'export'})
            })
            .then(r => {
                loading.remove();
                if (r.ok) {
                    return r.blob().then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'ProjetPhoto.docx';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);
                    });
                } else {
                    return r.json().then(data => {
                        alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                    });
                }
            })
            .catch(err => {
                loading.remove();
                alert('Erreur: ' + err.message);
            });
        }
    },

    /**
     * Show notification
     */
    notify(message, type = 'info', duration = 3000) {
        const notif = document.createElement('div');
        notif.className = 'notification notification-' + type;
        notif.textContent = message;
        document.body.appendChild(notif);

        setTimeout(() => notif.remove(), duration);
    },

    /**
     * API call helper
     */
    async api(endpoint, data) {
        const response = await fetch(this.baseUrl + '/api/' + endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(data)
        });
        if (!response.ok) throw new Error(`API error: ${response.status}`);
        return response.json();
    },

    async apiForm(endpoint, formData) {
        const response = await fetch(this.baseUrl + '/api/' + endpoint, {
            method: 'POST',
            body: formData
        });
        if (!response.ok) throw new Error(`API error: ${response.status}`);
        return response.json();
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});

// Add notification styles
const style = document.createElement('style');
style.textContent = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 1rem;
    background: white;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
}

.notification-success {
    border-left: 4px solid #28a745;
}

.notification-error {
    border-left: 4px solid #dc3545;
}

.notification-info {
    border-left: 4px solid #007bff;
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
