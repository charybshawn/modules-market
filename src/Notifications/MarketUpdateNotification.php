<?php

namespace Cultpantry\Market\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The update email: markets new to the directory, then new dates for markets
 * already on file, each linking to its page. Plain and factual.
 */
class MarketUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $digest  see BuildUpdateDigest::handle()
     */
    public function __construct(public array $digest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->digest;

        $parts = [];
        if ($d['markets'] > 0) {
            $parts[] = $d['markets'].' new '.($d['markets'] === 1 ? 'market' : 'markets');
        }
        if ($d['dated_markets'] > 0) {
            $parts[] = 'new dates for '.$d['dated_markets'].($d['dated_markets'] === 1 ? ' market' : ' markets');
        }

        $message = (new MailMessage)
            ->subject("Farmer's Markets: ".implode(', ', $parts))
            ->greeting("New in the Farmer's Markets directory")
            ->line('This update covers '.implode(' and ', $parts).'.');

        if ($d['new'] !== []) {
            $message->line('**New markets**');
            foreach ($d['new'] as $market) {
                $type = $market['type'] ? ' — '.$this->escape($market['type']) : '';
                $next = $market['next'] ? '. Next: '.$this->escape($market['next']) : '. No upcoming date on file yet';
                $message->line($this->link($market).$type.$next.'.');
            }
            if ($d['more_new'] > 0) {
                $message->line('Plus '.$d['more_new'].' more in the directory.');
            }
        }

        if ($d['dates'] !== []) {
            $message->line('**New dates**');
            foreach ($d['dates'] as $market) {
                $message->line($this->link($market).': '.$this->escape(implode('; ', $market['items'])).'.');
            }
            if ($d['more_dates'] > 0) {
                $message->line('Plus new dates for '.$d['more_dates'].' more '.($d['more_dates'] === 1 ? 'market' : 'markets').'.');
            }
        }

        return $message
            ->action('Open the market list', route('admin.market.index'))
            ->line("You receive this because you have access to the Farmer's Markets section.");
    }

    /** @param  array{name: string, city: ?string, url: string}  $market */
    private function link(array $market): string
    {
        return '['.$this->escape($market['name']).']('.$market['url'].')'.($market['city'] ? ' ('.$this->escape($market['city']).')' : '');
    }

    private function escape(string $text): string
    {
        return addcslashes($text, '[]()*_`\\');
    }
}
