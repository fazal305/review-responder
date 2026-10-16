{{-- Plain-text part: not HTML, so values are output raw rather than entity-escaped. --}}
Good morning, {!! $ownerName !!}

@if ($total > 0)
{{ $total }} new reply {{ $total === 1 ? 'draft is' : 'drafts are' }} waiting for you to review:

@foreach ($locations as $location)
- {!! $location['title'] !!}: {{ $location['count'] }} {{ $location['count'] === 1 ? 'draft' : 'drafts' }}
@endforeach

@endif
@if ($failed > 0)
{{ $failed }} {{ $failed === 1 ? 'draft' : 'drafts' }} could not be written automatically. You can retry {{ $failed === 1 ? 'it' : 'them' }} or write the reply yourself.

@endif
Open your queue: {{ $queueUrl }}

Nothing is posted to Google until you approve it.

--
You get this email because the daily summary is switched on for your Review Responder account.
Change notification settings: {{ $settingsUrl }}
