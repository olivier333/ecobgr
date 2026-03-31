<?php
/**
 * send_devis.php — ECO-BGR Multi Services
 * Backend d'envoi de devis par email via PHP mail()
 * Déploiement : cPanel LWS (ecobgr.fr)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://ecobgr.fr');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupération et nettoyage des champs
$nom        = strip_tags(trim($_POST['nom']        ?? ''));
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$telephone  = strip_tags(trim($_POST['telephone']  ?? ''));
$service    = strip_tags(trim($_POST['service']    ?? ''));
$superficie = strip_tags(trim($_POST['superficie'] ?? ''));
$frequence  = strip_tags(trim($_POST['frequence']  ?? ''));
$message    = strip_tags(trim($_POST['message']    ?? ''));
$adresse    = strip_tags(trim($_POST['adresse']    ?? ''));

// Validation minimale
if (empty($nom) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides']);
    exit;
}

$destinataire = 'devis@ecobgr.fr';
$sujet        = "Nouveau devis ECO-BGR — $service — $nom";

$corps  = "=== NOUVEAU DEVIS ECO-BGR ===\n\n";
$corps .= "Nom       : $nom\n";
$corps .= "Email     : $email\n";
$corps .= "Téléphone : $telephone\n";
$corps .= "Adresse   : $adresse\n\n";
$corps .= "Service   : $service\n";
$corps .= "Superficie: $superficie\n";
$corps .= "Fréquence : $frequence\n\n";
$corps .= "Message   :\n$message\n\n";
$corps .= "---\nEnvoyé le " . date('d/m/Y à H:i') . "\n";

$headers  = "From: noreply@ecobgr.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$envoye = mail($destinataire, $sujet, $corps, $headers);

if ($envoye) {
    echo json_encode(['success' => true, 'message' => 'Devis envoyé avec succès']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'envoi"]);
}
