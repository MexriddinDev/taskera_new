<?php

declare(strict_types=1);

namespace App\Modules\SLA\Domain\Services;

use Illuminate\Support\Facades\DB;

final class PolicyMatcher
{
    public function candidates(int $organizationId): array
    {
        return DB::table('sla_policies as p')
            ->join('sla_policy_versions as v', 'v.id', '=', 'p.published_version_id')
            ->where('p.organization_id', $organizationId)->whereNull('p.deleted_at')
            ->where('p.publication_status', 'ACTIVE')->where('p.is_active', true)
            ->select('v.*')->get()->map(function ($v) {
                $v->config = json_decode($v->config, true, 512, JSON_THROW_ON_ERROR);
                return $v;
            })->all();
    }

    public function match(array $versions, array $ticket, ?string $at = null): ?object
    {
        $at = \Carbon\CarbonImmutable::parse($at ?? 'now');
        $matches = [];
        foreach ($versions as $v) {
            $config = $v->config;
            if ($at < \Carbon\CarbonImmutable::parse($config['effective_from']) ||
                (!empty($config['effective_to']) && $at > \Carbon\CarbonImmutable::parse($config['effective_to'])->endOfDay())) {
                continue;
            }
            $scope = $config['scope'] ?? [];
            $score = 0;
            // Aniqlik og'irliklari: qoida qancha ko'p shartni qamrasa, ballari
            // shuncha yuqori. Xizmat (service) prioritetdan yuqori turadi —
            // xizmat katalogidagi muddat ("Zoom: 15 daq / 1 soat") aynan shu
            // xizmat uchun kelishilgan, umumiy prioritet qoidasidan aniqroq.
            foreach (['category_id' => 64, 'subcategory_id' => 128, 'priority_id' => 32, 'service_id' => 48,
                'department_id' => 8, 'source_id' => 4, 'requester_type' => 2] as $key => $weight) {
                if (!empty($scope[$key])) {
                    if ((string) $scope[$key] !== (string) ($ticket[$key] ?? '')) {
                        continue 2;
                    }
                    $score += $weight;
                }
            }
            $matches[] = ['version' => $v, 'score' => $score, 'priority' => $config['policy_priority'] ?? 0];
        }
        usort($matches, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['priority'] <=> $a['priority']) ?: ($a['version']->sla_policy_id <=> $b['version']->sla_policy_id));
        return $matches[0]['version'] ?? null;
    }

    public function ticketScope(object $ticket): array
    {
        $category = DB::table('categories')->where('id', $ticket->category_id)->first();
        $service = $ticket->service_offering_id ? DB::table('service_offerings')->where('id', $ticket->service_offering_id)->value('service_id') : null;
        $metadata = is_array($ticket->metadata) ? $ticket->metadata : json_decode($ticket->metadata ?? '{}', true);
        return ['category_id' => $category?->parent_id ?: $ticket->category_id,
            'subcategory_id' => $category?->parent_id ? $ticket->category_id : null,
            'priority_id' => $ticket->priority_id, 'service_id' => $service,
            'department_id' => $ticket->department_id, 'source_id' => $ticket->source_id,
            'requester_type' => $metadata['requester_type'] ?? null];
    }
}
