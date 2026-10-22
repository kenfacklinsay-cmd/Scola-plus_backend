<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reçu {{ $payment->receipt_number }} — {{ $schoolName }}</title>
    <style>
        body { font-family: "DejaVu Sans", sans-serif; color: #1d2f52; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; background: #f3f5fa; }
        .receipt { width: 100%; max-width: 760px; margin: 0 auto; padding: 42px; background: #fff; border: 1px solid #dfe5ef; border-radius: 16px; }
        .toolbar { width: min(100%, 760px); margin: 0 auto 16px; display: flex; justify-content: flex-end; }
        button { border: 0; border-radius: 8px; padding: 12px 18px; background: #1d2f52; color: #fff; font-size: 15px; cursor: pointer; }
        header { display: table; width: 100%; padding-bottom: 24px; border-bottom: 2px solid #eba643; }
        header > div { display: table-cell; vertical-align: top; }
        header > div:last-child { text-align: right; }
        h1 { margin: 0 0 8px; font-family: Georgia, serif; font-size: 30px; }
        p { margin: 5px 0; color: #65738e; }
        .receipt-number { text-align: right; font-weight: 700; }
        .amount { margin: 28px 0; padding: 22px; border-radius: 12px; background: #eefaf6; text-align: center; }
        .amount strong { display: block; margin-top: 6px; color: #07866c; font-family: Georgia, serif; font-size: 32px; }
        dl { display: table; width: 100%; margin: 0; }
        dl > div { display: table-row; }
        dt, dd { display: table-cell; width: 50%; padding: 9px 8px; }
        dt { color: #8792a8; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        dd { font-weight: 600; }
        footer { margin-top: 34px; padding-top: 18px; border-top: 1px solid #e4e8ef; color: #65738e; font-size: 13px; }
        @media (max-width: 560px) { body { padding: 12px; } .receipt { padding: 24px; } }
        .pdf-document { padding: 0; background: #fff; }
        .pdf-document .toolbar { display: none; }
        .pdf-document .receipt { width: 100%; max-width: none; margin: 0; padding: 0; border: 0; border-radius: 0; }
        @media print { @page { size: A4; margin: 18mm; } body { padding: 0; background: #fff; } .toolbar { display: none; } .receipt { width: 100%; margin: 0; padding: 0; border: 0; border-radius: 0; } }
    </style>
</head>
<body class="{{ ($isPdf ?? false) ? 'pdf-document' : '' }}">
    @if (! ($isPdf ?? false))
        <div class="toolbar"><button type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button></div>
    @endif
    <main class="receipt">
        <header>
            <div>
                <h1>{{ $schoolName }}</h1>
                <p>Reçu de paiement des frais de scolarité</p>
            </div>
            <div class="receipt-number">
                <p>Numéro du reçu</p>
                <strong>{{ $payment->receipt_number }}</strong>
            </div>
        </header>

        <section class="amount" aria-label="Montant payé">
            Montant reçu
            <strong>{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</strong>
        </section>

        <dl>
            <div><dt>Élève</dt><dd>{{ $payment->student->first_name }} {{ $payment->student->last_name }}</dd></div>
            <div><dt>Matricule</dt><dd>{{ $payment->student->matricule }}</dd></div>
            <div><dt>Classe</dt><dd>{{ $payment->installment->tuitionFee->schoolClass->name }}</dd></div>
            <div><dt>Année scolaire</dt><dd>{{ $payment->installment->tuitionFee->schoolYear->label }}</dd></div>
            <div><dt>Tranche</dt><dd>{{ $payment->installment->label }}</dd></div>
            <div><dt>Date du paiement</dt><dd>{{ $payment->payment_date->locale('fr')->translatedFormat('d F Y à H:i') }}</dd></div>
            <div><dt>Mode de paiement</dt><dd>{{ $payment->payment_method }}</dd></div>
        </dl>

        <footer>Ce document confirme l'enregistrement du paiement portant le numéro {{ $payment->receipt_number }}.</footer>
    </main>
</body>
</html>
