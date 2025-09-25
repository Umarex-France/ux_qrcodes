<?php
// Vérification si le module est appelé via PrestaShop, sinon arrêter l'exécution
if (! defined('_PS_VERSION_')) {
    exit;
}

// Définition de la classe du module
class Umarexqrcodes extends Module
{
    // Constructeur du module
    public function __construct()
    {
                                                 // Définition des propriétés du module
        $this->name          = 'umarexqrcodes';  // Nom technique du module (minuscule)
        $this->tab           = 'administration'; // Groupe d'affichage dans le BO
        $this->version       = '1.0.0';          // Version du module
        $this->author        = 'Umarex';         // Auteur
        $this->need_instance = 0;                // Aucune instance front nécessaire

        // Appel du constructeur parent
        parent::__construct();

        // Nom affiché dans la liste des modules
        $this->displayName = $this->l('Umarex - QR Codes Admin');
        $this->description = $this->l('Capture et enregistre les scans QRCode avec redirection intelligente.');
    }

    // Fonction exécutée lors de l'installation du module
    public function install()
    {
        // Installation des tables, des hooks, et de l'onglet d'administration
        return parent::install()
        && $this->installDatabase()
        && $this->registerHook('moduleRoutes')
        && $this->installTab();
    }

    // Fonction exécutée lors de la désinstallation
    public function uninstall()
    {
        // Suppression de l'onglet d'administration
        $this->uninstallTab();
        return parent::uninstall();
    }

    // Création des tables nécessaires à l'enregistrement des QR codes et scans
    private function installDatabase()
    {
        $db = Db::getInstance();

        // Table des QR codes enregistrés
        $sql_codes = "
                CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "qrcodes_codes` (
                    `ref` VARCHAR(255) NOT NULL,          -- Référence du QR code (ancienne colonne 'id')
                    `name` VARCHAR(255) NOT NULL,         -- Nom descriptif du QR code
                    `url` TEXT NOT NULL,                  -- URL de redirection
                    `active` TINYINT(1) NOT NULL DEFAULT 1, -- Statut actif/inactif
                    PRIMARY KEY (`ref`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

        // Table des scans de QR codes
        $sql_scans = "
                CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "qrcodes_scans` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, -- ID auto-incrémenté du scan
                    `code_ref` VARCHAR(255) NOT NULL,          -- Référence du QR code scanné
                    `ip` VARCHAR(45) DEFAULT NULL,             -- Adresse IP du scanner
                    `time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Horodatage
                    `os` VARCHAR(100) DEFAULT NULL,            -- Système d'exploitation
                    `nav` VARCHAR(100) DEFAULT NULL,           -- Navigateur
                    `ua` TEXT DEFAULT NULL,                    -- User-agent complet
                    PRIMARY KEY (`id`),
                    INDEX (`code_ref`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ";

        // Exécution des deux requêtes
        return $db->execute($sql_codes) && $db->execute($sql_scans);
    }

    // Redirection de la configuration du module vers le contrôleur Admin
    public function getContent()
    {
        Tools::redirectAdmin('index.php?controller=AdminUmarexQRCodes&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes'));
    }

    // Déclaration d'une route front pour capter les accès aux QR codes
    public function hookModuleRoutes($params)
    {
        return [
            'module-umarexqrcode-scan' => [
                'controller' => 'scan', // Nom du contrôleur dans /controllers/front/scan.php
                'rule'       => 'qr',
                'keywords'   => [
                    'qr' => ['regexp' => '[_a-zA-Z0-9-]+', 'param' => 'qr'],
                ],
                'params'     => [
                    'fc'     => 'module',
                    'module' => $this->name,
                ],
            ],
        ];
    }

    // Installation de l'onglet "QRCode Admin" dans le menu UMAREX (parent personnalisé)
    private function installTab()
    {
        // Création du groupe parent UMAREX s’il n’existe pas
        $parentClass = 'AdminUmarexParent';
        $id_parent   = Tab::getIdFromClassName($parentClass);

        if (! $id_parent) {
            $parentTab             = new Tab();
            $parentTab->active     = 1;
            $parentTab->class_name = $parentClass;
            foreach (Language::getLanguages(true) as $lang) {
                $parentTab->name[$lang['id_lang']] = 'UMAREX';
            }
            $parentTab->id_parent = 0;          // Menu principal
            $parentTab->icon      = 'settings'; // Icône stable Material Icons
            $parentTab->module    = $this->name;
            $parentTab->add();
            $id_parent = (int) $parentTab->id;
        }

        // Onglet enfant principal : QR Codes Admin
        $childTab             = new Tab();
        $childTab->active     = 1;
        $childTab->class_name = 'AdminUmarexQRCodes';
        foreach (Language::getLanguages(true) as $lang) {
            $childTab->name[$lang['id_lang']] = 'QR Codes Admin';
        }
        $childTab->id_parent = $id_parent;
        $childTab->icon      = 'center_focus_weak'; // QR code-style
        $childTab->module    = $this->name;
        $childTab->add();

        // Onglet enfant fictif pour stabiliser le groupe (non affiché dans menu)
        $fakeTab             = new Tab();
        $fakeTab->active     = 0; // invisible
        $fakeTab->class_name = 'AdminUmarexPlaceholder';
        foreach (Language::getLanguages(true) as $lang) {
            $fakeTab->name[$lang['id_lang']] = '-';
        }
        $fakeTab->id_parent = $id_parent;
        $fakeTab->icon      = 'remove'; // peu importe
        $fakeTab->module    = $this->name;
        $fakeTab->add();

        return true;
    }

    // Fonction pour désinstaller l'onglet dans le back-office
    private function uninstallTab()
    {
        foreach (['AdminUmarexQRCodes', 'AdminUmarexPlaceholder', 'AdminUmarexParent'] as $class) {
            $id_tab = (int) Tab::getIdFromClassName($class);
            if ($id_tab) {
                $tab = new Tab($id_tab);
                $tab->delete();
            }
        }
        return true;
    }

}
