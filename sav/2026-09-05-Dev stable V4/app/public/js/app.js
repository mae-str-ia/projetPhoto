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

        const generateTextPdfBtn = document.getElementById('generateTextPdfBtn');
        if (generateTextPdfBtn) {
            generateTextPdfBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.generateTextPdf();
            });
        }

        const generateFinalPdfBtn = document.getElementById('generateFinalPdfBtn');
        if (generateFinalPdfBtn) {
            generateFinalPdfBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.generateFinalPdf();
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
     * Generate text PDF from Markdown and update the source PDF used by the app.
     */
    async generateTextPdf() {
        if (!confirm('Regenerer le PDF texte depuis le Markdown ?\n\nCela peut prendre quelques secondes.')) {
            return;
        }

        const loading = document.createElement('div');
        loading.className = 'loading';
        loading.textContent = 'Generation du PDF texte...';
        document.body.appendChild(loading);

        try {
            const data = await this.api('markdown.php', {
                action: 'generateTextPdf',
                copyToSource: true,
            });

            loading.remove();
            if (!data.success) {
                alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                return;
            }

            if (window.PdfViewer) {
                PdfViewer._pdf = null;
            }
            this.notify('PDF texte regenere', 'success');
            window.setTimeout(() => window.location.reload(), 500);
        } catch (err) {
            loading.remove();
            alert('Erreur: ' + err.message);
        }
    },

    /**
     * Generate final PDF (with photos and text merged)
     */
    async generateFinalPdf() {
        if (!confirm('Générer le PDF complet (livre.print.pdf) ?\n\nCela va:\n1. Capturer les pages photos (~12 min)\n2. Fusionner avec le PDF texte\n3. Corriger les dimensions\n\nLa génération se fera en arrière-plan. Vous pouvez continuer à utiliser l\'app.\nDurée estimée: 15-20 minutes.')) {
            return;
        }

        try {
            const data = await this.api('pdf.php', {
                action: 'generateFinalPdf'
            });

            if (!data.success) {
                alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                return;
            }

            this.notify('✅ Génération lancée en arrière-plan. Vous serez notifié à la fin.', 'success', 5000);

            // Start polling for completion
            this.pollPdfStatus();
        } catch (err) {
            alert('Erreur: ' + err.message);
        }
    },

    /**
     * Poll PDF generation status
     */
    async pollPdfStatus() {
        let checkCount = 0;
        const maxChecks = 120; // 2 hours with 60s interval
        const checkInterval = 60000; // Check every 60 seconds

        const pollInterval = setInterval(async () => {
            checkCount++;

            try {
                const response = await fetch(this.baseUrl + '/api/pdf.php?action=status');
                const data = await response.json();

                if (data.success && data.status === 'completed') {
                    clearInterval(pollInterval);
                    this.notify('✅ PDF complet généré avec succès! (livre.print.pdf)', 'success', 10000);
                    // Optionally reload to refresh UI
                    // window.location.reload();
                } else if (!data.success) {
                    clearInterval(pollInterval);
                    this.notify('⚠️ Erreur lors de la vérification du statut.', 'error', 5000);
                }

                // Stop after max checks
                if (checkCount >= maxChecks) {
                    clearInterval(pollInterval);
                    this.notify('⏱️ Vérification du statut arrêtée (timeout).', 'warning', 5000);
                }
            } catch (err) {
                console.error('Poll error:', err);
                // Continue polling even on error
            }
        }, checkInterval);
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
