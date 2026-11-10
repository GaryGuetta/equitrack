<?php

namespace App\Monitoring;

use App\Entity\Alert;
use App\Enum\AlertSeverity;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Prévient le propriétaire par e-mail. Les alertes restent visibles dans l'application même si l'envoi échoue.
 */
class AlertNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'ALERT_MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function notify(Alert $alert): void
    {
        if (AlertSeverity::Info === $alert->getSeverity()) {
            return;
        }

        $owner = $alert->getHorse()->getOwner();
        $email = (new TemplatedEmail())
            ->from($this->from)
            ->to($owner->getEmail())
            ->subject(\sprintf('[%s] %s — %s', $alert->getSeverity()->label(), $alert->getHorse()->getName(), $alert->getType()->label()))
            ->htmlTemplate('emails/alert.html.twig')
            ->context(['alert' => $alert, 'firstName' => $owner->getFirstName()]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Envoi de l\'alerte {id} impossible : {message}', ['id' => $alert->getId(), 'message' => $e->getMessage()]);
        }
    }
}
