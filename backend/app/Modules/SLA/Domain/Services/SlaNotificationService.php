<?php

declare(strict_types=1);

namespace App\Modules\SLA\Domain\Services;

use App\Modules\Telegram\Infrastructure\Services\TelegramNotifierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

final class SlaNotificationService
{
    /** Policy kanali -> `notification_channels.code`. */
    private const CHANNELS = ['IN_APP' => 'WEB', 'EMAIL' => 'EMAIL', 'TELEGRAM' => 'TELEGRAM'];

    /** Darhol yetkazilmaydigan, navbat orqali yuboriladigan kanallar. */
    private const QUEUED = ['EMAIL', 'TELEGRAM'];

    public function process(): void
    {
        DB::table('sla_escalation_outbox')->whereNull('processed_at')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row) {
                    $outbox = DB::table('sla_escalation_outbox')->where('id', $row->id)->lockForUpdate()->first();
                    if ($outbox->processed_at) return;
                    $instance = DB::table('ticket_sla_instances')->find($outbox->instance_id);
                    $ticket = DB::table('tickets')->find($instance->ticket_id);
                    $rule = json_decode($outbox->rule, true);
                    $ids = $rule['user_ids'];
                    if ($rule['assignee'] && $ticket->assigned_user_id) $ids[] = $ticket->assigned_user_id;
                    $recipients = DB::table('users')->where('organization_id', $instance->organization_id)->whereNull('deleted_at')->whereIn('id', array_unique($ids))->get();
                    foreach ($recipients as $user) {
                        $title = "SLA {$rule['threshold']}%: {$ticket->ticket_no}";
                        $body = "{$ticket->subject}\n{$instance->metric} — {$instance->status}\nDeadline: {$instance->due_at}\n".rtrim(config('app.frontend_url', config('app.url')), '/')."/task/{$ticket->id}";
                        $notification = DB::table('notifications')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => $instance->organization_id,
                            'event_type' => 'SLA_ESCALATED', 'notifiable_type' => 'App\\Modules\\Identity\\Infrastructure\\Eloquent\\User', 'notifiable_id' => $user->id,
                            'template_code' => 'SLA_ESCALATED', 'title' => $title, 'body' => $body,
                            'data' => json_encode(['ticket_id' => $ticket->id, 'instance_id' => $instance->id, 'threshold' => $rule['threshold']]),
                            'correlation_id' => (string) Str::uuid(), 'created_at' => now()]);
                        foreach ($rule['channels'] as $channel) {
                            $code = self::CHANNELS[$channel] ?? null;
                            $channelId = $code ? DB::table('notification_channels')->where('code', $code)->value('id') : null;
                            if (!$channelId) throw new \RuntimeException('Missing SLA notification channel '.$channel);
                            // Telegramda manzil — foydalanuvchi id'si; chat id'ni
                            // TelegramNotifierService tasdiqlangan hisobdan topadi.
                            $queued = in_array($channel, self::QUEUED, true);
                            DB::table('notification_deliveries')->insert(['public_id' => (string) Str::uuid(), 'organization_id' => $instance->organization_id,
                                'notification_id' => $notification, 'channel_id' => $channelId, 'recipient' => $channel === 'EMAIL' ? ($user->email ?? '') : (string) $user->id,
                                'status' => $queued ? 'PENDING' : 'DELIVERED', 'delivered_at' => $queued ? null : now(),
                                'next_attempt_at' => $queued ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
                        }
                    }
                    DB::table('sla_escalation_outbox')->where('id', $outbox->id)->update(['processed_at' => now()]);
                }, 3);
            }
        });
        $queued = DB::table('notification_channels')->whereIn('code', self::QUEUED)->pluck('id')->all();
        if (!$queued) return;
        DB::table('notification_deliveries as d')->join('notifications as n', 'n.id', '=', 'd.notification_id')
            ->where('n.event_type', 'SLA_ESCALATED')->whereIn('d.channel_id', $queued)->where('d.status', 'PENDING')
            ->where('d.next_attempt_at', '<=', now())->select('d.id')->orderBy('d.id')->chunkById(50, function ($rows) {
                foreach ($rows as $row) $this->deliver($row->id);
            }, 'd.id', 'id');
    }

    private function deliver(int $id): void
    {
        DB::transaction(function () use ($id) {
            $delivery = DB::table('notification_deliveries')->where('id', $id)->lockForUpdate()->first();
            if ($delivery->status !== 'PENDING') return;
            $notification = DB::table('notifications')->find($delivery->notification_id);
            $code = DB::table('notification_channels')->where('id', $delivery->channel_id)->value('code');
            try {
                if ($code === 'TELEGRAM') {
                    $sent = app(TelegramNotifierService::class)->sendToUser((int) $delivery->organization_id,
                        (int) $delivery->recipient, $notification->title."\n\n".$notification->body);
                    // Hisobi ulanmagan yoki bot javob bermagan bo'lsa — qayta urinish.
                    if (!$sent) throw new \RuntimeException('Telegram hisobi ulanmagan yoki xabar yuborilmadi.');
                } else {
                    if (!filter_var($delivery->recipient, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Recipient has no valid email address.');
                    Mail::raw($notification->body, fn ($m) => $m->to($delivery->recipient)->subject($notification->title));
                }
                DB::table('notification_deliveries')->where('id', $id)->update(['status' => 'SENT', 'sent_at' => now(),
                    'attempt_count' => $delivery->attempt_count + 1, 'next_attempt_at' => null, 'updated_at' => now()]);
            } catch (\Throwable $e) {
                $attempt = $delivery->attempt_count + 1;
                DB::table('notification_deliveries')->where('id', $id)->update(['status' => $attempt >= 5 ? 'FAILED' : 'PENDING',
                    'attempt_count' => $attempt, 'next_attempt_at' => now()->addMinutes(2 ** $attempt), 'failed_at' => now(),
                    'error_message' => Str::limit($e->getMessage(), 1000), 'updated_at' => now()]);
            }
        });
    }
}
