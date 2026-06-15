@php
    /** @var list<array{label: string, value: string, highlight?: bool, monospace?: bool}> $rows */
@endphp

<div style="background: linear-gradient(145deg, #FBFAFE 0%, #F4F0FA 100%); border-radius: 16px; padding: 22px 24px; margin-bottom: 28px; border: 1px solid #E8E0F5; box-shadow: 0 8px 24px rgba(36, 22, 62, 0.04);">
    <p style="margin: 0 0 18px; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8B7BA8;">
        {{ $sectionTitle }}
    </p>

    @foreach ($rows as $row)
        <div style="padding: 14px 0; {{ ! $loop->last ? 'border-bottom: 1px solid rgba(75, 42, 125, 0.08);' : '' }}">
            <p style="margin: 0 0 4px; font-size: 12px; font-weight: 600; color: #8B7BA8; letter-spacing: 0.02em;">
                {{ $row['label'] }}
            </p>
            <p style="margin: 0; font-size: {{ ($row['monospace'] ?? false) ? '15px' : '16px' }}; font-weight: 700; color: {{ ($row['highlight'] ?? false) ? '#4B2A7D' : '#24163E' }}; line-height: 1.45; {{ ($row['monospace'] ?? false) ? "font-family: 'SF Mono', 'Monaco', 'Consolas', monospace; letter-spacing: 0.04em;" : '' }}">
                {{ $row['value'] }}
            </p>
        </div>
    @endforeach
</div>
