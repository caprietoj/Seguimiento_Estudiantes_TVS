<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha Integral de Seguimiento · {{ $student['full_name'] }}</title>
    <style>
        @page {
            margin: 18mm 14mm 18mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9.5px;
            color: #1c2536;
            margin: 0;
            padding: 0;
            line-height: 1.4;
            background: #ffffff;
        }

        /* ─── Encabezado Institucional Principal ─── */
        .masthead-table {
            width: 100%;
            border-collapse: collapse;
            background: #364e76;
            color: #ffffff;
            border-bottom: 4px solid #ed3236;
            margin-bottom: 10px;
        }

        .masthead-table td {
            padding: 12px 14px;
            vertical-align: middle;
            border: none;
        }

        .masthead-brand {
            font-size: 8px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #b9c5db;
            font-weight: bold;
            margin: 0 0 2px 0;
        }

        .masthead-title {
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
            margin: 0;
            letter-spacing: -0.2px;
        }

        .masthead-sub {
            font-size: 9px;
            color: #dbe2ef;
            margin: 3px 0 0 0;
        }

        .masthead-badge {
            display: inline-block;
            background: #ed3236;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: bold;
            padding: 1px 6px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-left: 6px;
            vertical-align: middle;
        }

        .masthead-meta {
            text-align: right;
            font-size: 8px;
            color: #cad3e2;
            line-height: 1.5;
        }

        .masthead-meta strong {
            color: #ffffff;
        }

        /* ─── Ficha de Identidad del Estudiante ─── */
        .idbox {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #d5d9e0;
            border-top: 3px solid #364e76;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .idbox td {
            padding: 8px 10px;
            vertical-align: middle;
            border: none;
        }

        .photo-cell {
            width: 74px;
            text-align: center;
            background: #eef2f7;
            border-right: 1px solid #e2e8f0 !important;
        }

        .photo {
            width: 58px;
            height: 58px;
            border-radius: 4px;
            border: 2px solid #ffffff;
            display: block;
            margin: 0 auto;
        }

        .initials {
            width: 58px;
            height: 58px;
            border-radius: 4px;
            background: #364e76;
            color: #ffffff;
            font-size: 19px;
            font-weight: bold;
            line-height: 58px;
            text-align: center;
            margin: 0 auto;
            border: 2px solid #ffffff;
        }

        .stu-name {
            font-size: 14px;
            font-weight: bold;
            color: #364e76;
            margin: 0 0 2px 0;
        }

        .stu-code {
            font-size: 8px;
            color: #5d6470;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .id-fields-table {
            width: 100%;
            border-collapse: collapse;
        }

        .id-fields-table td {
            padding: 3px 6px;
            vertical-align: top;
            border: none;
        }

        .lbl-dim {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #717b8c;
            font-weight: bold;
            display: block;
            margin-bottom: 1px;
        }

        .val-bold {
            font-size: 10px;
            font-weight: bold;
            color: #1c2536;
        }

        /* ─── Tarjetas de Resumen KPI ─── */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 0;
            margin-bottom: 12px;
        }

        .kpi-card {
            background: #f8fafc;
            border: 1px solid #dbe2ef;
            border-radius: 4px;
            padding: 6px 8px;
            text-align: center;
            vertical-align: middle;
            width: 25%;
        }

        .kpi-card.alert-crit {
            background: #fdf2f2;
            border-color: #fca5a5;
        }

        .kpi-card.alert-warn {
            background: #fffbeb;
            border-color: #fde68a;
        }

        .kpi-num {
            font-size: 17px;
            font-weight: bold;
            color: #364e76;
            line-height: 1.1;
        }

        .kpi-card.alert-crit .kpi-num {
            color: #c92a2e;
        }

        .kpi-card.alert-warn .kpi-num {
            color: #b26a00;
        }

        .kpi-lbl {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5d6470;
            margin-top: 2px;
            font-weight: bold;
        }

        /* ─── Secciones Numeradas ─── */
        .sec {
            border: 1px solid #d5d9e0;
            border-top: 3px solid #364e76;
            border-radius: 4px;
            padding: 8px 10px 9px;
            margin-bottom: 10px;
            page-break-inside: avoid;
            background: #ffffff;
        }

        .sec.sec-accent {
            border-top-color: #ed3236;
        }

        .sec.sec-confidential {
            border-top-color: #555d6b;
            background: #fafbfc;
        }

        .sec-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .sec-header td {
            padding: 0;
            border: none;
            vertical-align: middle;
        }

        .sec-title {
            font-size: 10.5px;
            color: #364e76;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .sec-num {
            background: #364e76;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            margin-right: 5px;
            display: inline-block;
        }

        .sec-subtitle {
            font-size: 8px;
            color: #717b8c;
            margin: 1px 0 0 0;
        }

        .lock-pill {
            display: inline-block;
            background: #eef0f4;
            color: #4b5563;
            border: 1px dashed #9ca3af;
            border-radius: 3px;
            padding: 1px 6px;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        /* ─── Cajas de Notas y Aportes ─── */
        .note-card {
            background: #f8fafc;
            border-left: 3px solid #364e76;
            border-top: 1px solid #eef2f6;
            border-right: 1px solid #eef2f6;
            border-bottom: 1px solid #eef2f6;
            border-radius: 0 4px 4px 0;
            padding: 5px 8px;
            margin-bottom: 5px;
            page-break-inside: avoid;
        }

        .note-card.note-improvement {
            border-left-color: #ed3236;
        }

        .note-card.note-conf {
            border-left-color: #6b7280;
            background: #f3f4f6;
        }

        .note-body {
            font-size: 9.5px;
            color: #1f2937;
            margin-bottom: 2px;
            line-height: 1.35;
        }

        .note-meta {
            font-size: 8px;
            color: #717b8c;
        }

        .note-meta strong {
            color: #374151;
        }

        .period-chip {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            border-radius: 3px;
            padding: 0 4px;
            font-size: 7.5px;
            font-weight: bold;
            margin-right: 4px;
        }

        /* ─── Badges de Estado ─── */
        .badge {
            display: inline-block;
            border-radius: 3px;
            padding: 1px 6px;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            line-height: 1.2;
        }

        .badge-good {
            background: #def7ec;
            color: #03543f;
        }

        .badge-warn {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-crit {
            background: #fde8e8;
            color: #9b1c1c;
        }

        .badge-mute {
            background: #f3f4f6;
            color: #4b5563;
        }

        .badge-info {
            background: #e1effe;
            color: #1e429f;
        }

        /* ─── Tablas de Datos Estructurados ─── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        .data-table th,
        .data-table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 4px 6px;
            text-align: left;
            font-size: 9px;
            vertical-align: top;
        }

        .data-table th {
            background: #f1f5f9;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.5px;
            color: #475569;
            font-weight: bold;
            border-bottom: 1px solid #cbd5e1;
        }

        .data-table tr:nth-child(even) td {
            background: #fafbfc;
        }

        .data-table td.center,
        .data-table th.center {
            text-align: center;
        }

        /* Niveles IB */
        .lvl-pill {
            display: inline-block;
            width: 20px;
            height: 18px;
            line-height: 18px;
            text-align: center;
            border-radius: 3px;
            font-weight: bold;
            font-size: 9px;
        }

        .lvl-good {
            background: #def7ec;
            color: #03543f;
        }

        .lvl-warn {
            background: #fef3c7;
            color: #92400e;
        }

        .lvl-crit {
            background: #fde8e8;
            color: #9b1c1c;
        }

        .lvl-na {
            color: #9ca3af;
        }

        /* ─── Estrategias con Historial de Evaluaciones ─── */
        .strategy-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 3px solid #364e76;
            border-radius: 0 4px 4px 0;
            padding: 6px 8px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        .strategy-head {
            font-size: 9.5px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .strategy-meta {
            font-size: 8px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .reviews-timeline {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #edf2f7;
            border-radius: 3px;
            margin-top: 3px;
        }

        .reviews-timeline td {
            padding: 2.5px 6px;
            font-size: 8px;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .reviews-timeline tr:last-child td {
            border-bottom: none;
        }

        /* ─── Chips y Etiquetas Rápidas ─── */
        .chip {
            display: inline-block;
            background: #eef2f6;
            border: 1px solid #dbe2ef;
            border-radius: 10px;
            padding: 1px 8px;
            margin: 0 3px 3px 0;
            font-size: 8.5px;
            font-weight: bold;
            color: #334155;
        }

        .empty-text {
            color: #94a3b8;
            font-style: italic;
            font-size: 9px;
            padding: 3px 0;
        }

        /* ─── Bloque de Firmas y Validación Institucional ─── */
        .signatures-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }

        .signatures-table td {
            width: 33.3%;
            padding: 0 10px;
            text-align: center;
            vertical-align: bottom;
            border: none;
        }

        .sig-line {
            border-top: 1px solid #64748b;
            padding-top: 4px;
            margin-top: 32px;
        }

        .sig-name {
            font-size: 8.5px;
            font-weight: bold;
            color: #1e293b;
        }

        .sig-role {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ─── Aviso Legal y Confidencialidad ─── */
        .legal-notice {
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            margin-top: 10px;
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.4;
            text-align: justify;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

    <!-- ════════ ENCABEZADO INSTITUCIONAL ════════ -->
    <table class="masthead-table">
        <tr>
            <td style="width: 62%;">
                <p class="masthead-brand">The Victoria School · Colegio del Mundo del IB</p>
                <h1 class="masthead-title">
                    FICHA INTEGRAL DE SEGUIMIENTO
                    <span class="masthead-badge">{{ $group['section'] }}</span>
                </h1>
                <p class="masthead-sub">
                    Comité de Seguimiento Académico y Convivencial · Año Escolar {{ $schoolYear }}
                </p>
            </td>
            <td style="width: 38%;" class="masthead-meta">
                <div>Documento: <strong>Oficial / Confidencial</strong></div>
                <div>Emisión: <strong>{{ $generatedOn ?? date('d/m/Y H:i') }}</strong></div>
                @if (!empty($exportedBy))
                    <div>Generado por: <strong>{{ $exportedBy }}</strong></div>
                @endif
                <div>Sistema: <strong>Seguimiento de Estudiantes TVS</strong></div>
            </td>
        </tr>
    </table>

    <!-- ════════ FICHA DE IDENTIDAD DEL ESTUDIANTE ════════ -->
    <table class="idbox">
        <tr>
            <td class="photo-cell" rowspan="2">
                @if (!empty($photoBase64))
                    <img class="photo" src="{{ $photoBase64 }}" alt="Foto">
                @else
                    <div class="initials">{{ collect(explode(' ', $student['full_name']))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') }}</div>
                @endif
            </td>
            <td colspan="3" style="padding-bottom: 2px;">
                <h2 class="stu-name">{{ $student['full_name'] }}</h2>
                <div class="stu-code">
                    @if (!empty($student['institutional_code']))
                        CÓDIGO: <strong>{{ $student['institutional_code'] }}</strong> &nbsp;·&nbsp;
                    @endif
                    ID: <strong>#{{ $student['id'] }}</strong> &nbsp;·&nbsp;
                    ESTADO: <span class="badge badge-good">Matriculado Activo</span>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 33%;">
                <table class="id-fields-table">
                    <tr>
                        <td>
                            <span class="lbl-dim">Grupo y Grado</span>
                            <span class="val-bold">{{ $group['code'] }} · {{ $group['grade_label'] }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="lbl-dim">Sección Continuo IB</span>
                            <span class="val-bold">{{ $group['section'] }}</span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 35%;">
                <table class="id-fields-table">
                    <tr>
                        <td>
                            <span class="lbl-dim">Director(a) de Grupo</span>
                            <span class="val-bold">{{ $group['director_name'] ?? 'Sin asignar' }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="lbl-dim">Año Lectivo</span>
                            <span class="val-bold">{{ $schoolYear }}</span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 32%;">
                <table class="id-fields-table">
                    <tr>
                        <td>
                            <span class="lbl-dim">Compromisos Abiertos</span>
                            <span class="val-bold">
                                {{ $stats['open_commitments'] }}
                                @if ($stats['late_commitments'] > 0)
                                    <span style="color:#c92a2e;font-size:9px">({{ $stats['late_commitments'] }} vencidos)</span>
                                @endif
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <span class="lbl-dim">Estrategias Activas</span>
                            <span class="val-bold">{{ count($individualStrategies) }} individual(es)</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ════════ TARJETAS KPI DE RESUMEN ════════ -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-num">{{ $stats['open_commitments'] }}</div>
                <div class="kpi-lbl">Compromisos Abiertos</div>
            </td>
            <td class="kpi-card {{ $stats['late_commitments'] > 0 ? 'alert-crit' : '' }}">
                <div class="kpi-num">{{ $stats['late_commitments'] }}</div>
                <div class="kpi-lbl">Compromisos Vencidos</div>
            </td>
            <td class="kpi-card {{ $stats['attention_subjects'] > 0 ? 'alert-warn' : '' }}">
                <div class="kpi-num">{{ $stats['attention_subjects'] }}</div>
                <div class="kpi-lbl">Asignaturas en Atención</div>
            </td>
            <td class="kpi-card">
                <div class="kpi-num">{{ count($individualStrategies) + count($groupStrategies) }}</div>
                <div class="kpi-lbl">Estrategias en Curso</div>
            </td>
        </tr>
    </table>

    <!-- ════════ 01 FORTALEZAS Y AVANCES ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">01</span>Fortalezas y avances formativos</h2>
                    <p class="sec-subtitle">Aportes docentes y de coordinación sobre desempeño positivo y progresos</p>
                </td>
            </tr>
        </table>

        @forelse ($strengths as $n)
            <div class="note-card">
                <div class="note-body">{{ $n['body'] }}</div>
                <div class="note-meta">
                    <span class="period-chip">{{ $n['follow_up_label'] }}</span>
                    Registrado por <strong>{{ $n['author_name'] }}</strong>
                </div>
            </div>
        @empty
            <p class="empty-text">Sin fortalezas registradas para este periodo.</p>
        @endforelse
    </div>

    <!-- ════════ 02 ASPECTOS DE MEJORA ════════ -->
    <div class="sec sec-accent">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num" style="background:#ed3236">02</span>Aspectos de mejora y áreas de atención</h2>
                    <p class="sec-subtitle">Oportunidades de crecimiento académico, actitudinal y de convivencia</p>
                </td>
            </tr>
        </table>

        @forelse ($improvements as $n)
            <div class="note-card note-improvement">
                <div class="note-body">{{ $n['body'] }}</div>
                <div class="note-meta">
                    <span class="period-chip">{{ $n['follow_up_label'] }}</span>
                    Registrado por <strong>{{ $n['author_name'] }}</strong>
                </div>
            </div>
        @empty
            <p class="empty-text">Sin aspectos de mejora registrados para este periodo.</p>
        @endforelse
    </div>

    <!-- ════════ 03 ESTRATEGIAS INDIVIDUALES ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">03</span>Estrategias individuales y seguimiento</h2>
                    <p class="sec-subtitle">Planes de acción pedagógica con historial de evaluación y efectividad</p>
                </td>
            </tr>
        </table>

        @forelse ($individualStrategies as $s)
            @php
                $rb = $s['last_result'];
                $rclass = $rb === 'Funciona' ? 'badge-good' : ($rb === 'Funciona parcialmente' ? 'badge-warn' : ($rb === 'No funciona' ? 'badge-crit' : 'badge-mute'));
            @endphp
            <div class="strategy-box">
                <div class="strategy-head">{{ $s['body'] }}</div>
                <div class="strategy-meta">
                    <span class="badge {{ $rclass }}">{{ $rb }}</span>
                    &nbsp;·&nbsp; Responsable: <strong>{{ $s['responsible'] ?? 'Sin asignar' }}</strong>
                    &nbsp;·&nbsp; Implementada desde: <strong>{{ $s['since'] }}</strong>
                </div>

                @if (!empty($s['reviews']) && count($s['reviews']) > 0)
                    <table class="reviews-timeline">
                        @foreach ($s['reviews'] as $rev)
                            @php
                                $revClass = $rev['result'] === 'Funciona' ? 'badge-good' : ($rev['result'] === 'Funciona parcialmente' ? 'badge-warn' : ($rev['result'] === 'No funciona' ? 'badge-crit' : 'badge-mute'));
                            @endphp
                            <tr>
                                <td style="width: 14%; color:#64748b;">
                                    {{ $rev['reviewed_on'] }}
                                </td>
                                <td style="width: 20%;">
                                    <span class="badge {{ $revClass }}">{{ $rev['result'] }}</span>
                                </td>
                                <td style="width: 26%; color:#475569;">
                                    <strong>{{ $rev['author_name'] }}</strong>
                                </td>
                                <td style="width: 40%; color:#1e293b;">
                                    {{ $rev['body'] }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @empty
            <p class="empty-text">Sin estrategias individuales registradas.</p>
        @endforelse
    </div>

    <!-- ════════ 04 ESTRATEGIAS GRUPALES ════════ -->
    @if (count($groupStrategies) > 0)
        <div class="sec">
            <table class="sec-header">
                <tr>
                    <td>
                        <h2 class="sec-title"><span class="sec-num">04</span>Estrategias grupales (Grupo {{ $group['code'] }})</h2>
                        <p class="sec-subtitle">Acciones pedagógicas aplicadas a la dinámica general del salón</p>
                    </td>
                </tr>
            </table>

            @foreach ($groupStrategies as $s)
                @php
                    $rb = $s['last_result'];
                    $rclass = $rb === 'Funciona' ? 'badge-good' : ($rb === 'Funciona parcialmente' ? 'badge-warn' : ($rb === 'No funciona' ? 'badge-crit' : 'badge-mute'));
                @endphp
                <div class="strategy-box">
                    <div class="strategy-head">{{ $s['body'] }}</div>
                    <div class="strategy-meta">
                        <span class="badge {{ $rclass }}">{{ $rb }}</span>
                        &nbsp;·&nbsp; Responsable: <strong>{{ $s['responsible'] ?? 'Docentes del grupo' }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- ════════ 05 COMPROMISOS (COLEGIO Y FAMILIA) ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">05</span>Acuerdos y compromisos</h2>
                    <p class="sec-subtitle">Compromisos pactados con el colegio y con los acudientes / familia</p>
                </td>
            </tr>
        </table>

        @php
            $allComms = $commitments['school']->concat($commitments['family']);
        @endphp

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Ámbito</th>
                    <th style="width: 44%;">Compromiso</th>
                    <th style="width: 18%;">Responsable</th>
                    <th style="width: 12%;" class="center">Fecha límite</th>
                    <th style="width: 14%;" class="center">Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allComms as $c)
                    @php
                        $isSchool = $loop->index < $commitments['school']->count();
                        $cclass = $c['is_late'] ? 'badge-crit' : ($c['status'] === 'done' ? 'badge-good' : ($c['status'] === 'closed' ? 'badge-mute' : 'badge-warn'));
                    @endphp
                    <tr>
                        <td>
                            <span class="badge {{ $isSchool ? 'badge-info' : 'badge-warn' }}">
                                {{ $isSchool ? 'Colegio' : 'Familia' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $c['body'] }}</strong>
                        </td>
                        <td>{{ $c['responsible'] }}</td>
                        <td class="center">{{ $c['due_on'] ?? '—' }}</td>
                        <td class="center">
                            <span class="badge {{ $cclass }}">
                                {{ $c['status_label'] }}
                                @if ($c['is_late']) · Vencido @endif
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-text center">Sin compromisos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ════════ 06 RESPONSABLES / ACUDIENTES ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">06</span>Padres de familia y acudientes registrados</h2>
                    <p class="sec-subtitle">Personas a cargo del acompañamiento en el hogar</p>
                </td>
            </tr>
        </table>
        <div style="margin-top: 4px;">
            @forelse ($responsibles as $r)
                <span class="chip">{{ $r }}</span>
            @empty
                <span class="empty-text">Sin responsables registrados en la matrícula.</span>
            @endforelse
        </div>
    </div>

    <!-- ════════ 07 APOYOS INTERNOS (CONFIDENCIAL) ════════ -->
    @if ($internalSupports->isNotEmpty())
        <div class="sec sec-confidential">
            <table class="sec-header">
                <tr>
                    <td>
                        <h2 class="sec-title"><span class="sec-num" style="background:#555d6b">07</span>Apoyos internos (Dpto. de Apoyo y Psicología)</h2>
                        <p class="sec-subtitle">Seguimiento psicopedagógico, fonoaudiológico y terapéutico interno</p>
                    </td>
                    <td style="text-align: right;">
                        <span class="lock-pill">🔒 Uso Confidencial</span>
                    </td>
                </tr>
            </table>

            @foreach ($internalSupports as $area)
                <div style="margin-top: 5px; margin-bottom: 5px;">
                    <strong style="font-size: 8.5px; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                        Área: {{ $area['label'] }}
                    </strong>
                    @forelse ($area['notes'] as $n)
                        <div class="note-card note-conf" style="margin-top: 3px;">
                            <div class="note-body">{{ $n['body'] }}</div>
                            <div class="note-meta">
                                <span class="period-chip">{{ $n['follow_up_label'] }}</span>
                                Profesional: <strong>{{ $n['author_name'] }}</strong>
                            </div>
                        </div>
                    @empty
                        <p class="empty-text" style="margin: 2px 0 4px 6px;">Sin observaciones registradas en esta área.</p>
                    @endforelse
                </div>
            @endforeach
        </div>
    @endif

    <!-- ════════ 08 APOYOS EXTERNOS (CONFIDENCIAL) ════════ -->
    @if ($externalSupports)
        <div class="sec sec-confidential">
            <table class="sec-header">
                <tr>
                    <td>
                        <h2 class="sec-title"><span class="sec-num" style="background:#555d6b">08</span>Terapias y apoyos externos</h2>
                        <p class="sec-subtitle">Profesionales e instituciones externas vinculadas al proceso de apoyo</p>
                    </td>
                    <td style="text-align: right;">
                        <span class="lock-pill">🔒 Confidencial</span>
                    </td>
                </tr>
            </table>

            @forelse ($externalSupports['items'] as $x)
                <div class="note-card note-conf">
                    <div style="font-weight: bold; color: #1e293b; font-size: 9.5px;">
                        {{ $x['provider'] }} — <span style="font-weight: normal; color: #475569;">{{ $x['specialty'] }}</span>
                    </div>
                    <div class="note-meta" style="margin: 2px 0;">
                        Frecuencia: <strong>{{ $x['frequency'] ?? 'No especificada' }}</strong>
                        @if (!empty($x['contact']))
                            &nbsp;·&nbsp; Contacto: <strong>{{ $x['contact'] }}</strong>
                        @endif
                    </div>
                    @if ($x['notes'])
                        <div class="note-body" style="margin-top: 3px; font-style: italic;">
                            {{ $x['notes'] }}
                        </div>
                    @endif
                </div>
            @empty
                <p class="empty-text">Sin apoyos externos registrados.</p>
            @endforelse
        </div>
    @endif

    <!-- ════════ 09 DESEMPEÑO ACADÉMICO (ESCALA IB 1–7) ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">09</span>Desempeño académico oficial (Escala IB 1–7)</h2>
                    <p class="sec-subtitle">Registro de calificaciones por periodo lectivo</p>
                </td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 46%;">Asignatura</th>
                    <th style="width: 18%;" class="center">Periodo 1</th>
                    <th style="width: 18%;" class="center">Periodo 2</th>
                    <th style="width: 18%;" class="center">Periodo 3</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($performance as $row)
                    <tr>
                        <td><strong>{{ $row['subject'] }}</strong></td>
                        @for ($p = 1; $p <= 3; $p++)
                            @php
                                $lv = $row['levels'][$p] ?? null;
                            @endphp
                            <td class="center">
                                @if ($lv === null)
                                    <span class="lvl-pill lvl-na">–</span>
                                @else
                                    <span class="lvl-pill @if ($lv <= 2) lvl-crit @elseif ($lv == 3) lvl-warn @else lvl-good @endif">
                                        {{ $lv }}
                                    </span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-text center">Sin asignaturas asociadas al grado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <table style="width: 100%; margin-top: 4px; font-size: 7.5px; color: #64748b; border-collapse: collapse;">
            <tr>
                <td style="border: none; padding: 2px 0;">
                    <strong>Escala IB:</strong>
                    &nbsp;<span class="badge badge-good">4 a 7</span> Desempeño Satisfactorio
                    &nbsp;·&nbsp;<span class="badge badge-warn">3</span> Nivel de Atención
                    &nbsp;·&nbsp;<span class="badge badge-crit">1 a 2</span> Intervención Prioritaria
                </td>
            </tr>
        </table>

        @if (!empty($subjectAttentions) && count($subjectAttentions) > 0)
            <div style="margin-top: 10px;">
                <div style="font-size: 8.5px; font-weight: bold; color: #364e76; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; border-bottom: 1.5px solid #364e76; padding-bottom: 2px;">
                    Asignaturas para tener en cuenta · Observaciones pedagógicas docentes
                </div>
                @foreach ($subjectAttentions as $sa)
                    <div class="note-card" style="border-left: 3px solid #364e76; margin-bottom: 4px;">
                        <div class="note-body">{{ $sa['body'] }}</div>
                        <div class="note-meta">
                            <span class="period-chip">{{ $sa['follow_up_label'] }}</span>
                            Registrado por <strong>{{ $sa['author_name'] }}</strong>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- ════════ 10 COMITÉ EVALUADOR ════════ -->
    <div class="sec">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num">10</span>Decisiones del comité evaluador</h2>
                    <p class="sec-subtitle">Dictámenes de comisiones de evaluación y promoción</p>
                </td>
            </tr>
        </table>

        @forelse ($committee['items'] as $c)
            <div class="note-card">
                <div style="font-size: 10px; font-weight: bold; color: #364e76;">
                    {{ $c['decision'] }}
                </div>
                <div class="note-meta" style="margin-top: 1px;">
                    Fecha: <strong>{{ $c['decided_on'] }}</strong> &nbsp;·&nbsp;
                    Registrado por: <strong>{{ $c['author_name'] }}</strong>
                </div>
                @if ($c['notes'])
                    <div class="note-body" style="margin-top: 3px;">{{ $c['notes'] }}</div>
                @endif
            </div>
        @empty
            <p class="empty-text">Sin decisiones registradas del comité evaluador.</p>
        @endforelse
    </div>

    <!-- ════════ 11 PROCESO DISCIPLINARIO ════════ -->
    <div class="sec sec-accent">
        <table class="sec-header">
            <tr>
                <td>
                    <h2 class="sec-title"><span class="sec-num" style="background:#ed3236">11</span>Proceso formativo y convivencial</h2>
                    <p class="sec-subtitle">Situaciones tipificadas conforme al Manual de Convivencia institucional</p>
                </td>
            </tr>
        </table>

        @if ($disciplinary)
            @forelse ($disciplinary['items'] as $d)
                @php
                    $sclass = $d['status'] === 'closed' ? 'badge-mute' : 'badge-crit';
                @endphp
                <div class="note-card note-improvement">
                    <div style="font-size: 9.5px; font-weight: bold; color: #9b1c1c;">
                        {{ $d['type'] }}
                    </div>
                    <div class="note-body" style="margin-top: 2px;">{{ $d['description'] }}</div>
                    @if (!empty($d['action_taken']))
                        <div class="note-body" style="margin-top: 2px; color: #475569;">
                            <strong>Acción tomada:</strong> {{ $d['action_taken'] }}
                        </div>
                    @endif
                    <div class="note-meta" style="margin-top: 2px;">
                        <span class="badge {{ $sclass }}">{{ $d['status_label'] }}</span>
                        &nbsp;·&nbsp; Fecha del hecho: <strong>{{ $d['occurred_on'] }}</strong>
                    </div>
                </div>
            @empty
                <p class="empty-text">Sin situaciones disciplinarias registradas.</p>
            @endforelse
        @else
            <p class="empty-text">Información restringida para tu perfil de usuario.</p>
        @endif
    </div>

    <!-- ════════ BLOQUE DE VALIDACIÓN Y FIRMAS ════════ -->
    <table class="signatures-table">
        <tr>
            <td>
                <div class="sig-line">
                    <div class="sig-name">{{ $group['director_name'] ?? 'Director(a) de Grupo' }}</div>
                    <div class="sig-role">Director(a) de Grupo {{ $group['code'] }}</div>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <div class="sig-name">Coordinación de Sección</div>
                    <div class="sig-role">Equipo de Mandos Medios (EMC) · {{ $group['section'] }}</div>
                </div>
            </td>
            <td>
                <div class="sig-line">
                    <div class="sig-name">Dpto. de Apoyo Integral</div>
                    <div class="sig-role">Psicología / Apoyo Pedagógico TVS</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ════════ CLÁUSULA DE CONFIDENCIALIDAD Y PROTECCIÓN DE DATOS ════════ -->
    <div class="legal-notice">
        <strong>Aviso de Confidencialidad y Protección de Datos Personales:</strong> Este documento contiene información
        académica, formativa y psicológica confidencial de The Victoria School, protegida bajo la Ley Estatutaria 1581 de 2012
        de la República de Colombia y su Decreto Reglamentario 1377 de 2013 sobre protección de datos personales de menores de edad.
        Su custodia, consulta y tratamiento están restringidos exclusivamente al equipo pedagógico y administrativo autorizado.
        Queda prohibida su reproducción, divulgación total o parcial o entrega a terceros sin la autorización expresa de la Dirección.
    </div>

</body>
</html>
