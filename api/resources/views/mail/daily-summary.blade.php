<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your morning summary</title>
</head>
<body style="margin:0;padding:0;background:#faf6ef;color:#1f1b16;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#faf6ef;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e6dfd2;border-radius:12px;">
<tr><td style="padding:28px 28px 8px;">
<p style="margin:0;font-size:13px;font-weight:600;color:#5c554c;">Review Responder</p>
<h1 style="margin:8px 0 0;font-family:Georgia,serif;font-size:24px;line-height:1.3;color:#1f1b16;">Good morning, {{ $ownerName }}</h1>
@if ($total > 0)
<p style="margin:12px 0 0;font-size:15px;line-height:1.6;">{{ $total }} new reply {{ $total === 1 ? 'draft is' : 'drafts are' }} waiting for you to review:</p>
@endif
</td></tr>
@if ($total > 0)
<tr><td style="padding:8px 28px 0;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;">
@foreach ($locations as $location)
<tr>
<td style="padding:10px 0;border-bottom:1px solid #e6dfd2;">{{ $location['title'] }}</td>
<td align="right" style="padding:10px 0;border-bottom:1px solid #e6dfd2;font-weight:600;white-space:nowrap;">{{ $location['count'] }} {{ $location['count'] === 1 ? 'draft' : 'drafts' }}</td>
</tr>
@endforeach
</table>
</td></tr>
@endif
@if ($failed > 0)
<tr><td style="padding:16px 28px 0;">
<p style="margin:0;padding:12px 14px;background:#faefd9;border-radius:8px;font-size:14px;line-height:1.5;color:#8a5a00;">{{ $failed }} {{ $failed === 1 ? 'draft' : 'drafts' }} could not be written automatically. You can retry {{ $failed === 1 ? 'it' : 'them' }} or write the reply yourself.</p>
</td></tr>
@endif
<tr><td style="padding:24px 28px 28px;">
<a href="{{ $queueUrl }}" style="display:inline-block;background:#24365c;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;padding:12px 20px;border-radius:8px;">Open your queue</a>
<p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:#5c554c;">Nothing is posted to Google until you approve it.</p>
</td></tr>
</table>
<p style="max-width:560px;margin:16px auto 0;font-size:12px;line-height:1.5;color:#5c554c;">You get this email because the daily summary is switched on for your Review Responder account. <a href="{{ $settingsUrl }}" style="color:#24365c;">Change notification settings</a></p>
</td></tr>
</table>
</body>
</html>
