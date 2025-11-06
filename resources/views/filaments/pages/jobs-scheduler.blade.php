@php
    $workers = $this->workers ?? [];
    $scheduler = $this->scheduler ?? ['last' => null, 'recent' => []];
    $lastOutput = $this->lastOutput ?? '';
@endphp

<x-filament::page>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-filament::section>
            <x-slot name="heading">Workers Queue</x-slot>
            <x-slot name="description">Statut des workers et PID détectés</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left border-b">
                            <th class="py-2 pr-4">Worker</th>
                            <th class="py-2 pr-4">Statut</th>
                            <th class="py-2 pr-4">PID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workers as $w)
                            <tr class="border-b">
                                <td class="py-2 pr-4 font-medium">{{ $w['name'] }}</td>
                                <td class="py-2 pr-4">
                                    @if ($w['status'] === 'ACTIF')
                                        <span class="inline-flex items-center text-green-700">●&nbsp;Actif</span>
                                    @else
                                        <span class="inline-flex items-center text-red-700">●&nbsp;Inactif</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $w['pid'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-3 text-slate-500">Aucun worker détecté (aucun fichier PID).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Scheduler</x-slot>
            <x-slot name="description">Dernières exécutions et historique récent</x-slot>

            <div class="space-y-3">
                <div>
                    <div class="text-xs text-slate-500">Dernière ligne du log</div>
                    <div class="mt-1 rounded bg-slate-50 p-3 text-xs font-mono">
                        {{ $scheduler['last'] ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Historique récent</div>
                    <div class="mt-1 rounded bg-slate-50 p-3 text-xs font-mono max-h-64 overflow-y-auto">
                        @forelse ($scheduler['recent'] as $line)
                            <div>{{ $line }}</div>
                        @empty
                            <div>—</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Sortie des actions</x-slot>
        <x-slot name="description">Résultats des scripts et commandes</x-slot>

        <div class="rounded bg-slate-50 p-3 text-xs font-mono whitespace-pre-wrap leading-relaxed">
            {{ $lastOutput ?: '—' }}
        </div>
    </x-filament::section>
</x-filament::page>