<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulletin — {{ $bulletin['student']->first_name }} {{ $bulletin['student']->last_name }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #1d2f52; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 30px; background: #f3f5fa; }
        .toolbar, .bulletin { width: min(100%, 900px); margin: auto; }
        .toolbar { display: flex; justify-content: flex-end; margin-bottom: 16px; }
        button { border: 0; border-radius: 8px; padding: 12px 18px; background: #1d2f52; color: white; font-size: 15px; cursor: pointer; }
        .bulletin { padding: 38px; background: #fff; border: 1px solid #dfe5ef; border-radius: 16px; }
        header { display: table; width: 100%; padding-bottom: 22px; border-bottom: 3px solid #eba643; }
        header > div { display: table-cell; vertical-align: top; }
        h1, h2 { margin: 0; font-family: Georgia, serif; }
        h1 { font-size: 29px; }
        h2 { margin-bottom: 8px; font-size: 23px; }
        p { margin: 6px 0; color: #687691; }
        .school { text-align: right; font-weight: 700; }
        .identity { display: table; width: 100%; margin: 24px 0; }
        .identity > div { display: table-cell; width: 25%; padding: 5px; vertical-align: top; }
        .label { display: block; margin-bottom: 5px; color: #8792a8; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .value { font-weight: 600; }
        .summary { display: table; width: 100%; margin-bottom: 24px; border-spacing: 8px 0; }
        .card { display: table-cell; width: 33.33%; padding: 18px; border-radius: 12px; background: #f5f7fc; }
        .card strong { display: block; margin-top: 6px; font-family: Georgia, serif; font-size: 27px; }
        .card.average { background: #eefaf6; color: #07866c; }
        .card.rank { background: #fff8e9; color: #b86a06; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 9px; border-bottom: 1px solid #e6eaf1; text-align: left; }
        th { background: #f5f7fc; color: #687691; font-size: 11px; text-transform: uppercase; }
        td.number, th.number { text-align: center; }
        .missing { color: #8792a8; font-style: italic; }
        .appreciation { display: block; margin-top: 4px; color: #687691; font-size: 12px; }
        footer { display: table; width: 100%; margin-top: 34px; padding-top: 18px; border-top: 1px solid #e4e8ef; color: #687691; }
        footer span { display: table-cell; width: 50%; }
        footer span:last-child { text-align: right; }
        .pdf-document { padding: 0; background: #fff; }
        .pdf-document .toolbar { display: none; }
        .pdf-document .bulletin { width: 100%; max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; }
        @media (max-width: 640px) { body { padding: 10px; } .bulletin { padding: 20px; overflow-x: auto; } }
        @media print { @page { size: A4; margin: 14mm; } body { padding: 0; background: #fff; } .toolbar { display: none; } .bulletin { width: 100%; margin: 0; padding: 0; border: 0; border-radius: 0; } thead { display: table-header-group; } tr { break-inside: avoid; } }
    </style>
</head>
<body class="{{ ($isPdf ?? false) ? 'pdf-document' : '' }}">
    @if (! ($isPdf ?? false))
        <div class="toolbar"><button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
    @endif
    <main class="bulletin">
        <header>
            <div>
                <h1>Bulletin de notes</h1>
                <p>{{ $bulletin['period']->label }} · Année scolaire {{ $bulletin['schoolYear']->label }}</p>
            </div>
            <div class="school">{{ $schoolName }}<p>Relevé scolaire</p></div>
        </header>

        <section class="identity" aria-label="Informations de l'élève">
            <div><span class="label">Élève</span><span class="value">{{ $bulletin['student']->first_name }} {{ $bulletin['student']->last_name }}</span></div>
            <div><span class="label">Matricule</span><span class="value">{{ $bulletin['student']->matricule }}</span></div>
            <div><span class="label">Classe</span><span class="value">{{ $bulletin['class']->name }}</span></div>
            <div><span class="label">Niveau</span><span class="value">{{ $bulletin['class']->level }}</span></div>
        </section>

        <section class="summary" aria-label="Résultats de la période">
            <div class="card average"><span class="label">Moyenne générale /20</span><strong>{{ $bulletin['average'] === null ? '—' : number_format($bulletin['average'], 2, ',', ' ') }}</strong></div>
            <div class="card rank"><span class="label">Rang dans la classe</span><strong>{{ $bulletin['rank'] === null ? '—' : ($bulletin['rank'] === 1 ? '1er' : $bulletin['rank'].'e') }}</strong><p>Sur {{ $bulletin['class_size'] }} élève(s) avec une moyenne</p></div>
            <div class="card"><span class="label">Mention</span><strong>{{ $bulletin['mention'] ?? 'Non disponible' }}</strong><p>Moyenne calculée avec les notes saisies</p></div>
        </section>

        <h2>Détail des matières</h2>
        <table>
            <thead><tr><th>Matière / évaluations</th><th class="number">Notes /20</th><th class="number">Moyenne</th><th class="number">Coeff.</th></tr></thead>
            <tbody>
                @forelse ($bulletin['subjects'] as $subject)
                    <tr>
                        <td>
                            <strong>{{ $subject['name'] }}</strong>
                            @forelse ($subject['assessments'] as $assessment)
                                <span class="appreciation">{{ $assessment['label'] }} ({{ $assessment['date'] }}){{ $assessment['appreciation'] ? ' — '.$assessment['appreciation'] : '' }}</span>
                            @empty
                                <span class="appreciation">Aucune évaluation pour cette période</span>
                            @endforelse
                        </td>
                        <td class="number">
                            @forelse ($subject['assessments'] as $assessment)
                                {{ $assessment['value'] === null ? '—' : number_format($assessment['value'], 2, ',', ' ') }}@if (! $loop->last) · @endif
                            @empty
                                <span class="missing">Non saisie</span>
                            @endforelse
                        </td>
                        <td class="number">{{ $subject['average'] === null ? '—' : number_format($subject['average'], 2, ',', ' ') }}</td>
                        <td class="number">{{ $subject['coefficient'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Aucune matière n'est affectée à cette classe.</td></tr>
                @endforelse
            </tbody>
        </table>

        <footer><span>Document généré le {{ now()->locale('fr')->translatedFormat('d F Y') }}</span><span>Signature et cachet de l'établissement</span></footer>
    </main>
</body>
</html>
