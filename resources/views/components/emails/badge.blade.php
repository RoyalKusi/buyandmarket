@props(['tone' => 'success'])
@php
    $palette = match ($tone) {
        'danger' => ['bg' => '#FDECEE', 'fg' => '#9E0C24'],
        default => ['bg' => '#E8F6EE', 'fg' => '#095F2E'],
    };
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 16px;">
    <tr>
        <td style="background-color:{{ $palette['bg'] }}; border-radius:9999px; padding:4px 14px;">
            <span style="font-family:'Inter', Arial, Helvetica, sans-serif; font-size:12px; line-height:20px; font-weight:700; letter-spacing:0.02em; text-transform:uppercase; color:{{ $palette['fg'] }};">
                {{ $slot }}
            </span>
        </td>
    </tr>
</table>
