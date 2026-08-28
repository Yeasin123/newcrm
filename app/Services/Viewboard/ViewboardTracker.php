<?php

namespace App\Services\Viewboard;

use App\Models\Client;
use App\Models\ClientButtonTrack;
use App\Models\ClientRenewButtonTrack;
use App\Models\ClientRenewTrackAction;
use App\Models\ClientTrackAction;
use App\Models\Note;
use Illuminate\Support\Carbon;

/**
 * Shared write-side for the Payment Failed and Renewal call viewboards:
 * board notes (`client_button_tracks` / `client_renew_button_tracks` + a
 * `notes` row) and rep action rows (`client_track_actions` /
 * `client_renew_track_actions`, one string-timestamp column per action).
 */
class ViewboardTracker
{
    /** @param 'failed'|'renewal' $board */
    public function note(string $board, Client $client, string $text, ?string $statusLabel = null): void
    {
        $trackModel = $board === 'renewal' ? ClientRenewButtonTrack::class : ClientButtonTrack::class;

        $trackModel::create([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            'create_date' => now()->toDateTimeString(),
            'status' => null,
            'button_text' => $statusLabel ?? 'Note',
            'comments' => $text,
        ]);

        Note::create([
            'client_id' => (string) $client->id,
            'user_id' => (string) auth()->id(),
            'notes' => $text,
            'date_time' => now()->toDateTimeString(),
            'status' => 1,
        ]);
    }

    /**
     * @param  'failed'|'renewal'  $board
     * @param  string  $action  one of click_to_call|save|send_sms|send_mail|voice|convert_to_client
     */
    public function action(string $board, Client $client, string $action, ?string $statusLabel = null): void
    {
        $model = $board === 'renewal' ? ClientRenewTrackAction::class : ClientTrackAction::class;
        $columns = $board === 'renewal' ? ClientRenewTrackAction::ACTIONS : ClientTrackAction::ACTIONS;

        $model::create([
            'client_id' => $client->id,
            'user_id' => auth()->id(),
            $action => in_array($action, $columns, true) ? Carbon::now()->toDateTimeString() : null,
            'client_status' => $statusLabel,
            'STORE_DATE' => Carbon::now(),
        ]);
    }
}
