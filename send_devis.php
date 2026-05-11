<?php
/**
 * send_devis.php — ECO-BGR Multi Services
 * Backend d'envoi de devis par email via PHP mail()
 * Déploiement : cPanel LWS (ecobgr.fr)
 */

// ── CORS : autoriser uniquement le domaine ECO-BGR ────────────────────────────
$allowed_origins = ['https://ecobgr.fr', 'https://www.ecobgr.fr'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Pas d'en-tête CORS si l'origine n'est pas autorisée
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

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

// ── Anti-spam honeypot ────────────────────────────────────────────────────────
// Le champ "ecobgr_hp" est invisible côté HTML : un bot le remplit, un humain non.
// Nom délibérément non-générique pour éviter l'autofill navigateur.
if (!empty($_POST['ecobgr_hp'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
    exit;
}

// Anti-spam délai : désactivé — trop agressif en prod (UX).
// La protection honeypot (champ "website") est suffisante.

// ── Récupération et nettoyage ─────────────────────────────────────────────────
function post($keys) {
    foreach ((array)$keys as $k) {
        $v = trim(strip_tags($_POST[$k] ?? ''));
        if ($v !== '') return $v;
    }
    return '';
}

$prenom     = post('prenom');
$nom_raw    = post('nom');
$nom        = trim($prenom ? "$prenom $nom_raw" : $nom_raw) ?: 'Non renseigné';
$email_raw  = trim($_POST['email'] ?? '');
$email      = filter_var($email_raw, FILTER_SANITIZE_EMAIL);
$telephone  = post(['telephone', 'tel']) ?: 'Non renseigné';
$service    = post(['service', 'type'])  ?: 'Non précisé';
$superficie = post(['superficie', 'surface']) ?: 'Non renseignée';
$frequence  = post(['frequence', 'freq'])     ?: 'Non renseignée';
$message    = post('message') ?: 'Non renseigné';
$adresse    = post('adresse') ?: 'Non renseignée';
$rgpd       = !empty($_POST['rgpd']);

// ── Validation stricte ────────────────────────────────────────────────────────
$errors = [];

// Prénom/nom : lettres, espaces, tirets, apostrophes — 2 à 60 caractères
if ($prenom !== '' && !preg_match('/^[\p{L}\s\'\-]{2,60}$/u', $prenom)) {
    $errors[] = 'Prénom invalide — uniquement lettres, espaces, apostrophes ou tirets';
}
if ($nom_raw !== '' && !preg_match('/^[\p{L}\s\'\-]{2,60}$/u', $nom_raw)) {
    $errors[] = 'Nom invalide — uniquement lettres, espaces, apostrophes ou tirets';
}

// Email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Email invalide';
}

// Téléphone (optionnel) — si renseigné, doit ressembler à un numéro
if ($telephone !== 'Non renseigné' && !preg_match('/^[\d\s\+\.\-\(\)]{7,20}$/', $telephone)) {
    $errors[] = 'Numéro de téléphone invalide';
}

// Nom complet obligatoire
if (empty(trim($prenom . $nom_raw))) {
    $errors[] = 'Nom obligatoire';
}

// Service obligatoire
if (empty($service) || $service === 'Non précisé') {
    $errors[] = 'Type de prestation obligatoire';
}

// Message : optionnel — si renseigné, max 2000 caractères
$msg_len = mb_strlen($message);
if ($msg_len > 2000) {
    $errors[] = 'Message trop long (maximum 2000 caractères)';
}

// Consentement RGPD
if (!$rgpd) {
    $errors[] = 'Consentement RGPD obligatoire';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// ── Échappement HTML pour injection dans l'email ──────────────────────────────
function h($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$h_nom        = h($nom);
$h_email      = h($email);
$h_telephone  = h($telephone);
$h_service    = h($service);
$h_superficie = h($superficie);
$h_frequence  = h($frequence);
$h_adresse    = h($adresse);
$h_message    = nl2br(h($message));

// ── Email à ECO-BGR ───────────────────────────────────────────────────────────
$destinataire = 'devis@ecobgr.fr';
$sujet_admin  = "=?UTF-8?B?" . base64_encode("Nouveau devis ECO-BGR — $service — $nom") . "?=";
$date_envoi   = date('d/m/Y à H:i');

$corps_admin = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Devis à traiter</title>
</head>
<body style="margin:0;padding:0;background:#f0f5fc;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f5fc;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(13,45,107,0.12);">

          <!-- EN-TÊTE -->
          <tr>
            <td style="background:#0d2d6b;padding:28px 32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#a8c8f8;letter-spacing:1px;text-transform:uppercase;">ECO-BGR Multi Services</p>
              <h1 style="margin:8px 0 0;font-size:22px;font-weight:700;color:#ffffff;">Nouveau devis à traiter</h1>
            </td>
          </tr>

          <!-- BANDEAU SERVICE -->
          <tr>
            <td style="background:#e8f0fb;padding:14px 32px;border-bottom:1px solid #c8d8f4;">
              <p style="margin:0;font-size:12px;color:#5a7096;">Service demandé</p>
              <p style="margin:4px 0 0;font-size:17px;font-weight:700;color:#1a4fa0;">$h_service</p>
            </td>
          </tr>

          <!-- CORPS -->
          <tr>
            <td style="padding:28px 32px;">

              <h2 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#0d2d6b;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e8f0fb;padding-bottom:6px;">Contact</h2>
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;width:130px;">Nom</td>
                  <td style="padding:5px 0;font-size:14px;color:#1c2d4a;font-weight:600;">$h_nom</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;">Email</td>
                  <td style="padding:5px 0;font-size:14px;"><a href="mailto:$h_email" style="color:#1a4fa0;text-decoration:none;">$h_email</a></td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;">Téléphone</td>
                  <td style="padding:5px 0;font-size:14px;color:#1c2d4a;">$h_telephone</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;">Adresse</td>
                  <td style="padding:5px 0;font-size:14px;color:#1c2d4a;">$h_adresse</td>
                </tr>
              </table>

              <h2 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#0d2d6b;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e8f0fb;padding-bottom:6px;">Détails de la prestation</h2>
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;width:130px;">Superficie</td>
                  <td style="padding:5px 0;font-size:14px;color:#1c2d4a;">$h_superficie</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#5a7096;">Fréquence</td>
                  <td style="padding:5px 0;font-size:14px;color:#1c2d4a;">$h_frequence</td>
                </tr>
              </table>

              <h2 style="margin:0 0 14px;font-size:13px;font-weight:700;color:#0d2d6b;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e8f0fb;padding-bottom:6px;">Message</h2>
              <div style="background:#f0f5fc;border-left:4px solid #1a4fa0;padding:14px 16px;border-radius:0 6px 6px 0;font-size:14px;color:#1c2d4a;line-height:1.7;">
                $h_message
              </div>
            </td>
          </tr>

          <!-- PIED -->
          <tr>
            <td style="background:#e8f0fb;padding:14px 32px;text-align:center;border-top:1px solid #c8d8f4;">
              <p style="margin:0;font-size:11px;color:#5a7096;">Demande reçue le $date_envoi — ECO-BGR Multi Services</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

// ── Email de confirmation au client ───────────────────────────────────────────
$sujet_client = "=?UTF-8?B?" . base64_encode("Votre demande de devis ECO-BGR a bien été reçue") . "?=";

$corps_client = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Confirmation de votre demande</title>
</head>
<body style="margin:0;padding:0;background:#f0f5fc;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f5fc;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(13,45,107,0.12);">

          <!-- EN-TÊTE -->
          <tr>
            <td style="background:#0d2d6b;padding:32px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#a8c8f8;letter-spacing:1px;text-transform:uppercase;">ECO-BGR Multi Services</p>
              <h1 style="margin:10px 0 4px;font-size:22px;font-weight:700;color:#ffffff;">Demande bien reçue ✓</h1>
              <p style="margin:0;font-size:13px;color:#a8c8f8;">Nettoyage Professionnel — Île-de-France</p>
            </td>
          </tr>

          <!-- CORPS -->
          <tr>
            <td style="padding:32px;">
              <p style="margin:0 0 16px;font-size:15px;color:#1c2d4a;">Bonjour <strong>$h_nom</strong>,</p>
              <p style="margin:0 0 16px;font-size:14px;color:#1c2d4a;line-height:1.7;">
                Nous avons bien reçu votre demande de devis pour le service <strong style="color:#1a4fa0;">$h_service</strong>.<br>
                Notre équipe l'examine et vous répondra dans les <strong>24h ouvrées</strong>.
              </p>

              <!-- Récap -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f5fc;border-radius:6px;padding:16px;margin:20px 0;">
                <tr><td style="font-size:12px;font-weight:700;color:#0d2d6b;text-transform:uppercase;letter-spacing:0.8px;padding-bottom:12px;">Récapitulatif de votre demande</td></tr>
                <tr>
                  <td style="font-size:13px;color:#5a7096;padding:4px 0;width:140px;">Service</td>
                  <td style="font-size:13px;color:#1c2d4a;font-weight:600;">$h_service</td>
                </tr>
                <tr>
                  <td style="font-size:13px;color:#5a7096;padding:4px 0;">Superficie</td>
                  <td style="font-size:13px;color:#1c2d4a;">$h_superficie</td>
                </tr>
                <tr>
                  <td style="font-size:13px;color:#5a7096;padding:4px 0;">Fréquence</td>
                  <td style="font-size:13px;color:#1c2d4a;">$h_frequence</td>
                </tr>
              </table>

              <p style="margin:0 0 8px;font-size:14px;color:#1c2d4a;line-height:1.7;">
                En attendant, n'hésitez pas à nous contacter directement :
              </p>
              <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:4px 0;font-size:13px;color:#1c2d4a;">📞 <a href="tel:+33758881415" style="color:#1a4fa0;text-decoration:none;">+33 7 58 88 14 15</a></td>
                </tr>
                <tr>
                  <td style="padding:4px 0;font-size:13px;color:#1c2d4a;">📞 <a href="tel:+33622982424" style="color:#1a4fa0;text-decoration:none;">+33 6 22 98 24 24</a></td>
                </tr>
                <tr>
                  <td style="padding:4px 0;font-size:13px;color:#1c2d4a;">✉️ <a href="mailto:devis@ecobgr.fr" style="color:#1a4fa0;text-decoration:none;">devis@ecobgr.fr</a></td>
                </tr>
                <tr>
                  <td style="padding:4px 0;font-size:13px;color:#1c2d4a;">🕐 Lun – Sam · 7h – 20h</td>
                </tr>
              </table>

              <p style="margin:0;font-size:13px;color:#5a7096;line-height:1.6;">
                Merci de votre confiance.<br>
                <strong style="color:#0d2d6b;">L'équipe ECO-BGR Multi Services</strong>
              </p>
            </td>
          </tr>

          <!-- PIED -->
          <tr>
            <td style="background:#e8f0fb;padding:14px 32px;text-align:center;border-top:1px solid #c8d8f4;">
              <p style="margin:0;font-size:11px;color:#5a7096;">
                ECO-BGR Multi Services — <a href="https://ecobgr.fr" style="color:#1a4fa0;text-decoration:none;">ecobgr.fr</a> — Île-de-France<br>
                🌿 Produits écologiques certifiés
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

// ── Envoi des emails ──────────────────────────────────────────────────────────
$headers_admin  = "From: devis@ecobgr.fr\r\n";
$headers_admin .= "Reply-To: $email\r\n";
$headers_admin .= "MIME-Version: 1.0\r\n";
$headers_admin .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers_admin .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$envoye_admin = mail($destinataire, $sujet_admin, $corps_admin, $headers_admin);

// Email de confirmation au client
$envoye_client = false;
if ($envoye_admin) {
    $headers_client  = "From: devis@ecobgr.fr\r\n";
    $headers_client .= "Reply-To: devis@ecobgr.fr\r\n";
    $headers_client .= "MIME-Version: 1.0\r\n";
    $headers_client .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers_client .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $envoye_client = mail($email, $sujet_client, $corps_client, $headers_client);
}

// ── Réponse ───────────────────────────────────────────────────────────────────
if (!$envoye_admin) {
    // Log en cas d'échec
    $log  = date('Y-m-d H:i:s') . " | ECHEC mail() admin\n";
    $log .= "  Nom   : $nom\n";
    $log .= "  Email : $email\n";
    $log .= "  Erreur: " . (error_get_last()['message'] ?? 'inconnue') . "\n---\n";
    file_put_contents(__DIR__ . '/mail_errors.log', $log, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'envoi"]);
    exit;
}

echo json_encode([
    'success'        => true,
    'message'        => 'Devis envoyé avec succès',
    'confirmation'   => $envoye_client,
]);
