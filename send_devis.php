<?php
/**
 * send_devis.php — ECO-BGR Multi Services
 * Backend d'envoi de devis par email via PHP mail()
 * Déploiement : cPanel LWS (ecobgr.fr)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération — accepte les deux conventions de nommage (JS renommé ET noms bruts HTML)
function post($keys) {
    foreach ((array)$keys as $k) {
        $v = strip_tags(trim($_POST[$k] ?? ''));
        if ($v !== '') return $v;
    }
    return '';
}

$prenom     = post('prenom');
$nom_raw    = post('nom');
$nom        = trim($prenom ? "$prenom $nom_raw" : $nom_raw) ?: 'Non renseigné';
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$telephone  = post(['telephone', 'tel'])       ?: 'Non renseigné';
$service    = post(['service',   'type'])      ?: 'Non précisé';
$superficie = post(['superficie','surface'])   ?: 'Non renseignée';
$frequence  = post(['frequence', 'freq'])      ?: 'Non renseignée';
$message    = post('message')                  ?: 'Non renseigné';
$adresse    = post('adresse')                  ?: 'Non renseignée';

// Validation minimale
if (empty($nom) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$destinataire = 'devis@ecobgr.fr';
$sujet        = "=?UTF-8?B?" . base64_encode("Nouveau devis ECO-BGR — $service — $nom") . "?=";

$corps  = "=== NOUVEAU DEVIS ECO-BGR ===\r\n\r\n";
$corps .= "Nom       : $nom\r\n";
$corps .= "Email     : $email\r\n";
$corps .= "Téléphone : $telephone\r\n";
$corps .= "Adresse   : $adresse\r\n\r\n";
$corps .= "Service   : $service\r\n";
$corps .= "Superficie: $superficie\r\n";
$corps .= "Fréquence : $frequence\r\n\r\n";
$corps .= "Message   :\r\n$message\r\n\r\n";
$corps .= "---\r\nEnvoyé le " . date('d/m/Y à H:i') . "\r\n";


// ⚠️ LWS : le From DOIT être un email du domaine hébergé
$headers  = "From: devis@ecobgr.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$envoye = mail($destinataire, $sujet, $corps, $headers);

// Log en cas d'échec (visible dans cPanel > Fichiers > mail_errors.log)
if (!$envoye) {
    $log  = date('Y-m-d H:i:s') . " | ECHEC mail()\n";
    $log .= "  From    : devis@ecobgr.fr\n";
    $log .= "  To      : $destinataire\n";
    $log .= "  Nom     : $nom\n";
    $log .= "  Email   : $email\n";
    $log .= "  Erreur  : " . error_get_last()['message'] ?? 'inconnue';
    $log .= "\n---\n";
    file_put_contents(__DIR__ . '/mail_errors.log', $log, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'envoi"]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Devis envoyé avec succès']);
