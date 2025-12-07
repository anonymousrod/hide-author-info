<?php
/**
 * Plugin Name: Hide Author Info & Lock User Endpoints
 * Description: Empêche l'affichage des infos auteur (nom, email) et bloque l'exposition via REST, archives auteur et enumeration ?author=.
 * Version: 1.0
 * Author: rodrigu  for User
 * License: GPLv2+
 */

// Bloque l'accès aux endpoints REST users
add_filter('rest_endpoints', function($endpoints) {
    if (isset($endpoints['/wp/v2/users'])) {
        unset($endpoints['/wp/v2/users']);
    }
    if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
        unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
    }
    // bloque aussi les routes de users/me si besoin
    if (isset($endpoints['/wp/v2/users/me'])) {
        unset($endpoints['/wp/v2/users/me']);
    }
    return $endpoints;
});

// Empêche exposition d'informations sensibles dans la réponse REST d'un post (champ author)
add_filter('rest_prepare_post', function($response, $post, $request) {
    if (isset($response->data['author'])) {
        // remplacer par vide ou 0 selon préférence
        $response->data['author'] = '';
    }
    return $response;
}, 10, 3);

// Désactive XML-RPC (souvent source d'info)
add_filter('xmlrpc_enabled', '__return_false');

// Empêche les archives auteur d'être accessibles (redirection ou 404 selon choix)
add_action('template_redirect', function() {
    if (is_author()) {
        // Redirige vers la page d'accueil (301). Pour 404 : use wp_die or status_header(404); include get_query_template('404');
        wp_redirect(home_url(), 301);
        exit;
    }
});

// Empêche l'énumération des auteurs via ?author= ou /?author=1
add_action('parse_request', function($wp) {
    // Si la variable author est présente dans l'URL ou dans query_vars
    if (!empty($wp->query_vars['author']) || isset($_GET['author'])) {
        // Renvoie 403 (ou rediriger)
        status_header(403);
        nocache_headers();
        wp_die(__('Accès interdit.'), '', array('response' => 403));
        exit;
    }
});

// Filtre global pour masquer les métadonnées d'auteur quand les fonctions natives sont appelées
add_filter('get_the_author_meta', function($value, $field, $user_id) {
    // Champs à neutraliser : email, nicename, display_name, user_login
    $blocked = array('user_email', 'email', 'user_nicename', 'display_name', 'user_login', 'nickname');
    if (in_array($field, $blocked, true)) {
        return '';
    }
    return $value;
}, 10, 3);

// Masque le nom affiché par the_author() et fonctions liées
add_filter('the_author', function($display_name) {
    return ''; // ou retourner 'Auteur' si tu veux masquer mais laisser une valeur
});
add_filter('get_the_author_display_name', function($display_name) {
    return '';
});
add_filter('get_the_author', function($display_name) {
    return '';
});
add_filter('author_link', function($link, $author_id, $author_nicename) {
    return home_url(); // neutralise le lien auteur
}, 10, 3);

// Supprime l'auteur dans les flux RSS
add_filter('the_author', function($n){ return ''; });

// Empêche l'API oEmbed d'exposer des infos d'auteur (si présent)
add_filter('oembed_response_data', function($data) {
    if (is_array($data)) {
        if (isset($data['author_name'])) $data['author_name'] = '';
        if (isset($data['author_url'])) $data['author_url'] = '';
    } elseif (is_object($data)) {
        if (isset($data->author_name)) $data->author_name = '';
        if (isset($data->author_url)) $data->author_url = '';
    }
    return $data;
});

// Retire l'utilisateur de l'export de l'objet WP_User pour JS (wp_prepare_user_for_js)
// Si une extension expose toujours l'email via rest_prepare_user, on peut les bloquer ici :
add_filter('wp_prepare_user_for_js', function($prepared_user, $user, $field) {
    // vider champs sensibles
    $suppress = array('email', 'user_email', 'nicename', 'display_name', 'roles', 'slug');
    foreach ($suppress as $f) {
        if (isset($prepared_user[$f])) $prepared_user[$f] = '';
    }
    return $prepared_user;
}, 10, 3);

// Optionnel : retire l'affichage author meta dans head si le thème l'ajoute (meta generator / relational link)
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'feed_links', 2);

// Petit en-tête d'administration : message dans plugins.php
add_action('admin_notices', function(){
    if (!current_user_can('manage_options')) return;
    echo '<div class="notice notice-info"><p><strong>Hide Author Info</strong> actif — les noms/emails d\'auteur et endpoints utilisateurs publics sont restreints.</p></div>';
});
