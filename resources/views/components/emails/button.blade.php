@props(['url'])
{{-- Bulletproof table-based button — renders correctly in Outlook/Windows
     Mail (which ignore `display:inline-block` padding on an <a>) as well
     as every other client. Primary CTA color is always brand blue-600,
     never gold (§1.5: "Accent — Marketplace Gold, never for primary CTAs"). --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 28px 0;">
    <tr>
        <td style="border-radius:8px; background-color:#003594;">
            <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:14px 28px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:15px; line-height:20px; font-weight:600; color:#FFFFFF; text-decoration:none; border-radius:8px;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
