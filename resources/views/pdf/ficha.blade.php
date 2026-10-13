@php
    use Illuminate\Support\Carbon;

    $fecha = fn (?string $d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
    $sec = 0;
    $num = function () use (&$sec) {
        $sec++;

        return str_pad((string) $sec, 2, '0', STR_PAD_LEFT);
    };
    $initials = collect(preg_split('/\s+/', trim($student['full_name'])))
        ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $strategyClass = fn (string $r) => match ($r) {
        'Funciona' => 'tag-good',
        'Funciona parcialmente' => 'tag-warn',
        'No funciona' => 'tag-crit',
        default => 'tag-mute',
    };
    $levelClass = fn (?int $lv) => $lv === null ? '' : ($lv <= 2 ? 'lvl-crit' : ($lv <= 3 ? 'lvl-warn' : 'lvl-good'));
    $allCommitments = collect($commitments['school'])->map(fn ($c) => $c + ['scope' => 'Colegio'])
        ->concat(collect($commitments['family'])->map(fn ($c) => $c + ['scope' => 'Familia']))
        ->sortBy(fn ($c) => $c['due_on'] ?? '9999-12-31')->values();
    $supportsWithNotes = collect($internalSupports)->filter(fn ($a) => count($a['notes']) > 0)->values();
    $supportsEmpty = collect($internalSupports)->filter(fn ($a) => count($a['notes']) === 0)->pluck('label');
    $restricted = collect([
        collect($internalSupports)->isEmpty() ? 'Apoyos internos' : null,
        $externalSupports === null ? 'Apoyos externos' : null,
        $disciplinary === null ? 'Proceso formativo y convivencial' : null,
    ])->filter()->values();
    $font = fn (string $file) => resource_path('fonts/pdf/'.$file);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de seguimiento · {{ $student['full_name'] }}</title>
    <style>
        @font-face { font-family: 'Public Sans'; font-weight: normal; font-style: normal; src: url('{{ $font('PublicSans_400Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Public Sans'; font-weight: normal; font-style: italic; src: url('{{ $font('PublicSans_400Regular_Italic.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Public Sans'; font-weight: bold; font-style: normal; src: url('{{ $font('PublicSans_700Bold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Public Sans SemiBold'; font-weight: normal; font-style: normal; src: url('{{ $font('PublicSans_600SemiBold.ttf') }}') format('truetype'); }

        @page { margin: 34pt 40pt 52pt 40pt; }

        * { box-sizing: border-box; }
        body {
            font-family: 'Public Sans', 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            line-height: 1.45;
            color: #212838;
            margin: 0;
        }
        .semi { font-family: 'Public Sans SemiBold', 'Public Sans', sans-serif; }
        b, strong { font-weight: bold; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #5e636e; }
        .small { font-size: 7.5pt; }

        /* ── Membrete ── */
        .topbar { height: 5pt; background: #364a72; }
        .topbar-red { height: 2pt; background: #ee3338; margin-bottom: 12pt; }
        .brand { font-size: 8pt; letter-spacing: 2.4pt; color: #364a72; }
        .brand-sub { font-size: 7pt; color: #5e636e; letter-spacing: 0.6pt; margin-top: 2pt; }
        .doc-title { font-size: 15pt; color: #364a72; text-align: right; line-height: 1.1; }
        .doc-sub { font-size: 7.5pt; color: #5e636e; text-align: right; margin-top: 3pt; }
        .conf { display: inline-block; border: 0.75pt solid #a9252a; color: #a9252a; font-size: 6.5pt; letter-spacing: 1.2pt; padding: 1.5pt 5pt; border-radius: 2pt; }

        /* ── Identificación del estudiante ── */
        .id-card { margin-top: 14pt; border: 0.75pt solid #d3d7df; border-radius: 4pt; }
        .photo-cell { width: 140pt; padding: 12pt; background: #f4f6f9; border-right: 0.75pt solid #d3d7df; text-align: center; }
        .photo { width: 116pt; height: 116pt; border-radius: 4pt; border: 2pt solid #ffffff; }
        .initials { width: 116pt; border-radius: 4pt; background: #364a72; }
        .initials td { height: 116pt; vertical-align: middle; text-align: center; color: #ffffff; font-size: 36pt; line-height: 1; }
        .info-cell { padding: 13pt 14pt 10pt 16pt; }
        .stu-name { font-size: 19pt; line-height: 1.15; color: #212838; margin: 0 0 3pt 0; }
        .stu-meta { font-size: 8.5pt; color: #5e636e; margin-bottom: 10pt; }
        .stu-meta b { color: #212838; }
        .facts td { padding: 5pt 10pt 5pt 0; border-top: 0.5pt solid #e4e7ec; width: 33%; }
        .fact-label { font-size: 6.5pt; letter-spacing: 0.9pt; text-transform: uppercase; color: #5e636e; }
        .fact-value { font-size: 9pt; margin-top: 1pt; }

        /* ── Indicadores ── */
        .kpis { margin-top: 10pt; }
        .kpis td { width: 25%; padding: 0 3pt; }
        .kpis td:first-child { padding-left: 0; }
        .kpis td:last-child { padding-right: 0; }
        .kpi { border: 0.75pt solid #d3d7df; border-left: 3pt solid #364a72; padding: 7pt 9pt; }
        .kpi.warn { border-left-color: #b77a00; background: #fbf4e3; }
        .kpi.crit { border-left-color: #a9252a; background: #fdeeee; }
        .kpi-num { font-size: 17pt; line-height: 1; color: #212838; }
        .kpi-label { font-size: 7pt; color: #5e636e; margin-top: 3pt; }

        /* ── Secciones ── */
        .section { margin-top: 16pt; }
        .sec-head { border-bottom: 1pt solid #364a72; padding-bottom: 3pt; margin-bottom: 7pt; page-break-after: avoid; }
        .sec-num { display: inline-block; width: 18pt; color: #ee3338; font-size: 10pt; }
        .sec-title { display: inline; font-size: 10.5pt; color: #364a72; letter-spacing: 0.3pt; text-transform: uppercase; }
        .sec-tag { float: right; font-size: 6.5pt; letter-spacing: 1pt; color: #a9252a; margin-top: 2pt; }
        .sec-desc { font-size: 7.5pt; color: #5e636e; margin: -3pt 0 7pt 0; }
        .empty { font-size: 8.5pt; font-style: italic; color: #8a8f99; padding: 2pt 0 2pt 10pt; border-left: 2pt solid #e4e7ec; }

        .note { border-left: 2pt solid #364a72; padding: 2pt 0 2pt 9pt; margin-bottom: 7pt; page-break-inside: avoid; }
        .note.red { border-left-color: #ee3338; }
        .note.grey { border-left-color: #8a8f99; }
        .note-body { text-align: left; }
        .note-meta { font-size: 7pt; color: #5e636e; margin-top: 2pt; }
        .chip { display: inline-block; background: #ebeef4; color: #364a72; font-size: 6.5pt; padding: 0.5pt 4pt; border-radius: 2pt; margin-right: 3pt; }

        .tag { display: inline-block; font-size: 6.5pt; padding: 1pt 5pt; border-radius: 2pt; }
        .tag-good { background: #e3f4ec; color: #106a45; }
        .tag-warn { background: #fbf0d6; color: #8f5b00; }
        .tag-crit { background: #fdeeee; color: #a9252a; }
        .tag-mute { background: #eef0f3; color: #5e636e; }

        .strategy { border: 0.75pt solid #e4e7ec; border-radius: 3pt; padding: 7pt 9pt; margin-bottom: 7pt; page-break-inside: avoid; }
        .strategy-body { font-size: 9pt; }
        .reviews { margin-top: 5pt; }
        .reviews td { font-size: 7.5pt; padding: 3pt 6pt 3pt 0; border-top: 0.5pt solid #eef0f3; }

        .grid th { font-size: 6.5pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #5e636e; text-align: left; padding: 4pt 6pt; background: #f4f6f9; border-bottom: 0.75pt solid #d3d7df; font-weight: normal; }
        .grid td { padding: 3.5pt 6pt; border-bottom: 0.5pt solid #e4e7ec; font-size: 8.5pt; }
        .grid tr { page-break-inside: avoid; }
        .center, .grid th.center { text-align: center; }
        .lvl { display: inline-block; width: 18pt; padding: 0.5pt 0; border-radius: 2pt; text-align: center; font-size: 8pt; }
        .lvl-cell { width: 20pt; margin: 0 auto; padding: 0.5pt 0; border-radius: 2pt; text-align: center; font-size: 8.5pt; }
        .lvl-good { background: #e3f4ec; color: #106a45; }
        .lvl-warn { background: #fbf0d6; color: #8f5b00; }
        .lvl-crit { background: #fdeeee; color: #a9252a; }
        .legend { font-size: 7pt; color: #5e636e; margin-top: 5pt; }

        .area-title { font-size: 7.5pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #364a72; margin: 6pt 0 4pt 0; }

        /* ── Firmas y aviso ── */
        .closing { margin-top: 22pt; }
        .sign { page-break-inside: avoid; }
        .sign td { width: 33.3%; padding: 0 10pt; text-align: center; }
        .sign td:first-child { padding-left: 0; }
        .sign td:last-child { padding-right: 0; }
        .sign-line { border-top: 0.75pt solid #212838; padding-top: 4pt; margin-top: 30pt; }
        .sign-name { font-size: 8.5pt; }
        .sign-role { font-size: 7pt; color: #5e636e; }
        .legal { margin-top: 14pt; page-break-inside: avoid; padding: 7pt 9pt; background: #f4f6f9; border-radius: 3pt; font-size: 6.8pt; line-height: 1.45; color: #5e636e; text-align: justify; }
    </style>
</head>
<body>

    {{-- ═══════════ Membrete ═══════════ --}}
    <div class="topbar"></div>
    <div class="topbar-red"></div>
    <table>
        <tr>
            <td style="width: 50%;">
                <div class="brand semi">THE VICTORIA SCHOOL</div>
                <div class="brand-sub">Colegio del Mundo del IB · Continuo IB (PEP · PAI · DP)</div>
            </td>
            <td style="width: 50%; text-align: right;">
                <div class="doc-title semi">Ficha integral de seguimiento</div>
                <div class="doc-sub">Seguimiento académico y convivencial · Año escolar {{ $schoolYear }}</div>
                <div style="margin-top: 5pt;"><span class="conf semi">CONFIDENCIAL</span></div>
            </td>
        </tr>
    </table>

    {{-- ═══════════ Identificación ═══════════ --}}
    <table class="id-card">
        <tr>
            <td class="photo-cell">
                @if (!empty($photoBase64))
                    <img class="photo" src="{{ $photoBase64 }}" alt="Foto de {{ $student['full_name'] }}">
                @else
                    <table class="initials" align="center"><tr><td class="semi">{{ $initials }}</td></tr></table>
                @endif
            </td>
            <td class="info-cell">
                <h1 class="stu-name semi">{{ $student['full_name'] }}</h1>
                <div class="stu-meta">
                    @if (!empty($student['institutional_code']))
                        Código institucional <b>{{ $student['institutional_code'] }}</b> &nbsp;·&nbsp;
                    @endif
                    Grupo <b>{{ $group['code'] }}</b> &nbsp;·&nbsp; {{ $group['grade_label'] }}
                </div>
                <table class="facts">
                    <tr>
                        <td>
                            <div class="fact-label">Sección</div>
                            <div class="fact-value">{{ $group['section'] }}</div>
                        </td>
                        <td>
                            <div class="fact-label">Director(a) de grupo</div>
                            <div class="fact-value">{{ $group['director_name'] ?? 'Sin asignar' }}</div>
                        </td>
                        <td>
                            <div class="fact-label">Año escolar</div>
                            <div class="fact-value">{{ $schoolYear }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fact-label">Fecha de emisión</div>
                            <div class="fact-value">{{ $generatedOn }}</div>
                        </td>
                        <td colspan="2">
                            <div class="fact-label">Generado por</div>
                            <div class="fact-value">{{ $exportedBy ?? '—' }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ═══════════ Indicadores ═══════════ --}}
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi">
                    <div class="kpi-num semi">{{ $stats['open_commitments'] }}</div>
                    <div class="kpi-label">Compromisos abiertos</div>
                </div>
            </td>
            <td>
                <div class="kpi {{ $stats['late_commitments'] > 0 ? 'crit' : '' }}">
                    <div class="kpi-num semi">{{ $stats['late_commitments'] }}</div>
                    <div class="kpi-label">Compromisos vencidos</div>
                </div>
            </td>
            <td>
                <div class="kpi {{ $stats['attention_subjects'] > 0 ? 'warn' : '' }}">
                    <div class="kpi-num semi">{{ $stats['attention_subjects'] }}</div>
                    <div class="kpi-label">Asignaturas por atender</div>
                </div>
            </td>
            <td>
                <div class="kpi">
                    <div class="kpi-num semi">{{ count($individualStrategies) }}</div>
                    <div class="kpi-label">Estrategias individuales</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ═══════════ Fortalezas ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Fortalezas y avances</h2></div>
        @forelse ($strengths as $n)
            <div class="note">
                <div class="note-body">{{ $n['body'] }}</div>
                <div class="note-meta"><span class="chip">{{ $n['follow_up_label'] }}</span>{{ $n['author_name'] }}</div>
            </div>
        @empty
            <div class="empty">Sin fortalezas registradas en este año escolar.</div>
        @endforelse
    </div>

    {{-- ═══════════ Aspectos de mejora ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Aspectos de mejora</h2></div>
        @forelse ($improvements as $n)
            <div class="note red">
                <div class="note-body">{{ $n['body'] }}</div>
                <div class="note-meta"><span class="chip">{{ $n['follow_up_label'] }}</span>{{ $n['author_name'] }}</div>
            </div>
        @empty
            <div class="empty">Sin aspectos de mejora registrados en este año escolar.</div>
        @endforelse
    </div>

    {{-- ═══════════ Desempeño académico ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Desempeño académico · escala IB 1–7</h2></div>
        @if (count($performance) > 0)
            <table class="grid">
                <thead>
                    <tr>
                        <th>Asignatura</th>
                        <th class="center" style="width: 70pt;">Periodo 1</th>
                        <th class="center" style="width: 70pt;">Periodo 2</th>
                        <th class="center" style="width: 70pt;">Periodo 3</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($performance as $row)
                        <tr>
                            <td>{{ $row['subject'] }}</td>
                            @for ($p = 1; $p <= 3; $p++)
                                @php($lv = $row['levels'][$p] ?? null)
                                <td class="center">
                                    @if ($lv === null)
                                        <span class="muted">—</span>
                                    @else
                                        <div class="lvl-cell semi {{ $levelClass($lv) }}">{{ $lv }}</div>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="legend">
                <span class="lvl lvl-good">4–7</span> Desempeño satisfactorio &nbsp;&nbsp;
                <span class="lvl lvl-warn">3</span> Requiere atención &nbsp;&nbsp;
                <span class="lvl lvl-crit">1–2</span> Intervención prioritaria
            </div>
        @else
            <div class="empty">El grado no tiene asignaturas asociadas.</div>
        @endif

        @if (count($subjectAttentions) > 0)
            <div class="area-title semi" style="margin-top: 10pt;">Asignaturas para tener en cuenta</div>
            @foreach ($subjectAttentions as $n)
                <div class="note">
                    <div class="note-body">{{ $n['body'] }}</div>
                    <div class="note-meta"><span class="chip">{{ $n['follow_up_label'] }}</span>{{ $n['author_name'] }}</div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- ═══════════ Estrategias individuales ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Estrategias individuales</h2></div>
        @forelse ($individualStrategies as $s)
            <div class="strategy">
                <table>
                    <tr>
                        <td class="strategy-body">{{ $s['body'] }}</td>
                        <td style="width: 90pt; text-align: right;"><span class="tag semi {{ $strategyClass($s['last_result']) }}">{{ $s['last_result'] }}</span></td>
                    </tr>
                </table>
                <div class="note-meta"><span class="chip">Desde {{ $s['since'] }}</span>Responsable: {{ $s['responsible'] ?: 'Sin asignar' }}</div>
                @if (count($s['reviews']) > 0)
                    <table class="reviews">
                        @foreach ($s['reviews'] as $rev)
                            <tr>
                                <td style="width: 52pt;" class="muted">{{ $fecha($rev['reviewed_on']) }}</td>
                                <td style="width: 92pt;"><span class="tag {{ $strategyClass($rev['result']) }}">{{ $rev['result'] }}</span></td>
                                <td>{{ $rev['body'] }} <span class="muted">— {{ $rev['author_name'] }}</span></td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @empty
            <div class="empty">Sin estrategias individuales en este año escolar.</div>
        @endforelse

        @if (count($groupStrategies) > 0)
            <div class="area-title semi" style="margin-top: 10pt;">Estrategias grupales de {{ $group['code'] }}</div>
            @foreach ($groupStrategies as $s)
                <div class="note grey">
                    <div class="note-body">{{ $s['body'] }}</div>
                    <div class="note-meta">
                        <span class="tag {{ $strategyClass($s['last_result']) }}">{{ $s['last_result'] }}</span>
                        &nbsp;Responsable: {{ $s['responsible'] ?: 'Docentes del grupo' }}
                    </div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- ═══════════ Compromisos ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Acuerdos y compromisos</h2></div>
        @if ($allCommitments->isNotEmpty())
            <table class="grid">
                <thead>
                    <tr>
                        <th style="width: 50pt;">Ámbito</th>
                        <th>Compromiso</th>
                        <th style="width: 100pt;">Responsable</th>
                        <th style="width: 56pt;">Fecha límite</th>
                        <th style="width: 64pt;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($allCommitments as $c)
                        @php($cls = $c['is_late'] ? 'tag-crit' : ($c['status'] === 'done' ? 'tag-good' : ($c['status'] === 'closed' ? 'tag-mute' : 'tag-warn')))
                        <tr>
                            <td class="muted">{{ $c['scope'] }}</td>
                            <td>{{ $c['body'] }}</td>
                            <td>{{ $c['responsible'] ?: '—' }}</td>
                            <td>{{ $fecha($c['due_on']) }}</td>
                            <td><span class="tag {{ $cls }}">{{ $c['is_late'] ? 'Vencido' : $c['status_label'] }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">Sin compromisos registrados.</div>
        @endif
    </div>

    {{-- ═══════════ Apoyos internos (confidencial) ═══════════ --}}
    @if (collect($internalSupports)->isNotEmpty())
        <div class="section">
            <div class="sec-head">
                <span class="sec-tag semi">USO CONFIDENCIAL</span>
                <span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Apoyos internos</h2>
            </div>
            <div class="sec-desc">Departamento de Apoyo y Coordinación</div>
            @foreach ($supportsWithNotes as $area)
                <div class="area-title semi">{{ $area['label'] }}</div>
                @foreach ($area['notes'] as $n)
                    <div class="note grey">
                        <div class="note-body">{{ $n['body'] }}</div>
                        <div class="note-meta"><span class="chip">{{ $n['follow_up_label'] }}</span>{{ $n['author_name'] }}</div>
                    </div>
                @endforeach
            @endforeach
            @if ($supportsEmpty->isNotEmpty())
                <div class="empty" @if ($supportsWithNotes->isNotEmpty()) style="margin-top: 6pt;" @endif>
                    Sin registros en: {{ $supportsEmpty->implode(', ') }}.
                </div>
            @endif
        </div>
    @endif

    {{-- ═══════════ Apoyos externos ═══════════ --}}
    @if ($externalSupports !== null)
        <div class="section">
            <div class="sec-head">
                <span class="sec-tag semi">USO CONFIDENCIAL</span>
                <span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Terapias y apoyos externos</h2>
            </div>
            @if (count($externalSupports['items']) > 0)
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Profesional / institución</th>
                            <th style="width: 100pt;">Especialidad</th>
                            <th style="width: 80pt;">Frecuencia</th>
                            <th style="width: 110pt;">Contacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($externalSupports['items'] as $x)
                            <tr>
                                <td>
                                    {{ $x['provider'] }}
                                    @if (!empty($x['notes']))<div class="note-meta">{{ $x['notes'] }}</div>@endif
                                </td>
                                <td>{{ $x['specialty'] }}</td>
                                <td>{{ $x['frequency'] ?: '—' }}</td>
                                <td>{{ $x['contact'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">Sin apoyos externos registrados.</div>
            @endif
        </div>
    @endif

    {{-- ═══════════ Comité evaluador ═══════════ --}}
    <div class="section">
        <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Decisiones del comité evaluador</h2></div>
        @forelse ($committee['items'] as $c)
            <div class="note">
                <div class="note-body semi">{{ $c['decision'] }}</div>
                @if (!empty($c['notes']))<div class="note-body">{{ $c['notes'] }}</div>@endif
                <div class="note-meta"><span class="chip">{{ $fecha($c['decided_on']) }}</span>{{ $c['author_name'] }}</div>
            </div>
        @empty
            <div class="empty">Sin decisiones del comité en este año escolar.</div>
        @endforelse
    </div>

    {{-- ═══════════ Proceso formativo y convivencial ═══════════ --}}
    @if ($disciplinary !== null)
        <div class="section">
            <div class="sec-head"><span class="sec-num semi">{{ $num() }}</span><h2 class="sec-title semi">Proceso formativo y convivencial</h2></div>
            @forelse ($disciplinary['items'] as $d)
                <div class="note red">
                    <div class="note-body">
                        <span class="semi">{{ $d['type'] }}</span>
                        &nbsp;<span class="tag {{ $d['status'] === 'closed' ? 'tag-mute' : 'tag-crit' }}">{{ $d['status_label'] }}</span>
                    </div>
                    <div class="note-body">{{ $d['description'] }}</div>
                    @if (!empty($d['action_taken']))
                        <div class="note-body"><span class="muted">Acción tomada:</span> {{ $d['action_taken'] }}</div>
                    @endif
                    <div class="note-meta"><span class="chip">{{ $fecha($d['occurred_on']) }}</span>Fecha del hecho</div>
                </div>
            @empty
                <div class="empty">Sin situaciones registradas.</div>
            @endforelse
        </div>
    @endif

    {{-- ═══════════ Firmas y aviso legal ═══════════ --}}
    <div class="closing">
        <table class="sign">
            <tr>
                <td>
                    <div class="sign-line">
                        <div class="sign-name semi">{{ $group['director_name'] ?? 'Director(a) de grupo' }}</div>
                        <div class="sign-role">Director(a) de grupo · {{ $group['code'] }}</div>
                    </div>
                </td>
                <td>
                    <div class="sign-line">
                        <div class="sign-name semi">Coordinación de sección</div>
                        <div class="sign-role">EMC · {{ $group['section'] }}</div>
                    </div>
                </td>
                <td>
                    <div class="sign-line">
                        <div class="sign-name semi">Departamento de Apoyo</div>
                        <div class="sign-role">Psicología y apoyo pedagógico</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="legal">
            <b>Aviso de confidencialidad y protección de datos personales.</b> Este documento contiene información académica,
            formativa y psicológica confidencial de The Victoria School, protegida por la Ley Estatutaria 1581 de 2012 de la
            República de Colombia y su Decreto Reglamentario 1377 de 2013 sobre protección de datos personales de menores de edad.
            Su custodia, consulta y tratamiento están restringidos exclusivamente al equipo pedagógico y administrativo autorizado.
            Queda prohibida su reproducción, divulgación total o parcial o entrega a terceros sin la autorización expresa de la Dirección.
            @if ($restricted->isNotEmpty())
                <br><br>Este documento refleja la información visible para el perfil de quien lo generó; no incluye: {{ $restricted->implode(', ') }}.
            @endif
        </div>
    </div>

</body>
</html>
