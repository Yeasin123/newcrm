<?php

namespace App\Services\Lead;

use App\Models\BasicSettings;
use App\Models\EntranceAuth;
use App\Models\EntranceGrpMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Config plumbing for the external Entrance Group blast platform. Ported from
 * the legacy EntranceBlastController — the same entrance_auths JSON layout and
 * the same basic_settings.entrance_delay (milliseconds) / entrance_split
 * behaviour. The blast itself runs client-side against entrancegrp.com.
 */
class EntranceBlastService
{
    /** Store the credential blob for one entrance slot and register its userId. */
    public function saveSlot(int $userId, array $data): void
    {
        $auth = EntranceAuth::singleton();
        $data['userId'] = $userId;

        $ids = $this->userIds($auth);
        if (! $ids->contains(fn ($e) => (int) ($e['userId'] ?? 0) === $userId)) {
            $ids->push(['userId' => $userId]);
        }

        $column = EntranceAuth::SLOTS[$userId] ?? null;
        if ($column) {
            $auth->{$column} = json_encode($data);
        }

        $auth->entrance_userIds = json_encode($ids->values()->all());
        $auth->save();
    }

    public function clearSlot(int $userId): void
    {
        $auth = EntranceAuth::singleton();
        $column = EntranceAuth::SLOTS[$userId] ?? null;

        if ($column && $this->userIds($auth)->contains(fn ($e) => (int) ($e['userId'] ?? 0) === $userId)) {
            $auth->update([$column => null]);
        }
    }

    /** Cache a message group's lines onto an entrance slot; return the lines. */
    public function attachMessageGroup(int $userId, string $uniqueGrpId): Collection
    {
        $messages = EntranceGrpMessage::query()
            ->where('unique_grp_id', $uniqueGrpId)
            ->orderByDesc('id')
            ->get();

        $column = EntranceAuth::MESSAGE_SLOTS[$userId] ?? null;
        if ($column) {
            $auth = EntranceAuth::singleton();
            $auth->{$column} = json_encode($messages);
            $auth->save();
        }

        return $messages;
    }

    /** @param  array<int,string>  $texts */
    public function createMessageGroup(string $grpName, array $texts): string
    {
        $uniqueId = uniqid();

        foreach ($texts as $text) {
            $text = trim((string) $text);
            if ($text === '') {
                continue;
            }

            EntranceGrpMessage::create([
                'grp_name' => $grpName,
                'text' => $text,
                'unique_grp_id' => $uniqueId,
            ]);
        }

        return $uniqueId;
    }

    public function deleteMessageGroup(string $uniqueGrpId): int
    {
        return EntranceGrpMessage::query()->where('unique_grp_id', $uniqueGrpId)->delete();
    }

    /** @return Collection<int,array{unique_grp_id:string, grp_name:string, lines:array<int,string>}> */
    public function messageGroups(): Collection
    {
        return EntranceGrpMessage::query()
            ->orderByDesc('id')
            ->get()
            ->groupBy('unique_grp_id')
            ->map(fn ($rows) => [
                'unique_grp_id' => (string) $rows->first()->unique_grp_id,
                'grp_name' => (string) $rows->first()->grp_name,
                'lines' => $rows->pluck('text')->all(),
            ])
            ->values();
    }

    public function setDelayMinutes(float $minutes): void
    {
        // Legacy: minutes -> seconds -> milliseconds.
        BasicSettings::singleton()->update(['entrance_delay' => (int) round($minutes * 60 * 1000)]);
    }

    public function setCampaignSplit(int $split): void
    {
        BasicSettings::singleton()->update(['entrance_split' => max(1, $split)]);
    }

    /** @return array<int,array{user_id:?int, configured:bool}> keyed by slot userId */
    public function slots(): array
    {
        $auth = EntranceAuth::singleton();
        $out = [];

        foreach (EntranceAuth::SLOTS as $userId => $column) {
            $blob = $auth->{$column} ? json_decode((string) $auth->{$column}, true) : null;
            $out[$userId] = [
                'user_id' => is_array($blob) ? (int) ($blob['userId'] ?? $userId) : null,
                'configured' => ! empty($auth->{$column}),
            ];
        }

        return $out;
    }

    private function userIds(EntranceAuth $auth): Collection
    {
        $decoded = $auth->entrance_userIds ? json_decode((string) $auth->entrance_userIds, true) : [];

        return collect(is_array($decoded) ? $decoded : [])->filter(fn ($e) => is_array($e) && Str::of((string) ($e['userId'] ?? ''))->isNotEmpty());
    }
}
