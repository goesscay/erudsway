<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Certificate {{ $certificate->serial }}</title>
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #2b2925; }
    .sheet { position: absolute; top: 0; left: 0; width: 842pt; height: 595pt; background: #fbf8f1; }
    .frame { position: absolute; top: 18pt; left: 18pt; right: 18pt; bottom: 18pt; border: 2.5pt solid #001b40; }
    .rule { position: absolute; top: 25pt; left: 25pt; right: 25pt; bottom: 25pt; border: 0.75pt solid #c9a227; }
    .content { position: absolute; top: 58pt; left: 70pt; width: 702pt; text-align: center; }
    .logo { height: 92pt; }
    .kicker { margin-top: 18pt; font-size: 10pt; letter-spacing: 4pt; color: #a8841a; font-weight: bold; }
    .lead { margin-top: 20pt; font-size: 10.5pt; color: #6b665c; }
    .name { margin-top: 6pt; font-family: DejaVu Serif, serif; font-size: 34pt; font-weight: bold; color: #001b40; }
    .name-rule { margin: 8pt auto 0; width: 300pt; border-top: 0.75pt solid #c9a227; }
    .course { margin-top: 10pt; font-family: DejaVu Serif, serif; font-size: 18pt; color: #001b40; line-height: 1.3; }
    .meta { margin-top: 8pt; font-size: 10pt; color: #6b665c; }
    .footer { position: absolute; top: 468pt; left: 70pt; width: 702pt; }
    .footer td { vertical-align: bottom; font-size: 9pt; color: #6b665c; }
    .sig { border-top: 0.75pt solid #2b2925; padding-top: 5pt; width: 180pt; }
    .sig strong { display: block; font-size: 10.5pt; color: #2b2925; }
    .verify { width: 300pt; text-align: right; line-height: 1.5; }
    .verify strong { color: #001b40; }
</style>
</head>
<body>
<div class="sheet">
    <div class="frame"></div>
    <div class="rule"></div>

    <div class="content">
        @if ($logo)
            <img src="{{ $logo }}" class="logo" alt="Erudsway Educorp LLP">
        @else
            <div style="font-size: 22pt; font-weight: bold; color: #001b40;">ERUDSWAY</div>
        @endif
        <div class="kicker">CERTIFICATE OF COMPLETION</div>
        <div class="lead">This is to certify that</div>
        <div class="name">{{ $certificate->recipient_name }}</div>
        <div class="name-rule"></div>
        <div class="lead">has successfully completed the online course</div>
        <div class="course">{{ $certificate->course_title }}</div>
        <div class="meta">{{ $length }} · Completed {{ $certificate->issued_at->timezone('Asia/Kolkata')->format('j F Y') }}</div>
    </div>

    <table class="footer" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <div class="sig">
                    <strong>{{ $certificate->instructor_name }}</strong>
                    Instructor
                </div>
            </td>
            <td>
                <div class="sig">
                    <strong>Erudsway Educorp LLP</strong>
                    Knowledge That Moves Minds
                </div>
            </td>
            <td class="verify">
                Certificate no. <strong>{{ $certificate->serial }}</strong><br>
                Verify at {{ preg_replace('#^https?://#', '', $certificate->verifyUrl()) }}
            </td>
        </tr>
    </table>
</div>
</body>
</html>
