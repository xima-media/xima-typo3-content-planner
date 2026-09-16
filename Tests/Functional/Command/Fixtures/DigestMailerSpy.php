<?php

declare(strict_types=1);

/*
 * This file is part of the "xima_typo3_content_planner" TYPO3 CMS extension.
 *
 * (c) 2024-2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Xima\XimaTypo3ContentPlanner\Tests\Functional\Command\Fixtures;

use Symfony\Component\Mailer\{Envelope, SentMessage};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\{NullTransport, TransportInterface};
use Symfony\Component\Mime\{Email, RawMessage};
use TYPO3\CMS\Core\Mail\MailerInterface;

/**
 * DigestMailerSpy.
 *
 * Test double for {@see MailerInterface} used by
 * {@see \Xima\XimaTypo3ContentPlanner\Tests\Functional\Command\EmailDigestCommandTest}: records
 * every message handed to {@see self::send()} instead of actually sending it, so the test can
 * assert "one mail per recipient max" and inspect the rendered subject/body.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0-or-later
 */
final class DigestMailerSpy implements MailerInterface
{
    /**
     * @var list<Email>
     */
    public array $sentMessages = [];

    public bool $throwOnSend = false;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if ($this->throwOnSend) {
            throw new TransportException('Simulated transport failure for testing.');
        }

        if ($message instanceof Email) {
            $this->sentMessages[] = $message;
        }
    }

    public function getSentMessage(): ?SentMessage
    {
        return null;
    }

    public function getTransport(): TransportInterface
    {
        return new NullTransport();
    }

    public function getRealTransport(): TransportInterface
    {
        return new NullTransport();
    }
}
