@props(['label', 'value' => null])

<div class="flex justify-between gap-4">
    <dt class="text-zinc-500">{{ $label }}</dt>
    <dd class="text-right font-medium text-zinc-800 dark:text-zinc-200">{{ filled($value) ? $value : '—' }}</dd>
</div>
