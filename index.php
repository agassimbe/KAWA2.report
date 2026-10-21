<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/requetes.php';

/*
|--------------------------------------------------------------------------
| Filtres (optionnels, via GET)
|--------------------------------------------------------------------------
*/

$dateDebut = $_GET['date_debut'] ?? null;
$dateFin   = $_GET['date_fin']   ?? null;

$dateDebut = ($dateDebut !== null && $dateDebut !== '') ? $dateDebut : null;
$dateFin   = ($dateFin   !== null && $dateFin   !== '') ? $dateFin   : null;

/*
|--------------------------------------------------------------------------
| KPI globaux
|--------------------------------------------------------------------------
*/

$totalDessertes   = getTotalDessertes($pdo, $dateDebut, $dateFin);
$totalTournees    = getTotalTournees($pdo, $dateDebut, $dateFin);
$totalClients     = getTotalClients($pdo, $dateDebut, $dateFin);
$montantTotal     = getMontantTotal($pdo, $dateDebut, $dateFin);
$kilometrageTotal = getKilometrageTotal($pdo, $dateDebut, $dateFin);
$dureeMoyenne     = getDureeMoyenneDessertes($pdo, $dateDebut, $dateFin);

$montantMoyen = $totalDessertes > 0
    ? $montantTotal / $totalDessertes
    : 0;

/*
|--------------------------------------------------------------------------
| Données graphiques / tableaux
|--------------------------------------------------------------------------
*/

$dessertesParCentre = getDessertesParCentre($pdo, $dateDebut, $dateFin);
$dessertesParClient = getDessertesParClient($pdo, $dateDebut, $dateFin);
$dessertesParEtat   = getDessertesParEtat($pdo, $dateDebut, $dateFin);
$dessertesParType   = getDessertesParType($pdo, $dateDebut, $dateFin);

$dessertesParJour = getDessertesParJour($pdo, $dateDebut, $dateFin);
$montantParJour   = getMontantParJour($pdo, $dateDebut, $dateFin);

$topClients         = getTopClients($pdo, 10, $dateDebut, $dateFin);
$topCentres         = getTopCentres($pdo, 10, $dateDebut, $dateFin);
$dernieresDessertes = getDernieresDessertes($pdo, 10, $dateDebut, $dateFin);

$filtreActif = ($dateDebut !== null || $dateFin !== null);

/*
|--------------------------------------------------------------------------
| Couleurs par état (pour le donut)
|--------------------------------------------------------------------------
*/

$couleursEtat = [
    'Livré'  => '#28a745',
    'En cours'  => '#ffc107',
    'Annulé'   => '#dc3545',
    'Initialisé' => '#17a2b8',
    'A planifier' => '#6c757d', 
    'Transit' => '#007bff',
];

function couleurEtat(string $etat, array $couleurs): string
{
    return $couleurs[$etat] ?? '#6c757d';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>

    <meta charset="UTF-8">
    <title>KAWA2 | Tableau de bord</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">

</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- NAVBAR -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
    </nav>

    <!-- SIDEBAR -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <span class="brand-text font-weight-light">KAWA2</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Tableau de bord</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#dernieres-dessertes" class="nav-link">
                            <i class="nav-icon fas fa-truck"></i>
                            <p>Dessertes</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#top-clients" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Clients</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- CONTENU -->
    <div class="content-wrapper">

        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2 align-items-center">
                    <div class="col-sm-6">
                        <h1>
                            Tableau de bord Opérations
                            <?php if ($filtreActif): ?>
                                <span class="badge badge-info align-middle" style="font-size: 13px;">
                                    <i class="fas fa-filter"></i>
                                    <?= $dateDebut ? htmlspecialchars($dateDebut) : '…' ?>
                                    &rarr;
                                    <?= $dateFin ? htmlspecialchars($dateFin) : '…' ?>
                                </span>
                            <?php endif; ?>
                        </h1>
                    </div>
                    <div class="col-sm-6">
                        <form class="form-inline float-sm-right" method="get">
                            <label class="mr-2 mb-0">Du</label>
                            <input
                                type="date"
                                name="date_debut"
                                class="form-control form-control-sm mr-2"
                                value="<?= htmlspecialchars($dateDebut ?? '') ?>"
                            >
                            <label class="mr-2 mb-0">Au</label>
                            <input
                                type="date"
                                name="date_fin"
                                class="form-control form-control-sm mr-2"
                                value="<?= htmlspecialchars($dateFin ?? '') ?>"
                            >
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <?php if ($filtreActif): ?>
                                <a href="index.php" class="btn btn-sm btn-outline-secondary ml-2">
                                    <i class="fas fa-times"></i> Réinitialiser
                                </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <section class="content">
            <div class="container-fluid">

                <!-- ============================================================
                     KPI
                     ============================================================ -->
                <div class="row">

                    <div class="col-lg-2-4 col-md-6 col-12">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?= number_format($totalDessertes, 0, ',', ' ') ?></h3>
                                <p>Nombre dessertes</p>
                            </div>
                            <div class="icon"><i class="fas fa-truck"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-2-4 col-md-6 col-12">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?= number_format($totalTournees, 0, ',', ' ') ?></h3>
                                <p>Nombre de tournées</p>
                            </div>
                            <div class="icon"><i class="fas fa-route"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-2-4 col-md-6 col-12">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?= number_format($totalClients, 0, ',', ' ') ?></h3>
                                <p>Nombre de clients</p>
                            </div>
                            <div class="icon"><i class="fas fa-users"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-2-4 col-md-6 col-12">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h3">
                                    <?= number_format($montantTotal, 0, ',', ' ') ?>
                                </h3>
                                <p>Mt total transporté (FCFA)</p>
                            </div>
                            <div class="icon"><i class="fas fa-coins"></i></div>
                        </div>
                    </div>

                    <div class="col-lg-2-4 col-md-6 col-12">
                        <div class="small-box bg-secondary">
                            <div class="inner">
                                <h3>
                                    <?= number_format($kilometrageTotal, 0, ',', ' ') ?> km
                                </h3>
                                <p>Kilométrage total</p>
                            </div>
                            <div class="icon"><i class="fas fa-road"></i></div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-md-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-primary"><i class="fas fa-hand-holding-usd"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Montant moyen par desserte</span>
                                <span class="info-box-number">
                                    <?= number_format($montantMoyen, 0, ',', ' ') ?> FCFA
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-dark"><i class="fas fa-clock"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Durée moyenne d'une desserte</span>
                                <span class="info-box-number">
                                    <?= $dureeMoyenne !== null
                                        ? number_format($dureeMoyenne, 0, ',', ' ') . ' min'
                                        : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     GRAPHIQUES : EVOLUTION + REPARTITION PAR ETAT
                     ============================================================ -->
                <div class="row">

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Évolution des dessertes et du montant</h3>
                            </div>
                            <div class="card-body">
                                <div class="chart">
                                    <canvas id="evolution-chart-canvas" style="height: 300px;"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Répartition par état</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="etat-chart-canvas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================================================
                     TOP CLIENTS + DESSERTES PAR CENTRE
                     ============================================================ -->
                <div class="row">

                    <div class="col-md-6" id="top-clients">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Top 10 clients (par montant)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="top-clients-chart-canvas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Dessertes par centre</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="centre-chart-canvas" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ============================================================
                     REPARTITION PAR TYPE
                     ============================================================ -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Dessertes par type</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="type-chart-canvas" style="height: 250px;"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     TABLEAU DES DERNIERES DESSERTES
                     ============================================================ -->
                <div class="row" id="dernieres-dessertes">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Dernières dessertes</h3>
                            </div>
                            <div class="card-body table-responsive p-0" style="max-height: 500px;">
                                <table class="table table-head-fixed table-striped">
                                    <thead>
                                        <tr>
                                            <th>N° desserte</th>
                                            <th>Tournée</th>
                                            <th>Centre</th>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th>Date expédition</th>
                                            <th>Date arrivée</th>
                                            <th>Montant</th>
                                            <th>État</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($dernieresDessertes)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                Aucune donnée disponible
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($dernieresDessertes as $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($d['numero_desserte'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['numero_tournee'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['centre'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['client'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['type_desserte'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['date_expedition'] ?? '') ?></td>
                                                <td><?= htmlspecialchars($d['date_arrivee'] ?? '') ?></td>
                                                <td>
                                                    <?= number_format(
                                                        (float) ($d['montant_colis'] ?? 0),
                                                        0, ',', ' '
                                                    ) ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background-color: <?= couleurEtat($d['etat_desserte'] ?? '', $couleursEtat) ?>; color: #fff;">
                                                        <?= htmlspecialchars($d['etat_desserte'] ?? '') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>KAWA2 &copy; <?= date('Y') ?></strong> — Tableau de bord transport de fonds
    </footer>

</div>

<style>
    .col-lg-2-4 {
        position: relative;
        width: 100%;
        padding-right: 15px;
        padding-left: 15px;
    }
    @media (min-width: 992px) {
        .col-lg-2-4 {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }
</style>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>

/* =========================================================
   Données injectées depuis PHP
   ========================================================= */

const dessertesParJour = <?= json_encode($dessertesParJour, JSON_UNESCAPED_UNICODE) ?>;
const montantParJour   = <?= json_encode($montantParJour, JSON_UNESCAPED_UNICODE) ?>;
const dessertesParEtat = <?= json_encode($dessertesParEtat, JSON_UNESCAPED_UNICODE) ?>;
const dessertesParType = <?= json_encode($dessertesParType, JSON_UNESCAPED_UNICODE) ?>;
const dessertesParCentre = <?= json_encode($dessertesParCentre, JSON_UNESCAPED_UNICODE) ?>;
const topClients        = <?= json_encode($topClients, JSON_UNESCAPED_UNICODE) ?>;

const couleursEtat = <?= json_encode($couleursEtat, JSON_UNESCAPED_UNICODE) ?>;

/* =========================================================
   1. Evolution des dessertes / montant
   ========================================================= */

new Chart(document.getElementById('evolution-chart-canvas'), {
    type: 'line',
    data: {
        labels: dessertesParJour.map(item => item.date),
        datasets: [
            {
                label: 'Nombre de dessertes',
                data: dessertesParJour.map(item => Number(item.total)),
                yAxisID: 'y',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Montant transporté',
                data: montantParJour.map(item => Number(item.montant)),
                yAxisID: 'y1',
                fill: false,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                type: 'linear',
                position: 'left',
                beginAtZero: true
            },
            y1: {
                type: 'linear',
                position: 'right',
                beginAtZero: true,
                grid: { drawOnChartArea: false }
            }
        }
    }
});

/* =========================================================
   2. Répartition par état (donut)
   ========================================================= */

new Chart(document.getElementById('etat-chart-canvas'), {
    type: 'doughnut',
    data: {
        labels: dessertesParEtat.map(item => item.etat_desserte),
        datasets: [{
            data: dessertesParEtat.map(item => Number(item.total)),
            backgroundColor: dessertesParEtat.map(
                item => couleursEtat[item.etat_desserte] || '#6c757d'
            )
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});

/* =========================================================
   3. Top clients (par montant)
   ========================================================= */

new Chart(document.getElementById('top-clients-chart-canvas'), {
    type: 'bar',
    data: {
        labels: topClients.map(item => item.client),
        datasets: [{
            label: 'Montant total',
            data: topClients.map(item => Number(item.montant_total)),
            backgroundColor: '#007bff'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { beginAtZero: true }
        }
    }
});

/* =========================================================
   4. Dessertes par centre
   ========================================================= */

new Chart(document.getElementById('centre-chart-canvas'), {
    type: 'bar',
    data: {
        labels: dessertesParCentre.map(item => item.centre),
        datasets: [{
            label: 'Nombre de dessertes',
            data: dessertesParCentre.map(item => Number(item.total)),
            backgroundColor: '#28a745'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

/* =========================================================
   5. Dessertes par type
   ========================================================= */

new Chart(document.getElementById('type-chart-canvas'), {
    type: 'bar',
    data: {
        labels: dessertesParType.map(item => item.type_desserte),
        datasets: [{
            label: 'Nombre de dessertes',
            data: dessertesParType.map(item => Number(item.total)),
            backgroundColor: '#ffc107'
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: { beginAtZero: true }
        }
    }
});

</script>

</body>
</html>
