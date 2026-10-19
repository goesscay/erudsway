<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')">
Erudsway
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Erudsway Educorp LLP · Knowledge That Moves Minds<br>
© {{ date('Y') }} Erudsway Educorp LLP. You're receiving this because you have an Erudsway account.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
