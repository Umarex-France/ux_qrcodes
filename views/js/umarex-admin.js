// === Instance globale du QR Code ===
// Sert pour générer et exporter ultérieurement
let qr = null;

/**
 * 🔁 Fonction de génération du QR Code via QRCodeStyling
 *
 * @param {string} data - Contenu à encoder (URL)
 * @param {string} logoPath - Chemin du logo au centre du QR
 */
/**
 * 🔁 Fonction de génération du QR Code via QRCodeStyling
 *
 * @param {string} data - Contenu à encoder (URL)
 * @param {string} logoPath - Chemin du logo au centre du QR
 */
function generate_qr(data, logoPath) {
	qr = new QRCodeStyling({
		width: 1200, // Image réelle générée
		height: 1200,
		data: data,
		dotsOptions: {
			color: '#000',
			type: 'rounded',
		},
		cornersSquareOptions: {
			color: '#000',
		},
		cornersDotOptions: {
			color: '#c00',
		},
		backgroundOptions: {
			color: '#ffffff',
		},
		image: logoPath,
		imageOptions: {
			crossOrigin: 'anonymous',
			hideBackgroundDots: true,
			imageSize: 0.75,
		},
	});

	// 📦 Injection dans l'élément d'affichage
	const container = document.getElementById('qrcode-preview');
	if (container) {
		container.innerHTML = ''; // Nettoyage
		qr.append(container); // Ajout du QR
	}
}

/**
 * 🔄 Vide le QR affiché (ex. champ vide)
 */
function clear_qr() {
	const container = document.getElementById('qrcode-preview');
	if (container) {
		container.innerHTML = '';
	}
}

// === Exécution une fois que le DOM est prêt ===
document.addEventListener('DOMContentLoaded', function () {
	const refInput = document.getElementById('codeRef'); // Champ de référence
	const codeInput = document.getElementById('codeURL'); // Champ auto-généré d'URL
	const logoURL = document.getElementById('logoURL').value; // l'URL du logo de QRCode
	const baseURL = document.getElementById('baseURL').value; // L'URL de base pour le QRCode
	const btnDownload = document.getElementById('btn-telecharger'); // Le bouton d'export

	/**
	 * 🧠 Lors de la saisie dans le champ Référence :
	 * - Met à jour le champ codeURL
	 * - Active ou désactive le bouton Export
	 * - Génère automatiquement le QR code
	 */
	if (refInput && codeInput) {
		refInput.addEventListener('input', function () {
			const refValue = this.value.trim(); // Pour s'actualiser à chaque saisie
			const fullURL = baseURL + encodeURIComponent(refValue);

			codeInput.value = fullURL; // Mise à jour URL d'encodage
			//
			if (refValue.length > 0) {
				generate_qr(fullURL, logoURL); // Génère QR en live
				btnDownload.disabled = false;
				//
			} else {
				clear_qr();
				btnDownload.disabled = true;
			}
		});

		// ✅ Si le champ REF est déjà rempli au chargement (cas de l'édition)
		refValue = refInput.value.trim(); // Référence existante
		if (refValue.trim() !== '') {
			const fullURL = baseURL + encodeURIComponent(refValue); // URL complète encodée
			codeInput.value = fullURL; // Assure la synchro du champ URL
			generate_qr(fullURL, logoURL); // Génère le QR dès le chargement
			btnDownload.disabled = false; // Active le bouton d’export
		}
	}

	/**
	 * 💾 Exporter le QR Code au format PNG
	 * ⚠️ Requiert que le QR soit déjà généré
	 */
	if (btnDownload) {
		btnDownload.addEventListener('click', function () {
			if (!qr) {
				alert('Veuillez générer le QR Code avant d’exporter.');
				return;
			}

			// 📦 Récupération des données pour le nom du fichier
			const ref = this.dataset.ref || 'code';
			const name = this.dataset.name || 'nom';
			const id = this.dataset.id ? `--[#${this.dataset.id}]` : '';

			qr.getRawData('png').then(function (blob) {
				const url = URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = `${ref}__${name}${id}.png`.replace(/\s+/g, '_');
				a.click();
				URL.revokeObjectURL(url); // 🧹 Nettoyage mémoire
			});
		});
	}
});
