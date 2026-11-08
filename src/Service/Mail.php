<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Courriels transactionnels via Brevo ; sans clé, le message est seulement journalisé. */
final class Mail
{
    public function __construct(private readonly Site $site, private readonly HttpClientInterface $http, private readonly LoggerInterface $logger, private readonly Db $db)
    {
    }

    public function send(string $to, string $subject, string $html): void
    {
        $key = $this->site->env('BREVO_API_KEY');
        if (!$key) {
            $this->logger->info('Courriel non envoyé (BREVO_API_KEY absente) : '.$subject);

            return;
        }
        try {
            $this->http->request('POST', 'https://api.brevo.com/v3/smtp/email', ['timeout' => 5, 'headers' => ['api-key' => $key], 'json' => [
                'sender' => ['name' => 'Thé Tip Top', 'email' => $this->site->env('MAIL_FROM', 'jeu@thetiptop.fr')],
                'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $this->layout($html),
            ]])->getStatusCode();
        } catch (\Throwable $e) {
            $this->logger->error('Échec d\'envoi du courriel : '.$e->getMessage());
        }
    }

    public function gainConfirmation(int $gainId): void
    {
        try {
            $g = $this->db->one('SELECT g.date_limite, l.libelle, p.email, p.prenom FROM gain g JOIN lot l ON l.id=g.lot_id JOIN participant p ON p.id=g.participant_id WHERE g.id=?', [$gainId]);
            if ($g) {
                $this->send($g['email'], 'Votre lot vous attend', '<p>Bonjour '.htmlspecialchars($g['prenom']).',</p><p>Votre code a bien été enregistré. Votre lot : <strong>'.htmlspecialchars($g['libelle']).'</strong>.</p><p>À réclamer avant le '.$this->site->dateFr(new \DateTimeImmutable($g['date_limite'])).'.</p><p><a href="'.$this->site->url().'/mes-participations">Voir mon lot</a></p>');
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
        }
    }

    private function layout(string $body): string
    {
        return '<div style="max-width:560px;margin:auto;font-family:Arial,sans-serif;color:#1A211C"><div style="background:#1B4332;color:#F2F4F0;text-align:center;padding:18px;font-size:24px;font-weight:bold">Thé Tip Top</div><div style="padding:20px">'.$body.'</div><div style="background:#1A211C;color:#D9DCD6;padding:14px;font-size:12px">Thé Tip Top, SA au capital de 150 000 €, 18 rue Léon Frot, 75011 Paris. Ce message est nécessaire à la gestion de votre participation. Projet étudiant fictif : aucun lot réel n\'est distribué.</div></div>';
    }
}
