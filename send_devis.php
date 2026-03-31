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

$date_envoi = date('d/m/Y à H:i');

$corps = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Devis à traiter</title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:30px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

          <!-- EN-TÊTE -->
          <tr>
            <td style="background:#1a7a4a;padding:28px 32px;text-align:center;">
              <p style="margin:0;font-size:13px;color:#a8e6c1;letter-spacing:1px;text-transform:uppercase;">ECO-BGR Multi Services</p>
              <h1 style="margin:8px 0 0;font-size:24px;font-weight:700;color:#ffffff;letter-spacing:0.5px;">Devis à traiter</h1>
            </td>
          </tr>

          <!-- BANDEAU SERVICE -->
          <tr>
            <td style="background:#e8f5ee;padding:14px 32px;border-bottom:1px solid #c8e6d4;">
              <p style="margin:0;font-size:13px;color:#555;">Service demandé</p>
              <p style="margin:4px 0 0;font-size:17px;font-weight:700;color:#1a7a4a;">$service</p>
            </td>
          </tr>

          <!-- CORPS -->
          <tr>
            <td style="padding:28px 32px;">

              <!-- CONTACT -->
              <h2 style="margin:0 0 14px;font-size:14px;font-weight:700;color:#1a7a4a;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e0ede6;padding-bottom:6px;">Contact</h2>
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;width:130px;">Nom</td>
                  <td style="padding:5px 0;font-size:14px;color:#222;font-weight:600;">$nom</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;">Email</td>
                  <td style="padding:5px 0;font-size:14px;color:#1a7a4a;"><a href="mailto:$email" style="color:#1a7a4a;text-decoration:none;">$email</a></td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;">Téléphone</td>
                  <td style="padding:5px 0;font-size:14px;color:#222;">$telephone</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;">Adresse</td>
                  <td style="padding:5px 0;font-size:14px;color:#222;">$adresse</td>
                </tr>
              </table>

              <!-- DÉTAILS PRESTATION -->
              <h2 style="margin:0 0 14px;font-size:14px;font-weight:700;color:#1a7a4a;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e0ede6;padding-bottom:6px;">Détails de la prestation</h2>
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;width:130px;">Superficie</td>
                  <td style="padding:5px 0;font-size:14px;color:#222;">$superficie</td>
                </tr>
                <tr>
                  <td style="padding:5px 0;font-size:13px;color:#888;">Fréquence</td>
                  <td style="padding:5px 0;font-size:14px;color:#222;">$frequence</td>
                </tr>
              </table>

              <!-- MESSAGE -->
              <h2 style="margin:0 0 14px;font-size:14px;font-weight:700;color:#1a7a4a;text-transform:uppercase;letter-spacing:0.8px;border-bottom:2px solid #e0ede6;padding-bottom:6px;">Message</h2>
              <div style="background:#f9fbfa;border-left:4px solid #1a7a4a;padding:14px 16px;border-radius:0 6px 6px 0;font-size:14px;color:#333;line-height:1.6;margin-bottom:8px;">
                $message
              </div>

            </td>
          </tr>

          <!-- PIED DE PAGE -->
          <tr>
            <td style="background:#f0f4f2;padding:16px 32px;text-align:center;border-top:1px solid #dce8e0;">
              <p style="margin:0;font-size:12px;color:#999;">Demande reçue le $date_envoi — ECO-BGR Multi Services</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

// ⚠️ LWS : le From DOIT être un email du domaine hébergé
$headers  = "From: devis@ecobgr.fr\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
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
