<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>
        @if($stage === 'ho')
            An Official Report (Berita Acara) is ready for your approval as MD Production.
        @else
            An Official Report (Berita Acara) has been approved by MD Production and needs your authorization.
        @endif
    </p>
    <table style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Production Group</td><td>{{ $report->production_group }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Operation</td><td>{{ $report->operation }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Purchase Order</td><td>{{ $report->matched_po_number ?? '—' }}</td></tr>
        <tr><td style="padding: 3px 10px 3px 0; color: #666;">Total Deduction</td><td>Rp {{ number_format($report->totalDeduction(), 0, ',', '.') }}</td></tr>
        @if($report->needsRevision())
        <tr><td style="padding: 3px 10px 3px 0; color: #b45309;">Note</td><td style="color: #b45309;">This is a resend after a Director decline (revision #{{ $report->revision_count }}).</td></tr>
        @endif
    </table>
    <p>
        <a href="{{ $approveUrl }}" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">
            {{ $stage === 'ho' ? 'Review & Approve' : 'Review & Authorize' }}
        </a>
        @if($declineUrl)
            &nbsp;
            <a href="{{ $declineUrl }}" style="background:#dc2626;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;">Decline</a>
        @endif
    </p>
</body>
</html>
