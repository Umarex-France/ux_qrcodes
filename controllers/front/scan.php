<?php

// Définition du contrôleur pour gérer les scans de QR code
class UmarexqrcodesScanModuleFrontController extends ModuleFrontController
{
    // Fonction qui initialise le contenu du module
    public function initContent()
    {
        // Appel du constructeur parent pour initialiser le contexte
        parent::initContent();

        // Récupération de la valeur du QR code depuis l'URL
        // Si aucun QR code n'est fourni, redirige vers une page 404
        $qr = Tools::getValue('qr');
        if (! $qr) {
            Tools::redirect($this->get404URL());
            exit;
        }

                                                  // Récupération des informations de l'utilisateur
        $ip  = Tools::getRemoteAddr();            // Adresse IP
        $ua  = $_SERVER['HTTP_USER_AGENT'] ?? ''; // User-Agent complet
        $os  = $this->getOS($ua);                 // Système d'exploitation
        $nav = $this->getBrowser($ua);            // Navigateur

                                            // Recherche du QRCode dans la table 'qrcodes_codes'
        $code      = $this->getQRCode($qr); // Récupère le code si existant
        $codeExist = (bool) $code;          // Vérifie si le code existe

                                                        // Recherche du produit si existant
        $product_id   = Product::getIdByReference($qr); // Cherche un produit avec cette réf
        $isProductRef = (bool) $product_id;
        $product      = $isProductRef ? new Product($product_id, false, Context::getContext()->language->id) : null;

        // Pas de QRCode existant //////////////////////////
        if (! $codeExist) {
            //
            // N'est pas non plus une référence produit ----
            if (! $isProductRef) {
                Tools::redirect($this->get404URL());
                exit;
            }

            // Est bien une référence Article --------------

            // Mais obsolète : vers la catégorie...
            if (! $product->active) {
                Tools::redirect($this->getCategoryURL($product));
                exit;
            }

            // ...sinon ajouter la réf comme Code et continuer
            // Il devient donc un QRCode existant...
            $name = $product->name;
            $url  = $this->getProductURL($product);
            $this->addCode($qr, $name, $url, 1);

            // Récupération du code QRCode créé
            $code = $this->getQRCode($qr);
        }

        // QRCode existant /////////////////////////////////

        // Si le code est désactivé ------------------------
        if (! $code['active']) {
            //
            // ...et le produit aussi : vers la catégorie
            if (! $product || ! $product->active) {
                Tools::redirect($this->getCategoryURL($product));
                exit;
            }

            // ...sinon réactivation du QRCode, et continuer
            $this->reactivateCode($qr);
        }

        // Si le code est actif ----------------------------

        // On ajoute le scan à la table des scans
        $this->addScan($qr, $ip, $os, $nav, $ua);

        // On redirige vers l'URL du QRCode, que ce soit
        // une référence produit ou une entrée manuelle
        Tools::redirect($code['url']);
        exit;
    }

    // Retourne le code QR à partir de la référence
    // @param string $qr Référence du QR code
    // @return array|null Informations du QR code
    private function getQRCode($qr)
    {
        return Db::getInstance()->getRow("
            SELECT * FROM " . _DB_PREFIX_ . "qrcodes_codes
            WHERE ref = '" . pSQL($qr) . "'
        ");
    }

    // Retourne l'URL de la page 404
    private function get404URL()
    {
        return 'index.php?controller=404';
    }

    // Retourne l'URL du produit
    private function getProductURL($product)
    {
        return Context::getContext()->link->getProductLink($product);
    }

    // Retourne l'URL de la catégorie par défaut du produit
    private function getCategoryURL($product)
    {
        return Context::getContext()->link->getCategoryLink($product->id_category_default);
    }

    // Réactive un code QR dans la base de données
    private function reactivateCode($qr)
    {
        Db::getInstance()->update("qrcodes_codes", [
            'active' => 1,
        ], "ref = '" . pSQL($qr) . "'");
    }

    // Ajoute un nouveau QR code à la base
    private function addCode($ref, $name, $url, $active = 1)
    {
        Db::getInstance()->insert("qrcodes_codes", [
            'ref'    => pSQL($ref),
            'name'   => pSQL($name),
            'url'    => pSQL($url),
            'active' => (int) $active,
        ]);
    }

    // Ajoute un enregistrement de scan à la base
    private function addScan($code_ref, $ip, $os, $nav, $ua)
    {
        Db::getInstance()->insert("qrcodes_scans", [
            'code_ref' => pSQL($code_ref),
            'ip'       => pSQL($ip),
            'os'       => pSQL($os),
            'nav'      => pSQL($nav),
            'ua'       => pSQL($ua),
        ]);
    }

    // Détecte le système d'exploitation à partir du User-Agent
    private function getOS($userAgent)
    {
        if (preg_match('/linux/i', $userAgent)) {
            return 'Linux';
        }
        if (preg_match('/macintosh|mac os x/i', $userAgent)) {
            return 'Mac';
        }
        if (preg_match('/windows|win32/i', $userAgent)) {
            return 'Windows';
        }
        return 'Inconnu';
    }

    // Détecte le navigateur à partir du User-Agent
    private function getBrowser($userAgent)
    {
        if (preg_match('/MSIE/i', $userAgent)) {
            return 'Internet Explorer';
        }
        if (preg_match('/Firefox/i', $userAgent)) {
            return 'Firefox';
        }
        if (preg_match('/Chrome/i', $userAgent)) {
            return 'Chrome';
        }
        if (preg_match('/Safari/i', $userAgent)) {
            return 'Safari';
        }
        if (preg_match('/Opera/i', $userAgent)) {
            return 'Opera';
        }
        return 'Inconnu';
    }
}
