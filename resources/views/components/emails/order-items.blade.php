@props(['group'])
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 8px; border:1px solid #E9EDF4; border-radius:12px; overflow:hidden;">
    @foreach ($group->items as $item)
        <tr>
            <td style="padding:14px 16px; {{ ! $loop->last ? 'border-bottom:1px solid #E9EDF4;' : '' }} font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#232B3B;">
                {{ $item->variant->product->title }}
                @if ($item->variant->sku)
                    <br><span style="font-size:12px; color:#8B96AB;">SKU {{ $item->variant->sku }}</span>
                @endif
            </td>
            <td align="center" style="padding:14px 8px; {{ ! $loop->last ? 'border-bottom:1px solid #E9EDF4;' : '' }} font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; color:#66718A; white-space:nowrap;">
                &times;{{ $item->quantity }}
            </td>
            <td align="right" style="padding:14px 16px; {{ ! $loop->last ? 'border-bottom:1px solid #E9EDF4;' : '' }} font-family:'Inter', Arial, Helvetica, sans-serif; font-size:14px; line-height:20px; font-weight:600; color:#131926; white-space:nowrap;">
                ${{ number_format((float) $item->price_at_purchase, 2) }}
            </td>
        </tr>
    @endforeach
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 28px;">
    <tr>
        <td style="padding:4px 16px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:13px; line-height:22px; color:#66718A;">Subtotal</td>
        <td align="right" style="padding:4px 16px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:13px; line-height:22px; color:#363F52;">${{ number_format((float) $group->subtotal, 2) }}</td>
    </tr>
    <tr>
        <td style="padding:4px 16px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:13px; line-height:22px; color:#66718A;">Delivery</td>
        <td align="right" style="padding:4px 16px; font-family:'Inter', Arial, Helvetica, sans-serif; font-size:13px; line-height:22px; color:#363F52;">${{ number_format((float) $group->delivery_fee, 2) }}</td>
    </tr>
</table>
