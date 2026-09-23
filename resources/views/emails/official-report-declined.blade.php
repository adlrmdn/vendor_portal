<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>The Director declined the Official Report below. Please revise and resend.</p>
    <table style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Production Group</td><td>{{ $report->production_group }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Operation</td><td>{{ $report->operation }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Reason</td><td>{{ $report->director_decline_reason }}</td></tr>
    </table>
    <p>
        <a href="{{ $reviseUrl }}" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Revise &amp; Resend</a>
    </p>
</body>
</html>
