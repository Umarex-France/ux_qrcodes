<?php

/**
 * Contrôleur Admin personnalisé pour la gestion des QR Codes dans le back-office.
 *
 * Ce controller permet :
 * - La création manuelle de QR codes
 * - L'affichage et le tri d'une liste avec pagination native
 * - L'affichage des scans associés
 * - L'export CSV des données
 * - Le basculement du statut actif/inactif
 * - La suppression des codes et des scans liés
 *
 * 🛠️ TODO :
 * 1. Afficher le détail des Scans au clic sur un code dans la liste
 * 2. Scinder les fonctions longues en sous-fonctions claires avec paramètres (ex. HTML rendering)
 */

class AdminUmarexQRCodesController extends ModuleAdminController
{
    /**
     * Constructeur du contrôleur.
     * Configure les propriétés de base pour l'affichage de la liste des QR codes.
     */
    public function __construct()
    {
        $this->bootstrap  = true;            // Active le thème Bootstrap
        $this->table      = 'qrcodes_codes'; // Nom de la table principale
        $this->identifier = 'id';            // Clé primaire
        parent::__construct();               // Appel du constructeur parent
    }

    /**
     * Ajoute les fichiers CSS nécessaires pour le module dans le back-office.
     */
    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);

        if ($this->module instanceof Module) {
            $this->addCSS($this->module->getPathUri() . 'views/css/admin.css');
            $this->addJS($this->module->getPathUri() . 'views/js/qr-code-styling.js');
            $this->addJS($this->module->getPathUri() . 'views/js/umarex-admin.js');
        }
    }

    /**
     * Point d'entrée du contrôleur. Affiche soit la vue principale, soit la vue des scans selon le paramètre GET.
     */
    public function initContent()
    {
        parent::initContent(); // Initialisation standard du contenu

        // Si on souhaite visualiser les scans pour un code spécifique
        if (Tools::isSubmit('update' . $this->table) && Tools::isSubmit('id')) {
            $this->renderEditView((int) Tools::getValue('id'));
            return;
        }
        // Sinon, vue par défaut avec export, formulaire et liste
        $this->renderDefaultView();
    }

    /**
     * Rend la vue principale contenant :
     * - Section d'export
     * - Formulaire de création
     * - Liste des codes QR
     */
    protected function renderDefaultView()
    {
        $html = $this->renderExportSection();    // Bloc d'export CSV
        $html .= $this->renderCreationSection(); // Bloc formulaire de création
        $html .= $this->renderQRCodesSection();  // Liste avec recherche, tri et pagination

        // Affichage d'une erreur SQL si transmise en paramètre (debug)
        if (Tools::getValue('debug_sql_error')) {
            $html .= '<div class="alert alert-danger">Erreur SQL : ' . Tools::safeOutput(Tools::getValue('debug_sql_error')) . '</div>';
        }
        // Injection HTML dans le template via Smarty
        $this->context->smarty->assign(['content' => $html]);
    }

    /**
     * Retourne le bloc HTML du formulaire QR (création ou édition)
     *
     * @param string $titlePanel Titre du panneau
     * @param array|null $code null = création / array = édition
     * @param bool $withActions Affiche les boutons Générer / Exporter
     * @return string
     */
    protected function renderQRCodeForm($titlePanel, $code = null, $withActions = false)
    {
        require_once $this->module->getLocalPath() . 'classes/QRCodeManager.php';

        // Valeurs par défaut pour création
        $ref    = $code['ref'] ?? '';
        $name   = $code['name'] ?? '';
        $url    = $code['url'] ?? '';
        $active = isset($code['active']) ? (bool) $code['active'] : true;
        $id     = isset($code['id']) ? (int) $code['id'] : 0;

        //$qrManager = new QRCodeManager($this->module, $qrLogoURL);
        //$qrExists  = $code ? $qrManager->exists($code) : false;
        //$qrUrl     = $qrExists ? $qrManager->getFileUrl($code) : '';
        $qrLogo  = $this->module->getPathUri() . 'views/img/logo-small.png';
        $baseURL = 'https://umarex.fr/?qr=';

        // Formulaire HTML réutilisable
        return '
        <div class="panel">
            <div class="panel-heading">' . $titlePanel . '</div>
            <form method="post">
                ' . ($id ? '<input type="hidden" name="edit_id" value="' . $id . '">' : '') . '

                <input type="hidden" id="logoURL" value="' . $qrLogo . '">
                <input type="hidden" id="baseURL" value="' . $baseURL . '">

                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <!-- Colonne QR -->
                    <div style="width: auto">
                        <div style="border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                            <div id="qrcode-preview" style="text-align: center; margin-bottom: 15px;"> </div>
                            <div style="text-align: center;">
                                <button type="button" id="btn-telecharger" ' . ($code ? '' : 'disabled') . ' class="btn btn-default"
                                    data-id="' . $id . '"
                                    data-ref="' . htmlspecialchars($ref) . '"
                                    data-url="' . htmlspecialchars($baseURL . $ref) . '"
                                    data-name="' . htmlspecialchars($name) . '"
                                    data-logo="' . $this->module->getPathUri() . 'views/img/logo-small.png">
                                    Exporter
                                </button>
                            </div>
                        </div>
                    </div>


                    <!-- Colonne infos -->
                    <div style="flex: 1;" id="qrcode-infos">
                        <div class="form-group">
                            <label for="codeRef">Référence</label>
                            <input type="text" name="codeRef" id="codeRef" class="form-control" required value="' . htmlspecialchars($ref) . '">
                        </div>

                        <div class="form-group">
                            <label for="codeName">Nom</label>
                            <input type="text" name="codeName" id="codeName" class="form-control" required value="' . htmlspecialchars($name) . '">
                        </div>

                        <div class="form-group">
                            <label for="codeURL">URL du code</label>
                            <input readOnly type="url" name="codeURL" id="codeURL" class="form-control" required value="https://www.umarex.fr/?qr=' . htmlspecialchars($ref) . '">
                        </div>

                        <div class="form-group">
                            <label for="codeRedirection">URL de redirection</label>
                            <input type="url" name="codeRedirection" id="codeRedirection" class="form-control" required value="' . htmlspecialchars($url) . '">
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="active" value="1" ' . ($active ? 'checked' : '') . '> Actif
                            </label>
                        </div>

                        <button type="submit" name="' . ($id ? 'submit_qrcode_edit' : 'submit_qrcode') . '" class="btn btn-' . ($id ? 'primary' : 'success') . '">
                            <i class="icon-' . ($id ? 'save' : 'plus') . '"></i> ' . ($id ? 'Enregistrer les modifications' : 'Ajouter un QR Code') . '
                        </button>

                        ' . ($id ? '<a href="' . self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes') . '" class="btn btn-default"><i class="icon-remove"></i> Annuler</a>' : '') . '
                    </div>
                </div>
            </form>
        </div>';
    }

    /**
     * Affiche le formulaire prérempli pour un code QR + la liste des scans liés.
     *
     * @param int $id ID du code QR à éditer
     */
    protected function renderEditView($id)
    {
        require_once $this->module->getLocalPath() . 'classes/QRCodeManager.php';

        // 🔁 On récupère d’abord le code depuis la base
        $code = Db::getInstance()->getRow(
            'SELECT * FROM ' . _DB_PREFIX_ . 'qrcodes_codes WHERE id = ' . (int) $id
        );

        // 🔐 Sécurité : on vérifie qu’il existe
        if (! $code) {
            Tools::redirectAdmin(self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes'));
        }

        // ✅ On peut maintenant utiliser $code
        $qrManager = new QRCodeManager($this->module);
        $qrExists  = $qrManager->exists($code);
        $qrUrl     = $qrManager->getFileUrl($code);

        // Construction du formulaire prérempli
        $form = '
            <div style="margin: 15px 0;">
                <a href="' . self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes') . '" class="btn btn-default pull-right">
                <i class="icon-arrow-left"></i> Revenir à la liste
                </a>
            </div>
            <div>
                <h2>Code: <strong>' . htmlspecialchars($code['ref']) . ' - ' . htmlspecialchars($code['name']) . '</strong></h2><br>
            </div>';
        $form .= $this->renderQRCodeForm('Edition du Code', $code, true); // mode édition avec actions

        // Ajout de la liste native des scans liés (table HelperList)
        $scansHtml = $this->renderScansListForRef($code['ref'], $code['name']);

        // Bouton de retour à la liste principale
        $buttonBack = '
        <div style="margin: 15px 0;">
            <a href="' . self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes') . '" class="btn btn-default">
             <i class="icon-arrow-left"></i> Revenir à la liste
            </a>
        </div>';

        // Affichage dans Smarty
        $this->context->smarty->assign([
            'content' => $form . $scansHtml . $buttonBack,
        ]);
    }

    /**
     * Affiche la liste des scans pour une référence donnée.
     *
     * @param string $ref  Référence du code
     * @param string $name Nom du code
     * @return string HTML de la liste des scans
     */
    protected function renderScansListForRef($ref, $name = '')
    {
        // Setup de la table
        $this->table      = 'qrcodes_scans';
        $this->identifier = 'id';

        $this->_where           = 'AND a.code_ref = "' . pSQL($ref) . '"';
        $this->_defaultOrderBy  = 'time';
        $this->_defaultOrderWay = 'DESC';

        $this->fields_list = [
            'time' => [
                'title'   => 'Date',
                'type'    => 'datetime',
                'orderby' => true,
            ],
            'ip'   => [
                'title'   => 'IP',
                'orderby' => true,
            ],
            'os'   => [
                'title'   => 'OS',
                'orderby' => true,
            ],
            'nav'  => [
                'title'   => 'Navigateur',
                'orderby' => true,
            ],
            'ua'   => [
                'title'   => 'User-Agent',
                'orderby' => true,
            ],
        ];

        // Récupère les données
        $orderBy  = Tools::getValue($this->table . 'Orderby', 'time');
        $orderWay = Tools::strtoupper(Tools::getValue($this->table . 'Orderway', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $this->getList($this->context->language->id, $orderBy, $orderWay);

        $helper                = new HelperList();
        $helper->module        = $this->module;
        $helper->title         = htmlspecialchars($ref) . ' - ' . htmlspecialchars($name);
        $helper->table         = $this->table;
        $helper->identifier    = $this->identifier;
        $helper->actions       = [];
        $helper->show_toolbar  = false;
        $helper->simple_header = false;
        $helper->token         = Tools::getAdminTokenLite('AdminUmarexQRCodes');
        $helper->currentIndex  = self::$currentIndex;
        $helper->no_link       = true;

        // Genération de la liste
        $scansList = $helper->generateList($this->_list, $this->fields_list);

        // Supprime ligne de filtres car non fonctionnels
        $scansList = preg_replace_callback(
            '#<thead>(.*?)</thead>#is',
            function ($matches) {
                $cleaned = preg_replace('#<tr[^>]*?filter[^>]*?>.*?</tr>#is', '', $matches[1]);
                return '<thead>' . $cleaned . '</thead>';
            },
            $scansList
        );
        //
        return $scansList;
    }

    /**
     * Affiche le bloc d’exportation CSV.
     * Propose deux boutons : un pour les codes, un pour les scans.
     *
     * @return string HTML du bloc export
     */
    protected function renderExportSection()
    {
        return '
        <div class="panel">
            <div class="panel-heading">Exportation CSV</div>
            <form method="post">

                <button type="submit" name="export_type" value="codes" class="btn btn-primary" style="margin-right: 15px;">
                    <i class="icon-download"></i> Télécharger Codes
                </button>

                <button type="submit" name="export_type" value="scans" class="btn btn-primary">
                    <i class="icon-download"></i> Télécharger Scans
                </button>

            </form>
        </div>';
    }

    /**
     * Affiche le formulaire de création de QR Code.
     * Contient les champs : référence, nom, URL, statut actif.
     *
     * @return string HTML du formulaire
     */
    protected function renderCreationSection()
    {
        return $this->renderQRCodeForm("Création d'un Code", null, false); // null = mode création, false = pas de boutons QR
    }

    /**
     * Affiche la liste des QR Codes avec recherche globale, tri, actions.
     *
     * @return string HTML du tableau des QR codes
     */
    protected function renderQRCodesSection()
    {
        // Définition des colonnes pour HelperList
        $this->fields_list = [
            'id'              => [
                'title'      => 'ID',
                'class'      => 'fixed-width-xs',
                'orderby'    => true,
                'filter_key' => 'a.id',
            ],
            'ref'             => [
                'title'      => 'Référence',
                'orderby'    => true,
                'filter_key' => 'a.ref',
            ],
            'name'            => [
                'title'      => 'Nom',
                'orderby'    => true,
                'filter_key' => 'a.name',
            ],
            'url'             => [
                'title'      => 'URL',
                'orderby'    => true,
                'filter_key' => 'a.url',
            ],
            'unique_visitors' => [
                'title'   => 'Visiteurs',
                'orderby' => false,
            ],
            'total_views'     => [
                'title'   => 'Vues',
                'orderby' => false,
            ],
            'active'          => [
                'title'   => 'Actif',
                'type'    => 'bool',
                'active'  => 'toggleStatus',
                'orderby' => false,
            ],
        ];

        // Recherche globale
        $search = trim(Tools::getValue('global_search'));
        if (! empty($search)) {
            $like = pSQL('%' . $search . '%');
            $this->_where .= ' AND (
            a.ref LIKE "' . $like . '" OR
            a.name LIKE "' . $like . '" OR
            a.url LIKE "' . $like . '"
        )';
        }

        // Tri
        $orderBy  = Tools::getValue('qrcodes_codesOrderby', 'id');
        $orderWay = Tools::getValue('qrcodes_codesOrderway', 'DESC');

        // SQL
        $this->_defaultOrderBy  = 'ref';
        $this->_defaultOrderWay = 'ASC';
        $this->_select          = 'a.id, a.ref, a.name, a.url, a.active';
        $this->_join            = '';
        $this->_group           = '';

        // Get list
        $this->getList($this->context->language->id, $orderBy, $orderWay);

        // Calcul Vues et Visiteurs
        foreach ($this->_list as &$row) {
            $ref                = pSQL($row['ref']);
            $row['total_views'] = Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'qrcodes_scans WHERE code_ref = "' . $ref . '"'
            );
            $row['unique_visitors'] = Db::getInstance()->getValue(
                'SELECT COUNT(DISTINCT ip) FROM ' . _DB_PREFIX_ . 'qrcodes_scans WHERE code_ref = "' . $ref . '"'
            );
        }

        // HelperList
        $helper                = new HelperList();
        $helper->module        = $this->module;
        $helper->title         = '';
        $helper->table         = $this->table;
        $helper->identifier    = $this->identifier;
        $helper->actions       = ['reset', 'edit', 'delete'];
        $helper->show_toolbar  = false;
        $helper->simple_header = false;
        $helper->filter        = false;
        $helper->token         = Tools::getAdminTokenLite('AdminUmarexQRCodes');
        $helper->currentIndex  = self::$currentIndex;
        $helper->context       = $this->context;

        $codesList = $helper->generateList($this->_list, $this->fields_list);

        // Suppression de la ligne de filtres par défaut non utilisée
        $codesList = preg_replace_callback(
            '#<thead>(.*?)</thead>#is',
            function ($matches) {
                $cleaned = preg_replace('#<tr[^>]*?filter[^>]*?>.*?</tr>#is', '', $matches[1]);
                return '<thead>' . $cleaned . '</thead>';
            },
            $codesList
        );

        return '
            <div class="panel">
                <div class="panel-heading">Liste des Codes</div>
                <form method="post" style="margin: 10px 15px;">
                    <div class="form-group" style="display: flex; max-width: 300px;">
                        <input type="text" name="global_search" value="' . Tools::safeOutput($search) . '"
                            placeholder="Rechercher un code..." class="form-control">
                        <button type="submit" class="btn btn-primary" style="margin-left:5px;">
                            <i class="icon-search"></i> Rechercher
                        </button>
                    </div>
                </form>
                ' . $codesList . '
            </div>';
    }

    /**
     * Affiche les scans liés à une référence de code QR.
     *
     * @param string $ref  Référence du code
     * @param string $name Nom du code
     * @return string HTML du tableau de scans
     */
    protected function renderScansSection($ref, $name = '')
    {
        $this->table      = 'qrcodes_scans';
        $this->identifier = 'id';

        // Colonnes à afficher
        $this->fields_list = [
            'time' => [
                'title'      => 'Date',
                'type'       => 'datetime',
                'orderby'    => true,
                'filter_key' => 'a.time',
            ],
            'ip'   => [
                'title'      => 'IP',
                'orderby'    => true,
                'filter_key' => 'a.ip',
            ],
            'os'   => [
                'title'      => 'OS',
                'orderby'    => true,
                'filter_key' => 'a.os',
            ],
            'nav'  => [
                'title'      => 'Navigateur',
                'orderby'    => true,
                'filter_key' => 'a.nav',
            ],
            'ua'   => [
                'title'      => 'User-Agent',
                'orderby'    => true,
                'filter_key' => 'a.ua',
            ],
        ];

        // Construction du WHERE pour filtrer uniquement les scans du code
        $this->_where = 'AND a.code_ref = "' . pSQL($ref) . '"';

        // Requête
        $this->_defaultOrderBy  = 'time';
        $this->_defaultOrderWay = 'DESC';

        // Récupération des données
        $this->getList($this->context->language->id);

        // Création du HelperList
        $helper                    = new HelperList();
        $helper->module            = $this->module;
        $helper->title             = 'Scans pour : ' . htmlspecialchars($ref) . ' - ' . htmlspecialchars($name);
        $helper->table             = 'qrcodes_scans';
        $helper->identifier        = 'id';
        $helper->actions           = [];
        $helper->show_toolbar      = false;
        $helper->simple_header     = true;
        $helper->token             = Tools::getAdminTokenLite('AdminUmarexQRCodes');
        $helper->currentIndex      = self::$currentIndex;
        $helper->no_link           = true; // Désactive les liens sur lignes
        $helper->shopLinkType      = '';   // Pas de colonne "shop"
        $helper->colorOnBackground = false;

        // Génération HTML
        $scansList = $helper->generateList($this->_list, $this->fields_list);

        // Supprime ligne de filtres (comme dans la table des codes)
        $scansList = preg_replace_callback(
            '#<thead>(.*?)</thead>#is',
            function ($matches) {
                $cleaned = preg_replace('#<tr[^>]*?filter[^>]*?>.*?</tr>#is', '', $matches[1]);
                return '<thead>' . $cleaned . '</thead>';
            },
            $scansList
        );

        // En-tête de la section
        $html = $scansList;
        return $html;
    }

    /**
     * Gère tous les formulaires POST soumis :
     * - Export CSV
     * - Création de code
     * - Activation/désactivation
     * - Suppression
     */
    public function postProcess()
    {
        parent::postProcess();

        // Reset des scans pour un code
        if (Tools::getIsset('resetCode') && ($id = (int) Tools::getValue('id'))) {
            $this->_processResetCode($id);
        }

        // Export CSV
        if (Tools::isSubmit('export_type')) {
            $this->_processExport(Tools::getValue('export_type'));
        }

        // Création de Code
        if (Tools::isSubmit('submit_qrcode')) {
            $this->_processCreateCode(
                Tools::getValue('codeRef'),
                Tools::getValue('codeName'),
                Tools::getValue('codeRedirection'),
                (int) Tools::getValue('active', 0)
            );
        }

        // Toggle actif/inactif
        if (Tools::isSubmit('toggleStatus' . $this->table)) {
            $this->_processToggleActive((int) Tools::getValue('id'));
        }

        // Suppression
        if (Tools::isSubmit('delete' . $this->table)) {
            $this->_processDeleteCode((int) Tools::getValue('id'));
        }

        // Modification
        if (Tools::isSubmit('submit_qrcode_edit') && Tools::isSubmit('edit_id')) {
            $this->_processEditCode(
                (int) Tools::getValue('edit_id'),
                Tools::getValue('codeRef'),
                Tools::getValue('codeName'),
                Tools::getValue('codeRedirection'),
                (int) Tools::getValue('active', 0)
            );
        }

    }

    /**
     * Réinitialise les scans liés à un code QR.
     * Supprime tous les scans associés à la référence du code QR spécifié.
     *
     * @param int $id ID du code QR
     * @return void
     */
    private function _processResetCode($id)
    {
        // 🔍 On récupère la référence du code QR à partir de son ID
        $ref = Db::getInstance()->getValue(
            'SELECT ref FROM ' . _DB_PREFIX_ . 'qrcodes_codes WHERE id = ' . (int) $id
        );

        // 🔐 Sécurité : vérifier que la ref existe
        if ($ref) {
            // 🧹 Suppression des scans liés à cette ref
            Db::getInstance()->execute(
                'DELETE FROM ' . _DB_PREFIX_ . 'qrcodes_scans WHERE code_ref = "' . pSQL($ref) . '"'
            );
        }

        // 🔁 Redirection vers la liste principale
        Tools::redirectAdmin(
            self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes')
        );
    }

    /**
     * Gère l’exportation CSV des données.
     *
     * @param string $type 'codes' ou 'scans'
     */
    private function _processExport($type)
    {
        $table    = $type === 'codes' ? 'qrcodes_codes' : 'qrcodes_scans';
        $filename = 'export_' . $type . '_' . date('Y-m-d_H-i-s') . '.csv';

        $data = Db::getInstance()->executeS('SELECT * FROM ' . _DB_PREFIX_ . bqSQL($table));

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);
        $output = fopen('php://output', 'w');

        if (! empty($data)) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    /**
     * Crée un nouveau QR Code dans la base.
     *
     * @param string $ref
     * @param string $name
     * @param string $url
     * @param int $active
     */
    private function _processCreateCode($ref, $name, $url, $active)
    {
        $inserted = Db::getInstance()->insert('qrcodes_codes', [
            'ref'    => pSQL($ref),
            'name'   => pSQL($name),
            'url'    => pSQL($url),
            'active' => $active,
        ]);
        //
        Tools::redirectAdmin(
            self::$currentIndex .
            '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes') .
            (! $inserted ? '&debug_sql_error=' . urlencode(Db::getInstance()->getMsgError()) : '')
        );
    }

    /**
     * Active ou désactive un code via toggle.
     *
     * @param int $id ID du code à modifier
     */
    private function _processToggleActive($id)
    {
        if ($id > 0) {
            Db::getInstance()->execute('
                UPDATE ' . _DB_PREFIX_ . 'qrcodes_codes
                SET active = IF(active = 1, 0, 1)
                WHERE id = ' . (int) $id
            );
        }
        Tools::redirectAdmin(self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes'));
    }

    /**
     * Supprime un code et tous ses scans liés.
     *
     * @param int $id ID du code
     */
    public function _processDeleteCode($id)
    {
        $ref = Db::getInstance()->getValue(
            'SELECT ref FROM ' . _DB_PREFIX_ . 'qrcodes_codes WHERE id = ' . (int) $id
        );
        if ($ref) {
            Db::getInstance()->delete('qrcodes_scans', 'code_ref = "' . pSQL($ref) . '"');
        }
        Db::getInstance()->delete('qrcodes_codes', 'id = ' . (int) $id);
        //
        Tools::redirectAdmin(self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes'));
    }

    /**
     * Enregistre les modifications d'un Code
     *
     * @param int $id ID du code
     * @param string $ref Référence du code
     * @param string $name Nom du code
     * @param string $url URL de redirection
     * @param int $active Statut actif (1 ou 0)
     * @return void
     */
    private function _processEditCode($id, $ref, $name, $url, $active)
    {
        $updated = Db::getInstance()->update('qrcodes_codes', [
            'ref'    => pSQL($ref),
            'name'   => pSQL($name),
            'url'    => pSQL($url),
            'active' => $active,
        ], 'id = ' . (int) $id);
        //
        Tools::redirectAdmin(
            self::$currentIndex .
            '&token=' . Tools::getAdminTokenLite('AdminUmarexQRCodes') .
            (! $updated ? '&debug_sql_error=' . urlencode(Db::getInstance()->getMsgError()) : '')
        );
    }

    /**
     * Génère le lien HTML de l'action personnalisée "reset" dans la liste des QR codes.
     * Cette méthode est appelée automatiquement par PrestaShop lorsqu'on déclare
     * l'action 'reset' dans `$helper->actions = ['reset', 'edit', 'delete'];`.
     * Elle permet d'afficher une icône "🔄" (icon-refresh) dans la colonne des actions
     * pour réinitialiser les scans d'un code QR donné.
     *
     * @param string|null $token Jeton CSRF (non utilisé ici car généré en interne)
     * @param int $id ID du code QR sur lequel l'action est appliquée
     * @return string HTML du lien avec l'icône "reset"
     */
    public function displayResetLink($token = null, $id)
    {
        $token = Tools::getAdminTokenLite('AdminUmarexQRCodes');
        $url   = self::$currentIndex . '&id=' . (int) $id . '&resetCode=1&token=' . $token;

        return '
        <a href="' . htmlspecialchars($url) . '"
           title="Réinitialiser les scans"
           onclick="return confirm(\'Êtes-vous sûr de vouloir réinitialiser les scans pour ce code ?\');">
            <i class="icon-refresh"></i>
        </a>';
    }

}
