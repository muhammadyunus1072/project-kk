<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>戸籍謄本 翻訳文</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 10mm;
        }

        * {
            box-sizing: border-box;
            color: #000000;
        }

        body {
            font-family: 'DejaVu Sans', 'Yu Gothic', 'MS PGothic', 'IPAGothic', sans-serif;
            font-size: 8pt;
            line-height: 1.25;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        .document-wrapper {
            width: 100%;
            margin: 0 auto;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 6px;
        }

        .header h1 {
            font-size: 15pt;
            font-weight: bold;
            letter-spacing: 0.25em;
            margin: 0 0 2px 0;
            line-height: 1.2;
        }

        .header .doc-number {
            font-size: 9pt;
        }

        .header .doc-number strong {
            letter-spacing: 0.15em;
        }

        .header .doc-number span {
            font-family: Arial, sans-serif;
            font-weight: bold;
            letter-spacing: 0.05em;
        }

        /* ── Metadata Section (2-Column Table) ── */
        .meta-table {
            width: 100%;
            margin-bottom: 6px;
            border-collapse: collapse;
            font-size: 8pt;
        }

        .meta-table td {
            vertical-align: top;
            padding: 1px 0;
        }

        .meta-col {
            width: 50%;
        }

        .meta-row {
            margin-bottom: 2px;
        }

        .meta-label {
            display: inline-block;
            width: 95px;
            font-weight: bold;
        }

        .meta-colon {
            display: inline-block;
            width: 12px;
            font-weight: bold;
            text-align: center;
        }

        .meta-value {
            display: inline;
        }

        /* ── Main Data Tables ── */
        .kk-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            table-layout: fixed;
            font-size: 7.8pt;
        }

        .kk-table th,
        .kk-table td {
            border: 1px solid #000000;
            padding: 0 2px;
            text-align: center;
            vertical-align: middle;
            height: 22px;
            overflow: hidden;
            word-wrap: break-word;
        }

        .kk-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 8pt;
        }

        .kk-table tr.indices th {
            background-color: #fafafa;
            font-weight: normal;
            font-size: 7pt;
            height: 14px;
        }

        .kk-table td {
            font-weight: normal;
        }

        .text-left {
            text-align: left !important;
            padding-left: 4px !important;
        }

        .text-center {
            text-align: center !important;
        }

        /* ── Footer ── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            font-size: 7.5pt;
        }

        .footer-table td {
            vertical-align: top;
            padding: 0 4px;
        }

        .footer-col-left {
            width: 32%;
        }

        .footer-col-center {
            width: 34%;
            text-align: center;
        }

        .footer-col-right {
            width: 34%;
            text-align: center;
        }

        .sign-title {
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 0.1em;
            margin-bottom: 1px;
        }

        .sign-subtitle {
            font-size: 7pt;
            color: #222222;
            margin-bottom: 22px;
        }

        .sign-name {
            font-weight: bold;
            font-size: 8pt;
            min-height: 14px;
            margin-bottom: 2px;
        }

        .sign-line {
            border-bottom: 1px solid #000000;
            width: 75%;
            margin: 0 auto 3px auto;
        }

        .sign-nip {
            font-family: Arial, sans-serif;
            font-size: 7.5pt;
            margin-bottom: 1px;
        }

        .sign-role {
            font-size: 7.2pt;
        }
    </style>
</head>
<body>
<div class="document-wrapper">

    {{-- HEADER --}}
    <div class="header">
        <h1>戸籍謄本</h1>
        <div class="doc-number">
            <strong>番号.</strong>
            <span>{{ $data['familyCardNumber'] ?? '-' }}</span>
        </div>
    </div>

    {{-- METADATA SECTION --}}
    <table class="meta-table">
        <tr>
            {{-- Left column --}}
            <td class="meta-col">
                <div class="meta-row">
                    <span class="meta-label">家長名</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['headOfFamily'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">住所</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['address']['street'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">隣組・民組番号</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value" style="font-family: Arial, sans-serif;">{{ $data['address']['rtRw'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">村</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['address']['subDistrict'] ?? '-' }}</span>
                </div>
            </td>
            {{-- Right column --}}
            <td class="meta-col" style="padding-left: 20px;">
                <div class="meta-row">
                    <span class="meta-label" style="width: 75px;">郡</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['address']['district'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label" style="width: 75px;">市</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['address']['regencyCity'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label" style="width: 75px;">郵便番号</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value" style="font-family: Arial, sans-serif;">{{ $data['address']['postalCode'] ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label" style="width: 75px;">州</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $data['address']['province'] ?? '-' }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- TABLE 1: MEMBER IDENTITY --}}
    <table class="kk-table">
        <thead>
            <tr>
                <th style="width: 3.5%;" rowspan="2">番</th>
                <th style="width: 21%;">姓名</th>
                <th style="width: 15%;">人口登録番号</th>
                <th style="width: 5.5%;">性別</th>
                <th style="width: 11%;">出身地</th>
                <th style="width: 11%;">生年月日</th>
                <th style="width: 8%;">宗教</th>
                <th style="width: 12.5%;">学歴</th>
                <th style="width: 12.5%;">職業</th>
            </tr>
            <tr class="indices">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
                <th>(7)</th>
                <th>(8)</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < 10; $i++)
                @php
                    $member = $data['members'][$i] ?? null;
                    $rowNo = $i + 1;
                @endphp
                <tr>
                    <td style="font-family: Arial, sans-serif;">{{ $rowNo }}</td>
                    <td class="text-left" style="font-size: {{ !empty($member['fullNameKatakana']) && mb_strlen($member['fullNameKatakana']) > 15 ? '7pt' : '7.8pt' }};">
                        {{ $member['fullNameKatakana'] ?? ($member['fullNameOriginal'] ?? '-') }}
                    </td>
                    <td style="font-family: Arial, sans-serif; font-size: 7.2pt;">
                        {{ $member['nik'] ?? '-' }}
                    </td>
                    <td>{{ $member['gender'] ?? '-' }}</td>
                    <td class="text-left">{{ $member['birthPlace'] ?? '-' }}</td>
                    <td style="font-size: 7.5pt;">{{ $member['birthDate'] ?? '-' }}</td>
                    <td>{{ $member['religion'] ?? '-' }}</td>
                    <td class="text-left">{{ $member['education'] ?? '-' }}</td>
                    <td class="text-left">{{ $member['occupation'] ?? '-' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- TABLE 2: STATUS, PASSPORT & PARENTS --}}
    <table class="kk-table">
        <thead>
            <tr>
                <th style="width: 3.5%;" rowspan="3">番</th>
                <th style="width: 10.5%;" rowspan="2">現状</th>
                <th style="width: 12%;" rowspan="2">家族関係</th>
                <th style="width: 10%;" rowspan="2">国籍</th>
                <th style="width: 32%;" colspan="2">入国文書</th>
                <th style="width: 32%;" colspan="2">両親の名</th>
            </tr>
            <tr>
                <th style="width: 16%;">パスポート番号</th>
                <th style="width: 16%;">在留資格認定書番号</th>
                <th style="width: 16%;">父</th>
                <th style="width: 16%;">母</th>
            </tr>
            <tr class="indices">
                <th>(9)</th>
                <th>(10)</th>
                <th>(11)</th>
                <th>(12)</th>
                <th>(13)</th>
                <th>(14)</th>
                <th>(15)</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < 10; $i++)
                @php
                    $member = $data['members'][$i] ?? null;
                    $rowNo = $i + 1;
                @endphp
                <tr>
                    <td style="font-family: Arial, sans-serif;">{{ $rowNo }}</td>
                    <td>{{ $member['maritalStatus'] ?? '-' }}</td>
                    <td>{{ $member['relationStatus'] ?? '-' }}</td>
                    <td>{{ $member['nationality'] ?? ($member ? 'インドネシア' : '-') }}</td>
                    <td style="font-family: Arial, sans-serif;">{{ !empty($member['passportNumber']) && $member['passportNumber'] !== '-' ? $member['passportNumber'] : '-' }}</td>
                    <td style="font-family: Arial, sans-serif;">{{ !empty($member['stayPermitNumber']) && $member['stayPermitNumber'] !== '-' ? $member['stayPermitNumber'] : '-' }}</td>
                    <td class="text-left">{{ $member['fatherName'] ?? '-' }}</td>
                    <td class="text-left">{{ $member['motherName'] ?? '-' }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- FOOTER SECTION --}}
    <table class="footer-table">
        <tr>
            {{-- Left column: Issued date and official copies --}}
            <td class="footer-col-left">
                <div style="margin-bottom: 2px;">
                    <strong style="display: inline-block; width: 45px;">発行日</strong>
                    <strong>:</strong>
                    <span>{{ $data['issuedDate'] ?? '-' }}</span>
                </div>
                <div style="margin-bottom: 1px;"><strong style="display: inline-block; width: 45px;">第1枚目</strong><strong>:</strong> 家長用</div>
                <div style="margin-bottom: 1px;"><strong style="display: inline-block; width: 45px;">第2枚目</strong><strong>:</strong> 隣組長用</div>
                <div style="margin-bottom: 1px;"><strong style="display: inline-block; width: 45px;">第3枚目</strong><strong>:</strong> 村長用</div>
                <div><strong style="display: inline-block; width: 45px;">第4枚目</strong><strong>:</strong> 郡長用</div>
            </td>

            {{-- Center column: Head of family signature --}}
            <td class="footer-col-center">
                <div class="sign-title">家長</div>
                <div class="sign-subtitle">（署名）</div>
                <div class="sign-name">{{ $data['headOfFamily'] ?? '-' }}</div>
                <div class="sign-line"></div>
                <div class="sign-role">指印又は署名</div>
            </td>

            {{-- Right column: Officer signature and NIP --}}
            <td class="footer-col-right">
                <div class="sign-title">民事登録事務官</div>
                <div class="sign-subtitle">（署名）（公印）</div>
                <div class="sign-name">{{ $data['signatoryOfficer'] ?? '-' }}</div>
                <div class="sign-line"></div>
                <div class="sign-nip">{{ $data['signatoryNip'] ?? '-' }}</div>
                <div class="sign-role">公務員番号</div>
            </td>
        </tr>
    </table>

</div>
</body>
</html>
