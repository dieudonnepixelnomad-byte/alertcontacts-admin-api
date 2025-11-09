<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\UserDataExported;

class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = [
            'user' => $this->user->toArray(),
            'safe_zones' => $this->user->safeZones->toArray(),
            'danger_zones_created' => $this->user->dangerZones->toArray(),
            'relationships_as_requester' => $this->user->relationshipsAsRequester->toArray(),
            'relationships_as_responder' => $this->user->relationshipsAsResponder->toArray(),
            'locations' => $this->user->locations->toArray(),
        ];

        $filename = 'export_data_' . $this->user->id . '_' . time() . '.json';
        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT));

        // Envoyer l'e-mail avec le lien de téléchargement
        Mail::to($this->user->email)->send(new UserDataExported(Storage::disk('local')->path($filename)));
    }
}