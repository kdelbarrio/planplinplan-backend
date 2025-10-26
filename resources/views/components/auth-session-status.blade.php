@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm p-4 bg-green-300 border rounded-md shadow-sm']) }}>
        {{ $status }}
    </div>
@endif
