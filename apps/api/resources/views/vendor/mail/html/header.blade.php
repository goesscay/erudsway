@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim((string) config('app.frontend_url'), '/') }}/brand/erudsway-logo.png" class="logo" alt="Erudsway" height="40" style="height: 40px; width: auto; max-height: 40px;">
</a>
</td>
</tr>
